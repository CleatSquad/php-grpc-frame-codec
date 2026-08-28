# Contributing

Contributions are welcome — bug reports, documentation, and code alike.

## Getting started

```bash
git clone https://github.com/CleatSquad/php-franken-grpc-codec.git
cd php-franken-grpc-codec
composer install
```

## Before opening a pull request

```bash
composer test      # PHPUnit
composer analyse   # PHPStan, max level, must report no error
```

## Guidelines

- Follow PSR-12. Match the style of the surrounding code.
- Every behaviour change needs a test. Bug fixes need a test that fails before
  the fix.
- Keep the public API small. A new public method is a long-term commitment.
- This library has no runtime dependencies, and that is deliberate. Pull
  requests adding one need to make a strong case.
- Update the README when public behaviour changes.

## Backward compatibility

Anything under `src/` that is `public` is part of the public API and follows
[Semantic Versioning](https://semver.org). Breaking it requires a major
release, so prefer additive changes.

## Commit messages

Short imperative subject line, explaining what changes and why.
