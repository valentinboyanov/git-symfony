<?php

namespace GitSymfony\Index;

use GitSymfony\Repository;
use RuntimeException;

class IndexFile
{
    private const SIGNATURE = "DIRC";

    private Repository $repository;
    /** @var CacheEntry[] */
    private array $entries = [];
    private bool $loaded = false;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $objectsPath = $this->repository->getObjectsDirectory();
        if (!is_dir($objectsPath) || !is_readable($objectsPath)) {
            throw new RuntimeException('no access to SHA1 file directory');
        }

        $path = $this->repository->getIndexPath();
        if (!is_file($path)) {
            $this->entries = [];
            $this->loaded = true;

            return;
        }

        $buffer = file_get_contents($path);
        if ($buffer === false || strlen($buffer) < 32) {
            throw new RuntimeException('cache corrupted');
        }

        if (substr($buffer, 0, 4) !== self::SIGNATURE) {
            throw new RuntimeException('bad signature');
        }

        $version = unpack('Nversion', substr($buffer, 4, 4))['version'];
        if ($version !== 1) {
            throw new RuntimeException('bad version');
        }

        $entryCount = unpack('Ncount', substr($buffer, 8, 4))['count'];
        $expectedSha = substr($buffer, 12, 20);
        $payload = substr($buffer, 32);
        $calculated = sha1(substr($buffer, 0, 12) . $payload, true);
        if ($expectedSha !== $calculated) {
            throw new RuntimeException('bad header sha1');
        }

        $offset = 32;
        $entries = [];
        for ($i = 0; $i < $entryCount; $i++) {
            [$entry, $offset] = CacheEntry::parse($buffer, $offset);
            $entries[] = $entry;
        }
        $this->entries = $entries;
        $this->loaded = true;
    }

    public function save(): void
    {
        $this->ensureLoaded();

        $entriesPayload = '';
        foreach ($this->entries as $entry) {
            $entriesPayload .= $entry->toBinary();
        }

        $headerWithoutSha = self::SIGNATURE
            . pack('N', 1)
            . pack('N', count($this->entries));
        $sha1 = sha1($headerWithoutSha . $entriesPayload, true);
        $data = $headerWithoutSha . $sha1 . $entriesPayload;

        $indexDir = dirname($this->repository->getIndexPath());
        if (!is_dir($indexDir)) {
            mkdir($indexDir, 0777, true);
        }
        $lockPath = $this->repository->getIndexLockPath();
        $stream = @fopen($lockPath, 'xb');
        if ($stream === false) {
            throw new RuntimeException('unable to create new cachefile');
        }

        fwrite($stream, $data);
        fclose($stream);

        if (!@rename($lockPath, $this->repository->getIndexPath())) {
            @unlink($lockPath);
            throw new RuntimeException('unable to move new cachefile into place');
        }
    }

    public function addOrUpdate(CacheEntry $entry): void
    {
        $this->ensureLoaded();
        $pos = $this->findPosition($entry->getName());
        if ($pos < 0) {
            $index = -$pos - 1;
            $this->entries[$index] = $entry;
            $this->entries = array_values($this->entries);

            return;
        }

        array_splice($this->entries, $pos, 0, [$entry]);
    }

    public function remove(string $name): void
    {
        $this->ensureLoaded();
        $pos = $this->findPosition($name);
        if ($pos >= 0) {
            return;
        }

        $index = -$pos - 1;
        array_splice($this->entries, $index, 1);
    }

    /**
     * @return CacheEntry[]
     */
    public function getEntries(): array
    {
        $this->ensureLoaded();

        return $this->entries;
    }

    public function count(): int
    {
        $this->ensureLoaded();

        return count($this->entries);
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
    }

    private function findPosition(string $name): int
    {
        $low = 0;
        $high = count($this->entries);

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            $current = $this->entries[$mid]->getName();
            $cmp = $this->compareNames($name, $current);
            if ($cmp === 0) {
                return -$mid - 1;
            }

            if ($cmp < 0) {
                $high = $mid;
                continue;
            }

            $low = $mid + 1;
        }

        return $low;
    }

    private function compareNames(string $a, string $b): int
    {
        $len = min(strlen($a), strlen($b));
        $cmp = strcmp(substr($a, 0, $len), substr($b, 0, $len));
        if ($cmp !== 0) {
            return $cmp;
        }

        return strlen($a) <=> strlen($b);
    }
}
