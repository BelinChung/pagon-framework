<?php

namespace Pagon;

use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pagon-session-test-' . bin2hex(random_bytes(8));
        Session::$defaultStore = null;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/SESS_*') as $path) unlink($path);
        if (is_dir($this->dir)) rmdir($this->dir);
        Session::$current = null;
        Session::$defaultStore = null;
    }

    public function testFreshNonGlobalSessionPersistsAndIteratesFalseValues(): void
    {
        $app = new App(array('cli' => false));
        $store = new Session\Store\File(array('dir' => $this->dir));
        $options = array('app' => $app, 'store' => $store, 'global' => false, 'id' => 'test-id');
        $session = new Session($options);
        $session->startSession();
        $this->assertCount(0, $session);
        $session['false'] = false;
        $session['nested'] = array();
        $session['nested']['key'] = 'value';
        $session->saveSession();

        $restored = new Session($options);
        $restored->startSession();
        $this->assertSame(array('false' => false, 'nested' => array('key' => 'value')), iterator_to_array($restored));
        $restored->destroySession();
        $restored->saveSession();
        $this->assertSame('', $store->read('test-id'));
    }

    public function testFileStoreAcceptsEmptyWritesAndReportsGarbageCollectionCount(): void
    {
        $store = new Session\Store\File(array('dir' => $this->dir));
        $this->assertTrue($store->open());
        $this->assertTrue($store->write('old', ''));
        touch($this->dir . '/SESS_old', time() - 100);
        $store->write('new', 'data');
        $this->assertSame(1, $store->gc(50));
        $this->assertSame('data', $store->read('new'));
        $this->assertTrue($store->destroy('missing'));
    }

    public function testObjectHandlerUsesNativeSessionEncoding(): void
    {
        $script = <<<'PHP'
require $argv[1];
error_reporting(E_ALL);
set_error_handler(function ($level, $message) { throw new ErrorException($message); });
$app = new Pagon\App(array('cli' => false));
$store = new Pagon\Session\Store\File(array('dir' => $argv[2]));
$options = array('app' => $app, 'store' => $store, 'global' => true, 'id' => 'native-test');
$first = new Pagon\Session($options);
$first->startSession();
$first['value'] = false;
$first->saveSession();
$second = new Pagon\Session($options);
$second->startSession();
if ($second->allSession() !== array('value' => false)) exit(1);
$second->saveSession();
session_id('already-active');
session_start();
$_SESSION = array('existing' => 'value');
$third = new Pagon\Session(array('app' => $app, 'global' => true));
$third->startSession();
$third->startSession();
if (session_id() !== 'already-active' || $third['existing'] !== 'value') exit(2);
$third->saveSession();
echo 'ok';
PHP;
        $process = proc_open(array(PHP_BINARY, '-d', 'session.use_cookies=0', '-r', $script,
            dirname(__DIR__) . '/vendor/autoload.php', $this->dir),
            array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $stderr);
        $this->assertSame('ok', $stdout, $stderr);
    }
}
