<?php

namespace Pagon\Session\Store;

use Pagon\Session\Store;

class File extends Store
{
    protected $injectors = array(
        'dir' => '/tmp'
    );

    /*--------------------
    * Session Handlers
    ---------------------*/

    public function open($path = null, $name = null)
    {
        if (!is_dir($this->injectors['dir']) && !mkdir($this->injectors['dir'], 0777, true) && !is_dir($this->injectors['dir'])) {
            return false;
        }
        return is_writable($this->injectors['dir']);
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        if (file_exists($this->injectors['dir'] . '/SESS_' . $id)) {
            return file_get_contents($this->injectors['dir'] . '/SESS_' . $id);
        }
        return "";
    }

    public function write($id, $data)
    {
        return file_put_contents($this->injectors['dir'] . '/SESS_' . $id, $data, LOCK_EX) !== false;
    }

    public function destroy($id)
    {
        $path = $this->injectors['dir'] . '/SESS_' . $id;
        return !is_file($path) || unlink($path);
    }

    public function gc($lifetime)
    {
        $deleted = 0;
        foreach (glob($this->injectors['dir'] . '/SESS_*') as $path) {
            if (is_file($path) && filemtime($path) < time() - $lifetime && unlink($path)) {
                $deleted++;
            }
        }
        return $deleted;
    }
}
