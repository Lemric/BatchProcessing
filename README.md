<h1 align="center">Lemric BatchProcessing</h1>

<p align="center">
    <em>Enterprise-grade batch processing for PHP 8.4+.</em>
</p>

<p align="center">
    <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-%E2%89%A58.4-777BB4" alt="PHP"></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-Dual%20License-blue" alt="License"></a>
    <a href="https://github.com/Lemric/BatchProcessing/issues"><img src="https://img.shields.io/badge/issues-GitHub-181717" alt="Issues"></a>
</p>

---

Lemric BatchProcessing is a framework-agnostic library for building robust,
restartable, observable batch jobs in PHP.
Compliance with PSR standards and the idiomatic PHP style.

## Why Lemric BatchProcessing?

* **Production-ready** — atomic per-chunk transactions, full restart semantics,
  durable metadata.
* **Framework-agnostic** — works standalone, with optional bridges for Symfony
  6.4/7+ and Laravel 11+/12+.
* **Standards-first** — PSR-3 logger, PSR-6/16 cache, PSR-11 container, PSR-14 events.
* **Modern PHP** — readonly classes, enums, attributes, typed properties, strict types.

## Installation

```bash
composer require lemric/batch-processing
```

Requires **PHP 8.4** or higher.

## Quick Example

```php
use Lemric\BatchProcessing\BatchProcessing;
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Item\Reader\IteratorItemReader;
use Lemric\BatchProcessing\Testing\InMemoryItemWriter;

$ctx    = BatchProcessing::inMemory();
$reader = new IteratorItemReader(range(1, 1000));
$writer = new InMemoryItemWriter();

$step = $ctx['stepBuilderFactory']->get('demoStep')
    ->chunk(100, $reader, null, $writer)
    ->build();

$job = $ctx['jobBuilderFactory']->get('demoJob')->start($step)->build();

$execution = $ctx['launcher']->run($job, JobParameters::of(['run.id' => 1]));

echo $execution->getStatus()->value; // COMPLETED
```

## Documentation

Full documentation lives in the [`docs/`](docs/index.md) directory:

* **[Getting Started](docs/index.md)** — installation, quick start, architecture.
* **[Core Concepts](docs/index.md#core-concepts)** — domain model, jobs, steps, chunks.
* **[Reading & Writing](docs/index.md#reading--writing)** — readers, processors, writers.
* **[Error Handling](docs/index.md#error-handling)** — retry, skip, exceptions.
* **[Infrastructure](docs/index.md#infrastructure)** — repository, transactions, events.
* **[Advanced](docs/index.md#advanced)** — partitioning, flow jobs, scopes, restart.
* **[Framework Integration](docs/index.md#framework-integration)** — Symfony & Laravel.
* **[Reference](docs/index.md#reference)** — configuration, PSR, testing, performance.

## Development

Clone the repository and install dependencies:

```bash
git clone https://github.com/Lemric/BatchProcessing.git
cd BatchProcessing
composer install
```

Run the test suite:

```bash
composer test
```

Run static analysis and code style fixes:

```bash
composer stan
```

## Support

* **Issues**: [GitHub Issues](https://github.com/Lemric/BatchProcessing/issues)
* **Security**: [Security Policy](https://github.com/Lemric/BatchProcessing/security)
* **Discussions**: [GitHub Discussions](https://github.com/Lemric/BatchProcessing/discussions)

## License

This project is developed by Lemric and is available under a **dual licensing model**:

| Use case                       | Allowed | Cost                    |
|--------------------------------|---------|-------------------------|
| Open Source project            | ✅      | Free                    |
| Personal non-commercial use    | ✅      | Free                    |
| Commercial / SaaS / enterprise | ❌      | Paid license required   |
| Closed-source project          | ❌      | Paid license required   |

For commercial licensing inquiries, contact: **dominik@labudzinski.com**

See the [LICENSE](LICENSE) file for full terms.

## Authors

* **Dominik Labudzinski** — [dominik@labudzinski.com](mailto:dominik@labudzinski.com) — [labudzinski.com](https://labudzinski.com)
* **Lemric** — [lemric.com](https://lemric.com)
