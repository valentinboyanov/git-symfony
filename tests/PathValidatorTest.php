<?php

namespace GitSymfony\Tests;

use GitSymfony\Tests\TestCase;
use GitSymfony\PathValidator;
use PHPUnit\Framework\Attributes\DataProvider;

class PathValidatorTest extends TestCase
{
    #[DataProvider('validPaths')]
    public function testValidPaths(string $path): void
    {
        $this->assertTrue(PathValidator::isValid($path));
    }

    #[DataProvider('invalidPaths')]
    public function testInvalidPaths(string $path): void
    {
        $this->assertFalse(PathValidator::isValid($path));
    }

    public static function validPaths(): array
    {
        return [
            ['foo'],
            ['src/File.php'],
            ['dir/sub/file'],
        ];
    }

    public static function invalidPaths(): array
    {
        return [
            [''],
            ['.hidden'],
            ['..'],
            ['/absolute'],
            ['dir//file'],
            ['dir/.file'],
            ['dir/.'],
        ];
    }
}
