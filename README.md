# php-grpc-frame-codec

[![Tests](https://github.com/CleatSquad/php-grpc-frame-codec/actions/workflows/tests.yml/badge.svg)](https://github.com/CleatSquad/php-grpc-frame-codec/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

The 5-byte gRPC message frame your PHP backend must send back to
[`franken-grpc`](https://github.com/CleatSquad/franken-grpc) — extracted
into a tiny, dependency-free package so every backend integrating with
the relay doesn't have to hand-roll it.

**Not tied to FrankenPHP.** Despite the name of the relay it was built
for, this package is pure byte manipulation (`pack()`/`unpack()`) with no
runtime dependency at all. It works identically under nginx+PHP-FPM,
Apache+mod_php, RoadRunner, Swoole, plain php-fpm, or FrankenPHP — the
only thing that matters is that *something* in front of your backend
speaks the same 5-byte frame (franken-grpc does; anything reusing its
wire contract would too).

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
composer require cleatsquad/php-grpc-frame-codec
```

## Usage

```php
use CleatSquad\GrpcFrameCodec\GrpcFrameCodec;

$codec = new GrpcFrameCodec();

// Your backend receives the raw protobuf request body directly — no
// decode() needed on the way in.
$requestBytes = file_get_contents('php://input');

// ... decode $requestBytes with your generated protobuf message class,
// handle the call, encode the response message ...

// Frame the response before sending it back:
header('Content-Type: ' . GrpcFrameCodec::CONTENT_TYPE);
echo $codec->encode($responseBytes);
```

`decode()` is provided for symmetry and for tests — most backends never
need to call it, since franken-grpc already strips the frame on the
request side.

## Server-streaming responses

A server-streaming RPC emits several frames back-to-back in the same HTTP
response. If you're reading the body incrementally (chunked output,
`fwrite`/flush per message, or a client consuming the response as a
stream) you may not have a full frame yet on a given read. `readNextFrame()`
handles that: it consumes one frame from the front of `$buffer` and
advances it past what it read, returning `null` — without touching
`$buffer` — when the frame isn't complete yet.

```php
use CleatSquad\GrpcFrameCodec\GrpcFrameCodec;

$codec = new GrpcFrameCodec();
$buffer = '';

foreach ($chunksAsTheyArrive as $chunk) {
    $buffer .= $chunk;

    while (($payload = $codec->readNextFrame($buffer)) !== null) {
        handle($payload); // one decoded message
    }
}
```

For a response you already have in full (the common unary case),
`decode()` remains the simpler one-shot call.

## Examples by PHP runtime

The snippet above works as-is under any of these — only the entry point
changes.

### nginx + PHP-FPM

```php
// public/index.php
// nginx forwards POST /{package}.{Service}/{Method} to this script via fastcgi_pass
use CleatSquad\GrpcFrameCodec\GrpcFrameCodec;

$codec = new GrpcFrameCodec();
$requestBytes = file_get_contents('php://input');

$responseBytes = handle($_SERVER['REQUEST_URI'], $requestBytes); // your dispatch + business logic

header('Content-Type: application/grpc+proto');
echo $codec->encode($responseBytes);
```

### Apache + mod_php

Identical to the nginx example — `mod_php` is just another SAPI calling
the same `index.php`. Route `/{package}.{Service}/{Method}` to it with a
`RewriteRule`, the same way you would for any front-controller pattern.

### RoadRunner

```php
use CleatSquad\GrpcFrameCodec\GrpcFrameCodec;
use Spiral\RoadRunner\Http\PSR7Worker;

$codec = new GrpcFrameCodec();

while ($request = $psr7Worker->waitRequest()) {
    $requestBytes = (string) $request->getBody();
    $responseBytes = handle($request->getUri()->getPath(), $requestBytes);

    $response = new \Nyholm\Psr7\Response(200, ['Content-Type' => 'application/grpc+proto'], $codec->encode($responseBytes));
    $psr7Worker->respond($response);
}
```

### FrankenPHP

```php
// public/index.php — same code as the nginx+PHP-FPM example, FrankenPHP
// is just the server process running it (worker mode or classic).
```

## License

MIT — see [LICENSE](LICENSE).
