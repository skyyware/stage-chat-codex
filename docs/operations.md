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
Verify Codex 0.159.3 on its deployment host before accepting visitor traffic.

Relevant upstream references are [non-interactive mode](https://learn.chatgpt.com/docs/non-interactive-mode)
and the [configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference).
The installed CLI's help and the wire probe establish what the deployed binary
actually supports.
