<?php

namespace GitSymfony\Command;

use GitSymfony\Index\CacheEntry;
use GitSymfony\Index\IndexFile;
use GitSymfony\Util\PathValidator;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCacheCommand extends RepositoryCommand
{
    protected static $defaultName = 'update-cache';

    protected function configure(): void
    {
        $this
            ->setDescription('Add file contents to the cache')
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'File paths to add');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $input->getArgument('paths');
        $io = new SymfonyStyle($input, $output);
        $index = new IndexFile($this->repository);
        $index->load();

        foreach ($paths as $path) {
            if (!PathValidator::isValid($path)) {
                $io->getErrorStyle()->writeln(sprintf('Ignoring path %s', $path));
                continue;
            }

            $this->processPath($index, $path);
        }

        $index->save();

        return self::SUCCESS;
    }

    private function processPath(IndexFile $index, string $path): void
    {
        if (!file_exists($path)) {
            $index->remove($path);

            return;
        }

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('%s is not a regular file', $path));
        }

        $stat = @stat($path);
        if ($stat === false) {
            throw new RuntimeException(sprintf('Unable to stat %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s', $path));
        }

        $sha1 = $this->objects->storeRaw('blob', $contents);
        $entry = CacheEntry::fromStat($path, $stat, $sha1);
        $index->addOrUpdate($entry);
    }
}
