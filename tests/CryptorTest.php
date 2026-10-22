<?php

namespace Pagon;

use Pagon\Utility\Cryptor;
use PHPUnit\Framework\TestCase;

class CryptorTest extends TestCase
{
    public function testRoundTripAndRandomNonce(): void
    {
        $cryptor = new Cryptor('test-only-secret');
        foreach (array('', 'hello', "中文\0payload") as $value) {
            $encrypted = $cryptor->encrypt($value);
            $this->assertStringStartsWith('v2:', $encrypted);
            $this->assertSame($value, $cryptor->decrypt($encrypted));
            $this->assertNotSame($encrypted, $cryptor->encrypt($value));
        }
    }

    public function testTamperingWrongKeyAndMalformedPayloadAreRejected(): void
    {
        $cryptor = new Cryptor('test-only-secret');
        $encrypted = $cryptor->encrypt('private');
        $payload = base64_decode(substr($encrypted, 3));
        $payload[12] = chr(ord($payload[12]) ^ 1);
        $this->assertFalse($cryptor->decrypt('v2:' . base64_encode($payload)));
        $this->assertFalse((new Cryptor('wrong-secret'))->decrypt($encrypted));
        foreach (array(null, '', 'v2:!', 'v2:YWJj', base64_encode('old ciphertext')) as $invalid) {
            $this->assertFalse($cryptor->decrypt($invalid));
        }
    }

    public function testLegacyReaderIsOnlyUsedForOldFormats(): void
    {
        $cryptor = new Cryptor(array('key' => 'test-only-secret', 'legacy_decryptor' => function ($value) {
            return $value === 'legacy' ? 'old data' : false;
        }));
        $this->assertSame('old data', $cryptor->decrypt('legacy'));
        $this->assertFalse($cryptor->decrypt('v2:invalid'));
    }

    public function testLegacyCipherConfigurationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Cryptor(array('key' => 'test-only-secret', 'cipher' => 'rijndael-256'));
    }

    public function testEncryptedCookieRoundTrip(): void
    {
        $app = new App(array('cli' => false));
        $app->cryptor = new Cryptor('test-only-secret');
        $app->set('cookie', array('path' => '/', 'domain' => null, 'secure' => false,
            'httponly' => true, 'timeout' => 0, 'sign' => false, 'secret' => '', 'encrypt' => true));
        $app->output->cookie('test', array('user' => 123));
        $cookie = $app->output->buildCookie()[0];
        $input = new Http\Input(array('app' => $app, '_cookies' => array('test' => $cookie['value'])));
        $this->assertSame(array('user' => 123), $input->cookie('test'));
    }
}
