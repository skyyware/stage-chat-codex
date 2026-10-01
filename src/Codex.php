<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use Closure;
use SensitiveParameter;
use Stage\Chat\Connector;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;
use Stage\Chat\Request;
use Stage\Chat\Response;

final readonly class Codex implements Connector
{
    public function __construct(private Options $options)
    {
    }

    /** @param (Closure(): bool)|null $cancelled */
    public function complete(#[SensitiveParameter] Request $request, #[SensitiveParameter] ?Closure $cancelled = null): Response
    {
        $profile = new Profile($this->options);
        $process = new Process();
        $deadline = hrtime(true) / 1e9 + $this->options->timeoutSeconds;
        $version = '';
        $process->run(
            [$this->options->binary, '--version'], '', $profile->environment(), $this->options->workingDirectory,
            $deadline, 1024, static function (string $chunk) use (&$version): void { $version .= $chunk; }, $cancelled,
        );
        if (!in_array(trim($version), ['codex-cli 0.159.2', 'codex-cli 0.159.3'], true)) {
            throw new Failure(FailureReason::Unavailable);
        }

        $events = new Events();
        $process->run(
            $profile->command($request), $profile->input($request), $profile->environment(), $this->options->workingDirectory,
            $deadline, $this->options->maxOutputBytes, $events->receive(...), $cancelled,
        );

        return $events->response();
    }
}
