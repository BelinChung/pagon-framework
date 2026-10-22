<?php

namespace Pagon\Session;

/**
 * Bridge existing stores to PHP's object-based session handler API.
 */
class Handler implements \SessionHandlerInterface
{
    private Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function open(string $path, string $name): bool
    {
        return (bool)$this->store->open($path, $name);
    }

    public function close(): bool
    {
        return (bool)$this->store->close();
    }

    public function read(string $id): string|false
    {
        $data = $this->store->read($id);
        return $data === null || $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        return (bool)$this->store->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        return (bool)$this->store->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        $result = $this->store->gc($max_lifetime);
        return $result === true ? 0 : $result;
    }
}
