# Operating the Codex connector

Run chat under a dedicated, unprivileged service identity. Keep its Codex home,
empty working directory and static schema outside the document root, uploads
and application caches. Never pass an entire developer home to a web process.
Use an account and service plan approved for the application.

Point `Options::$binary` at a direct, version-pinned executable owned by the
host administrator. A global command may be a wrapper that changes users,
homes or environment variables. A version string alone cannot detect that.
Verify the real executable and run the probe under the same service identity
as PHP. The paths below assume `/opt/codex/0.159.3/codex`; use the actual
verified installation path on your host.

## Authentication and files

For a service account named `citychat`, prepare the directories during host
provisioning. Replace these example paths to match the owning application.

```sh
sudo install -d -o citychat -g citychat -m 0700 /srv/citychat/codex /srv/citychat/work
sudo -u citychat env CODEX_HOME=/srv/citychat/codex /opt/codex/0.159.3/codex login --device-auth
sudo -u citychat env CODEX_HOME=/srv/citychat/codex /opt/codex/0.159.3/codex login status
```

A human completes the sign-in. Credentials stay in this service's private home.
Do not commit them, copy personal configuration, put credentials in command
arguments or grant a web process access to a developer's Codex directory.
Keep `auth.json`, when present, readable only by the service account. The package
does not provision accounts, copy credentials or change permissions for you.

Both directories must have mode 0700. The working directory must be empty.
The schema must be an absolute readable path, at most 64 KiB, with a root JSON
object type. Deploy it with code; never generate it from visitor input.
PHP must permit `proc_open` and access these paths. Give the web server and PHP
worker longer deadlines than the configured connector timeout, including time
to render an error. The default connector deadline is 25 seconds.

Use an application concurrency limit before starting the CLI. Every request
launches a process and occupies a PHP worker until completion. The application
owns rate limits and admission control. If using a client-disconnect callback,
ensure the PHP request remains alive long enough to run connector cleanup;
PHP buffering can delay when `connection_aborted()` observes a disconnect.

Three concurrent requests may share one dedicated Codex home and empty working
directory. Each request has its own process and stdin/stdout pipes. Local probes
verified this setup with a fresh shared metadata database, including provider
errors. Three authenticated synthetic `gpt-6-luna` completions also returned
their own distinct answers in 3.93 to 5.68 seconds. No content markers remained
in the CLI home. This does not exercise an expired-credential refresh race or
establish capacity beyond three workers. Repeat the three-request probe on the
deployment host before using this arrangement there.

## Fast Mode

The application selects `model: 'gpt-6.1-sol', serviceTier: 'fast'` in `Options`.
The connector supplies `service_tier="fast"` and `features.fast_mode=true` to the
CLI. Codex translates that choice to the provider request value `priority`.
`reasoningEffort` remains a separate option; reducing reasoning is not Fast Mode.
The default `serviceTier: null` preserves the previous command profile. No
provider-specific setting is added to the `Stage\Chat\Connector` contract.

