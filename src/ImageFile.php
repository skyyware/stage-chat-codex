<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use SensitiveParameter;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;

/** @internal */
final readonly class ImageFile
{
    private function __construct(public string $path)
    {
    }

    public static function create(#[SensitiveParameter] JpegImage $image): self
    {
        $directory = sys_get_temp_dir() . '/stage-codex-image-' . bin2hex(random_bytes(16));
        if (!@mkdir($directory, 0700)) {
            throw new Failure(FailureReason::Unavailable);
        }
        $file = new self($directory . '/input.jpg');
        $stream = @fopen($file->path, 'xb');
        if ($stream === false) {
            $file->close();
            throw new Failure(FailureReason::Unavailable);
        }
        try {
            if (!@chmod($file->path, 0600)) {
                throw new Failure(FailureReason::Unavailable);
            }
            $bytes = $image->bytes();
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = @fwrite($stream, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new Failure(FailureReason::Unavailable);
                }
                $offset += $written;
            }
        } catch (Failure $failure) {
            fclose($stream);
            $file->close();
            throw $failure;
        }
        fclose($stream);

        return $file;
    }

    public function close(): void
    {
        if ((file_exists($this->path) && !@unlink($this->path)) || !@rmdir(dirname($this->path))) {
            throw new Failure(FailureReason::Unavailable);
        }
    }
}
