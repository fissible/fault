# fissible/fault — Project

Current version: **1.1.0**
Org standards: [fissible/.github](https://github.com/fissible/.github)

---

## Status

All features documented in README are implemented. No open issues.

---

## Session handoff notes

### 2026-03-25

**Completed:**
- Full feature audit — implementation matches README spec; two undocumented extras (Run Test, Delete)
- Wired CI: `.github/workflows/ci.yml` (PHP 8.2/8.3 matrix, checks out `fissible/watch` sibling for path dep)
- Wired release workflow: `.github/workflows/release.yml` (delegates to `fissible/.github` reusable)
- Copied `release.sh` and `.cliff.toml` from `fissible/.github`
- Cut and pushed **v0.1.0** (first release)
- Fixed stale test assertion: `@group fault-` → `#[Group('fault-` (PHPUnit 11 attribute syntax)

**Next task:** No scheduled tasks. Candidates:
- Fix config comment discrepancy: `fault.php` claims wildcard `str_starts_with` matching but `shouldIgnore()` uses `instanceof`
- Add HTTP/controller tests (currently only unit coverage for `FaultReporter` and `TestStubGenerator`)
- Publish to Packagist (not in current roadmap — fault consumed as VCS dep per Phase 12 design)

**Decisions:**
- CI checks out `fissible/watch` as a sibling (not a Packagist dep) because fault requires `@dev` path version locally
- First release is `v0.1.0` (minor bump from placeholder `0.0.0`) — conventional for an initial feature-complete release
- `fissible/watch` must be a **public** repo for CI to check it out without a PAT; made public 2026-03-25

### Follow-up (same session)

- CI failed: `fissible/watch` was private — `actions/checkout` returned 404
- Fixed by making `watch` public on GitHub (no code change needed)
- fault CI confirmed green; Phase 12 exit condition fully met

---

### 2026-03-26

**Completed:**
- Fixed CI failure (fissible/fault#1 follow-up): `accord`, `drift`, `forge` path deps were not checked out in CI
- Discovered path repos report as `dev-main` (no `version` field in composer.json) — `^1.0` constraint could never resolve
- Switched all four repositories (`watch`, `accord`, `drift`, `forge`) from `path` to `vcs` type pointing to GitHub
- Removed manual sibling checkout steps from CI (no longer needed with VCS repos)
- Pinned `fissible/watch` to `^1.0` (was `@dev`)
- Created GitHub Release for `v0.1.0` (tag existed but no Release object)
- Closed fissible/fault#1
- Cut **v0.1.1** with the VCS fix so installable versions resolve cleanly

**Next task:** No scheduled tasks. Candidates (unchanged from prior session):
- Fix config comment discrepancy: `fault.php` claims wildcard `str_starts_with` matching but `shouldIgnore()` uses `instanceof`
- Add HTTP/controller tests (currently only unit coverage for `FaultReporter` and `TestStubGenerator`)
- Publish to Packagist (not in current roadmap)

**Decisions:**
- Switched to VCS repos instead of path repos so `^1.0` constraints resolve against real git tags; path repos require a `version` field in composer.json to work with semver constraints
- Local dev note: `composer install` now fetches deps from GitHub rather than local siblings; add local path overrides if developing across repos simultaneously

---

### 2026-04-12

**Completed:**
- **Filament Plugin** — added `src/Filament/` with FaultPlugin, FaultGroupResource, FaultSummaryWidget
  - FaultPlugin implements `Filament\Contracts\Plugin` with `enabled()` config toggle
  - FaultGroupResource: table (status/class/message/location/count/dates), infolist (5 sections), triage actions (resolve/ignore/reopen), bulk resolve/ignore, type-to-confirm delete, 30s poll
  - ViewFaultGroup: resolve with notes + version, ignore, reopen, generate test, delete
  - FaultSummaryWidget: open count, new today, seen today stats
  - Filament in `suggest` not `require` — zero impact on non-Filament consumers
- **FaultService** — extracted mutation logic from FaultController into shared service layer (resolve, ignore, reopen, saveNotes, generateTest, delete). Both Blade UI and Filament call the same service.
- **resolved_by + resolved_in_version** — new migration adds columns to `watch_fault_groups`. Model updated with fillable.
- 3 new tests (FaultServiceTest), 34/34 full suite passing

**Next task:** No scheduled tasks. Candidates:
- Release v1.1.0 (Filament plugin is a minor feature addition)
- Publish to Packagist
- Integration test in Station (register FaultPlugin, verify resource renders)

**Decisions:**
- Filament code isolated under `src/Filament/` with zero imports from core — designed for future extraction to `fissible/fault-filament`
- `resolved_by` stores user ID (not FK — package is app-agnostic, can't reference a specific users table)
- FaultService guards `resolved_by`/`resolved_in_version` assignments with `in_array($fillable)` check for backwards compat

---

### 2026-04-12 (continued)

**Completed:**
- Updated `FaultGroupResource` for Filament v5: `Filament\Infolists\Infolist` → `Filament\Schemas\Schema`, `Section` moved to `Filament\Schemas\Components`, `TextEntry` stays in `Filament\Infolists\Components`
- Loosened `fissible/watch` constraint: `"^1.0"` → `"^1.0 || dev-main"` (same pattern as sebastian/diff fix in Watch — allows parallel VCS development)
- Cut **v1.1.0** (minor: Filament v5 compat + constraint fix)
- Published to Packagist: https://packagist.org/packages/fissible/fault
- Added Packagist auto-update job to release workflow (pings Packagist API after GitHub Release)
- Set `PACKAGIST_USERNAME` + `PACKAGIST_API_TOKEN` secrets on all 5 fissible repos: fault, watch, accord, drift, forge
- Published `fissible/watch` to Packagist: https://packagist.org/packages/fissible/watch

**Next task:** No scheduled tasks. Candidates:
- Integration test in Station (register FaultPlugin, verify resource renders with Filament v5)
- Fix config comment discrepancy: `fault.php` claims wildcard `str_starts_with` matching but `shouldIgnore()` uses `instanceof`
- Add HTTP/controller tests beyond current unit coverage
- Publish remaining packages (accord, drift, forge) to Packagist

**Decisions:**
- Packagist webhook via GitHub Actions (not GitHub webhook) — keeps automation visible in workflow files and auditable
- All fissible PHP repos share the same `PACKAGIST_USERNAME`/`PACKAGIST_API_TOKEN` secret pair

---

### 2026-03-26 (continued)

**Completed:**
- Implemented namespace prefix wildcard in `shouldIgnore()` — entries ending with `\` now use `str_starts_with` instead of `instanceof`, matching the documented behaviour in `config/fault.php`
- Added `FaultControllerTest` (Feature suite): 12 tests covering all 7 controller actions
- Added `authors`, `keywords`, `homepage` to `composer.json`
- Improved test coverage from 80% → 84% lines, 76% → 93% methods
  - `FaultGroup`: 100% / 100%
  - `TestStubGenerator`: 100% / 100%
  - `FaultReporter`: 83% methods / 98% lines (re-entry guard + catch block covered)
  - `FaultController`: 87% methods / 63% lines
- Remaining gap: `FaultController::runTest` success path requires `php artisan test` in a real app; not testable in testbench isolation — integration test candidate for fissible/pilot
- Cut **v1.0.0** (deliberate major bump to declare API stable)

**Next task:** No scheduled tasks. Candidates:
- Integration-test `runTest` success path in fissible/pilot
- Publish to Packagist (not in current roadmap)

**Decisions:**
- `v1.0.0` cut as a `major` bump despite conventional commits suggesting `v0.2.0` — explicit declaration of API stability
- `FaultController::runTest` success path is genuinely an integration concern: it spawns `php artisan test` which requires the host app's `vendor/autoload.php` + `artisan`; testbench skeleton has neither
