<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class JpegImage
{
    public function __construct(#[SensitiveParameter] private string $bytes)
    {
        if (strlen($bytes) > 4194304 || !str_starts_with($bytes, "\xff\xd8") || !str_ends_with($bytes, "\xff\xd9")) {
            throw new InvalidArgumentException('Image must be a JPEG of at most 4 MiB.');
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[2] !== IMAGETYPE_JPEG || $size[0] < 1 || $size[1] < 1 || $size[0] > 4096 || $size[1] > 4096 || $size[0] * $size[1] > 4194304) {
            throw new InvalidArgumentException('JPEG dimensions must fit 4096 per side and 4194304 pixels.');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new InvalidArgumentException('JPEG input requires the GD extension.');
        }
        $decoded = @imagecreatefromstring($bytes);
        if ($decoded === false) {
            throw new InvalidArgumentException('Image must contain a decodable JPEG.');
        }
    }

    /** @internal */
    public function bytes(): string
    {
        return $this->bytes;
    }

    /** @return array{bytes: int} */
    public function __debugInfo(): array
    {
        return ['bytes' => strlen($this->bytes)];
    }
}
