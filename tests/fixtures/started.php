#!/usr/bin/env php
<?php

declare(strict_types=1);

$home = getenv('CODEX_HOME');
if ($home === false) {
    exit(9);
}
file_put_contents($home . '/process-started', 'started');
require __DIR__ . '/codex-0.160.0.php';
