<?php

namespace Fissible\Fault\Tests\Unit;

use Fissible\Fault\Models\FaultGroup;
use Fissible\Fault\Services\FaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class FaultServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            \Fissible\Fault\Providers\FaultServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    public function test_resolve_marks_group_resolved(): void
    {
        $group = FaultGroup::create([
            'group_hash' => hash('sha256', 'test-resolve'),
            'class_name' => 'RuntimeException',
            'status' => 'open',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $service = new FaultService();
        $service->resolve($group, 'Fixed in PR #42');

        $group->refresh();
        $this->assertEquals('resolved', $group->status);
        $this->assertEquals('Fixed in PR #42', $group->resolution_notes);
        $this->assertNotNull($group->resolved_at);
    }

    public function test_ignore_marks_group_ignored(): void
    {
        $group = FaultGroup::create([
            'group_hash' => hash('sha256', 'test-ignore'),
            'class_name' => 'RuntimeException',
            'status' => 'open',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $service = new FaultService();
        $service->ignore($group, 'Expected in dev');

        $group->refresh();
        $this->assertEquals('ignored', $group->status);
        $this->assertEquals('Expected in dev', $group->resolution_notes);
    }

    public function test_reopen_sets_status_to_open(): void
    {
        $group = FaultGroup::create([
            'group_hash' => hash('sha256', 'test-reopen'),
            'class_name' => 'RuntimeException',
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => 'Was fixed',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $service = new FaultService();
        $service->reopen($group);

        $group->refresh();
        $this->assertEquals('open', $group->status);
        $this->assertNull($group->resolved_at);
        $this->assertNull($group->resolution_notes);
    }
}
