<?php

namespace GitSymfony\Command;

use GitSymfony\Index\IndexFile;
use GitSymfony\Util\Sha1;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WriteTreeCommand extends RepositoryCommand
{
    protected static $defaultName = 'write-tree';

    protected function configure(): void
    {
        $this->setDescription('Create a tree object from the cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $index = new IndexFile($this->repository);
        $index->load();
        $entries = $index->getEntries();
        if (!$entries) {
            throw new RuntimeException('No file-cache to create a tree of');
        }

        $buffer = '';
        foreach ($entries as $entry) {
            $hex = $entry->getSha1Hex();
            if (!$this->objects->objectExists($hex)) {
                throw new RuntimeException(sprintf('Missing blob %s', $hex));
            }

            $buffer .= sprintf('%o %s', $entry->getMode(), $entry->getName());
            $buffer .= "\0";
            $buffer .= $entry->getSha1Binary();
        }

        $sha1 = $this->objects->storeRaw('tree', $buffer);
        $output->writeln(Sha1::toHex($sha1));

        return self::SUCCESS;
    }
}
