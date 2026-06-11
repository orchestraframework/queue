# orchestraframework/queue

Bee-queue integration for the [Orchestra Framework](https://github.com/orchestraframework/framework). Wraps [`g4t/laravel-bee-queue`](https://github.com/hussein4alaa/laravel-bee-queue) — a Redis-backed job queue that's wire-compatible with the Node.js bee-queue library — so the same Redis keys move jobs between PHP and Node.

## Install

```bash
composer require orchestraframework/queue
```

Add the provider to `config/app.php`:

```php
'providers' => [
    \Orchestra\Queue\QueueServiceProvider::class,
],
```

## Configure

Publish or hand-write `config/queue.php`. See [the full docs](https://github.com/orchestraframework/framework/blob/main/docs/34-queue.md).

## CLI

```bash
orchestra queue:work [queue] --handler=App\\Handlers\\Foo
orchestra queue:stats [queue]
```

## License

MIT.
# queue
