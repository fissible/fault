<?php

declare(strict_types=1);

namespace Fissible\Fault\Tests\Unit;

use Fissible\Fault\Models\FaultGroup;
use Fissible\Fault\Services\TestStubGenerator;
use PHPUnit\Framework\TestCase;

class TestStubGeneratorTest extends TestCase
{
    private function makeGroup(array $attrs = []): FaultGroup
    {
        $group = new FaultGroup();
        $group->forceFill(array_merge([
            'id'          => 1,
            'group_hash'  => str_repeat('a', 64),
            'class_name'  => 'App\Exceptions\MyException',
            'message'     => 'Something broke',
            'file'        => 'app/Services/Foo.php',
            'line'        => 42,
        ], $attrs));

        return $group;
    }

    public function test_generates_valid_php_class(): void
    {
        $generator = new TestStubGenerator();
        $stub      = $generator->generate($this->makeGroup());

        $this->assertStringContainsString('<?php', $stub);
        $this->assertStringContainsString('class MyExceptionFaultTest', $stub);
        $this->assertStringContainsString('extends TestCase', $stub);
    }

    public function test_includes_fault_group_annotation(): void
    {
        $generator = new TestStubGenerator();
        $stub      = $generator->generate($this->makeGroup(['group_hash' => str_repeat('b', 64)]));

        $this->assertStringContainsString('@group fault-', $stub);
    }

    public function test_includes_file_and_line_in_docblock(): void
    {
        $generator = new TestStubGenerator();
        $stub      = $generator->generate($this->makeGroup());

        $this->assertStringContainsString('app/Services/Foo.php:42', $stub);
    }

    public function test_includes_exception_class_in_docblock(): void
    {
        $generator = new TestStubGenerator();
        $stub      = $generator->generate($this->makeGroup());

        $this->assertStringContainsString('App\Exceptions\MyException', $stub);
    }

    public function test_test_method_is_named_after_hash_short(): void
    {
        $generator = new TestStubGenerator();
        $hash      = 'deadbeef' . str_repeat('0', 56);
        $stub      = $generator->generate($this->makeGroup(['group_hash' => $hash]));

        $this->assertStringContainsString('test_deadbeef_no_longer_throws', $stub);
    }

    public function test_handles_null_message_gracefully(): void
    {
        $generator = new TestStubGenerator();
        $stub      = $generator->generate($this->makeGroup(['message' => null]));

        $this->assertStringContainsString('(no message)', $stub);
    }
}
