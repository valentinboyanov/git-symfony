<?php

namespace GitSymfony\Command;

use GitSymfony\ObjectDatabase;
use GitSymfony\Sha1;
use Symfony\Component\Console\Command\Command;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReadTreeCommand extends Command
{
    private readonly ObjectDatabase $objects;

    public function __construct(ObjectDatabase $objects)
    {
        parent::__construct(name: 'read-tree');
        $this->objects = $objects;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Read and display a tree object')
            ->addArgument('sha1', InputArgument::REQUIRED, 'Tree object id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sha1 = $input->getArgument('sha1');
        $object = $this->objects->read($sha1);
        if ($object['type'] !== 'tree') {
            throw new RuntimeException('expected a tree node');
        }

        $data = $object['data'];
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $space = strpos($data, ' ', $offset);
            if ($space === false) {
                throw new RuntimeException('corrupt tree entry');
            }
            $mode = substr($data, $offset, $space - $offset);
            $null = strpos($data, "\0", $space);
            if ($null === false) {
                throw new RuntimeException('corrupt tree entry');
            }
            $path = substr($data, $space + 1, $null - $space - 1);
            $sha = substr($data, $null + 1, 20);
            if (strlen($sha) !== 20) {
                throw new RuntimeException('corrupt tree entry');
            }

            $offset = $null + 1 + 20;
            $output->writeln(sprintf('%o %s (%s)', intval($mode, 8), $path, Sha1::toHex($sha)));
        }

        return self::SUCCESS;
    }
}
