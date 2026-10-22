<?php

namespace Pagon;

use PHPUnit\Framework\TestCase;

class CompatibilityTest extends TestCase
{
    public function testHelpersAndTranslationLoadWithoutDeprecations(): void
    {
        require_once dirname(__DIR__) . '/lib/assistant.php';
        $this->assertTrue(pagon_str_contains('housing-server', array('none', 'server')));
        $this->assertFalse(pagon_str_contains('housing-server', array('none')));
        $this->assertTrue(str_contains('housing-server', 'server'));
        $this->assertTrue(class_exists(Middleware\I18N::class));
        $this->assertSame('Hello World', __('Hello :name', array(':name' => 'World')));
    }

    public function testCheckboxAndRadioGroupsPreserveKeysAndZeroSelection(): void
    {
        $checkboxes = Html::checkboxes('city', array(0 => 'Zero', 2 => 'Two'), array(0));
        $this->assertStringContainsString('value="0" type="checkbox" name="city" checked="true"', $checkboxes);
        $this->assertStringContainsString('value="2" type="checkbox" name="city"', $checkboxes);
        $this->assertStringContainsString('value="0" type="radio" name="city" checked="true"',
            Html::radios('city', array(0 => 'Zero', 2 => 'Two'), 0));
        $this->assertStringContainsString('type="checkbox"', Html::checkbox('city', 'gz'));
        $this->assertStringContainsString('type="radio"', Html::radio('city', 'gz'));
    }

    public function testXmlDecodesRepeatedNodes(): void
    {
        $result = Config\Xml::decode('<root><name>one</name><name>two</name></root>');
        $this->assertSame(array(array('#text' => 'one'), array('#text' => 'two')), $result['root']['name']);
    }

    public function testLastModifiedWithoutConditionalHeader(): void
    {
        $app = new App(array('cli' => false));
        $this->assertSame($app->output, $app->output->lastModified(1700000000));
        $this->assertArrayHasKey('Last-Modified', $app->output->header());
    }

    public function testFileCacheCanBeReadRepeatedly(): void
    {
        $dir = sys_get_temp_dir() . '/pagon-cache-test-' . bin2hex(random_bytes(8));
        $cache = new Cache\File(array('path' => $dir));
        try {
            $cache->set('key', array('value' => false));
            $this->assertSame(array('value' => false), $cache->get('key'));
            $this->assertSame(array('value' => false), $cache->get('key'));
        } finally {
            $cache->delete('key');
            if (is_dir($dir)) rmdir($dir);
        }
    }

    public function testTypeErrorUsesExceptionRouteAndCleansUp(): void
    {
        $app = new App();
        $app->command('', function () { throw new \TypeError('bad input'); });
        $seen = null;
        $app->handleError('exception', function ($input, $output) use (&$seen) {
            $seen = $input->params['error']['error'];
            $output->body('handled');
        });
        ob_start();
        try {
            $app->run();
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertInstanceOf(\TypeError::class, $seen);
        $this->assertSame('handled', $body);
        $this->assertFalse($app->running());
    }

    public function testDisabledExceptionHandlingStillRestoresState(): void
    {
        $app = new App(array('exception' => false, 'buffer' => true));
        $app->command('', function () { echo 'partial'; throw new \TypeError('bad input'); });
        $previous = function () { return false; };
        set_error_handler($previous);
        ob_start();
        try {
            try {
                $app->run();
                $this->fail('TypeError should be rethrown');
            } catch (\TypeError $e) {
                $this->assertSame('bad input', $e->getMessage());
            }
            $handler = set_error_handler(function () { return false; });
            restore_error_handler();
            $this->assertSame($previous, $handler);
            $this->assertFalse($app->running());
            $this->assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
            restore_error_handler();
        }
    }

    public function testBufferedResponsePreservesCallerBuffer(): void
    {
        $app = new App(array('buffer' => true));
        $app->command('', function ($input, $output) { echo 'direct'; $output->body('body'); });
        ob_start();
        $level = ob_get_level();
        try {
            $app->run();
            $this->assertSame($level, ob_get_level());
            $this->assertSame('bodydirect', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }
}
