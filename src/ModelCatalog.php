<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use InvalidArgumentException;
use JsonException;

/** @internal */
final class ModelCatalog
{
    private const int MAX_BYTES = 4194304;

    public static function verify(Options $options): void
    {
        $path = $options->modelCatalogPath;
        if ($path === null) {
            if ($options->serviceTier === 'ultrafast') {
                throw new InvalidArgumentException('Ultrafast requires a trusted model catalog path.');
            }
            return;
        }

        clearstatcache(true, $path);
        $size = @filesize($path);
        $mode = @fileperms($path);
        if (!str_starts_with($path, '/') || !is_file($path) || is_link($path) || !is_readable($path)
            || $size === false || $size > self::MAX_BYTES || $mode === false || ($mode & 0022) !== 0) {
            throw new InvalidArgumentException('Model catalog must be a protected readable absolute JSON file of at most 4 MiB.');
        }

        $content = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        try {
            $catalog = $content === false || strlen($content) > self::MAX_BYTES
                ? null : json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $catalog = null;
        }
        $models = is_array($catalog) ? ($catalog['models'] ?? null) : null;
        if (!is_array($models) || !array_is_list($models)) {
            throw new InvalidArgumentException('Model catalog must contain a models list.');
        }

        $selected = null;
        foreach ($models as $model) {
            if (!is_array($model) || !isset($model['slug']) || !is_string($model['slug'])) {
                throw new InvalidArgumentException('Model catalog contains an invalid model entry.');
            }
            if ($model['slug'] === $options->model) {
                if ($selected !== null) {
                    throw new InvalidArgumentException('Model catalog contains duplicate selected models.');
                }
                $selected = $model;
            }
        }
        if ($selected === null) {
            throw new InvalidArgumentException('Model catalog does not advertise the selected model.');
        }
        if (!in_array($options->reasoningEffort, self::identifiers($selected, 'supported_reasoning_levels', 'effort'), true)) {
            throw new InvalidArgumentException('Model catalog does not advertise the selected reasoning effort.');
        }
        $tier = $options->serviceTier === 'fast' ? 'priority' : $options->serviceTier;
        if ($tier !== null && !in_array($tier, self::identifiers($selected, 'service_tiers', 'id'), true)) {
            throw new InvalidArgumentException('Model catalog does not advertise the selected service tier.');
        }
    }

    /**
     * @param array<array-key, mixed> $model
     * @return list<string>
     */
    private static function identifiers(array $model, string $field, string $key): array
    {
        $entries = $model[$field] ?? null;
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new InvalidArgumentException('Model catalog capability list is invalid.');
        }
        $values = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry[$key]) || !is_string($entry[$key]) || $entry[$key] === '') {
                throw new InvalidArgumentException('Model catalog capability entry is invalid.');
            }
            $values[] = $entry[$key];
        }
        return $values;
    }
}
