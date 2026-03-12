# fissible/fault

Exception tracking and triage for the [fissible/watch](https://github.com/fissible/watch) cockpit. Captures exceptions via the Laravel exception handler, deduplicates them by fingerprint, and surfaces them at `/watch/faults` with status management, developer notes, and regression test generation.

**Depends on:** fissible/watch (uses the cockpit layout and route prefix config).

```
  [forge]  ──────────────────────────────►  [accord]  ◄── [watch] ◄── [fault]  ← you are here
  generate / update spec                   validate at      cockpit UI   exception tracking
      ▲                                    runtime │
      │                                            ▼
      └──────────────────────────────────  [drift]
                                           detect drift, bump version
```

---

## What it does

| Feature | Description |
|---|---|
| **Capture** | Hooks into Laravel's `withExceptions()` to record every unhandled exception |
| **Deduplication** | Groups exceptions by fingerprint: SHA-256 of `class\|relative_file\|line` — same bug, one group regardless of message variation |
| **Triage UI** | Filterable, paginated list at `/watch/faults` with open / resolved / ignored status |
| **Detail view** | Full meta, sample stack trace, occurrence count, first/last seen timestamps |
| **Notes** | Auto-saving textarea for AI evaluations, root-cause analysis, or developer comments |
| **Status workflow** | Mark as resolved, ignored, or reopen — with optional reopen-on-recurrence |
| **Test generation** | One-click PHPUnit skeleton annotated with `@group fault-{hash}`; passing the test signals the fix is complete |
| **Configurable ignore list** | Skip exceptions that are expected (404s, auth errors, validation errors) |

---

## Installation

```bash
composer require fissible/fault
```

The service provider registers automatically via Laravel's package discovery. Run the migration to create the `watch_fault_groups` table:

```bash
php artisan migrate
```

---

## Handler integration

Wire fault into your application's exception handler in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->report(function (Throwable $e): void {
        app(\Fissible\Fault\Services\FaultReporter::class)->capture($e);
    });
})
```

This is the only change needed. `FaultReporter::capture()` is a no-op when `FAULT_ENABLED=false`, when the exception matches the ignore list, or when the max group cap has been reached.

---

## Configuration

```dotenv
FAULT_ENABLED=true               # set false to disable capture entirely (e.g. in CI)
FAULT_MAX_GROUPS=500             # cap on distinct fault groups (0 = unlimited)
FAULT_REOPEN_ON_RECURRENCE=true  # reopen resolved/ignored faults when they fire again
FAULT_CONTEXT_DEPTH=10           # stack frames captured in sample_context
```

Publish the config to customise the ignore list or other defaults:

```bash
php artisan vendor:publish --tag=fault-config
```

`config/fault.php` ships with a sensible default ignore list covering Laravel's built-in HTTP and auth exceptions:

```php
'ignore' => [
    \Illuminate\Auth\AuthenticationException::class,
    \Illuminate\Auth\Access\AuthorizationException::class,
    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    \Illuminate\Session\TokenMismatchException::class,
    \Illuminate\Validation\ValidationException::class,
    \Symfony\Component\HttpKernel\Exception\HttpException::class,
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
],
```

---

## How fingerprinting works

Each exception is fingerprinted using a SHA-256 hash of three stable fields:

```
sha256("{exception_class}|{relative_file}|{line}")
```

The **message is intentionally excluded** from the fingerprint. Variable-content errors (database IDs, user input, timestamps in messages) all map to the same group — so you see "this class of bug has occurred 47 times" rather than 47 separate entries.

When an exception fires:
- If no group exists for the hash, a new one is created (subject to `max_groups` cap)
- If a group exists, `occurrence_count` and `last_seen_at` are updated
- If `reopen_on_recurrence` is true and the group is resolved or ignored, it is set back to `open`

---

## Fault groups

The `watch_fault_groups` table stores one row per unique fingerprint:

| Column | Description |
|---|---|
| `group_hash` | SHA-256 fingerprint (unique) |
| `class_name` | Fully-qualified exception class |
| `message` | Exception message (truncated to 500 chars) |
| `file` | Relative path from app base |
| `line` | Line number |
| `occurrence_count` | Total number of times this fault has fired |
| `first_seen_at` / `last_seen_at` | Timestamps |
| `status` | `open` \| `resolved` \| `ignored` |
| `resolution_notes` | Free-text developer notes (AI evals, root-cause, links) |
| `resolved_at` | When the fault was last resolved |
| `app_version` | Optional — pass via `FaultReporter::capture($e, $version)` |
| `sample_context` | JSON stack trace from first capture |
| `generated_test` | PHPUnit skeleton (set via "Generate Test" in the UI) |

---

## FaultGroup model API

```php
use Fissible\Fault\Models\FaultGroup;

$group = FaultGroup::where('status', 'open')->latest('last_seen_at')->get();

$group->isOpen();      // bool
$group->isResolved();  // bool
$group->isIgnored();   // bool

$group->markResolved('Fixed in PR #123');
$group->markIgnored('Expected during maintenance windows');
$group->reopen();

$group->shortClass();       // 'QueryException' (last segment of FQCN)
$group->relativeFile();     // 'app/Services/Foo.php'
$group->statusBadgeClass(); // 'badge-open' | 'badge-resolved' | 'badge-ignored'
```

---

## Test generation

Clicking **Generate Test** in the fault detail view calls `TestStubGenerator::generate()` and stores the result in `generated_test`. The stub:

- Is a valid PHPUnit class named `{ShortClass}FaultTest`
- Is annotated with `@group fault-{hash_short}` so you can run just that test
- Contains a `markTestIncomplete()` placeholder you replace with a real reproduction

```bash
php artisan test --filter fault-deadbeef
```

When the test passes, mark the fault group as **resolved** in the UI.

---

## Custom error page

For applications that want to show a branded error page linking back to the fault entry, add a render callback in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->report(function (Throwable $e): void {
        app(\Fissible\Fault\Services\FaultReporter::class)->capture($e);
    });

    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) return null;

        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        if (in_array($status, [401, 403, 404, 419])) return null;

        $faultGroup = \Fissible\Fault\Models\FaultGroup::where(
            'group_hash',
            hash('sha256', get_class($e).'|'.Str::after($e->getFile(), base_path('/')).'|'.$e->getLine())
        )->first();

        return response()->view('errors.cockpit', [
            'status'     => $status,
            'title'      => class_basename($e),
            'message'    => $e->getMessage(),
            'trace'      => $e->getTraceAsString(),
            'faultGroup' => $faultGroup,
        ], $status);
    });
})
```

See [Pilot](https://github.com/fissible/pilot) for a working implementation of this pattern.

---

## Prerequisites

fissible/watch must be installed and configured (routes registered, layout component available) before fault's UI will render correctly. See the [watch README](https://github.com/fissible/watch) for setup instructions.

---

## License

MIT
