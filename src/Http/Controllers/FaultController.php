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
        return view('fault::show', [
            'group'          => $faultGroup,
            'testFilePath'   => $this->stubGenerator->testFilePath($faultGroup),
            'testFileExists' => file_exists($this->stubGenerator->testFilePath($faultGroup)),
            'testClassName'  => $this->stubGenerator->testClassName($faultGroup),
        ]);
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

        // Write the file to disk so "Run Test" can execute it immediately.
        $this->stubGenerator->write($faultGroup);

        return response(
            view('fault::partials.generated-test', [
                'group'          => $faultGroup,
                'testFilePath'   => $this->stubGenerator->testFilePath($faultGroup),
                'testFileExists' => true,
                'testClassName'  => $this->stubGenerator->testClassName($faultGroup),
            ])->render(),
            200,
            ['Content-Type' => 'text/html'],
        );
    }

    public function runTest(FaultGroup $faultGroup): Response
    {
        $testFile = $this->stubGenerator->testFilePath($faultGroup);

        if (! file_exists($testFile)) {
            return response(
                view('fault::partials.test-result', [
                    'group'   => $faultGroup,
                    'output'  => null,
                    'passed'  => false,
                    'missing' => true,
                ])->render(),
                200,
                ['Content-Type' => 'text/html'],
            );
        }

        $relPath = 'tests/Unit/Faults/' . $this->stubGenerator->testClassName($faultGroup) . '.php';
        $cmd     = [PHP_BINARY, base_path('artisan'), 'test', '--no-ansi', $relPath];
        $env     = array_merge(getenv() ?: [], [
            'APP_ENV'        => 'testing',
            'SESSION_DRIVER' => 'array',
            'DB_CONNECTION'  => 'sqlite',
            'DB_DATABASE'    => ':memory:',
        ]);

        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path(), $env);

        $output   = '(no output)';
        $exitCode = 1;

        if ($process !== false) {
            fclose($pipes[0]);
            $stdout   = stream_get_contents($pipes[1]);
            $stderr   = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $combined = trim($stdout ?? '');
            if ($stderr && trim($stderr)) {
                $combined .= "\n" . trim($stderr);
            }
            $output = $combined ?: '(no output)';
        }

        $passed = $exitCode === 0;

        return response(
            view('fault::partials.test-result', [
                'group'   => $faultGroup,
                'output'  => $output,
                'passed'  => $passed,
                'missing' => false,
            ])->render(),
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
