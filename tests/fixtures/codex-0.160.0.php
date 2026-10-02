#!/usr/bin/env php
<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--version') {
    fwrite(STDOUT, "codex-cli 0.160.0\n");
    exit(0);
}

require __DIR__ . '/codex.php';
