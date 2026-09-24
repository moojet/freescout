<?php

namespace Tests\Unit;

use App\Module;
use Tests\TestCase;

class ModulePublicSymlinkTest extends TestCase
{
    private $root;

    protected function setUp()
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'freescout-module-symlink-'.uniqid();
        mkdir($this->root.DIRECTORY_SEPARATOR.'modules', 0755, true);
    }

    protected function tearDown()
    {
        \File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function testItPreservesAnAliasThatAlreadyResolvesToThePublicDirectory()
    {
        $assets = $this->root.DIRECTORY_SEPARATOR.'ReplyRabbit'.DIRECTORY_SEPARATOR.'Assets';
        mkdir($assets, 0755, true);
        file_put_contents($assets.DIRECTORY_SEPARATOR.'asset.txt', 'asset');
        $public = $this->root.DIRECTORY_SEPARATOR.'ReplyRabbit'.DIRECTORY_SEPARATOR.'Public';
        symlink('Assets', $public);
        $alias = $this->root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'replyrabbit';
        symlink('../ReplyRabbit/Public', $alias);

        $result = Module::ensurePublicSymlink($alias, $public);

        $this->assertTrue($result['success']);
        $this->assertSame('../ReplyRabbit/Public', readlink($alias));
        $this->assertTrue(is_link($public));
        $this->assertSame(realpath($public), realpath($alias));
    }

    public function testItReplacesOnlyAMismatchedAliasSymlink()
    {
        $public = $this->makePublicDirectory();
        $stale = $this->root.DIRECTORY_SEPARATOR.'stale-assets';
        mkdir($stale);
        $alias = $this->root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'replyrabbit';
        symlink($stale, $alias);

        $result = Module::ensurePublicSymlink($alias, $public);

        $this->assertTrue($result['success']);
        $this->assertTrue(is_link($alias));
        $this->assertSame(realpath($public), realpath($alias));
        $this->assertDirectoryExists($stale);
    }

    public function testItCreatesAnAliasForAnExistingPublicDirectory()
    {
        $public = $this->makePublicDirectory();
        $alias = $this->root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'replyrabbit';

        $result = Module::ensurePublicSymlink($alias, $public);

        $this->assertTrue($result['success']);
        $this->assertTrue(is_link($alias));
        $this->assertSame(realpath($public), realpath($alias));
    }

    public function testItPreservesARealAliasDirectoryAndReportsFailure()
    {
        $public = $this->makePublicDirectory();
        $alias = $this->root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'replyrabbit';
        mkdir($alias);
        file_put_contents($alias.DIRECTORY_SEPARATOR.'keep.txt', 'keep');

        $result = Module::ensurePublicSymlink($alias, $public);

        $this->assertFalse($result['success']);
        $this->assertDirectoryExists($alias);
        $this->assertFileExists($alias.DIRECTORY_SEPARATOR.'keep.txt');
    }

    public function testItRejectsBrokenAndSelfReferencingPublicTargets()
    {
        $broken = $this->root.DIRECTORY_SEPARATOR.'Broken'.DIRECTORY_SEPARATOR.'Public';
        mkdir(dirname($broken), 0755, true);
        symlink('missing', $broken);

        $alias = $this->root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'broken';
        $broken_result = Module::ensurePublicSymlink($alias, $broken);

        $self = $this->root.DIRECTORY_SEPARATOR.'Self'.DIRECTORY_SEPARATOR.'Public';
        mkdir(dirname($self), 0755, true);
        symlink('Public', $self);
        $self_result = Module::ensurePublicSymlink($alias, $self);

        $this->assertFalse($broken_result['success']);
        $this->assertTrue(is_link($broken));
        $this->assertFalse($self_result['success']);
        $this->assertTrue(is_link($self));
    }

    public function testItRejectsAMissingPublicDirectoryWithoutCreatingIt()
    {
        $public = $this->root.DIRECTORY_SEPARATOR.'ReplyRabbit'.DIRECTORY_SEPARATOR.'Public';
        mkdir(dirname($public), 0755, true);
        $alias = $this->root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'replyrabbit';

        $result = Module::ensurePublicSymlink($alias, $public);

        $this->assertFalse($result['success']);
        $this->assertFalse(file_exists($public));
        $this->assertFalse(file_exists($alias));
    }

    private function makePublicDirectory()
    {
        $public = $this->root.DIRECTORY_SEPARATOR.'ReplyRabbit'.DIRECTORY_SEPARATOR.'Public';
        mkdir($public, 0755, true);
        file_put_contents($public.DIRECTORY_SEPARATOR.'asset.txt', 'asset');

        return $public;
    }
}
