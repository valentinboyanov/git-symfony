<?php

namespace GitSymfony\Util;

final class PathValidator
{
    private function __construct()
    {
    }

    public static function isValid(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $length = strlen($path);
        $index = 0;
        $current = $path[$index] ?? '';
        if ($current === '/' || $current === '.') {
            return false;
        }

        while (true) {
            if ($current === '') {
                return true;
            }

            if ($current === '/') {
                $index++;
                $current = $path[$index] ?? '';
                if ($current === '' || $current === '/' || $current === '.') {
                    return false;
                }
                continue;
            }

            $index++;
            $current = $path[$index] ?? '';
        }
    }
}
