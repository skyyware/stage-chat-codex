# Changelog

## 0.2.1 — 2026-10-01

Public Packagist installation replaces private VCS configuration. Operational
examples use generic service paths. Contribution, security, and release guides
describe local checks and public PRs for people and agents.

No connector API, execution profile, supported CLI, or retention behavior changes
from 0.2.0. The Stage Chat dependency remains compatible with 0.1.

## 0.2.0 — 2026-10-01

`Options::$serviceTier` accepts explicit `fast` or `null`. Fast enables the CLI
feature and requests the provider's `priority` tier independently of reasoning
effort. Invalid tiers fail locally; provider rejection never triggers a model
or tier fallback. Omitting the option preserves the previous profile.

Default and Fast wire probes verify success, rejection, exact model/tier,
tool-free requests, disabled response storage, and three concurrent processes.
Provider-confirmed served-tier metadata and latency guarantees are not exposed.

## 0.1.0 — 2026-10-01

Initial MIT Codex CLI implementation of Stage Chat. The adapter uses private
directories, stdin input, an ephemeral tool-free profile, bounded output,
cancellation, child cleanup, and content-free failures. CLI versions 0.159.2
and 0.159.3 are supported. Applications own retrieval, policy, and rendering.
