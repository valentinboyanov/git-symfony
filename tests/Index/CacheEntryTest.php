<?php

namespace GitSymfony\Tests\Index;

use GitSymfony\Index\CacheEntry;
use GitSymfony\Tests\TestCase;

class CacheEntryTest extends TestCase
{
    public function testSerializeAndParseRoundTrip(): void
    {
        $stat = [
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

        $sha1 = random_bytes(20);
        $entry = CacheEntry::fromStat('file.txt', $stat, $sha1);

        $binary = $entry->toBinary();
        [$parsed] = CacheEntry::parse($binary, 0);

        $this->assertSame($entry->getName(), $parsed->getName());
        $this->assertSame($entry->getMode(), $parsed->getMode());
        $this->assertSame($entry->getSha1Hex(), $parsed->getSha1Hex());
        $this->assertSame($entry->getSerializedSize(), strlen($binary));
    }

    public function testDetectChanges(): void
    {
        $sha1 = random_bytes(20);
        $stat = [
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
        $entry = CacheEntry::fromStat('file.txt', $stat, $sha1);

        $stat['mtime'] += 1;
        $this->assertNotSame(0, $entry->detectChanges($stat));
    }
}
