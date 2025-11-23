<?php

namespace GitSymfony\Command;

use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatFileCommand extends RepositoryCommand
{
    protected static $defaultName = 'cat-file';

    protected function configure(): void
    {
        $this
            ->setDescription('Extract an object into a temporary file')
            ->addArgument('sha1', InputArgument::REQUIRED, 'Object id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sha1 = $input->getArgument('sha1');
        $object = $this->objects->read($sha1);
        $path = tempnam($this->repository->getRoot(), 'temp_git_file_');
        if (!$path) {
            throw new RuntimeException('unable to create tempfile');
        }

        $written = file_put_contents($path, $object['data']);
        if ($written === false || $written !== $object['size']) {
            throw new RuntimeException('unable to write tempfile');
        }

        $output->writeln(sprintf('%s: %s', $path, $object['type']));

        return self::SUCCESS;
    }
}
