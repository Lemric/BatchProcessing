Lemric BatchProcessing Documentation
=====================================

**Lemric BatchProcessing** is an enterprise-grade batch processing library for
PHP 8.4+.
It is framework-agnostic (PSR-3, PSR-6/16, PSR-11, PSR-14), with optional
bridges for Symfony 6.4/7+ and Laravel 11+/12+.

Getting Started
---------------

* [Installation](installation.md)
* [Quick Start](quick-start.md)
* [Architecture Overview](architecture.md)

Core Concepts
-------------

* [Domain Model](domain-model.md)
* [Jobs](jobs.md)
* [Steps](steps.md)
* [Chunk-Oriented Processing](chunk-processing.md)
* [Tasklet Steps](tasklets.md)

Reading & Writing
-----------------

* [Item Readers](item-readers.md)
* [Item Processors](item-processors.md)
* [Item Writers](item-writers.md)

Error Handling
--------------

* [Retry Framework](retry.md)
* [Skip Framework](skip.md)
* [Exception Hierarchy](exceptions.md)

Infrastructure
--------------

* [Repository & Schema](repository.md)
* [Transactions](transactions.md)
* [Listeners & Events (PSR-14)](events.md)

Advanced
--------

* [Partition & Parallel Processing](partition.md)
* [Flow Jobs & Conditional Steps](flow-jobs.md)
* [Job Operator & Explorer](operator.md)
* [Scopes & Late Binding](scopes.md)
* [Restart Semantics](restart.md)

Framework Integration
---------------------

* [Symfony Bridge](integration/symfony.md)
* [Laravel Bridge](integration/laravel.md)

Reference
---------

* [Configuration Reference](configuration.md)
* [PSR Compliance](psr.md)
* [Testing Utilities](testing.md)
* [Performance & Best Practices](performance.md)
* [Class Index](class-index.md)
* [Changelog](changelog.md)

