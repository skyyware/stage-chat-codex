# Working on Stage Chat Codex

Read README.md and docs/operations.md before changing the connector.
Keep application content rules in the caller. This package owns process
execution, the fixed Codex profile and decoding the CLI event stream.

Run `composer check`. Changes to the profile, CLI version or model need the
synthetic wire probe on the target host. Never turn a failing probe into a
passing check by deleting its assertions or enabling tools. An actual provider
smoke test must be explicitly scoped to synthetic application input.

Never log prompts, answers, stderr or credentials. Do not add arbitrary
CLI arguments, environment passthrough or tool configuration to the public API.
No explanatory source comments or TODOs; static-analysis annotations and legal
notices are allowed. Keep runtime data and dependencies outside Git.

Keep repositories private and automation disabled until the maintainer changes
those settings explicitly. Publish validated versions through manual Git tags.