The [Codex speed documentation](https://learn.chatgpt.com/docs/agent-configuration/speed)
and [configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference)
describe this mapping and its availability. Fast Mode depends on model, account,
client and workspace access. At the 1 October 2026 check, the documentation
listed increased usage: 2.5 times included subscription usage or twice the
Standard rate for purchased credits and Enterprise pay-as-you-go usage.
Recheck the account's current terms rather than treating those rates as fixed.
No account or subscription changes are performed by this package.

An invalid local tier fails at configuration time. A provider rejection becomes
the existing content-free failure; the connector never substitutes Luna, another
model or another tier. The supported completion interface returns answer text
without provider-confirmed model or served-tier metadata. Request verification and a successful completion
therefore establish that Fast was requested and the account completed the call,
not a latency guarantee or an independent audit of provider scheduling.

### Verification trial, 1 October 2026

Starting point: `v0.1.0` at `98ac7d441d76b726a282333398f3bf41fcdcac32`
allowed model and reasoning selection but had no explicit service-tier option.
Citychat required GPT-6.1 Sol with genuine Fast Mode. The trial used the documented
CLI tier setting, kept its translation in this package and left application
selection, retrieval, source validation and capacity limits with the caller.
Expected benefit: an explicit, testable speed choice without reducing reasoning
or silently changing the requested model.

Success required the actual CLI to send `gpt-6.1-sol`, `priority` and the unchanged
`low` reasoning effort, preserve the existing tool and retention boundaries, and
produce grounded synthetic moving and follow-up answers. Tier rejection, model
substitution, unrelated dog advice or invented missing sources counted as failure.
The investigation followed pstack boundary and verification principles within
gstack's reproduce, trace, test and verify sequence, using the package's own tools.

Results for the local direct Codex CLI 0.159.3 executable and PHP 8.4.26:

- The bundled and current authenticated model catalogues advertised `priority`
  as Fast for `gpt-6.1-sol`. Catalogue visibility alone was not treated as a
  successful model call.
- New contract tests failed before the option existed. After implementation,
  Composer validation, PHPStan at maximum level and 26 tests with 98 assertions
  passed, including timeout, cancellation, child cleanup and sanitized failures.
- Both default and Fast wire probes passed at three concurrent requests, for
  success and provider rejection. Fast sent `priority`; the default sent no tier.
  Every captured request retained the exact model and reasoning effort, exposed
  no tools and set `store: false`. No request or answer markers remained on disk,
  and known conversation tables were empty. A rejected Fast tier did not trigger
  additional requests or a fallback model.
- Three authenticated synthetic calls through the PHP connector passed:
  “Ich wollte einen Umzug melden” selected residence registration first in
  6.12 seconds; its personal-appointment follow-up retained the residence context
  in 6.09 seconds; the empty-source case acknowledged missing evidence and
  supplied no source IDs in 5.00 seconds. Irrelevant dog-registration evidence was
  present in the first two cases and was not recommended.
- After those calls, 15 non-authentication files and six known content tables
  contained zero synthetic markers and zero conversation rows. The temporary
  same-user authentication link and disposable home were removed. No account
  configuration, application runtime or shared host settings were changed.

The private `v0.2.0` tag was pushed at
`f8ce0e124ce7594e627ee916937b620db29d9259`, retaining `stage-chat v0.1.0`
at `240cf0852ea18c83ee2a887ef5cf4b1c2c942263`. A fresh Composer consumer
installed those exact references without development dependencies and exercised
the Fast option successfully through real subprocess pipes with the fake CLI.
These small synthetic samples isolate the connector and supplied evidence; they
do not establish Citychat retrieval quality, municipal correctness, a production
latency percentile or provider retention. The application owner must run the
same Fast wire probe under its Linux service identity and verify its real moving,
follow-up and missing-source journeys before accepting deployment. Keep that
application evidence in Citychat's own verification record.

## What is retained

The connector writes no prompt or answer files. It uses `--ephemeral`, disables
history, memories, analytics and telemetry exporters, and sets `RUST_LOG=off`.
It ignores user configuration and disables tools, plugins, hooks, skills,
browser access and agent delegation. It supplies only an allowlist of ordinary
process environment variables, excluding inherited API keys and endpoint
overrides. Unknown CLI versions and configuration fields fail closed.

Codex still creates authentication and metadata files, including SQLite files
and an installation ID. The synthetic probe checks those files for unique
request and answer markers and verifies that known conversation tables are
empty. That is evidence for the tested CLI, model and
configuration, not a promise that an arbitrary future version behaves alike.
Re-run it after binary, model or host configuration changes. A managed host's
system configuration is also part of that verification.

The model provider receives the conversation and supplied context. The probe
observes `store: false` in the API request; this is not a guarantee of zero
provider retention. Account policy, abuse monitoring, infrastructure logs,
backups and host crash dumps are separate concerns. Describe those limits
accurately in the application's privacy notice. Do not claim that no information
is stored anywhere.

Disable request-body logging and debug exception output in the application,
proxy and error reporting system. Keep visitor questions out of URLs and query
strings. The connector's own errors contain only a stable failure category.
Render output as text or safely escaped markup, and validate links against the
application's selected sources.

## Release checks

Run the local checks and synthetic wire probe on the target CLI and model.
Then run one clearly synthetic completion through the application using its
service account. Confirm a valid answer, cancellation behavior, bounded errors,
no conversation logging and the application's concurrency limits.

The initial local verification used Codex 0.159.2 with `gpt-6.1-sol` and
`gpt-6-luna`. Both synthetic wire probes exposed no tools and retained no content
markers. One real `gpt-6.1-sol` completion through the PHP connector returned the
expected JSON answer in 6.68 seconds. This is a smoke test, not a latency target.
On 2026-10-01, the deployment owner also ran the probe on Linux with the direct
Codex 0.159.3 executable, `gpt-6-luna` and three concurrent requests under an
isolated service account. Both successful responses and provider errors passed.
There were no exposed tools, retained content markers or conversation-table
rows, and response storage was disabled. The tested source and probe files
matched the `v0.1.0` release. This check used a local synthetic provider; it is
separate from the authenticated application check on that host.

Relevant upstream references are [non-interactive mode](https://learn.chatgpt.com/docs/non-interactive-mode)
and the [configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference).
The installed CLI's help and the wire probe establish what the deployed binary
actually supports.
