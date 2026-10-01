<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use JsonException;
use SensitiveParameter;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;
use Stage\Chat\Response;
use stdClass;

/** @internal */
final class Events
{
    private string $pending = '';
    private ?string $answer = null;
    private bool $completed = false;

    public function receive(#[SensitiveParameter] string $chunk): void
    {
        $this->pending .= $chunk;
        while (($end = strpos($this->pending, "\n")) !== false) {
            $line = substr($this->pending, 0, $end);
            $this->pending = substr($this->pending, $end + 1);
            $this->event($line);
        }
    }

    public function response(): Response
    {
        if ($this->pending !== '') {
            $this->event($this->pending);
            $this->pending = '';
        }

        if (!$this->completed || $this->answer === null) {
            throw new Failure(FailureReason::InvalidResponse);
        }

        try {
            $value = json_decode($this->answer, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Failure(FailureReason::InvalidResponse);
        }
        if (!$value instanceof stdClass) {
            throw new Failure(FailureReason::InvalidResponse);
        }

        return new Response($this->answer);
    }

    private function event(#[SensitiveParameter] string $line): void
    {
        if (trim($line) === '') {
            return;
        }
        try {
            $event = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Failure(FailureReason::InvalidResponse);
        }
        if (!is_array($event) || !isset($event['type']) || !is_string($event['type']) || $this->completed) {
            throw new Failure(FailureReason::InvalidResponse);
        }

        switch ($event['type']) {
            case 'thread.started':
            case 'turn.started':
                return;
            case 'error':
            case 'turn.failed':
                throw new Failure(FailureReason::Unavailable);
            case 'turn.completed':
                $this->completed = true;
                return;
            case 'item.started':
            case 'item.updated':
            case 'item.completed':
                $item = $event['item'] ?? null;
                if (!is_array($item) || !in_array($item['type'] ?? null, ['agent_message', 'reasoning', 'error'], true)) {
                    throw new Failure(FailureReason::InvalidResponse);
                }
                if ($event['type'] === 'item.completed' && $item['type'] === 'agent_message') {
                    if (!isset($item['text']) || !is_string($item['text'])) {
                        throw new Failure(FailureReason::InvalidResponse);
                    }
                    $this->answer = $item['text'];
                }
                return;
            default:
                throw new Failure(FailureReason::InvalidResponse);
        }
    }
}
