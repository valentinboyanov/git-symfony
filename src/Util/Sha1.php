<?php

namespace GitSymfony\Util;

use InvalidArgumentException;

final class Sha1
{
    private function __construct()
    {
    }

    public static function toHex(string $binary): string
    {
        return bin2hex($binary);
    }

    public static function fromHex(string $hex): string
    {
        $hex = strtolower(trim($hex));
        if (strlen($hex) !== 40 || !ctype_xdigit($hex)) {
            throw new InvalidArgumentException('Invalid sha1 hex string');
        }

        $binary = hex2bin($hex);
        if ($binary === false) {
            throw new InvalidArgumentException('Unable to convert sha1 hex to binary');
        }

        return $binary;
    }
}
