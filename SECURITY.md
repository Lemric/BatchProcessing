# Security policy

## Supported versions

Security fixes are applied to the latest release line maintained in this repository. Use the newest compatible version and keep framework integrations (Laravel, Symfony) patched.

## Reporting a vulnerability

Please use the contact published in [`composer.json`](composer.json) under `support.security` (GitHub Security Advisories / private report). Do not open public issues for undisclosed vulnerabilities.

## Operational guidance (integrators)

### Asynchronous jobs (Laravel queue / Symfony Messenger)

- **HMAC secret is mandatory** when async execution is enabled. Configure a long, random secret (e.g. 32+ bytes from a CSPRNG, stored in a secret manager) and use the **same** value on every web/worker instance.
- Laravel: `BATCH_ASYNC_MESSAGE_SECRET` / `batch_processing.async.message_secret`.
- Symfony: `batch_processing.async_launcher.message_secret`.
- Restrict who can **publish** to the batch transport (network ACLs, broker credentials, IAM). The signature protects **integrity** on the transport; it does not replace transport access control.

### Authorization and trust boundaries

- Running a batch job (Artisan / `bin/console`, HTTP-triggered launcher, or queue worker) executes **application code** registered for that job name. Treat **job launch** and **parameter injection** as privileged operations.
- Lock down Artisan / console in production; use deployment roles and avoid exposing launchers on untrusted networks without authentication.

### SQL and file paths

- Do not build SQL for `PaginatedPdoItemReader`, `LimitOffsetPagingQueryProvider`, or similar APIs from untrusted input. Use **static** SQL and bound parameters only.
- `UnsafeSqlQueryFragmentValidator` is a **heuristic** helper, not a substitute for safe query design.
- When using optional `allowedBaseDirectory` on file readers/writers (including Symfony Serializer adapters), set it to a dedicated import/export directory to limit path traversal risk.

### Late-binding expressions

- Expressions resolved by `ExpressionLanguageLateBindingResolver` or `SimpleLateBindingExpressionResolver` must come from **trusted configuration**. Never pass raw user input. Be cautious when registering custom ExpressionLanguage functions.

### Data at rest

- Job parameters, execution contexts, and exit messages may contain operational or personal data. Apply database encryption, minimal retention, and least-privilege database accounts as required by your compliance regime.

### Dependency auditing

- Run `composer audit` regularly. This package lists `roave/security-advisories` in `require-dev` to block known-vulnerable dependency versions during development.
