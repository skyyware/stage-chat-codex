# Changelog

## 0.2.3 — 2026-10-02

`Options::$modelCatalogPath` accepts a protected static CLI catalog file.
`serviceTier: 'ultrafast'` requires this explicit catalog to advertise the exact
selected model, reasoning effort and tier. Missing, malformed or unadvertised
metadata fails at configuration time. The connector rechecks before starting
any CLI process; a catalog that loses support returns `Unavailable`.

The profile passes the validated path through `model_catalog_json`. Defaults
and Fast without a catalog keep their previous behavior. Actual catalog
metadata advertised Astra Max with Ultrafast, and synthetic wire matrices
passed on CLI 0.160.0/macOS and 0.159.3/Linux for exact selection, success,
provider rejection, concurrency and the existing privacy profile.

Catalog metadata does not prove current authentication or provider acceptance.
The application owns its approved account, catalog provenance and deployment.
The package supplies no account catalog and does not modify credentials.

## 0.2.2 — 2026-10-02

`Options::$reasoningEffort` also accepts `max`. GPT-6 Astra sends Max independently
of the default or explicit Fast tier. Codex CLI 0.160.0 joins the supported
0.159.2 and 0.159.3 versions. Existing defaults and process limits are unchanged.

Ultrafast remains rejected locally in 0.2.2. The tested service-home catalog
snapshots on 0.159.3 and 0.160.0 advertised Fast but no Ultrafast tier;
the CLI silently omits an unadvertised tier. The connector does not replace catalog metadata or
substitute a different tier. Reasoning `ultra` remains rejected.

Those snapshots did not establish successful provider authentication or
Ultrafast availability for a different account.

The wire probe accepts an explicit reasoning effort and checks the exact
outbound value. Default and Fast probes with Sol/Low and Astra/Max verify
success, rejection, three concurrent processes and the existing privacy profile.

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
