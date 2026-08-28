<?php

declare(strict_types=1);

namespace CleatSquad\FrankenGrpcCodec;

/**
 * The 5-byte gRPC message frame franken-grpc (https://github.com/CleatSquad/franken-grpc)
 * expects on the PHP backend's HTTP response body:
 * 1 byte compression flag (must be 0) | 4 bytes big-endian payload length | N bytes payload.
 *
 * Only the response direction needs this: franken-grpc strips the frame
 * before forwarding an incoming gRPC call to your backend as a plain HTTP
 * POST, so the request body your backend receives is the raw protobuf
 * message, unframed. decode() exists for symmetry and for testing — most
 * backends only ever call encode().
 */
class FrankenGrpcFrameCodec
{
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

        $flag = $frame[0];
        if ($flag !== self::FLAG_UNCOMPRESSED) {
            throw new \InvalidArgumentException(sprintf('Unsupported frame flag: 0x%02x', ord($flag)));
        }

        $lengthBytes = substr($frame, 1, 4);
        $unpacked = unpack('Nlength', $lengthBytes);
        if ($unpacked === false) {
            throw new \InvalidArgumentException('Could not read the 4-byte length field.');
        }
        $length = $unpacked['length'];
        if (!is_int($length)) {
            throw new \InvalidArgumentException('Could not read the 4-byte length field.');
        }

        $payload = substr($frame, self::HEADER_LENGTH);
        if (strlen($payload) !== $length) {
            throw new \InvalidArgumentException(
                sprintf('Declared payload length %d does not match actual length %d.', $length, strlen($payload))
            );
        }

        return $payload;
    }
}
