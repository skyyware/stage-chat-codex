<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use InvalidArgumentException;
use JsonException;

final readonly class Options
{
    public function __construct(
        public string $binary,
        public string $workingDirectory,
        public string $codexHome,
        public string $model,
        public string $outputSchema,
        public float $timeoutSeconds = 25,
        public string $reasoningEffort = 'low',
        public int $maxOutputBytes = 262144,
        public ?string $serviceTier = null,
    ) {
        if (!str_starts_with($binary, '/') || !is_file($binary) || !is_executable($binary)) {
            throw new InvalidArgumentException('Codex binary must be an absolute executable file path.');
        }

        foreach ([$workingDirectory, $codexHome] as $directory) {
            $mode = @fileperms($directory);
            if (!str_starts_with($directory, '/') || !is_dir($directory) || $mode === false || ($mode & 0077) !== 0) {
                throw new InvalidArgumentException('Codex directories must be private absolute directories with mode 0700.');
            }
        }

        if (realpath($workingDirectory) === realpath($codexHome) || @scandir($workingDirectory) !== ['.', '..'] || !is_writable($codexHome)) {
            throw new InvalidArgumentException('Codex needs a separate empty working directory and a writable private home.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,127}$/D', $model) !== 1) {
            throw new InvalidArgumentException('Codex model identifier is invalid.');
        }

        if (!in_array($reasoningEffort, ['none', 'minimal', 'low', 'medium', 'high', 'max'], true)) {
            throw new InvalidArgumentException('Codex reasoning effort is invalid.');
        }

        if ($serviceTier !== null && $serviceTier !== 'fast') {
            throw new InvalidArgumentException('Codex service tier must be fast or null.');
        }

        if (!is_finite($timeoutSeconds) || $timeoutSeconds <= 0 || $timeoutSeconds > 120 || $maxOutputBytes < 1024 || $maxOutputBytes > 1048576) {
            throw new InvalidArgumentException('Codex time or output limit is invalid.');
        }

        $size = @filesize($outputSchema);
        if (!str_starts_with($outputSchema, '/') || !is_file($outputSchema) || $size === false || $size > 65536) {
            throw new InvalidArgumentException('Output schema must be a readable absolute JSON file of at most 64 KiB.');
        }

        $schema = @file_get_contents($outputSchema);
        try {
            $decoded = $schema === false ? null : json_decode($schema, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'object') {
            throw new InvalidArgumentException('Output schema must describe a JSON object.');
        }
    }
}
