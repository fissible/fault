<?php

declare(strict_types=1);

namespace Fissible\Fault\Tests\Feature;

use Fissible\Fault\Models\FaultGroup;
use Fissible\Fault\Services\TestStubGenerator;
use Fissible\Fault\Services\FaultReporter;
use Fissible\Fault\Tests\Support\NoOpMiddleware;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class FaultControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [\Fissible\Fault\Providers\FaultServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $app['router']->aliasMiddleware('watch.local', NoOpMiddleware::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        view()->prependNamespace('fault', __DIR__ . '/../stubs/views/fault');
        $this->withoutMiddleware([VerifyCsrfToken::class]);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200_with_open_groups_by_default(): void
    {
        $open     = $this->makeFaultGroup(['status' => 'open',     'class_name' => 'OpenException']);
        $resolved = $this->makeFaultGroup(['status' => 'resolved', 'class_name' => 'ResolvedException']);

        $response = $this->get('/watch/faults');

        $response->assertStatus(200);
        $response->assertSee('status:open');
        $response->assertSee($open->class_name);
        $response->assertDontSee($resolved->class_name);
    }

    public function test_index_filters_by_status(): void
    {
        $open     = $this->makeFaultGroup(['status' => 'open', 'class_name' => 'OpenException']);
        $resolved = $this->makeFaultGroup(['status' => 'resolved', 'class_name' => 'ResolvedException']);

        $response = $this->get('/watch/faults?status=resolved');

        $response->assertStatus(200);
        $response->assertSee($resolved->class_name);
        $response->assertDontSee($open->class_name);
    }

    public function test_index_filters_by_search_term(): void
    {
        $match   = $this->makeFaultGroup(['class_name' => 'SearchableException']);
        $nomatch = $this->makeFaultGroup(['class_name' => 'UnrelatedError']);

        $response = $this->get('/watch/faults?search=Searchable');

        $response->assertStatus(200);
        $response->assertSee($match->class_name);
        $response->assertDontSee($nomatch->class_name);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_200_for_existing_group(): void
    {
        $group = $this->makeFaultGroup();

        $response = $this->get("/watch/faults/{$group->id}");

        $response->assertStatus(200);
        $response->assertSee($group->class_name);
    }

    // ── updateStatus ──────────────────────────────────────────────────────────

    public function test_update_status_marks_group_resolved(): void
    {
        $group = $this->makeFaultGroup(['status' => 'open']);

        $response = $this->patch("/watch/faults/{$group->id}/status", [
            'status' => 'resolved',
            'notes'  => 'Fixed in PR #42',
        ]);

        $response->assertStatus(200);
        $this->assertSame('resolved', $group->fresh()->status);
        $this->assertSame('Fixed in PR #42', $group->fresh()->resolution_notes);
    }

    public function test_update_status_marks_group_ignored(): void
    {
        $group = $this->makeFaultGroup(['status' => 'open']);

        $response = $this->patch("/watch/faults/{$group->id}/status", ['status' => 'ignored']);

        $response->assertStatus(200);
        $this->assertSame('ignored', $group->fresh()->status);
    }

    public function test_update_status_reopens_group(): void
    {
        $group = $this->makeFaultGroup(['status' => 'resolved']);

        $response = $this->patch("/watch/faults/{$group->id}/status", ['status' => 'open']);

        $response->assertStatus(200);
        $this->assertSame('open', $group->fresh()->status);
    }

    public function test_update_status_returns_status_badge_html(): void
    {
        $group = $this->makeFaultGroup(['status' => 'open']);

        $response = $this->patch("/watch/faults/{$group->id}/status", ['status' => 'resolved']);

        $response->assertStatus(200);
        $response->assertSee('status-badge');
        $response->assertSee('resolved');
    }

    // ── saveNotes ─────────────────────────────────────────────────────────────

    public function test_save_notes_persists_and_returns_204(): void
    {
        $group = $this->makeFaultGroup();

        $response = $this->patch("/watch/faults/{$group->id}/notes", [
            'resolution_notes' => 'Root cause: missing null check.',
        ]);

        $response->assertStatus(204);
        $this->assertSame('Root cause: missing null check.', $group->fresh()->resolution_notes);
    }

    // ── generateTest ──────────────────────────────────────────────────────────

    public function test_generate_test_persists_stub_and_returns_html(): void
    {
        $group = $this->makeFaultGroup();

        $stubGenerator = $this->partialMock(TestStubGenerator::class);
        $stubGenerator->shouldReceive('write')->once();

        $response = $this->post("/watch/faults/{$group->id}/test");

        $response->assertStatus(200);
        $this->assertNotNull($group->fresh()->generated_test);
        $this->assertStringContainsString('FaultTest', $group->fresh()->generated_test);
    }

    // ── runTest ───────────────────────────────────────────────────────────────

    public function test_run_test_returns_missing_partial_when_file_does_not_exist(): void
    {
        $group = $this->makeFaultGroup();

        $stubGenerator = $this->mock(TestStubGenerator::class);
        $stubGenerator->shouldReceive('testFilePath')
            ->andReturn('/tmp/nonexistent-fault-test-' . $group->id . '.php');

        $response = $this->post("/watch/faults/{$group->id}/run-test");

        $response->assertStatus(200);
        $response->assertSee('Test file not found on disk');
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function test_delete_removes_group_and_returns_204(): void
    {
        $group = $this->makeFaultGroup();

        $response = $this->delete("/watch/faults/{$group->id}");

        $response->assertStatus(204);
        $this->assertNull(FaultGroup::find($group->id));
    }

    // ── TestStubGenerator::write ──────────────────────────────────────────────

    public function test_stub_generator_write_creates_file_with_stub_content(): void
    {
        $group     = $this->makeFaultGroup();
        $generator = app(TestStubGenerator::class);
        $path      = $generator->testFilePath($group);

        try {
            $generator->write($group);

            $this->assertFileExists($path);
            $this->assertStringContainsString('FaultTest', file_get_contents($path));
        } finally {
            @unlink($path);
            @rmdir(dirname($path));
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeFaultGroup(array $attrs = []): FaultGroup
    {
        return FaultGroup::create(array_merge([
            'group_hash'       => hash('sha256', uniqid('fault', true)),
            'class_name'       => 'RuntimeException',
            'message'          => 'Test error',
            'file'             => 'app/Test.php',
            'line'             => 10,
            'occurrence_count' => 1,
            'first_seen_at'    => now(),
            'last_seen_at'     => now(),
            'status'           => 'open',
        ], $attrs));
    }
}
