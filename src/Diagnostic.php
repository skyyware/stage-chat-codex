<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

final readonly class Diagnostic
{
    public function __construct(public DiagnosticClass $failureClass, public ?int $providerHttpStatus = null)
    {
    }
}
