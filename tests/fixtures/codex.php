#!/usr/bin/env php
<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--version') {
    fwrite(STDOUT, "codex-cli 0.159.2\n");
    exit(0);
}

$modelIndex = array_search('--model', $argv, true);
$mode = $modelIndex === false ? '' : ($argv[$modelIndex + 1] ?? '');
$directory = getenv('CODEX_HOME');
if ($directory === false) {
    exit(9);
}
file_put_contents($directory . '/fixture.pid', (string) getmypid());
$imageIndex = array_search('--image', $argv, true);
if ($imageIndex !== false) {
    $path = $argv[$imageIndex + 1];
    file_put_contents($directory . '/fixture-image.json', json_encode([
        'path' => $path,
        'sha256' => hash_file('sha256', $path),
        'mode' => fileperms($path) & 0777,
        'directoryMode' => fileperms(dirname($path)) & 0777,
    ], JSON_THROW_ON_ERROR));
}

if ($mode === 'hang' || $mode === 'no-read') {
    sleep(5);
    exit(0);
}

$input = stream_get_contents(STDIN);
if ($input === false) {
    exit(8);
}
$decoded = json_decode($input, true, 32, JSON_THROW_ON_ERROR);

if ($mode === 'error') {
    fwrite(STDERR, 'PRIVATE_PROVIDER_ERROR');
    exit(1);
}
if ($mode === 'overflow' || $mode === 'stderr-overflow') {
    fwrite($mode === 'overflow' ? STDOUT : STDERR, str_repeat('x', 300000));
    sleep(5);
    exit(0);
}
if ($mode === 'malformed') {
    fwrite(STDOUT, "not JSON\n");
    exit(0);
}
if ($mode === 'tool') {
    fwrite(STDOUT, "{\"type\":\"item.started\",\"item\":{\"type\":\"command_execution\",\"command\":\"PRIVATE_TOOL_DATA\"}}\n");
    sleep(5);
    exit(0);
}
if ($mode === 'turn-failed') {
    fwrite(STDOUT, "{\"type\":\"turn.failed\",\"error\":{\"message\":\"PRIVATE_PROVIDER_ERROR\"}}\n");
    exit(0);
}
if (str_starts_with($mode, 'diagnostic-')) {
    $message = match ($mode) {
        'diagnostic-auth' => 'Your access token could not be refreshed. Please log out and sign in again.',
        'diagnostic-auth-expired' => 'Your access token could not be refreshed because your refresh token has expired. Please log out and sign in again.',
        'diagnostic-auth-used' => 'Your access token could not be refreshed because your refresh token was already used. Please log out and sign in again.',
        'diagnostic-auth-revoked' => 'Your access token could not be refreshed because your refresh token was revoked. Please log out and sign in again.',
        'diagnostic-auth-embedded' => 'PRIVATE says: Your access token could not be refreshed. Please log out and sign in again.',
        'diagnostic-401', 'diagnostic-double' => 'unexpected status 401 Unauthorized: PRIVATE_BODY, url: https://private.invalid',
        'diagnostic-403' => 'unexpected status 403 Forbidden: PRIVATE_BODY',
        'diagnostic-429' => 'unexpected status 429 Too Many Requests: PRIVATE_BODY',
        'diagnostic-retry' => 'exceeded retry limit, last status: 429 Too Many Requests',
        'diagnostic-400' => 'unexpected status 400 Bad Request: PRIVATE_BODY',
        'diagnostic-503', 'diagnostic-final' => 'unexpected status 503 Service Unavailable: PRIVATE_BODY',
        'diagnostic-embedded' => 'PRIVATE_BODY mentions unexpected status 401 Unauthorized: not a reported status',
        'diagnostic-array' => ['PRIVATE_BODY'],
        default => 'PRIVATE_BODY',
    };
    $event = $mode === 'diagnostic-401'
        ? ['type' => 'error', 'message' => $message]
        : ['type' => 'turn.failed', 'error' => ['message' => $message]];
    if ($mode === 'diagnostic-fields') {
        $event['error']['status'] = 401;
        $event['error']['code'] = 'authentication';
    }
    $line = json_encode($event, JSON_THROW_ON_ERROR) . ($mode === 'diagnostic-final' ? '' : "\n");
    fwrite(STDOUT, $line . ($mode === 'diagnostic-double' ? $line : ''));
    exit(0);
}

$answer = match ($mode) {
    'invalid-json' => 'not an answer object',
    'non-object' => '[1,2,3]',
    default => json_encode(['answer' => 'Fixture response', 'received' => $decoded, 'argv' => $argv], JSON_THROW_ON_ERROR),
};
$events = json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => $answer]], JSON_THROW_ON_ERROR) . "\n";
if ($mode !== 'incomplete') {
    $events .= '{"type":"turn.completed"}' . ($mode === 'no-final-newline' ? '' : "\n");
}
if ($mode === 'stray-output') {
    $events .= "private accidental trailing output\n";
}
foreach (str_split($events, 47) as $chunk) {
    fwrite(STDOUT, $chunk);
}
exit($mode === 'exit-after-answer' ? 1 : 0);
