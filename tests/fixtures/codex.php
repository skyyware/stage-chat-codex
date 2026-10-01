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
