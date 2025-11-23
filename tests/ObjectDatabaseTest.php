<?php

namespace GitSymfony\Tests;

use GitSymfony\ObjectDatabase;

class ObjectDatabaseTest extends TestCase
{
    public function testStoreAndRead(): void
    {
        $repository = $this->createRepository();
        $database = new ObjectDatabase($repository);

        $sha = $database->storeRaw('blob', 'hello world');
        $hex = bin2hex($sha);
        $objectPath = sprintf(
            '%s/%s/%s',
            $repository->getObjectsDirectory(),
            substr($hex, 0, 2),
            substr($hex, 2)
        );
        $this->assertFileExists($objectPath);

        $object = $database->read($hex);
        $this->assertSame('blob', $object['type']);
        $this->assertSame('hello world', $object['data']);
        $this->assertSame(strlen('hello world'), $object['size']);
    }
}
