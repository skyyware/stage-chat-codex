<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use Closure;
use InvalidArgumentException;
use SensitiveParameter;
use Stage\Chat\Connector;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;
use Stage\Chat\Request;
use Stage\Chat\Response;
use Throwable;

final readonly class Codex implements Connector
{
    public function __construct(private Options $options, private ?DiagnosticReceiver $diagnosticReceiver = null)
    {
    }

    /** @param (Closure(): bool)|null $cancelled */
    public function complete(#[SensitiveParameter] Request $request, #[SensitiveParameter] ?Closure $cancelled = null): Response
    {
        $events = new Events($this->diagnosticReceiver !== null);
        $diagnostic = new Diagnostic(DiagnosticClass::Unknown);
        try {
            return $this->run($request, $cancelled, $events, $diagnostic);
        } catch (Failure $failure) {
            $diagnostic = match ($failure->reason) {
                FailureReason::Timeout => new Diagnostic(DiagnosticClass::Timeout),
                FailureReason::Cancelled => new Diagnostic(DiagnosticClass::Cancelled),
                FailureReason::InvalidResponse => new Diagnostic(DiagnosticClass::InvalidResponse),
                FailureReason::Unavailable => $events->diagnostic() ?? $diagnostic,
            };
            try {
                $this->diagnosticReceiver?->receive($diagnostic);
            } catch (Throwable) {
            }
            throw $failure;
        }
    }

    /** @param (Closure(): bool)|null $cancelled */
    private function run(#[SensitiveParameter] Request $request, #[SensitiveParameter] ?Closure $cancelled, #[SensitiveParameter] Events $events, Diagnostic &$diagnostic): Response
    {
        try {
            ModelCatalog::verify($this->options);
        } catch (InvalidArgumentException) {
            $diagnostic = new Diagnostic(DiagnosticClass::ModelCatalog);
            throw new Failure(FailureReason::Unavailable);
        }
        $profile = new Profile($this->options);
        $process = new Process();
        $deadline = hrtime(true) / 1e9 + $this->options->timeoutSeconds;
        $version = '';
        $process->run(
            [$this->options->binary, '--version'], '', $profile->environment(), $this->options->workingDirectory,
            $deadline, 1024, static function (string $chunk) use (&$version): void { $version .= $chunk; }, $cancelled,
        );
        if (!in_array(trim($version), ['codex-cli 0.159.2', 'codex-cli 0.159.3', 'codex-cli 0.160.0', 'codex-cli 0.167.0'], true)) {
            $diagnostic = new Diagnostic(DiagnosticClass::CliVersion);
            throw new Failure(FailureReason::Unavailable);
        }

        $process->run(
            $profile->command($request), $profile->input($request), $profile->environment(), $this->options->workingDirectory,
            $deadline, $this->options->maxOutputBytes, $events->receive(...), $cancelled,
        );

        return $events->response();
    }
}
