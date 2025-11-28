<?php

namespace GitSymfony\Command;

use GitSymfony\Index\CacheEntry;
use GitSymfony\Index\IndexFile;
use GitSymfony\ObjectDatabase;
use GitSymfony\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ShowDiffCommand extends Command
{
    protected static $defaultName = 'show-diff';

    private readonly Repository $repository;
    private readonly ObjectDatabase $objects;

    public function __construct(Repository $repository, ObjectDatabase $objects)
    {
        parent::__construct();
        $this->repository = $repository;
        $this->objects = $objects;
    }

    protected function configure(): void
    {
        $this->setDescription('Compare the cache against the working tree');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $index = new IndexFile($this->repository);
        $index->load();
        foreach ($index->getEntries() as $entry) {
            $this->compareEntry($entry, $output);
        }

        return self::SUCCESS;
    }

    private function compareEntry(CacheEntry $entry, OutputInterface $output): void
    {
        clearstatcache(true, $entry->getName());
        $stat = @stat($entry->getName());
        if ($stat === false) {
            $message = file_exists($entry->getName()) ? 'stat failed' : 'No such file or directory';
            $output->writeln(sprintf('%s: %s', $entry->getName(), $message));

            return;
        }

        $changed = $entry->detectChanges($stat);
        if ($changed === 0) {
            $output->writeln(sprintf('%s: ok', $entry->getName()));

            return;
        }

        $output->writeln(sprintf('%s:  %s', $entry->getName(), $entry->getSha1Hex()));
        $object = $this->objects->read($entry->getSha1Hex());
        $this->showDiff($entry->getName(), $object['data'], $output);
    }

    private function showDiff(string $path, string $historic, OutputInterface $output): void
    {
        $process = Process::fromShellCommandline('diff -u - ' . escapeshellarg($path));
        $process->setInput($historic);
        $process->run();
        $output->write($process->getOutput());
        $error = $process->getErrorOutput();
        if ($error !== '') {
            $output->write($error);
        }
    }
}
