<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

interface DiagnosticReceiver
{
    public function receive(Diagnostic $diagnostic): void;
}
