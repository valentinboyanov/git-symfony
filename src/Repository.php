<?php

namespace GitSymfony;

use Symfony\Component\Filesystem\Path;

class Repository
{
    private const DIRCACHE = '.dircache';
    private const OBJECTS = 'objects';
    public const ENV_SHA1_DIR = 'SHA1_FILE_DIRECTORY';

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ? rtrim($root, DIRECTORY_SEPARATOR) : getcwd();
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function getDirCachePath(): string
    {
        return Path::join($this->root, self::DIRCACHE);
    }

    public function getIndexPath(): string
    {
        return Path::join($this->getDirCachePath(), 'index');
    }

    public function getIndexLockPath(): string
    {
        return $this->getIndexPath() . '.lock';
    }

    public function getObjectsDirectory(): string
    {
        $env = getenv(self::ENV_SHA1_DIR);
        if ($env && $env !== '') {
            return $this->resolvePath($env);
        }

        return Path::join($this->getDirCachePath(), self::OBJECTS);
    }

    public function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->root;
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        return Path::join($this->root, $path);
    }
}
