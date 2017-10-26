<?php

/*
 * This file is part of the Composer Virtual Environment Plugin project.
 *
 * (c) Stephan Jorek <stephan.jorek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sjorek\Composer\VirtualEnvironment\Tests\Processor\GitHook;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;
use Sjorek\Composer\VirtualEnvironment\Processor\GitHook\FileProcessor;
use Sjorek\Composer\VirtualEnvironment\Tests\Processor\AbstractVfsStreamTestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * FileProcessor test case.
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class FileProcessorTest extends AbstractVfsStreamTestCase
{
    /**
     * @test
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\FileProcessor::__construct
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\AbstractProcessor::__construct
     * @see FileProcessor::__construct()
     */
    public function check__construct()
    {
        $this->assertInstanceOf(
            FileProcessor::class,
            new FileProcessor(null, null, null, null)
        );
    }

    public function provideCheckDeployData()
    {
        return array(
            'refuse to deploy for invalid hook' => array(
                false,
                'Invalid git-hook invalid-hook given.',
                array(),
                array(),
                false,
                null,
                null,
                'target/invalid-hook',
            ),
            'target already exists' => array(
                false,
                'The git-hook file vfs://test/target/pre-commit already exists.',
                array('target' => array('pre-commit' => ''), 'source' => array('source.sh' => '')),
                array('target' => array('pre-commit' => ''), 'source' => array('source.sh' => '')),
            ),
            'target already exists, forced removal' => array(
                true,
                'Removed existing git-hook file vfs://test/target/pre-commit.',
                array('target' => array('pre-commit' => 'X'), 'source' => array('source.sh' => 'X')),
                array('target' => array('pre-commit' => ''), 'source' => array('source.sh' => 'X')),
                true,
            ),
            'target already exists, forced removal fails due to lack of permissions' => array(
                false,
                '/^Failed to remove the existing git-hook file vfs:\/\/test\/target\/pre-commit: Could not delete/',
                array('target' => array('pre-commit' => ''), 'source' => array('source.sh' => '')),
                array('target' => array('pre-commit' => ''), 'source' => array('source.sh' => '')),
                true,
                0555,
            ),
            'missing template' => array(
                false,
                'The git-hook file template vfs://test/source/source.sh does not exist.',
                array(),
            ),
            'template is not readable' => array(
                false,
                'Failed to fetch the git-hook file template vfs://test/source/source.sh.',
                array('source' => array('source.sh' => '')),
                array('source' => array('source.sh' => '')),
                true,
                null,
                0222,
            ),
            'no permission to create target directory' => array(
                false,
                'Failed to create the git-hook file target directory vfs://test/target: vfs://test/target does not exist and could not be created.',
                array('source' => array('source.sh' => '')),
                array('source' => array('source.sh' => '')),
                true,
                0555,
            ),
            'no permission to write target file' => array(
                false,
                'Failed to write the git-hook file vfs://test/target/pre-commit.',
                array('source' => array('source.sh' => ''), 'target' => array()),
                array('source' => array('source.sh' => ''), 'target' => array()),
                true,
                0555,
            ),
            'everything works as expected' => array(
                true,
                array(
                    'Removed existing git-hook file vfs://test/target/pre-commit.',
                    'Installed git-hook file vfs://test/target/pre-commit.',
                    '',
                ),
                array('source' => array('source.sh' => 'X'), 'target' => array('pre-commit' => 'X')),
                array('source' => array('source.sh' => 'X'), 'target' => array('pre-commit' => 'Y')),
                true,
                0755,
                0644,
            ),
        );
    }

    /**
     * @test
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\FileProcessor::__construct
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\AbstractProcessor::__construct
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\AbstractProcessor::deploy()
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\FileProcessor::deployHook()
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\ExecutableFromTemplateTrait::deployTemplate()
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\ExecutableFromTemplateTrait::fetchTemplate()
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\ExecutableFromTemplateTrait::renderTemplate()
     * @dataProvider provideCheckDeployData
     *
     * @param bool   $expectedResult
     * @param string $expectedOutput
     * @param array  $expectedFilesystem
     * @param array  $structure
     * @param bool   $force
     * @param int    $directoryMode
     * @param int    $fileMode
     * @param string $hook
     * @param string $target
     * @see FileProcessor::deploy()
     */
    public function checkDeploy(
        $expectedResult,
        $expectedOutput,
        array $expectedFilesystem,
        array $filesystem = array(),
        $force = false,
        $directoryMode = null,
        $fileMode = null,
        $hook = 'target/pre-commit',
        $target = 'source/source.sh'
    ) {
        $io = new BufferedOutput(BufferedOutput::VERBOSITY_DEBUG, false);

        $root = vfsStream::setup('test', $directoryMode, $filesystem);
        foreach (array($target, $hook) as $file) {
            if ($fileMode !== null && $root->hasChild($file)) {
                $root->getChild($file)->chmod($fileMode);
            }
            if ($directoryMode !== null && $root->hasChild(dirname($file))) {
                $root->getChild(dirname($file))->chmod($directoryMode);
            }
        }
        $target = $root->url() . '/' . $target;
        $hook = $root->url() . '/' . $hook;
        $processor = new FileProcessor(basename($hook), $target, $root->url(), dirname($hook));

        \Composer\Util\vfsFilesystem::$vfs = $root;
        \Composer\Util\vfsFilesystem::$cwd = $root;
        $this->setProtectedProperty($processor, 'filesystem', new \Composer\Util\vfsFilesystem());

        $result = $processor->deploy($io, $force);
        $this->assertSame($expectedResult, $result, 'Assert that result is the same.');

        $output = explode(PHP_EOL, $io->fetch());
        if (is_array($expectedOutput)) {
            $output = array_slice(
                $output,
                0,
                count($expectedOutput) ?: 10
            );
            $this->assertEquals($expectedOutput, $output, 'Assert that output is equal.');
        } else {
            $output = array_shift($output);
            if ($expectedOutput && $expectedOutput[0] === '/') {
                $this->assertRegExp($expectedOutput, $output, 'Assert that output matches expectation.');
            } else {
                $this->assertSame($expectedOutput, $output, 'Assert that output is the same.');
            }
        }

        $visitor = new vfsStreamStructureVisitor();
        $filesystem = vfsStream::inspect($visitor)->getStructure();
        $this->assertEquals(
            $expectedFilesystem,
            $filesystem['test'],
            'Assert that the filesystem structure is equal.'
        );

        if ($root->hasChild($hook)) {
            $this->assertTrue(
                $root->getChild($hook)->getPermissions() === 0777,
                'Assert that the target file is executable.'
            );
        }
    }

    public function provideCheckRoolbackData()
    {
        return array(
            'refuse to remove an invalid hook' => array(
                false,
                'Invalid git-hook invalid-hook given.',
                array(),
                array(),
                null,
                null,
                'target/invalid-hook',
            ),
            'refuse removal of symlink' => array(
                false,
                'Refused to remove the git-hook file vfs://test/target/pre-commit, as it is a symbolic link.',
                array('target' => array('pre-commit' => 'symlink')),
                array('target' => array('pre-commit' => 'symlink')),
            ),
            'removal fails due to lack of permissions' => array(
                false,
                '/^Failed to remove the git-hook file vfs:\/\/test\/target\/pre-commit: Could not delete/',
                array('target' => array('pre-commit' => '')),
                array('target' => array('pre-commit' => '')),
                0555,
            ),
            'refuse removal of dangling symlink' => array(
                false,
                'Refused to remove the git-hook file vfs://test/target/pre-commit, as it is a dangling symbolic link.',
                array('target' => array('pre-commit' => 'dangling symlink')),
                array('target' => array('pre-commit' => 'dangling symlink')),
                0555,
            ),
            'skip removing missing file' => array(
                true,
                'Skipped removing the git-hook file vfs://test/target/pre-commit, as it does not exist.',
                array('target' => array()),
                array('target' => array()),
            ),
            'everything works as expected' => array(
                true,
                array(
                    'Removed git-hook file vfs://test/target/pre-commit.',
                    '',
                ),
                array('target' => array()),
                array('target' => array('pre-commit' => '')),
            ),
        );
    }

    /**
     * @test
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\FileProcessor::__construct
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\AbstractProcessor::__construct
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\AbstractProcessor::rollback()
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\GitHook\FileProcessor::rollbackHook()
     * @covers \Sjorek\Composer\VirtualEnvironment\Processor\ExecutableFromTemplateTrait::rollbackTemplate()
     * @dataProvider provideCheckRoolbackData
     *
     * @param bool   $expectedResult
     * @param string $expectedOutput
     * @param array  $expectedFilesystem
     * @param array  $structure
     * @param int    $directoryMode
     * @param int    $fileMode
     * @param string $hook
     * @see FileProcessor::rollback()
     */
    public function checkRollback(
        $expectedResult,
        $expectedOutput,
        array $expectedFilesystem,
        array $filesystem = array(),
        $directoryMode = null,
        $fileMode = null,
        $hook = 'target/pre-commit'
    ) {
        $io = new BufferedOutput(BufferedOutput::VERBOSITY_DEBUG, false);

        $root = vfsStream::setup('test', $directoryMode, $filesystem);
        $target = 'source/source.sh';
        foreach (array($target, $hook) as $file) {
            if ($fileMode !== null && $root->hasChild($file)) {
                $root->getChild($file)->chmod($fileMode);
            }
            if ($directoryMode !== null && $root->hasChild(dirname($file))) {
                $root->getChild(dirname($file))->chmod($directoryMode);
            }
        }
        $hook = $root->url() . '/' . $hook;
        $target = $root->url() . '/' . $target;
        $processor = new FileProcessor(basename($hook), $target, $root->url(), dirname($hook));

        \Composer\Util\vfsFilesystem::$vfs = $root;
        \Composer\Util\vfsFilesystem::$cwd = $root;
        $this->setProtectedProperty($processor, 'filesystem', new \Composer\Util\vfsFilesystem());

        $result = $processor->rollback($io);
        $this->assertSame($expectedResult, $result, 'Assert that result is the same.');

        $output = explode(PHP_EOL, $io->fetch());
        if (is_array($expectedOutput)) {
            $output = array_slice(
                $output,
                0,
                count($expectedOutput) ?: 10
            );
            $this->assertEquals($expectedOutput, $output, 'Assert that output is equal.');
        } else {
            $output = array_shift($output);
            if ($expectedOutput && $expectedOutput[0] === '/') {
                $this->assertRegExp($expectedOutput, $output, 'Assert that output matches expectation.');
            } else {
                $this->assertSame($expectedOutput, $output, 'Assert that output is the same.');
            }
        }

        $visitor = new vfsStreamStructureVisitor();
        $filesystem = vfsStream::inspect($visitor)->getStructure();
        $this->assertEquals(
            $expectedFilesystem,
            $filesystem['test'],
            'Assert that the filesystem structure is equal.'
        );
    }
}
