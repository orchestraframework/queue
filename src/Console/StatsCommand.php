<?php

namespace Orchestra\Queue\Console;

use G4T\BeeQueue\QueueManager;
use Orchestra\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `orchestra queue:stats` — show job counts for a bee-queue Redis queue
 * (waiting / active / succeeded / failed / delayed).
 */
#[AsCommand(name: 'queue:stats', description: 'Show job counts for a bee-queue Redis queue.')]
final class StatsCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name (defaults to config queue.default)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make('queue');
        $queueName = (string) ($input->getArgument('queue') ?? (string) (config('queue.default') ?? 'default'));

        $stats = $manager->stats($queueName);

        $table = new Table($output);
        $table->setHeaders(['Queue', 'Waiting', 'Active', 'Succeeded', 'Failed', 'Delayed']);
        $table->addRow([
            $queueName,
            (string) ($stats['waiting'] ?? 0),
            (string) ($stats['active'] ?? 0),
            (string) ($stats['succeeded'] ?? 0),
            (string) ($stats['failed'] ?? 0),
            (string) ($stats['delayed'] ?? 0),
        ]);
        $table->render();

        return Command::SUCCESS;
    }
}
