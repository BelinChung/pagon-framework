<?php

namespace Pagon\Utility;

/**
 * Authenticated, versioned encryption for cookies and CSRF tokens.
 */
class Cryptor
{
    private const PREFIX = 'v2:';
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_SIZE = 12;
    private const TAG_SIZE = 16;

    private string $key;
    private $legacyDecryptor;

    public function __construct($options = array())
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('Cryptor requires the openssl extension');
        }
        $options = is_array($options) ? $options : array('key' => $options);
        if (!isset($options['key']) || !is_string($options['key']) || $options['key'] === '') {
            throw new \InvalidArgumentException('Cryptor requires a non-empty string key');
        }
        foreach (array('cipher', 'mode', 'rand', 'block') as $option) {
            if (array_key_exists($option, $options)) {
                throw new \InvalidArgumentException('Legacy Cryptor option "' . $option . '" is no longer supported');
            }
        }
        $this->legacyDecryptor = $options['legacy_decryptor'] ?? null;
        if ($this->legacyDecryptor !== null && !is_callable($this->legacyDecryptor)) {
            throw new \InvalidArgumentException('legacy_decryptor must be callable');
        }
        // Preserve the string-key API while separating this key from other uses.
        $this->key = hash_hkdf('sha256', $options['key'], 32, 'pagon/cryptor/v2');
    }

    public function encrypt($data)
    {
        $nonce = random_bytes(self::NONCE_SIZE);
        $ciphertext = openssl_encrypt((string)$data, self::CIPHER, $this->key, OPENSSL_RAW_DATA,
            $nonce, $tag, self::PREFIX, self::TAG_SIZE);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt($data)
    {
        if (!is_string($data)) return false;
        if (strncmp($data, self::PREFIX, strlen(self::PREFIX)) !== 0) {
            return $this->legacyDecryptor !== null ? ($this->legacyDecryptor)($data) : false;
        }
        $payload = base64_decode(substr($data, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) < self::NONCE_SIZE + self::TAG_SIZE) return false;
        return openssl_decrypt(substr($payload, self::NONCE_SIZE + self::TAG_SIZE), self::CIPHER,
            $this->key, OPENSSL_RAW_DATA, substr($payload, 0, self::NONCE_SIZE),
            substr($payload, self::NONCE_SIZE, self::TAG_SIZE), self::PREFIX);
    }
}
