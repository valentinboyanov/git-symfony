<?php

namespace GitSymfony\Tests\Index;

use GitSymfony\Index\CacheEntry;
use GitSymfony\Index\IndexFile;
use GitSymfony\Tests\TestCase;

class IndexFileTest extends TestCase
{
    public function testAddAndReloadEntries(): void
    {
        $repository = $this->createRepository();
        $this->filesystem->mkdir($repository->getObjectsDirectory(), 0777);

        $index = new IndexFile($repository);
        $index->load();
        $this->assertSame(0, $index->count());

        $entry = CacheEntry::fromStat('file.txt', $this->createStat(), random_bytes(20));
        $index->addOrUpdate($entry);
        $index->save();

        $reloaded = new IndexFile($repository);
        $reloaded->load();
        $entries = $reloaded->getEntries();

        $this->assertCount(1, $entries);
        $this->assertSame('file.txt', $entries[0]->getName());
    }

    private function createStat(): array
    {
        return [
            'ctime' => 1700000000,
            'ctime_nsec' => 1,
            'mtime' => 1700000100,
            'mtime_nsec' => 2,
            'dev' => 2049,
            'ino' => 100,
            'mode' => 0100644,
            'uid' => 501,
            'gid' => 20,
            'size' => 12,
        ];
    }
}
