<?php

namespace GitSymfony\Command;

use GitSymfony\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class InitDbCommand extends Command
{
    protected static $defaultName = 'init-db';

    private readonly Filesystem $filesystem;
    private readonly Repository $repository;

    public function __construct(Repository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this->setDescription('Initialize repository storage');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dircache = $this->repository->getDirCachePath();
        if (!is_dir($dircache)) {
            $this->filesystem->mkdir($dircache, 0700);
        }

        $envDir = getenv(Repository::ENV_SHA1_DIR);
        if ($envDir) {
            $resolved = $this->repository->resolvePath($envDir);
            if (is_dir($resolved)) {
                return self::SUCCESS;
            }
            $io->getErrorStyle()->writeln(sprintf('DB_ENVIRONMENT set to bad directory %s', $envDir));
        }

        $objectsDir = $this->repository->getObjectsDirectory();
        $io->getErrorStyle()->writeln('defaulting to private storage area');
        if (!is_dir($objectsDir)) {
            $this->filesystem->mkdir($objectsDir, 0700);
        }

        for ($i = 0; $i < 256; $i++) {
            $fanout = sprintf('%s/%02x', $objectsDir, $i);
            if (!is_dir($fanout)) {
                $this->filesystem->mkdir($fanout, 0700);
            }
        }

        return self::SUCCESS;
    }
}
