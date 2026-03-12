<?php

declare(strict_types=1);

namespace Fissible\Fault\Http\Controllers;

use Fissible\Fault\Models\FaultGroup;
use Fissible\Fault\Services\TestStubGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class FaultController extends Controller
{
    public function __construct(
        private readonly TestStubGenerator $stubGenerator,
    ) {}

    public function index(Request $request): \Illuminate\View\View
    {
        $status = $request->query('status', 'open');
        $search = $request->query('search');

        $query = FaultGroup::query()
            ->when(in_array($status, ['open', 'resolved', 'ignored'], true), fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('class_name', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%")
                      ->orWhere('file', 'like', "%{$search}%");
            }))
            ->orderByDesc('last_seen_at');

        $groups = $query->paginate(50)->withQueryString();

        return view('fault::index', compact('groups', 'status', 'search'));
    }

    public function show(FaultGroup $faultGroup): \Illuminate\View\View
    {
        return view('fault::show', ['group' => $faultGroup]);
    }

    public function updateStatus(Request $request, FaultGroup $faultGroup): Response
    {
        $validated = $request->validate([
            'status' => 'required|in:open,resolved,ignored',
            'notes'  => 'nullable|string|max:10000',
        ]);

        match ($validated['status']) {
            'resolved' => $faultGroup->markResolved($validated['notes'] ?? null),
            'ignored'  => $faultGroup->markIgnored($validated['notes'] ?? null),
            'open'     => $faultGroup->reopen(),
        };

        return response(
            view('fault::partials.status-badge', ['group' => $faultGroup])->render(),
            200,
            ['Content-Type' => 'text/html'],
        );
    }

    public function saveNotes(Request $request, FaultGroup $faultGroup): Response
    {
        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:10000',
        ]);

        $faultGroup->update(['resolution_notes' => $validated['resolution_notes']]);

        return response('', 204);
    }

    public function generateTest(FaultGroup $faultGroup): Response
    {
        $stub = $this->stubGenerator->generate($faultGroup);

        $faultGroup->update(['generated_test' => $stub]);

        return response(
            view('fault::partials.generated-test', ['group' => $faultGroup])->render(),
            200,
            ['Content-Type' => 'text/html'],
        );
    }

    public function delete(FaultGroup $faultGroup): Response
    {
        $faultGroup->delete();

        return response('', 204, ['HX-Redirect' => route('watch.faults')]);
    }
}
