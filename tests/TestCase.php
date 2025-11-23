<?php

namespace GitSymfony\Tests;

use GitSymfony\Repository;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class TestCase extends BaseTestCase
{
    protected Filesystem $filesystem;
    /** @var string[] */
    private array $tempDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            if (is_dir($directory)) {
                $this->filesystem->remove($directory);
            }
        }
        $this->tempDirectories = [];
        parent::tearDown();
    }

    protected function createRepository(): Repository
    {
        $root = $this->createTempDirectory();

        return new Repository($root);
    }

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/git_symfony_' . uniqid('', true);
        $this->filesystem->mkdir($path);
        $this->tempDirectories[] = $path;

        return $path;
    }
}
