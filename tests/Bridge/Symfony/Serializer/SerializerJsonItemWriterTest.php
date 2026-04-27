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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony\Serializer;

use Lemric\BatchProcessing\Bridge\Symfony\Serializer\SerializerJsonItemWriter;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;
use const JSON_THROW_ON_ERROR;

final class SerializerJsonItemWriterTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir().'/bp_serializer_writer_'.bin2hex(random_bytes(8));
        mkdir($dir, 0o700, true);
        $this->baseDir = $dir;
    }

    protected function tearDown(): void
    {
        $files = glob($this->baseDir.'/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->baseDir)) {
            rmdir($this->baseDir);
        }
    }

    public function testRejectsFilesystemDestinationWithoutAllowedBaseDirectory(): void
    {
        $writer = new SerializerJsonItemWriter(
            serializer: $this->serializer(),
            destination: $this->baseDir.'/items.json',
            allowedBaseDirectory: null,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('allowedBaseDirectory must be configured');
        $writer->open(new ExecutionContext());
    }

    public function testRejectsPathTraversalOutsideAllowedBaseDirectory(): void
    {
        $outside = dirname($this->baseDir).'/escaped.json';
        $traversalPath = $this->baseDir.'/../'.basename($outside);

        $writer = new SerializerJsonItemWriter(
            serializer: $this->serializer(),
            destination: $traversalPath,
            allowedBaseDirectory: $this->baseDir,
        );

        $this->expectException(RuntimeException::class);
        $writer->open(new ExecutionContext());
    }

    public function testWritesInsideAllowedBaseDirectory(): void
    {
        $target = $this->baseDir.'/items.json';
        $writer = new SerializerJsonItemWriter(
            serializer: $this->serializer(),
            destination: $target,
            allowedBaseDirectory: $this->baseDir,
        );

        $writer->open(new ExecutionContext());
        $writer->write(new Chunk([], [['id' => 1], ['id' => 2]]));
        $writer->close();

        $content = file_get_contents($target);
        self::assertNotFalse($content);
        self::assertSame('[{"id":1},{"id":2}]', $content);
    }

    private function serializer(): SerializerInterface
    {
        return new class implements SerializerInterface {
            public function serialize(mixed $data, string $format, array $context = []): string
            {
                $encoded = json_encode($data, JSON_THROW_ON_ERROR);

                return $encoded;
            }

            public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
            {
                throw new RuntimeException('Not used in writer tests.');
            }
        };
    }
}
