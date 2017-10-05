<?php

/*
 * This file is part of Composer Virtual Environment Plugin.
 *
 * (c) Stephan Jorek <stephnan.jorek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sjorek\Composer\VirtualEnvironment\Config;

use Composer\Composer;

/**
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
abstract class AbstractConfiguration implements ConfigurationInterface
{
    public $filename;
    protected $composer;
    protected $data;
    protected $dirty;

    protected $blacklist = array('info');

    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
        $this->filename = $this->getRecipeFilename();
        $this->data = array(
            'info' => 'This configuration file is generated by the composer-virtual-environment-plugin',
        );
        $this->dirty = false;
        $this->load();
    }

    abstract protected function getRecipeFilename();

    public function all()
    {
        return array_keys($this->data);
    }

    public function has($key)
    {
        if (in_array($key, $this->blacklist, true)) {
            return false;
        }

        return array_key_exists($key, $this->data);
    }

    public function get($key, $default = null)
    {
        if (in_array($key, $this->blacklist, true)) {
            return $default;
        }

        return $this->has($key) ? $this->data[$key] : $default;
    }

    public function set($key, $value)
    {
        if (in_array($key, $this->blacklist, true)) {
            return;
        }
        $this->dirty = $this->get($key) !== $value;
        $this->data[$key] = $value;
    }

    public function remove($key)
    {
        if (in_array($key, $this->blacklist, true)) {
            return;
        }
        if ($this->has($key)) {
            unset($this->data[$key]);
            $this->dirty = true;
        }
    }

    public function load()
    {
        if (!file_exists($this->filename)) {
            return false;
        }

        $json = file_get_contents($this->filename, false);
        if ($json === false) {
            return false;
        }
        $data = json_decode($json, true, 3, JSON_OBJECT_AS_ARRAY);
        if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }
        if (!is_array($data)) {
            return false;
        }
        $this->data = $data;
        $this->dirty = false;

        return true;
    }

    public function persist($force = false)
    {
        if ($this->dirty || $force) {
            $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES /* | JSON_FORCE_OBJECT */);
            if ($json === false) {
                return false;
            }
            $result = file_put_contents($this->filename, $json);
            if ($result === false) {
                return false;
            }
            $this->dirty = false;

            return true;
        }

        return false;
    }
}
