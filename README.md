# Stage Chat Codex

Use the Codex CLI as a stateless chat provider from PHP. Each request returns
one JSON answer. There is no conversation database or shared agent session.
PHP 8.4 or newer, macOS or Linux, MIT licensed.

The current connector accepts Codex CLI 0.159.2 and 0.159.3. Other versions
fail before receiving conversation data. Run the wire probe for the deployed
binary and model before serving requests. This is an initial release with a
deliberately narrow execution profile.

## Install

Install the public package from
[Packagist](https://packagist.org/packages/skyyware/stage-chat-codex):

```sh
composer require skyyware/stage-chat-codex:^0.2.1
```

Composer also installs Stage Chat. No GitHub account, SSH identity, or VCS
override is required. Commit the application's lockfile. Version 0.2.1
preserves the 0.2.0 connector API; read [the changelog](CHANGELOG.md) before
updating. Running real completions requires a separately installed, supported
Codex CLI and an approved authenticated account.

## Use

Prepare the private directories and authenticate the service account as
described in [operations](docs/operations.md). The output schema is a static,
trusted application file. It must describe a JSON object.

```php
use Stage\Chat\Codex\Codex;
use Stage\Chat\Codex\Options;
use Stage\Chat\Message;
use Stage\Chat\Request;
use Stage\Chat\Role;

$chat = new Codex(new Options(
    binary: '/opt/codex/0.159.3/codex',
    workingDirectory: '/srv/stage-chat/work',
    codexHome: '/srv/stage-chat/codex',
    model: 'gpt-6.1-sol',
    outputSchema: '/srv/stage-chat/schema/answer.json',
    timeoutSeconds: 25,
    reasoningEffort: 'low',
    serviceTier: 'fast',
));

$response = $chat->complete(new Request(
    instructions: 'Answer from the context. Return JSON matching the answer schema.',
    context: 'The library opens at 09:00.',
    messages: [new Message(Role::User, 'When does the library open?')],
));

$answer = json_decode($response->text, true, 32, JSON_THROW_ON_ERROR);
```

`serviceTier: 'fast'` explicitly requests Fast Mode. The connector enables the
CLI's Fast feature and selects its `fast` tier, which sends `priority` to the
provider. Reasoning effort is independent. Omit `serviceTier` or use `null` to
keep the existing CLI default; other tier strings are rejected. The connector
does not switch models or retry with a different tier. Fast Mode requires
account and model support and consumes more usage; see
[availability and verification](docs/operations.md#fast-mode).

For that example, `answer.schema.json` can contain:

```json
{
  "type": "object",
  "properties": {"answer": {"type": "string"}},
  "required": ["answer"],
  "additionalProperties": false
}
```

Keep instructions, model, schema and process configuration under application
control. Pass retrieved text and browser history only through `context` and
`messages`. The connector sends that data through stdin, never command arguments
or request files. It validates the CLI protocol and that the final answer is a
JSON object. The application must validate its answer schema, facts, source IDs
and links before rendering. A provider's schema constraint does not replace
those checks.

The optional cancellation closure returns `true` to stop a request. Catch
`Stage\Chat\Failure` and inspect `$failure->reason` for `Unavailable`, `Timeout`,
`Cancelled` or `InvalidResponse`. Messages contain no provider payload. Never
log request objects, raw subprocess output or exception traces.

The timeout covers startup, the CLI version check and completion. Output and
stderr together are limited to 256 KiB by default. The connector starts no shell,
exposes no configurable tools, discards stderr and terminates the child on
cancellation, timeout or malformed output. It returns a final answer rather
than streaming partial text.

Authentication, retrieval, tenant isolation, safe HTML rendering, request and
concurrency limits, browser history and the maximum number of answer points
belong in the application. Stage core and Stage CMS are not runtime dependencies.

## Verify

```sh
composer install
composer check
python3 -B tests/wire_probe.py --binary /opt/codex/0.159.3/codex --model gpt-6.1-sol --concurrency 3
python3 -B tests/wire_probe.py --binary /opt/codex/0.159.3/codex --model gpt-6.1-sol --service-tier fast --concurrency 3
```

`composer check` exercises real subprocess pipes with a local fake CLI,
including cancellation, blocked stdin, timeouts, malformed and oversized
responses, child cleanup and unknown CLI versions. PHPStan runs at its maximum
level. The tests need PHP's POSIX extension.

The Python probe runs the actual CLI against a local synthetic server. It needs
no account or model call. It verifies the exact model, service tier and reasoning
effort. It also checks that the request has no tools, response storage is disabled,
personal configuration is ignored, context stays in the user data,
the JSON answer arrives and no prompt or answer marker appears in the temporary
CLI home. It checks successful and failed provider responses, known conversation
tables and isolation between concurrent requests. CLI metadata files are expected.
See the privacy limits in
[operations](docs/operations.md).

Contributions from people and agents are welcome. Read
[CONTRIBUTING.md](CONTRIBUTING.md), [AGENTS.md](AGENTS.md), and
[RELEASING.md](RELEASING.md). Use [private reporting](SECURITY.md) for vulnerabilities.
