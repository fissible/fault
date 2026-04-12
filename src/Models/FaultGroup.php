<?php

declare(strict_types=1);

namespace Fissible\Fault\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property string $group_hash
 * @property string $class_name
 * @property string|null $message
 * @property string|null $file
 * @property int|null $line
 * @property int    $occurrence_count
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property string $status  open|resolved|ignored
 * @property string|null $resolution_notes
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolved_in_version
 * @property string|null $app_version
 * @property array|null  $sample_context
 * @property string|null $generated_test
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FaultGroup extends Model
{
    protected $table = 'watch_fault_groups';

    protected $fillable = [
        'group_hash',
        'class_name',
        'message',
        'file',
        'line',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'status',
        'resolution_notes',
        'resolved_at',
        'resolved_by',
        'resolved_in_version',
        'app_version',
        'sample_context',
        'generated_test',
    ];

    protected $casts = [
        'line'            => 'integer',
        'occurrence_count'=> 'integer',
        'first_seen_at'   => 'datetime',
        'last_seen_at'    => 'datetime',
        'resolved_at'     => 'datetime',
        'sample_context'  => 'array',
    ];

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isIgnored(): bool
    {
        return $this->status === 'ignored';
    }

    public function markResolved(?string $notes = null): void
    {
        $this->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ]);
    }

    public function markIgnored(?string $notes = null): void
    {
        $this->update([
            'status'           => 'ignored',
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status'      => 'open',
            'resolved_at' => null,
        ]);
    }

    // ── Display helpers ───────────────────────────────────────────────────────

    public function shortClass(): string
    {
        $parts = explode('\\', $this->class_name);

        return end($parts);
    }

    public function relativeFile(): string
    {
        return $this->file ?? '(unknown)';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'resolved' => 'badge-resolved',
            'ignored'  => 'badge-ignored',
            default    => 'badge-open',
        };
    }
}
