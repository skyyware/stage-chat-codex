<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Stage\Chat\Codex\Options;
use Stage\Chat\Codex\Profile;
use Stage\Chat\Message;
use Stage\Chat\Request;
use Stage\Chat\Role;

if (count($argv) < 5 || count($argv) > 8) {
    exit(2);
}

$options = new Options($argv[1], $argv[2] . '/work', $argv[2] . '/codex', $argv[3], __DIR__ . '/fixtures/answer.json', reasoningEffort: $argv[6] ?? 'low', serviceTier: ($argv[5] ?? '') === '' ? null : $argv[5], modelCatalogPath: ($argv[7] ?? '') === '' ? null : $argv[7]);
$profile = new Profile($options);
$request = new Request('Return the supplied marker as the answer.', $argv[4], [new Message(Role::User, 'What is the marker?')]);

fwrite(STDOUT, json_encode([
    'command' => $profile->command($request),
    'input' => $profile->input($request),
    'environment' => $profile->environment(),
], JSON_THROW_ON_ERROR));
