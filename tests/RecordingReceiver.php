<?php

declare(strict_types=1);

namespace Stage\Chat\Codex\Tests;

use Stage\Chat\Codex\Diagnostic;
use Stage\Chat\Codex\DiagnosticReceiver;

final class RecordingReceiver implements DiagnosticReceiver
{
    /** @var list<Diagnostic> */
    public array $received = [];

    public function receive(Diagnostic $diagnostic): void
    {
        $this->received[] = $diagnostic;
    }
}
