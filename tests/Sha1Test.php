<?php

namespace GitSymfony\Tests;

use GitSymfony\Tests\TestCase;
use GitSymfony\Sha1;
use InvalidArgumentException;

class Sha1Test extends TestCase
{
    public function testRoundTrip(): void
    {
        $hex = str_repeat('ab', 20);
        $binary = Sha1::fromHex($hex);

        $this->assertSame(20, strlen($binary));
        $this->assertSame($hex, Sha1::toHex($binary));
    }

    public function testInvalidHexThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Sha1::fromHex('xyz');
    }
}
