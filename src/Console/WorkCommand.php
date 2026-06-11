<?php

namespace Orchestra\Queue\Console;

use G4T\BeeQueue\QueueManager;
use Orchestra\Foundation\Application;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `orchestra queue:work` — process jobs from a bee-queue Redis queue.
 *
 * Usage:
 *   orchestra queue:work                       # default queue, Closure handler must be registered
 *   orchestra queue:work emails                # specific queue
 *   orchestra queue:work emails --handler=App\\Handlers\\SendEmail
 *   orchestra queue:work --once                # process one job and exit
 *
 * SIGTERM stops the worker after the in-flight job finishes (kubernetes-safe).
 */
#[AsCommand(name: 'queue:work', description: 'Process jobs from a bee-queue Redis queue.')]
final class WorkCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('queue', InputArgument::OPTIONAL, 'Queue name (defaults to config queue.default)')
            ->addOption('handler', null, InputOption::VALUE_REQUIRED, 'Invokable class to process jobs')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Process a single job and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make('queue');
        $queueName = (string) ($input->getArgument('queue') ?? (string) (config('queue.default') ?? 'default'));

        $worker = $manager->worker($queueName);
        $handler = $this->resolveHandler((string) ($input->getOption('handler') ?? ''));

        if ($handler === null) {
            $output->writeln('<error>No handler given. Provide --handler=Class or register a queue handler in a provider.</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Worker started on queue "%s". Press Ctrl+C to stop.</info>', $queueName));

        // SIGTERM handling — finish current job, exit cleanly.
        $stop = false;
        if (function_exists('pcntl_signal') && function_exists('pcntl_signal_dispatch')) {
            pcntl_signal(SIGTERM, function () use (&$stop): void {
                $stop = true;
            });
            pcntl_signal(SIGINT, function () use (&$stop): void {
                $stop = true;
            });
        }

        $once = (bool) $input->getOption('once');

        $worker->process(function ($job) use ($handler, $output): mixed {
            try {
                return $handler($job);
            } catch (Throwable $e) {
                $this->reportFailure($e, $output);

                throw $e;
            }
        });

        if ($once) {
            return Command::SUCCESS;
        }

        // The bee-queue worker loop blocks internally; the loop below is a
        // fallback for backends/implementations where process() returns early.
        while (! $stop) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            usleep(100_000);
        }

        $output->writeln('<info>Worker stopped.</info>');

        return Command::SUCCESS;
    }

    private function resolveHandler(string $class): ?callable
    {
        if ($class === '') {
            return null;
        }
        if (! class_exists($class)) {
            return null;
        }
        /** @var object $instance */
        $instance = $this->app->make($class);
        if (is_callable($instance)) {
            return $instance;
        }
        if (method_exists($instance, 'handle')) {
            return fn ($job) => $instance->handle($job);
        }

        return null;
    }

    private function reportFailure(Throwable $e, OutputInterface $output): void
    {
        $output->writeln(sprintf('<error>Job failed: %s — %s</error>', $e::class, $e->getMessage()));
        if (! $this->app->bound(LoggerInterface::class)) {
            return;
        }
        try {
            /** @var LoggerInterface $log */
            $log = $this->app->make(LoggerInterface::class);
            $log->error('queue.job.failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (Throwable) {
            // ignore
        }
    }
}
