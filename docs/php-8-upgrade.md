# PHP 8.1–8.5 upgrade

The minimum supported PHP version is 8.1; PHP 9 is outside the declared range.
CI runs PHP 8.1, 8.2, 8.3, 8.4 and 8.5 with all diagnostics enabled. Optional
Redis/Memcache/Memcached, Opauth, Jade and YAML integrations still require their
own extensions/packages and service integration checks.

## Application changes

- Replace calls to the old global `str_contains($text, array(...))` helper with
  `pagon_str_contains`. Native PHP `str_contains` accepts a string needle only.
- Exceptions and PHP errors (`Throwable`, including `TypeError`) now share the
  application's exception route. Disabling exception handling rethrows them
  after cleanup, without flushing an incomplete buffered response.
- Sessions use `SessionHandlerInterface` internally. Existing custom Store
  subclasses retain their callback API. An already active native session keeps
  its existing ID and handler. Explicitly close it before selecting another store.
- HTML checkbox/radio helpers are static, including the group helpers.
- Composer replaces the old `pagon/framework` 0.8 package to avoid loading two
  copies of the Pagon namespace. Remove obsolete packages requiring PHP 5 tooling.

## Encrypted cookie / CSRF migration

Cryptor uses OpenSSL AES-256-GCM, random nonces and a `v2:` envelope. Configure a
nonempty secret key and install ext-openssl. The string-key constructor remains
supported; old `cipher`, `mode`, `rand` and `block` options are rejected.

Old mcrypt Rijndael-256 ciphertext is **not** AES-256-compatible and is not readable
by default. Plan expiry/relogin for encrypted cookies and regenerate CSRF tokens
at rollout. If continuity is required, supply a separately audited
`legacy_decryptor` callable; it is invoked only for data without the v2 prefix.
No legacy decryptor is bundled. All new writes use v2. Mixed old/new deployments
cannot read each other's new tokens; coordinate rollout and rollback accordingly.
Test application-specific persisted ciphertext before deployment.

## Validation and release

Run `composer update`, `composer validate --strict` and `composer test` on each
supported runtime. Run MySQL, cache service and third-party SDK staging checks
for consumers separately. Publish the framework changes before refreshing a
consumer's production lock file; local path repositories are development only.
