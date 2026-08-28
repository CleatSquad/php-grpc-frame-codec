# franken-grpc-codec-php

[![test](https://github.com/CleatSquad/franken-grpc-codec-php/actions/workflows/test.yml/badge.svg)](https://github.com/CleatSquad/franken-grpc-codec-php/actions/workflows/test.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

The 5-byte gRPC message frame your PHP backend must send back to
[`franken-grpc`](https://github.com/CleatSquad/franken-grpc) — extracted
into a tiny, dependency-free package so every backend integrating with
the relay doesn't have to hand-roll it.

## Why this exists

franken-grpc's contract is asymmetric: the **request** your backend
receives is raw protobuf, no envelope (the relay already stripped it
converting the incoming HTTP/2 gRPC call into a plain HTTP/1.1 POST) —
but the **response** you send back must carry the standard gRPC frame:

```text
1 byte   compression flag — must be 0, franken-grpc does not decompress
4 bytes  big-endian payload length
N bytes  raw protobuf payload
```

Getting this wrong produces no clean error on either side — just a
`frame declares compression flag N, compressed frames are not supported
by this relay` from the relay, once you happen to send the request-side
framing back by mistake. This package exists so that byte-level detail
is written once, tested, and reused instead of copy-pasted per project.

## Install

```bash
composer require cleatsquad/franken-grpc-codec-php
```

## Usage

```php
use CleatSquad\FrankenGrpcCodec\FrankenGrpcFrameCodec;

$codec = new FrankenGrpcFrameCodec();

// Your backend receives the raw protobuf request body directly — no
// decode() needed on the way in.
$requestBytes = file_get_contents('php://input');

// ... decode $requestBytes with your generated protobuf message class,
// handle the call, encode the response message ...

// Frame the response before sending it back:
header('Content-Type: application/grpc+proto');
echo $codec->encode($responseBytes);
```

`decode()` is provided for symmetry and for tests — most backends never
need to call it, since franken-grpc already strips the frame on the
request side.

## License

MIT — see [LICENSE](LICENSE).
