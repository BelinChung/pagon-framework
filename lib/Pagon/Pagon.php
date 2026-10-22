<?php
/**
 * Pagon Framework
 *
 * @package               Pagon
 * @author                Corrie Zhao <hfcorriez@gmail.com>
 * @copyright         (c) 2011 - 2014 Pagon Framework
 */

namespace Pagon;

const VERSION = '0.8.0';

/**
 * Autoload pagon namespace and add pagon alias
 */
spl_autoload_register(function ($class) {
    if (substr($class, 0, strlen(__NAMESPACE__) + 1) == __NAMESPACE__ . '\\') {
        // If with Pagon path, force require
        if ($file = stream_resolve_include_path(__DIR__ . '/' . str_replace('\\', '/', substr($class, strlen(__NAMESPACE__) + 1)) . '.php')) {
            require $file;
            return true;
        }
    } else if (class_exists(__NAMESPACE__ . '\\' . $class)) {
        // If class under pagon namespace, alias it.
        class_alias(__NAMESPACE__ . '\\' . $class, $class);
        return true;
    }

    return false;
});


class Pagon
{
    /**
     * Create app
     *
     * @param array $config
     * @return App
     */
    public static function create($config = array())
    {
        return new App($config);
    }
}
