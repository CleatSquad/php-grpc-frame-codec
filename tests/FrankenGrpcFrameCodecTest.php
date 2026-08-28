<?php

declare(strict_types=1);

namespace CleatSquad\FrankenGrpcCodec\Tests;

use CleatSquad\FrankenGrpcCodec\FrankenGrpcFrameCodec;
use PHPUnit\Framework\TestCase;

final class FrankenGrpcFrameCodecTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $codec = new FrankenGrpcFrameCodec();
        $payload = 'hello world';

        self::assertSame($payload, $codec->decode($codec->encode($payload)));
    }

    public function testEncodeProducesTheFiveByteHeader(): void
    {
        $codec = new FrankenGrpcFrameCodec();
        $frame = $codec->encode('ab');

        self::assertSame("\x00\x00\x00\x00\x02ab", $frame);
    }

    public function testDecodeEmptyPayload(): void
    {
        $codec = new FrankenGrpcFrameCodec();

        self::assertSame('', $codec->decode($codec->encode('')));
    }

    public function testDecodeRejectsFrameShorterThanHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('shorter than the 5-byte header');

        (new FrankenGrpcFrameCodec())->decode("\x00\x00\x00");
    }

    public function testDecodeRejectsNonZeroCompressionFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported frame flag: 0x01');

        (new FrankenGrpcFrameCodec())->decode("\x01\x00\x00\x00\x00");
    }

    public function testDecodeRejectsLengthMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Declared payload length 5 does not match actual length 2');

        (new FrankenGrpcFrameCodec())->decode("\x00\x00\x00\x00\x05ab");
    }
}
