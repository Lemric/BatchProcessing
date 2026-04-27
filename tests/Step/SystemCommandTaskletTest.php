<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

namespace Lemric\BatchProcessing\Tests\Step;

use InvalidArgumentException;
use Lemric\BatchProcessing\Chunk\ChunkContext;
use Lemric\BatchProcessing\Domain\{JobExecution, JobInstance, JobParameters, StepExecution};
use Lemric\BatchProcessing\Domain\StepContribution;
use Lemric\BatchProcessing\Exception\StepExecutionException;
use Lemric\BatchProcessing\Step\{RepeatStatus, SystemCommandTasklet};
use PHPUnit\Framework\TestCase;

final class SystemCommandTaskletTest extends TestCase
{
    public function testControlCharsInProgramRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(["/bin/echo\n"]);
    }

    public function testEmptyCommandRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet([]);
    }

    public function testEnvironmentParams(): void
    {
        [$contribution, $context] = $this->createContext();
        $tasklet = new SystemCommandTasklet(
            ['/bin/sh', '-c', 'test "$MY_VAR" = "test_value"'],
            environmentParams: ['MY_VAR' => 'test_value', 'PATH' => '/usr/bin:/bin'],
        );
        self::assertSame(RepeatStatus::FINISHED, $tasklet->execute($contribution, $context));
    }

    public function testEnvVarWithNullByteRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], environmentParams: ['OK' => "val\0nul"]);
    }

    public function testFailedCommand(): void
    {
        [$contribution, $context] = $this->createContext();
        // Use 'false' which is a POSIX command that always exits 1.
        $tasklet = new SystemCommandTasklet(['/usr/bin/false']);
        $this->expectException(StepExecutionException::class);
        $tasklet->execute($contribution, $context);
    }

    public function testInvalidEnvVarNameRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], environmentParams: ['1BAD' => 'value']);
    }

    public function testNegativeTimeoutRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], timeoutSeconds: -1.0);
    }

    public function testNonExistentWorkingDirectoryRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], workingDirectory: '/this/does/not/exist/anywhere');
    }

    public function testNonListArgumentRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /* @phpstan-ignore-next-line intentionally passing invalid type for the test */
        new SystemCommandTasklet(['cmd' => '/bin/echo']);
    }

    public function testNoShellInjection(): void
    {
        // The argument contains shell metacharacters; with a real shell this would create
        // /tmp/INJECTED. We assert that no shell is invoked, so the file is not created and
        // /bin/echo simply prints the literal string.
        $marker = '/tmp/lemric_injection_'.uniqid('', true);
        @unlink($marker);

        [$contribution, $context] = $this->createContext();
        $tasklet = new SystemCommandTasklet(['/bin/echo', "; touch {$marker}"]);
        $tasklet->execute($contribution, $context);

        self::assertFileDoesNotExist($marker, 'Shell injection MUST NOT execute touch via shell.');
    }

    public function testNullByteInArgumentRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo', "evil\0arg"]);
    }

    public function testNullByteInProgramRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(["/bin/ev\0il"]);
    }

    public function testRequireExecutableAllowListWithAllowListSucceeds(): void
    {
        [$contribution, $context] = $this->createContext();
        $tasklet = new SystemCommandTasklet(
            ['/bin/echo', 'ok'],
            allowedExecutableBasenames: ['echo'],
            requireExecutableAllowList: true,
        );
        self::assertSame(RepeatStatus::FINISHED, $tasklet->execute($contribution, $context));
    }

    public function testRequireExecutableAllowListWithEmptyAllowListThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], requireExecutableAllowList: true);
    }

    public function testSuccessfulCommand(): void
    {
        [$contribution, $context] = $this->createContext();
        $tasklet = new SystemCommandTasklet(['/bin/echo', 'hello']);
        self::assertSame(RepeatStatus::FINISHED, $tasklet->execute($contribution, $context));
    }

    public function testTimeoutKillsLongRunningProcess(): void
    {
        [$contribution, $context] = $this->createContext();
        $tasklet = new SystemCommandTasklet(['/bin/sleep', '10'], timeoutSeconds: 0.2);
        $this->expectException(StepExecutionException::class);
        $this->expectExceptionMessage('exceeded timeout');
        $tasklet->execute($contribution, $context);
    }

    public function testTinyOutputLimitRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], outputLimitBytes: 100);
    }

    public function testWorkingDirectory(): void
    {
        [$contribution, $context] = $this->createContext();
        $tasklet = new SystemCommandTasklet(['/bin/pwd'], workingDirectory: '/tmp');
        self::assertSame(RepeatStatus::FINISHED, $tasklet->execute($contribution, $context));
    }

    public function testZeroTimeoutRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemCommandTasklet(['/bin/echo'], timeoutSeconds: 0.0);
    }

    /**
     * @return array{StepContribution, ChunkContext}
     */
    private function createContext(): array
    {
        $jobExecution = new JobExecution(null, new JobInstance(null, 'test', 'test'), new JobParameters());
        $stepExecution = new StepExecution('step', $jobExecution);
        $contribution = new StepContribution($stepExecution);
        $chunkContext = new ChunkContext($contribution);

        return [$contribution, $chunkContext];
    }
}
