<?php

namespace Orchestra\Queue;

use G4T\BeeQueue\Queue;
use G4T\BeeQueue\QueueManager;
use G4T\BeeQueue\Worker;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Foundation\ServiceProvider;

/**
 * Wires the bee-queue (g4t/laravel-bee-queue) Redis-backed job system into
 * the Orchestra container. The package's `QueueManager` is configuration-only
 * (no Laravel container dependency), so we just hand it `config('queue')`.
 *
 * Bindings (deferred):
 *   - `queue` (singleton)           → G4T\BeeQueue\QueueManager
 *   - `G4T\BeeQueue\QueueManager`   → same instance
 *
 * Use the `queue()` helper or typehint `QueueManager` in your handlers.
 */
final class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('queue', function (): QueueManager {
            /** @var Repository $config */
            $config = $this->app->make('config');
            /** @var array<string,mixed> $settings */
            $settings = (array) $config->get('queue', []);

            return new QueueManager($settings);
        });

        $this->app->singleton(QueueManager::class, fn (): QueueManager => $this->app->make('queue'));
    }

    public function provides(): array
    {
        return [
            'queue',
            QueueManager::class,
            Queue::class,
            Worker::class,
        ];
    }
}
