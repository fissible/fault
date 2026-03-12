<?php

declare(strict_types=1);

namespace Fissible\Fault\Services;

use Fissible\Fault\Models\FaultGroup;

class TestStubGenerator
{
    public function generate(FaultGroup $group): string
    {
        $shortClass  = $group->shortClass();
        $testClass   = $shortClass . 'FaultTest';
        $hashShort   = substr($group->group_hash, 0, 8);
        $file        = $group->relativeFile();
        $line        = $group->line ?? '?';
        $message     = addslashes($group->message ?? '(no message)');
        $fullClass   = addslashes($group->class_name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Unit\Faults;

use PHPUnit\Framework\TestCase;

/**
 * Regression test for fault group {$group->group_hash}
 *
 * Exception : {$group->class_name}
 * File      : {$file}:{$line}
 * Message   : {$group->message}
 *
 * @group fault-{$hashShort}
 */
class {$testClass} extends TestCase
{
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
