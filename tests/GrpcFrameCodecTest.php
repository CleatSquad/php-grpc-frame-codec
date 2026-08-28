<?php

declare(strict_types=1);

namespace CleatSquad\GrpcFrameCodec\Tests;

use CleatSquad\GrpcFrameCodec\GrpcFrameCodec;
use PHPUnit\Framework\TestCase;

final class GrpcFrameCodecTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $codec = new GrpcFrameCodec();
        $payload = 'hello world';

        self::assertSame($payload, $codec->decode($codec->encode($payload)));
    }

    public function testEncodeProducesTheFiveByteHeader(): void
    {
        $codec = new GrpcFrameCodec();
        $frame = $codec->encode('ab');

        self::assertSame("\x00\x00\x00\x00\x02ab", $frame);
    }

    public function testDecodeEmptyPayload(): void
    {
        $codec = new GrpcFrameCodec();

        self::assertSame('', $codec->decode($codec->encode('')));
    }

    public function testDecodeRejectsFrameShorterThanHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('shorter than the 5-byte header');

        (new GrpcFrameCodec())->decode("\x00\x00\x00");
    }

    public function testDecodeRejectsNonZeroCompressionFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported frame flag: 0x01');

        (new GrpcFrameCodec())->decode("\x01\x00\x00\x00\x00");
    }

    public function testDecodeRejectsLengthMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Declared payload length 5 does not match actual length 2');

        (new GrpcFrameCodec())->decode("\x00\x00\x00\x00\x05ab");
    }

    public function testContentTypeConstant(): void
    {
        self::assertSame('application/grpc+proto', GrpcFrameCodec::CONTENT_TYPE);
    }

    public function testReadNextFrameReturnsNullOnEmptyBuffer(): void
    {
        $buffer = '';

        self::assertNull((new GrpcFrameCodec())->readNextFrame($buffer));
        self::assertSame('', $buffer);
    }

    public function testReadNextFrameReturnsNullWhenHeaderIsIncomplete(): void
    {
        $buffer = "\x00\x00\x00";

        self::assertNull((new GrpcFrameCodec())->readNextFrame($buffer));
        self::assertSame("\x00\x00\x00", $buffer, 'incomplete input must be left untouched for the next read');
    }

    public function testReadNextFrameReturnsNullWhenPayloadIsIncomplete(): void
    {
        $codec = new GrpcFrameCodec();
        $buffer = substr($codec->encode('hello world'), 0, 7); // header + 2 of 11 payload bytes

        self::assertNull($codec->readNextFrame($buffer));
    }

    public function testReadNextFrameConsumesOneFrameAndAdvancesTheBuffer(): void
    {
        $codec = new GrpcFrameCodec();
        $buffer = $codec->encode('first');

        $frame = $codec->readNextFrame($buffer);

        self::assertSame('first', $frame);
        self::assertSame('', $buffer);
    }

    public function testReadNextFrameConsumesFramesOneAtATimeFromAConcatenatedBuffer(): void
    {
        $codec = new GrpcFrameCodec();
        $incompleteThirdFrame = substr($codec->encode('third-frame-longer-than-what-arrived'), 0, 8);
        $buffer = $codec->encode('first') . $codec->encode('second') . $incompleteThirdFrame;

        self::assertSame('first', $codec->readNextFrame($buffer));
        self::assertSame('second', $codec->readNextFrame($buffer));
        self::assertNull($codec->readNextFrame($buffer));
        self::assertSame($incompleteThirdFrame, $buffer, 'the incomplete trailing frame must be left for the next read');
    }

    public function testReadNextFrameRejectsANonZeroCompressionFlagOnceTheHeaderIsComplete(): void
    {
        $codec = new GrpcFrameCodec();
        $buffer = "\x01\x00\x00\x00\x00";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported frame flag: 0x01');

        $codec->readNextFrame($buffer);
    }
}
