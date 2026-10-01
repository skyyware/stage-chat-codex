<?php

declare(strict_types=1);

namespace Stage\Chat\Codex;

use Closure;
use SensitiveParameter;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;

/** @internal */
final readonly class Process
{
    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     * @param Closure(string): void $receive
     * @param (Closure(): bool)|null $cancelled
     */
    public function run(
        #[SensitiveParameter] array $command,
        #[SensitiveParameter] string $input,
        array $environment,
        string $directory,
        float $deadline,
        int $maxOutputBytes,
        #[SensitiveParameter] Closure $receive,
        #[SensitiveParameter] ?Closure $cancelled,
    ): void {
        $this->check($deadline, $cancelled);
        $process = @proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $directory, $environment);
        if (!is_resource($process)) {
            throw new Failure(FailureReason::Unavailable);
        }

        try {
            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }

            $offset = 0;
            $bytes = 0;
            while (true) {
                $this->check($deadline, $cancelled);
                $status = proc_get_status($process);

                if (isset($pipes[0])) {
                    if ($offset < strlen($input) && $status['running']) {
                        $written = @fwrite($pipes[0], substr($input, $offset, 8192));
                        if ($written === false) {
                            throw new Failure(FailureReason::Unavailable);
                        }
                        $offset += $written;
                    }
                    if ($offset === strlen($input) || !$status['running']) {
                        fclose($pipes[0]);
                        unset($pipes[0]);
                    }
                }

                foreach ([1, 2] as $channel) {
                    $data = fread($pipes[$channel], 8192);
                    if ($data === false) {
                        throw new Failure(FailureReason::Unavailable);
                    }
                    $bytes += strlen($data);
                    if ($bytes > $maxOutputBytes) {
                        throw new Failure(FailureReason::InvalidResponse);
                    }
                    if ($channel === 1 && $data !== '') {
                        $receive($data);
                    }
                }

                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    if ($status['exitcode'] !== 0 || $offset !== strlen($input)) {
                        throw new Failure(FailureReason::Unavailable);
                    }
                    return;
                }

                usleep(10000);
            }
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            if ($this->running($process)) {
                proc_terminate($process);
                $stopAt = hrtime(true) / 1e9 + 0.2;
                while ($this->running($process) && hrtime(true) / 1e9 < $stopAt) {
                    usleep(5000);
                }
                if ($this->running($process)) {
                    proc_terminate($process, 9);
                }
            }
            proc_close($process);
        }
    }

    /**
     * @param resource $process
     * @phpstan-impure
     */
    private function running($process): bool
    {
        return proc_get_status($process)['running'];
    }

    /** @param (Closure(): bool)|null $cancelled */
    private function check(float $deadline, #[SensitiveParameter] ?Closure $cancelled): void
    {
        if ($cancelled !== null && $cancelled()) {
            throw new Failure(FailureReason::Cancelled);
        }
        if (hrtime(true) / 1e9 >= $deadline) {
            throw new Failure(FailureReason::Timeout);
        }
    }
}
