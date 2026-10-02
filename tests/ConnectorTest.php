<?php

declare(strict_types=1);

namespace Stage\Chat\Codex\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Stage\Chat\Codex\Codex;
use Stage\Chat\Codex\Options;
use Stage\Chat\Codex\Profile;
use Stage\Chat\Failure;
use Stage\Chat\FailureReason;
use Stage\Chat\Message;
use Stage\Chat\Request;
use Stage\Chat\Role;

final class ConnectorTest extends TestCase
{
    private string $root;
    private string $work;
    private string $home;

    protected function setUp(): void
    {
        $runtime = dirname(__DIR__) . '/.runtime';
        if (!is_dir($runtime)) {
            mkdir($runtime, 0700, true);
        }
        $this->root = $runtime . '/test-' . bin2hex(random_bytes(8));
        $this->work = $this->root . '/work';
        $this->home = $this->root . '/codex';
        mkdir($this->work, 0700, true);
        mkdir($this->home, 0700);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo) {
                throw new LogicException('Expected filesystem entries.');
            }
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function testFinalAnswerCrossesRealProcessPipesWithoutPuttingConversationInArguments(): void
    {
        $request = new Request('Trusted policy', 'SOURCE_SENTINEL', [
            new Message(Role::User, 'Earlier question'),
            new Message(Role::Assistant, 'Earlier answer'),
            new Message(Role::User, 'QUESTION_SENTINEL $(touch /invalid/path); Unicode: Grüße'),
        ]);
        $response = new Codex($this->options())->complete($request);
        $result = json_decode($response->text, true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertSame('Fixture response', $result['answer']);
        self::assertIsArray($result['received']);
        self::assertSame('SOURCE_SENTINEL', $result['received']['context']);
        self::assertIsArray($result['received']['messages']);
        self::assertCount(3, $result['received']['messages']);
        $arguments = json_encode($result['argv'], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('QUESTION_SENTINEL', $arguments);
        self::assertStringNotContainsString('SOURCE_SENTINEL', $arguments);
        self::assertStringContainsString('Trusted policy', $arguments);
        self::assertSame(['.', '..', 'fixture.pid'], scandir($this->home));
        self::assertSame(['.', '..'], scandir($this->work));
        $this->assertChildStopped();
    }

    #[DataProvider('failures')]
    public function testProviderFailuresAreBoundedSanitizedAndReaped(string $mode, FailureReason $reason): void
    {
        try {
            new Codex($this->options($mode))->complete($this->request());
            self::fail('Expected a content-free provider failure.');
        } catch (Failure $failure) {
            self::assertSame($reason, $failure->reason);
            self::assertStringNotContainsString('PRIVATE', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
        $this->assertChildStopped();
    }

    /** @return iterable<string, array{string, FailureReason}> */
    public static function failures(): iterable
    {
        foreach (['error', 'turn-failed', 'exit-after-answer'] as $mode) {
            yield $mode => [$mode, FailureReason::Unavailable];
        }
        foreach (['overflow', 'stderr-overflow', 'malformed', 'tool', 'invalid-json', 'non-object', 'incomplete', 'stray-output'] as $mode) {
            yield $mode => [$mode, FailureReason::InvalidResponse];
        }
    }

    public function testTimeoutIncludesBlockedStdinAndStopsTheProcess(): void
    {
        $request = new Request('Policy', str_repeat('x', 120000), [new Message(Role::User, 'Question')]);
        $started = hrtime(true);
        try {
            new Codex($this->options('no-read', 0.25))->complete($request);
            self::fail('Expected timeout.');
        } catch (Failure $failure) {
            self::assertSame(FailureReason::Timeout, $failure->reason);
        }
        self::assertLessThan(1.0, (hrtime(true) - $started) / 1e9);
        $this->assertChildStopped();
    }

    public function testCancellationStopsAnActiveRequest(): void
    {
        $pidFile = $this->home . '/fixture.pid';
        try {
            new Codex($this->options('hang'))->complete($this->request(), static fn (): bool => is_file($pidFile));
            self::fail('Expected cancellation.');
        } catch (Failure $failure) {
            self::assertSame(FailureReason::Cancelled, $failure->reason);
        }
        $this->assertChildStopped();
    }

    public function testCancellationBeforeStartCreatesNoProcess(): void
    {
        try {
            new Codex($this->options())->complete($this->request(), static fn (): bool => true);
            self::fail('Expected cancellation.');
        } catch (Failure $failure) {
            self::assertSame(FailureReason::Cancelled, $failure->reason);
        }
        self::assertFileDoesNotExist($this->home . '/fixture.pid');
    }

    public function testFinalLineDoesNotNeedANewline(): void
    {
        self::assertStringContainsString('Fixture response', new Codex($this->options('no-final-newline'))->complete($this->request())->text);
    }

    public function testUnverifiedCliVersionIsRejectedBeforeAnyChat(): void
    {
        $options = new Options(__DIR__ . '/fixtures/unsupported.php', $this->work, $this->home, 'success', __DIR__ . '/fixtures/answer.json');
        try {
            new Codex($options)->complete($this->request());
            self::fail('Expected unavailable for unverified CLI version.');
        } catch (Failure $failure) {
            self::assertSame(FailureReason::Unavailable, $failure->reason);
        }
        self::assertFileDoesNotExist($this->home . '/fixture.pid');
    }

    public function testVerifiedCli160CanCompleteThroughTheConnector(): void
    {
        $options = new Options(__DIR__ . '/fixtures/codex-0.160.0.php', $this->work, $this->home, 'success', __DIR__ . '/fixtures/answer.json');
        self::assertStringContainsString('Fixture response', new Codex($options)->complete($this->request())->text);
        $this->assertChildStopped();
    }

    public function testProfileDoesNotInheritApiCredentialsOrLoggingConfiguration(): void
    {
        $names = ['CODEX_API_KEY', 'OPENAI_API_KEY', 'OPENAI_BASE_URL', 'RUST_LOG', 'OTEL_EXPORTER_OTLP_ENDPOINT'];
        $saved = [];
        try {
            foreach ($names as $name) {
                $saved[$name] = getenv($name);
                putenv($name . '=PRIVATE_ENV_SENTINEL');
            }
            $environment = new Profile($this->options())->environment();
            self::assertSame('off', $environment['RUST_LOG']);
            self::assertSame($this->home, $environment['CODEX_HOME']);
            self::assertNotContains('PRIVATE_ENV_SENTINEL', $environment);
        } finally {
            foreach ($saved as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
        }
    }

    public function testFastTierReachesTheProcessWithoutChangingModelOrReasoning(): void
    {
        $options = new Options(
            __DIR__ . '/fixtures/codex.php',
            $this->work,
            $this->home,
            'gpt-6.1-sol',
            __DIR__ . '/fixtures/answer.json',
            serviceTier: 'fast',
        );
        $response = new Codex($options)->complete($this->request());
        $answer = json_decode($response->text, true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);
        self::assertIsArray($answer['argv']);
        self::assertContains('gpt-6.1-sol', $answer['argv']);
        self::assertContains('service_tier="fast"', $answer['argv']);
        self::assertContains('features.fast_mode=true', $answer['argv']);
        self::assertContains('model_reasoning_effort="low"', $answer['argv']);
        $this->assertChildStopped();
    }

    public function testExistingCallersDoNotRequestAServiceTier(): void
    {
        $command = new Profile($this->options())->command($this->request());
        self::assertSame([], array_filter($command, static fn (string $argument): bool => str_starts_with($argument, 'service_tier=')));
    }

    #[DataProvider('maxServiceTiers')]
    public function testMaxReasoningIsIndependentOfTheRequestedServiceTier(?string $tier): void
    {
        $options = new Options(
            __DIR__ . '/fixtures/codex.php',
            $this->work,
            $this->home,
            'gpt-6-astra',
            __DIR__ . '/fixtures/answer.json',
            reasoningEffort: 'max',
            serviceTier: $tier,
        );
        $answer = json_decode(new Codex($options)->complete($this->request())->text, true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);
        self::assertIsArray($answer['argv']);
        self::assertContains('gpt-6-astra', $answer['argv']);
        self::assertContains('model_reasoning_effort="max"', $answer['argv']);
        $settings = array_values(array_filter($answer['argv'], static fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'service_tier=')));
        self::assertSame($tier === null ? [] : ['service_tier="' . $tier . '"'], $settings);
        if ($tier !== null) {
            self::assertContains('features.fast_mode=true', $answer['argv']);
        }
        $this->assertChildStopped();
    }

    /** @return iterable<string, array{?string}> */
    public static function maxServiceTiers(): iterable
    {
        yield 'default' => [null];
        yield 'fast' => ['fast'];
    }

    #[DataProvider('unsupportedReasoningEfforts')]
    public function testUnverifiedReasoningEffortsAreRejected(string $effort): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Options(__DIR__ . '/fixtures/codex.php', $this->work, $this->home, 'gpt-6-astra', __DIR__ . '/fixtures/answer.json', reasoningEffort: $effort);
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedReasoningEfforts(): iterable
    {
        foreach (['', 'MAX', 'ultrafast', 'ultra'] as $effort) {
            yield $effort => [$effort];
        }
    }

    #[DataProvider('unsupportedServiceTiers')]
    public function testUnverifiedServiceTiersAreRejected(string $tier): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Options(
            __DIR__ . '/fixtures/codex.php',
            $this->work,
            $this->home,
            'gpt-6.1-sol',
            __DIR__ . '/fixtures/answer.json',
            serviceTier: $tier,
        );
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedServiceTiers(): iterable
    {
        foreach (['', 'priority', 'ultrafast', 'standard', 'turbo'] as $tier) {
            yield $tier => [$tier];
        }
    }

    public function testWorkingDirectoryCannotContainProjectInstructions(): void
    {
        file_put_contents($this->work . '/AGENTS.md', 'Unexpected instructions');
        $this->expectException(InvalidArgumentException::class);
        $this->options();
    }

    public function testSharedReadableCredentialDirectoryIsRejected(): void
    {
        chmod($this->home, 0755);
        clearstatcache();
        $this->expectException(InvalidArgumentException::class);
        $this->options();
    }

    public function testDifferentPathsCannotAliasTheSameHomeAndWorkingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Options(__DIR__ . '/fixtures/codex.php', $this->work, $this->work . '/.', 'success', __DIR__ . '/fixtures/answer.json');
    }

    private function options(string $model = 'success', float $timeout = 2): Options
    {
        return new Options(__DIR__ . '/fixtures/codex.php', $this->work, $this->home, $model, __DIR__ . '/fixtures/answer.json', $timeout);
    }

    private function request(): Request
    {
        return new Request('Trusted policy', 'Source data', [new Message(Role::User, 'Question')]);
    }

    private function assertChildStopped(): void
    {
        $pid = file_get_contents($this->home . '/fixture.pid');
        self::assertNotFalse($pid);
        self::assertFalse(posix_kill((int) $pid, 0), 'The connector must reap its child process.');
    }
}
