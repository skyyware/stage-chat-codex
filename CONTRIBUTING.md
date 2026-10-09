# Contribute to Stage Chat Codex

Bug reports, documentation improvements, and focused pull requests are welcome.
Include the package, PHP, OS, and CLI versions, a synthetic reproduction, and
the expected and actual result. Never attach authentication files, private
configuration, prompts, answers, subprocess stderr, or provider traces.

1. Fork and clone the repository. Read README.md, AGENTS.md, and docs/operations.md.
2. Run `composer install` with PHP 8.4 or later and the POSIX extension.
3. Keep application policy in the caller and process execution in this adapter.
4. Add a regression check for changed behavior. Exercise cancellation, malformed output, timeout, cleanup, and sanitized failures where relevant.
5. Update the affected API example and operational limits.
6. Run `composer check`. For profile, model, or CLI changes, also run the documented synthetic wire probe on the target binary and host.
7. Open a focused pull request with the problem, resulting behavior, and checks performed.

For behavior, API, requirements, or usage changes, review the package docs and
executable examples together. Identify affected technical guidance on
[stage.dev](https://stage.dev/). Maintainers coordinate its curated knowledge
update in the same delivery under [RELEASING.md](RELEASING.md).
You do not need access to the website repository to contribute.

The fake CLI and wire probe do not need an account or paid model call. Keep
their assertions intact. An authenticated smoke test needs separate, explicit
synthetic-input scope. Do not introduce tools, arbitrary CLI arguments,
environment passthrough, silent model fallback, or conversation persistence.

Use explicit PHP types and constructors; PHPStan runs at maximum level.
Add no narrative comments or TODOs. Consumed type annotations and required
legal notices are allowed. Repository automation stays disabled. Humans and
agents follow the same checks and [MIT contribution terms](LICENSE).

Maintainers follow [RELEASING.md](RELEASING.md). Use
[private reporting](SECURITY.md) for security findings.
