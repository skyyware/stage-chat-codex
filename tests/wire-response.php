<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Stage\Chat\Codex\Events;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;

$events = new Events();
try {
    while (!feof(STDIN)) {
        $chunk = fread(STDIN, 8192);
        if ($chunk === false) {
            exit(2);
        }
        $events->receive($chunk);
    }
    $answer = json_decode($events->response()->text, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($answer) || array_keys($answer) !== ['answer'] || !is_string($answer['answer'])) {
        throw new Failure(FailureReason::InvalidResponse);
    }
    $result = ['status' => 'complete', 'answer_sha256' => hash('sha256', $answer['answer'])];
} catch (Failure $failure) {
    $result = ['status' => 'failure', 'reason' => $failure->reason->value];
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
