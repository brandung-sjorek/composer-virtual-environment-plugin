<?php

/*
 * This file is part of the Composer Virtual Environment Plugin project.
 *
 * (c) Stephan Jorek <stephan.jorek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sjorek\Composer\VirtualEnvironment\Config;

use Composer\Composer;

/**
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class FileConfiguration extends AbstractConfiguration implements FileConfigurationInterface
{
    public $filePath;

    protected $blacklist = array('info');

    const INFO = array(
        'virtual environment configuration file',
        'generated by the composer-virtual-environment-plugin',
    );

    const JSON_DEPTH = 10;

    /**
     * @param Composer $composer
     * @param string   $file
     */
    public function __construct(Composer $composer, $file)
    {
        parent::__construct($composer);
        $this->filePath = $file;
    }

    /**
     * {@inheritDoc}
     * @see FileConfigurationInterface::file()
     */
    public function file()
    {
        return $this->filePath;
    }

    /**
     * {@inheritDoc}
     * @see FileConfigurationInterface::load()
     */
    public function load()
    {
        if (strpos($this->filePath, 'php://') === false && !file_exists($this->filePath)) {
            return false;
        }

        $json = file_get_contents($this->filePath, false);
        if ($json === false) {
            return false;
        }
        $data = json_decode($json, true, static::JSON_DEPTH, JSON_OBJECT_AS_ARRAY);
        if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }
        if (!is_array($data)) {
            return false;
        }

        $blacklist = $this->blacklist;
        $this->data = array_filter(
            $data,
            function ($key) use ($blacklist) {
                return !in_array($key, $blacklist, true);
            },
            ARRAY_FILTER_USE_KEY
        );
        $this->dirty = false;

        return true;
    }

    /**
     * {@inheritDoc}
     * @see FileConfigurationInterface::save()
     */
    public function save($force = false)
    {
        if ($this->dirty || $force) {
            $data = $this->export();
            $this->sortArrayByKeyRecursive($data);
            $json = $this->createJson($this->prepareSave($data));
            if ($json === false) {
                return false;
            }
            $result = file_put_contents($this->filePath, $json . PHP_EOL);
            if ($result === false) {
                return false;
            }
            $this->dirty = false;
        }

        return !$this->dirty;
    }

    /**
     * @param  array $data
     * @return array
     */
    protected function prepareSave(array $data)
    {
        return array_merge(array('info' => static::INFO), $data);
    }

    /**
     * @param  array        $data
     * @return string|false
     */
    protected function createJson(array $data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES /* | JSON_FORCE_OBJECT */);
    }

    /**
     * @param mixed $value
     */
    protected function sortArrayByKeyRecursive(& $value)
    {
        if (is_array($value)) {
            ksort($value);
            array_walk($value, __METHOD__);
        }
    }
}
