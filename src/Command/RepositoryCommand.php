<?php

namespace GitSymfony\Command;

use GitSymfony\ObjectDatabase;
use GitSymfony\Repository;
use Symfony\Component\Console\Command\Command;

abstract class RepositoryCommand extends Command
{
    protected Repository $repository;
    protected ObjectDatabase $objects;

    public function __construct(Repository $repository, ?ObjectDatabase $objects = null, ?string $name = null)
    {
        parent::__construct($name ?? static::$defaultName);
        $this->repository = $repository;
        $this->objects = $objects ?? new ObjectDatabase($repository);
    }
}
