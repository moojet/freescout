<?php

namespace Illuminate\Database\Eloquent {
    class Model
    {
    }
}

namespace {
    require dirname(__DIR__, 2).'/app/Module.php';

    function assertModulePublicSymlink($condition, $message)
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function removeModulePublicSymlinkFixture($path)
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item !== '.' && $item !== '..') {
                removeModulePublicSymlinkFixture($path.DIRECTORY_SEPARATOR.$item);
            }
        }
        rmdir($path);
    }

    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'freescout-module-public-symlink-'.uniqid('', true);

    try {
        $modules = $root.DIRECTORY_SEPARATOR.'modules';
        $assets = $root.DIRECTORY_SEPARATOR.'ReplyRabbit'.DIRECTORY_SEPARATOR.'Assets';
        $public = $root.DIRECTORY_SEPARATOR.'ReplyRabbit'.DIRECTORY_SEPARATOR.'Public';
        $alias = $modules.DIRECTORY_SEPARATOR.'replyrabbit';

        mkdir($modules, 0755, true);
        mkdir($assets, 0755, true);
        file_put_contents($assets.DIRECTORY_SEPARATOR.'asset.txt', 'asset');
        symlink('Assets', $public);
        symlink('../ReplyRabbit/Public', $alias);

        $result = \App\Module::ensurePublicSymlink($alias, $public);
        assertModulePublicSymlink($result['success'], 'A valid Public target must be accepted');
        assertModulePublicSymlink(readlink($alias) === '../ReplyRabbit/Public', 'A valid relative alias must be preserved');
        assertModulePublicSymlink(is_link($public), 'A valid Public symlink must be preserved');
        assertModulePublicSymlink(realpath($alias) === realpath($public), 'Alias must resolve to Public');

        $stale = $root.DIRECTORY_SEPARATOR.'stale-assets';
        mkdir($stale);
        unlink($alias);
        symlink($stale, $alias);

        $result = \App\Module::ensurePublicSymlink($alias, $public);
        assertModulePublicSymlink($result['success'], 'A mismatched alias symlink must be repaired');
        assertModulePublicSymlink(realpath($alias) === realpath($public), 'Repaired alias must resolve to Public');
        assertModulePublicSymlink(is_dir($stale), 'The stale target must remain intact');

        unlink($alias);
        mkdir($alias);
        file_put_contents($alias.DIRECTORY_SEPARATOR.'keep.txt', 'keep');
        $result = \App\Module::ensurePublicSymlink($alias, $public);
        assertModulePublicSymlink(!$result['success'], 'A real alias directory must fail');
        assertModulePublicSymlink(file_exists($alias.DIRECTORY_SEPARATOR.'keep.txt'), 'A real alias directory must not be changed');
        removeModulePublicSymlinkFixture($alias);

        $result = \App\Module::ensurePublicSymlink($alias, $public);
        assertModulePublicSymlink($result['success'] && is_link($alias), 'A missing alias must be created');
        unlink($alias);

        $missing = $root.DIRECTORY_SEPARATOR.'Missing'.DIRECTORY_SEPARATOR.'Public';
        mkdir(dirname($missing), 0755, true);
        $result = \App\Module::ensurePublicSymlink($alias, $missing);
        assertModulePublicSymlink(!$result['success'], 'A missing Public directory must fail');
        assertModulePublicSymlink(!file_exists($missing), 'A missing Public directory must not be created');

        $broken = $root.DIRECTORY_SEPARATOR.'Broken'.DIRECTORY_SEPARATOR.'Public';
        mkdir(dirname($broken), 0755, true);
        symlink('missing', $broken);
        $result = \App\Module::ensurePublicSymlink($alias, $broken);
        assertModulePublicSymlink(!$result['success'], 'A broken Public symlink must fail');
        assertModulePublicSymlink(is_link($broken), 'A broken Public symlink must be preserved');

        $self = $root.DIRECTORY_SEPARATOR.'Self'.DIRECTORY_SEPARATOR.'Public';
        mkdir(dirname($self), 0755, true);
        symlink('Public', $self);
        $result = \App\Module::ensurePublicSymlink($alias, $self);
        assertModulePublicSymlink(!$result['success'], 'A self-referencing Public symlink must fail');
        assertModulePublicSymlink(is_link($self), 'A self-referencing Public symlink must be preserved');

        echo "Module public symlink harness passed\n";
    } finally {
        removeModulePublicSymlinkFixture($root);
    }
}
