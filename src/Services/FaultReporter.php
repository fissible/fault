<?php

declare(strict_types=1);

namespace Fissible\Fault\Services;

use Fissible\Fault\Models\FaultGroup;
use Illuminate\Support\Str;
use Throwable;

class FaultReporter
{
    /** Prevent infinite loops if capture() itself throws. */
    private static bool $capturing = false;

    public function capture(Throwable $e, ?string $appVersion = null): ?FaultGroup
    {
        if (self::$capturing) {
            return null;
        }

        if (! config('fault.enabled', true)) {
            return null;
        }

        if ($this->shouldIgnore($e)) {
            return null;
        }

        self::$capturing = true;

        try {
            return $this->record($e, $appVersion);
        } catch (Throwable) {
            return null;
        } finally {
            self::$capturing = false;
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function record(Throwable $e, ?string $appVersion): FaultGroup
    {
        $relativeFile = $this->relativePath($e->getFile());
        $hash         = $this->fingerprint(get_class($e), $relativeFile, $e->getLine());

        $maxGroups = (int) config('fault.max_groups', 500);

        /** @var FaultGroup|null $group */
        $group = FaultGroup::where('group_hash', $hash)->first();

        if ($group === null) {
            if ($maxGroups > 0 && FaultGroup::count() >= $maxGroups) {
                // Silently drop new groups once the cap is reached
                return new FaultGroup(['group_hash' => $hash]);
            }

            $group = FaultGroup::create([
                'group_hash'     => $hash,
                'class_name'     => get_class($e),
                'message'        => Str::limit($e->getMessage(), 500),
                'file'           => $relativeFile,
                'line'           => $e->getLine(),
                'occurrence_count' => 1,
                'first_seen_at'  => now(),
                'last_seen_at'   => now(),
                'status'         => 'open',
                'app_version'    => $appVersion,
                'sample_context' => $this->captureContext($e),
            ]);
        } else {
            $updates = [
                'occurrence_count' => $group->occurrence_count + 1,
                'last_seen_at'     => now(),
            ];

            if (config('fault.reopen_on_recurrence', true) && ! $group->isOpen()) {
                $updates['status']      = 'open';
                $updates['resolved_at'] = null;
            }

            $group->update($updates);
            $group->refresh();
        }

        return $group;
    }

    private function shouldIgnore(Throwable $e): bool
    {
        $ignore = config('fault.ignore', []);

        foreach ($ignore as $ignored) {
            if ($e instanceof $ignored) {
                return true;
            }
        }

        return false;
    }

    private function fingerprint(string $class, string $relativeFile, int $line): string
    {
        return hash('sha256', $class . '|' . $relativeFile . '|' . $line);
    }

    private function relativePath(string $absolutePath): string
    {
        $base = rtrim(base_path(), '/') . '/';

        return Str::startsWith($absolutePath, $base)
            ? substr($absolutePath, strlen($base))
            : $absolutePath;
    }

    private function captureContext(Throwable $e): array
    {
        $depth  = (int) config('fault.context_depth', 10);
        $frames = [];

        foreach (array_slice($e->getTrace(), 0, $depth) as $frame) {
            $frames[] = [
                'file'     => isset($frame['file']) ? $this->relativePath($frame['file']) : null,
                'line'     => $frame['line'] ?? null,
                'function' => isset($frame['class'])
                    ? ($frame['class'] . ($frame['type'] ?? '::') . $frame['function'])
                    : $frame['function'],
            ];
        }

        return $frames;
    }
}
