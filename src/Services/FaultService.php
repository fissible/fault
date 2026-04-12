<?php

namespace Fissible\Fault\Services;

use Fissible\Fault\Models\FaultGroup;

class FaultService
{
    public function resolve(FaultGroup $group, ?string $notes = null, ?int $resolvedBy = null, ?string $version = null): void
    {
        $group->status = 'resolved';
        $group->resolution_notes = $notes;
        $group->resolved_at = now();

        if (in_array('resolved_by', $group->getFillable(), true)) {
            $group->resolved_by = $resolvedBy;
        }

        if ($version !== null && in_array('resolved_in_version', $group->getFillable(), true)) {
            $group->resolved_in_version = $version;
        }

        $group->save();
    }

    public function ignore(FaultGroup $group, ?string $notes = null, ?int $ignoredBy = null): void
    {
        $group->status = 'ignored';
        $group->resolution_notes = $notes;

        if (in_array('resolved_by', $group->getFillable(), true)) {
            $group->resolved_by = $ignoredBy;
        }

        $group->save();
    }

    public function reopen(FaultGroup $group): void
    {
        $group->status = 'open';
        $group->resolved_at = null;
        $group->resolution_notes = null;

        if (in_array('resolved_by', $group->getFillable(), true)) {
            $group->resolved_by = null;
        }

        if (in_array('resolved_in_version', $group->getFillable(), true)) {
            $group->resolved_in_version = null;
        }

        $group->save();
    }

    public function saveNotes(FaultGroup $group, string $notes, ?int $userId = null): void
    {
        $group->resolution_notes = $notes;

        if (in_array('resolved_by', $group->getFillable(), true)) {
            $group->resolved_by = $userId;
        }

        $group->save();
    }

    public function generateTest(FaultGroup $group): string
    {
        $generator = app(\Fissible\Fault\Services\TestStubGenerator::class);
        $stub = $generator->generate($group);
        $generator->write($group);

        $group->generated_test = $stub;
        $group->save();

        return $stub;
    }

    public function delete(FaultGroup $group): void
    {
        $group->delete();
    }
}
