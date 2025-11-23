<?php

namespace GitSymfony\Index;

use GitSymfony\Util\Sha1;
use RuntimeException;

class CacheEntry
{
    public const BASE_SIZE = 62;
    public const MTIME_CHANGED = 0x0001;
    public const CTIME_CHANGED = 0x0002;
    public const OWNER_CHANGED = 0x0004;
    public const MODE_CHANGED = 0x0008;
    public const INODE_CHANGED = 0x0010;
    public const DATA_CHANGED = 0x0020;

    private int $ctimeSec;
    private int $ctimeNsec;
    private int $mtimeSec;
    private int $mtimeNsec;
    private int $dev;
    private int $ino;
    private int $mode;
    private int $uid;
    private int $gid;
    private int $size;
    private string $sha1;
    private string $name;

    public function __construct(
        int $ctimeSec,
        int $ctimeNsec,
        int $mtimeSec,
        int $mtimeNsec,
        int $dev,
        int $ino,
        int $mode,
        int $uid,
        int $gid,
        int $size,
        string $sha1,
        string $name
    ) {
        $this->ctimeSec = $ctimeSec;
        $this->ctimeNsec = $ctimeNsec;
        $this->mtimeSec = $mtimeSec;
        $this->mtimeNsec = $mtimeNsec;
        $this->dev = $dev;
        $this->ino = $ino;
        $this->mode = $mode;
        $this->uid = $uid;
        $this->gid = $gid;
        $this->size = $size;
        $this->sha1 = $sha1;
        $this->name = $name;
    }

    public static function fromStat(string $path, array $stat, string $sha1): self
    {
        $ctime = self::resolveTime($stat, 'ctime');
        $mtime = self::resolveTime($stat, 'mtime');

        return new self(
            $ctime['sec'],
            $ctime['nsec'],
            $mtime['sec'],
            $mtime['nsec'],
            (int) ($stat['dev'] ?? 0),
            (int) ($stat['ino'] ?? 0),
            (int) ($stat['mode'] ?? 0),
            (int) ($stat['uid'] ?? 0),
            (int) ($stat['gid'] ?? 0),
            (int) ($stat['size'] ?? 0),
            $sha1,
            $path
        );
    }

    public static function parse(string $buffer, int $offset): array
    {
        if (strlen($buffer) < $offset + self::BASE_SIZE) {
            throw new RuntimeException('Corrupt index entry');
        }

        $meta = substr($buffer, $offset, 40);
        $values = unpack('NctimeSec/NctimeNsec/NmtimeSec/NmtimeNsec/Ndev/Nino/Nmode/Nuid/Ngid/Nsize', $meta);
        $sha1 = substr($buffer, $offset + 40, 20);
        $nameLenData = substr($buffer, $offset + 60, 2);
        $nameLen = unpack('nlen', $nameLenData)['len'];

        $nameOffset = $offset + self::BASE_SIZE;
        if (strlen($buffer) < $nameOffset + $nameLen) {
            throw new RuntimeException('Incomplete index entry');
        }
        $name = substr($buffer, $nameOffset, $nameLen);
        $entrySize = self::sizeForNameLength($nameLen);
        $nextOffset = $offset + $entrySize;

        $entry = new self(
            $values['ctimeSec'],
            $values['ctimeNsec'],
            $values['mtimeSec'],
            $values['mtimeNsec'],
            $values['dev'],
            $values['ino'],
            $values['mode'],
            $values['uid'],
            $values['gid'],
            $values['size'],
            $sha1,
            $name
        );

        return [$entry, $nextOffset];
    }

    public static function sizeForNameLength(int $nameLength): int
    {
        $size = self::BASE_SIZE + $nameLength;

        return ($size + 8) & ~7;
    }

    public function toBinary(): string
    {
        $buffer = pack(
            'N10',
            $this->ctimeSec,
            $this->ctimeNsec,
            $this->mtimeSec,
            $this->mtimeNsec,
            $this->dev,
            $this->ino,
            $this->mode,
            $this->uid,
            $this->gid,
            $this->size
        );
        $buffer .= $this->sha1;
        $buffer .= pack('n', strlen($this->name));
        $buffer .= $this->name;

        $pad = self::sizeForNameLength(strlen($this->name)) - self::BASE_SIZE - strlen($this->name);
        if ($pad > 0) {
            $buffer .= str_repeat("\0", $pad);
        }

        return $buffer;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function getSha1Binary(): string
    {
        return $this->sha1;
    }

    public function getSha1Hex(): string
    {
        return Sha1::toHex($this->sha1);
    }

    public function detectChanges(array $stat): int
    {
        $changed = 0;
        $ctime = self::resolveTime($stat, 'ctime');
        $mtime = self::resolveTime($stat, 'mtime');

        if ($this->mtimeSec !== $mtime['sec'] || $this->mtimeNsec !== $mtime['nsec']) {
            $changed |= self::MTIME_CHANGED;
        }
        if ($this->ctimeSec !== $ctime['sec'] || $this->ctimeNsec !== $ctime['nsec']) {
            $changed |= self::CTIME_CHANGED;
        }
        if ($this->uid !== (int) ($stat['uid'] ?? 0) || $this->gid !== (int) ($stat['gid'] ?? 0)) {
            $changed |= self::OWNER_CHANGED;
        }
        if ($this->mode !== (int) ($stat['mode'] ?? 0)) {
            $changed |= self::MODE_CHANGED;
        }
        if ($this->dev !== (int) ($stat['dev'] ?? 0) || $this->ino !== (int) ($stat['ino'] ?? 0)) {
            $changed |= self::INODE_CHANGED;
        }
        if ($this->size !== (int) ($stat['size'] ?? 0)) {
            $changed |= self::DATA_CHANGED;
        }

        return $changed;
    }

    public function getSerializedSize(): int
    {
        return self::sizeForNameLength(strlen($this->name));
    }

    private static function resolveTime(array $stat, string $key): array
    {
        $sec = (int) ($stat[$key] ?? 0);
        $nsecKey = $key . '_nsec';
        $nsec = isset($stat[$nsecKey]) ? (int) $stat[$nsecKey] : 0;

        return ['sec' => $sec, 'nsec' => $nsec];
    }
}
