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

For a service account named `stagechat`, prepare the directories during host
provisioning. Replace these example paths to match the owning application.

```sh
sudo install -d -o stagechat -g stagechat -m 0700 /srv/stage-chat/codex /srv/stage-chat/work
sudo -u stagechat env CODEX_HOME=/srv/stage-chat/codex /opt/codex/0.159.3/codex login --device-auth
sudo -u stagechat env CODEX_HOME=/srv/stage-chat/codex /opt/codex/0.159.3/codex login status
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
client and workspace access. It consumes more usage than Standard mode.
Check the account's current terms in the speed documentation.
No account or subscription changes are performed by this package.

An invalid local tier fails at configuration time. A provider rejection becomes
the existing content-free failure; the connector never substitutes Luna, another
model or another tier. The supported completion interface returns answer text
without provider-confirmed model or served-tier metadata. Request verification and a successful completion
therefore establish that Fast was requested and the account completed the call,
not a latency guarantee or an independent audit of provider scheduling.

### Verified behavior

The 0.2 profile was checked with the direct Codex CLI 0.159.3 executable.
Default and Fast synthetic wire probes passed at three concurrent requests,
for success and provider rejection. Fast sent `priority`; the default sent
no tier. Both kept the selected model and `low` reasoning effort, exposed no
tools, and sent `store: false`. A rejected Fast tier produced no fallback call.
Known conversation tables were empty and no content markers remained in the
isolated CLI home. CLI metadata files were expected.

Authenticated synthetic calls confirmed JSON completion with GPT-6.1 Sol and
explicit Fast. These small samples establish the tested request profile and
completion, not production capacity, a latency percentile, source correctness,
or provider retention. The interface does not report the provider's served
model or tier. Verify the real application and host separately after a runtime
change; keep that evidence with the application.

## Reasoning and Ultrafast

Set `model: 'gpt-6-astra', reasoningEffort: 'max'` to request Max reasoning.
Keep `serviceTier: null` for the default tier or select `serviceTier: 'fast'`.
Reasoning and speed are independent; the existing default remains `low`.
Use a reasoning level advertised by the selected model and verify its exact
request value with `--reasoning-effort` in the wire probe.

On 2 October 2026, the authenticated model catalogs for the tested macOS
CLI 0.160.0 and Linux CLI 0.159.3 advertised Astra Max and the `priority` tier,
but no `ultrafast` tier. A direct Ultrafast request silently omitted
`service_tier`, including with the Fast feature disabled. The configuration
reference requires a tier advertised by the active model. The package therefore
rejects `ultrafast` before starting a completion. It supplies no replacement
catalog and makes no account or workspace changes. General Ultrafast
availability in the speed documentation does not prove this CLI account path
supports it. Recheck the actual catalog and wire before extending the allowlist.

Reasoning `ultra` is a separate mode that the tested catalogs describe as
automatic task delegation. It remains rejected in this stateless connector.

The 0.2.2 wire matrix passed on macOS with direct CLI 0.160.0 and on Linux
with direct CLI 0.159.3 under the application's unprivileged service identity.
The tested source and schema matched the package checkout. It uses Sol/Low
and Astra/Max, each with the default and Fast
tiers. It checks success and provider rejection at three concurrent requests,
exact model, reasoning and tier, no tools, `store: false`, no retained content
markers and empty known conversation tables. These are synthetic provider
checks; they do not establish Max latency or authenticated provider acceptance.

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

Run `composer check` for every package release. After a profile, CLI, model,
or host change, run the synthetic wire probe on the target binary and model.
For an authorized application acceptance check, run one clearly synthetic
completion using its service account. Confirm a valid answer, cancellation behavior, bounded errors,
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
