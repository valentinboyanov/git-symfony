<?php

namespace GitSymfony;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ObjectDatabase
{
    private Repository $repository;
    private Filesystem $filesystem;

    public function __construct(Repository $repository, ?Filesystem $filesystem = null)
    {
        $this->repository = $repository;
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function storeRaw(string $type, string $data): string
    {
        $compressed = $this->compress($type, $data);
        $sha1Binary = sha1($compressed, true);
        $hex = bin2hex($sha1Binary);
        $this->writeObject($hex, $compressed);

        return $sha1Binary;
    }

    public function read(string $hex): array
    {
        $path = $this->getObjectPathFromHex($hex);
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Object %s not found', $hex));
        }

        $compressed = file_get_contents($path);
        if ($compressed === false) {
            throw new RuntimeException(sprintf('Unable to read %s', $path));
        }

        $expanded = gzuncompress($compressed);
        if ($expanded === false) {
            throw new RuntimeException(sprintf('Unable to inflate %s', $hex));
        }

        $nullPos = strpos($expanded, "\0");
        if ($nullPos === false) {
            throw new RuntimeException('Corrupt object header');
        }
        $header = substr($expanded, 0, $nullPos);
        $payload = substr($expanded, $nullPos + 1);

        $parts = explode(' ', $header, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Corrupt object header');
        }
        [$type, $size] = $parts;
        $size = (int) $size;
        if ($size !== strlen($payload)) {
            throw new RuntimeException('Invalid object size');
        }

        return [
            'type' => $type,
            'size' => $size,
            'data' => $payload,
        ];
    }

    public function objectExists(string $hex): bool
    {
        return is_file($this->getObjectPathFromHex($hex));
    }

    public function getObjectPathFromHex(string $hex): string
    {
        $hex = strtolower($hex);
        $dir = substr($hex, 0, 2);
        $file = substr($hex, 2);

        return Path::join($this->repository->getObjectsDirectory(), $dir, $file);
    }

    private function compress(string $type, string $data): string
    {
        $header = sprintf('%s %d', $type, strlen($data));
        $buffer = $header . "\0" . $data;
        $compressed = gzcompress($buffer, 9);
        if ($compressed === false) {
            throw new RuntimeException('Unable to compress object');
        }

        return $compressed;
    }

    private function writeObject(string $hex, string $compressed): void
    {
        $path = $this->getObjectPathFromHex($hex);
        if (is_file($path)) {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            $this->filesystem->mkdir($dir, 0777);
        }

        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            if (is_file($path)) {
                return;
            }

            throw new RuntimeException(sprintf('Unable to write %s', $path));
        }

        fwrite($stream, $compressed);
        fclose($stream);
    }
}
