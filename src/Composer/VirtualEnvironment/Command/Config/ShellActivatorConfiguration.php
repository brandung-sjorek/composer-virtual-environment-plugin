<?php

/*
 * This file is part of Composer Virtual Environment Plugin.
 *
 * (c) Stephan Jorek <stephan.jorek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sjorek\Composer\VirtualEnvironment\Command\Config;

use Composer\Config;
use Composer\Util\Filesystem;
use Sjorek\Composer\VirtualEnvironment\Config\GlobalConfiguration;
use Sjorek\Composer\VirtualEnvironment\Config\LocalConfiguration;
use Sjorek\Composer\VirtualEnvironment\Processor\ActivationScriptProcessor;

/**
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class ShellActivatorConfiguration extends AbstractConfiguration
{
    public function load()
    {
        $input = $this->input;

        if (!$input->getArgument('shell')) {
            $recipe = new LocalConfiguration($this->composer);
            if ($recipe->load()) {
                $this->recipe = $recipe;
                $this->set('load', true);
                $this->set('save', false);
            } else {
                $recipe = new GlobalConfiguration($this->composer);
                if ($recipe->load()) {
                    $this->recipe = $recipe;
                    $this->set('load', true);
                    $this->set('save', false);
                }
            }
        }

        if (!parent::load()) {
            return false;
        }

        $recipe = $this->recipe;

        $filesystem = new Filesystem();

        $this->set(
            'resPath',
            $filesystem->normalizePath(__DIR__ . '/../../../../../res')
        );
        $this->set(
            'binPath',
            $filesystem->normalizePath($this->composer->getConfig()->get('bin-dir'))
        );
        $relativeBinPath = $this->set(
            'relativeBinPath',
            $this->composer->getConfig()->get('bin-dir', Config::RELATIVE_PATHS)
        );

        $name = '{$name}';
        if ($input->getOption('name')) {
            $name = $input->getOption('name');
        } elseif ($recipe->has('name')) {
            $name = $recipe->get('name', $name);
        }
        $this->set('name', $name);
        $this->set('realName', $this->parseConfig($this->parseManifest($name)));

        $candidates = array('detect'); // = explode(',', ActivationScriptProcessor::AVAILABLE_ACTIVATORS);
        if ($input->getArgument('shell')) {
            $candidates = $input->getArgument('shell');
        } elseif ($recipe->has('shell')) {
            $candidates = $recipe->get('shell', $candidates);
        }
        $activators = $this->set('shell', ActivationScriptProcessor::import($candidates));

        $colors = true;
        if ($input->getOption('no-colors')) {
            $colors = false;
        } elseif ($input->getOption('colors')) {
            $colors = true;
        } elseif ($recipe->has('colors')) {
            $colors = $recipe->get('colors', $colors);
        }
        $this->set('colors', $colors);

        // If only has been given, we'll symlink to this activator
        if (count($activators) === 1) {
            $symlinks = array($relativeBinPath . '/activate' => 'activate.' . $activators[0]);
            $this->set('link', $symlinks);
            $this->set('symlinks', $this->expandPaths($symlinks));
        }

        return true;
    }

    public function save($force = false)
    {
        if ($this->get('save')) {
            $recipe = $this->recipe;
            $recipe->set('name', $this->get('name'));
            $recipe->set('shell', $this->get('shell'));
            $recipe->set('colors', $this->get('colors'));
        }

        return parent::save($force);
    }
}
