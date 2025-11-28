<?php

namespace GitSymfony\Command;

use GitSymfony\ObjectDatabase;
use GitSymfony\Sha1;
use Symfony\Component\Console\Command\Command;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CommitTreeCommand extends Command
{
    private readonly ObjectDatabase $objects;

    public function __construct(ObjectDatabase $objects)
    {
        parent::__construct(name: 'commit-tree');
        $this->objects = $objects;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a commit from a tree object')
            ->addArgument('tree', InputArgument::REQUIRED, 'Tree object id')
            ->addOption('parent', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Parent commits');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tree = $input->getArgument('tree');
        Sha1::fromHex($tree);

        $parents = $input->getOption('parent');
        foreach ($parents as $parent) {
            Sha1::fromHex($parent);
        }

        if (!$parents) {
            $io->getErrorStyle()->writeln(sprintf('Committing initial tree %s', $tree));
        }

        $identity = $this->resolveIdentity();
        $message = $this->readMessage($input);

        $buffer = sprintf("tree %s\n", $tree);
        foreach ($parents as $parent) {
            $buffer .= sprintf("parent %s\n", $parent);
        }
        $buffer .= sprintf(
            "author %s <%s> %s\n",
            $identity['committerName'],
            $identity['committerEmail'],
            $identity['committerDate']
        );
        $buffer .= sprintf(
            "committer %s <%s> %s\n\n",
            $identity['realName'],
            $identity['realEmail'],
            $identity['realDate']
        );
        $buffer .= $message;

        $sha1 = $this->objects->storeRaw('commit', $buffer);
        $output->writeln(Sha1::toHex($sha1));

        return self::SUCCESS;
    }

    private function resolveIdentity(): array
    {
        $user = function_exists('posix_getuid') ? posix_getuid() : null;
        $pw = $user !== null && function_exists('posix_getpwuid') ? posix_getpwuid($user) : null;

        if (!$pw) {
            throw new RuntimeException("You don't exist. Go away!");
        }

        $realName = $this->sanitizeIdentity($pw['gecos'] ?? $pw['name']);
        $hostname = gethostname() ?: 'localhost';
        $realEmail = $this->sanitizeIdentity(sprintf('%s@%s', $pw['name'], $hostname));
        $realDate = $this->sanitizeIdentity($this->formatDate());

        $committerName = $this->sanitizeIdentity(getenv('COMMITTER_NAME') ?: $realName);
        $committerEmail = $this->sanitizeIdentity(getenv('COMMITTER_EMAIL') ?: $realEmail);
        $committerDate = $this->sanitizeIdentity(getenv('COMMITTER_DATE') ?: $realDate);

        return [
            'committerName' => $committerName,
            'committerEmail' => $committerEmail,
            'committerDate' => $committerDate,
            'realName' => $realName,
            'realEmail' => $realEmail,
            'realDate' => $realDate,
        ];
    }

    private function sanitizeIdentity(string $value): string
    {
        return str_replace(["\n", '<', '>'], '', $value);
    }

    private function formatDate(): string
    {
        return date('D M j H:i:s Y');
    }

    private function readMessage(InputInterface $input): string
    {
        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $resource = $stream ?: STDIN;
        $message = stream_get_contents($resource);

        return $message === false ? '' : $message;
    }
}
