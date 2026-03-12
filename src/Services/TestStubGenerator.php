<?php

declare(strict_types=1);

namespace Fissible\Fault\Services;

use Fissible\Fault\Models\FaultGroup;

class TestStubGenerator
{
    public function testClassName(FaultGroup $group): string
    {
        return $group->shortClass() . 'FaultTest';
    }

    public function testFilePath(FaultGroup $group): string
    {
        return base_path('tests/Unit/Faults/' . $this->testClassName($group) . '.php');
    }

    public function write(FaultGroup $group): void
    {
        $path = $this->testFilePath($group);
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $this->generate($group));
    }

    public function generate(FaultGroup $group): string
    {
        $testClass  = $this->testClassName($group);
        $hashShort  = substr($group->group_hash, 0, 8);
        $file       = $group->relativeFile();
        $line       = $group->line ?? '?';
        $message    = addslashes($group->message ?? '(no message)');
        $fullClass  = addslashes($group->class_name);
        $shortClass = $group->shortClass();

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Unit\Faults;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression test for fault group {$group->group_hash}
 *
 * Exception : {$group->class_name}
 * File      : {$file}:{$line}
 * Message   : {$group->message}
 */
#[Group('fault-{$hashShort}')]
class {$testClass} extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce the scenario that triggered the {$shortClass} and assert it
     * no longer throws (or assert the new expected behaviour).
     *
     * When this test passes, mark the fault group as resolved in the Watch UI.
     */
    public function test_{$hashShort}_no_longer_throws(): void
    {
        // TODO: reproduce the conditions that caused:
        //   {$fullClass}: {$message}
        //   at {$file}:{$line}

        \$this->markTestIncomplete('Replace with a reproduction and fix assertion.');
    }
}
PHP;
    }
}
