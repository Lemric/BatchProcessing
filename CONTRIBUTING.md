# Contributing

Thank you for contributing to `lemric/batch-processing`.

## Requirements

- PHP `8.4+`
- Composer `2+`

## Local setup

```bash
composer install
```

## Quality checks

Run all required checks before opening a pull request:

```bash
vendor/bin/phpunit --colors=always
vendor/bin/phpstan analyse src tests --level max --memory-limit=-1
composer audit --locked
```

## Pull request guidelines

- Create focused pull requests that solve a single concern.
- Add or update tests for behavioral changes.
- Keep public API changes documented in `README.md`.
- Ensure CI is green before requesting review.

## Commit message guidelines

- Use concise, imperative messages.
- Explain **why** the change is needed, not only what changed.

## Reporting security issues

Please do not open public issues for vulnerabilities. Follow `SECURITY.md`.
