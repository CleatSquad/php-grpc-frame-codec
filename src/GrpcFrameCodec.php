<?php

declare(strict_types=1);

namespace CleatSquad\GrpcFrameCodec;

/**
 * The 5-byte gRPC message frame the franken-grpc relay
 * (https://github.com/CleatSquad/franken-grpc) expects on the PHP backend's
 * HTTP response body:
 * 1 byte compression flag (must be 0) | 4 bytes big-endian payload length | N bytes payload.
 *
 * Pure byte manipulation — no dependency on any particular PHP runtime.
 * Works under nginx+PHP-FPM, Apache, RoadRunner, Swoole, FrankenPHP, or
 * plain php-fpm, as long as franken-grpc (or anything using the same
 * wire contract) is the relay in front of it.
 *
 * Only the response direction needs this: franken-grpc strips the frame
 * before forwarding an incoming gRPC call to your backend as a plain HTTP
 * POST, so the request body your backend receives is the raw protobuf
 * message, unframed. decode() exists for symmetry and for testing — most
 * backends only ever call encode().
 */
final class GrpcFrameCodec
{
    /** Content-Type header franken-grpc expects on the HTTP response body. */
    public const CONTENT_TYPE = 'application/grpc+proto';

    private const FLAG_UNCOMPRESSED = "\x00";
    private const HEADER_LENGTH = 5;

    public function encode(string $payload): string
    {
        return self::FLAG_UNCOMPRESSED . pack('N', strlen($payload)) . $payload;
    }

    /**
     * @throws \InvalidArgumentException if the frame is malformed, compressed, or truncated.
     */
    public function decode(string $frame): string
    {
        if (strlen($frame) < self::HEADER_LENGTH) {
            throw new \InvalidArgumentException('Frame shorter than the 5-byte header.');
        }

        $length = $this->readHeader($frame);

        $payload = substr($frame, self::HEADER_LENGTH);
        if (strlen($payload) !== $length) {
            throw new \InvalidArgumentException(
                sprintf('Declared payload length %d does not match actual length %d.', $length, strlen($payload))
            );
        }

        return $payload;
    }

    /**
     * Consumes one frame from the front of $buffer — for a server-streaming
     * response, where several frames arrive back-to-back and a given read
     * may land mid-frame. Advances $buffer past the consumed frame.
     *
     * Returns null when $buffer does not yet hold a complete frame (read
     * more and call again with the same $buffer), never when it is simply
     * empty — an empty buffer also returns null, meaning "nothing to read
     * yet", not "malformed".
     *
     * @throws \InvalidArgumentException if a complete frame is present but malformed or compressed.
     */
    public function readNextFrame(string &$buffer): ?string
    {
        if (strlen($buffer) < self::HEADER_LENGTH) {
            return null;
        }

        $length = $this->readHeader($buffer);

        if (strlen($buffer) < self::HEADER_LENGTH + $length) {
            return null;
        }

        $payload = substr($buffer, self::HEADER_LENGTH, $length);
        $buffer = substr($buffer, self::HEADER_LENGTH + $length);

        return $payload;
    }

    /** @throws \InvalidArgumentException if the 5-byte header itself is malformed or compressed. */
    private function readHeader(string $frame): int
    {
        $flag = $frame[0];
        if ($flag !== self::FLAG_UNCOMPRESSED) {
            throw new \InvalidArgumentException(sprintf('Unsupported frame flag: 0x%02x', ord($flag)));
        }

        $unpacked = unpack('Nlength', substr($frame, 1, 4));
        if ($unpacked === false) {
            throw new \InvalidArgumentException('Could not read the 4-byte length field.');
        }
        $length = $unpacked['length'];
        if (!is_int($length)) {
            throw new \InvalidArgumentException('Could not read the 4-byte length field.');
        }

        return $length;
    }
}
