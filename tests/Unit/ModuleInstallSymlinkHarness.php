<?php

namespace Illuminate\Database\Eloquent {
    class Model
    {
    }
}

namespace Illuminate\Console {
    class Command
    {
        public $arguments = [];
        public $calls = [];
        public $errors = [];

        public function __construct()
        {
        }

        public function argument($name)
        {
            return isset($this->arguments[$name]) ? $this->arguments[$name] : null;
        }

        public function call($command, array $arguments = [])
        {
            $this->calls[] = $command;

            return 0;
        }

        public function error($message)
        {
            $this->errors[] = $message;
        }

        public function info($message)
        {
        }

        public function line($message)
        {
        }

        public function confirm($question)
        {
            return false;
        }
    }
}

namespace {
    class Module
    {
        public static $module;

        public static function all()
        {
            return self::$module ? [self::$module] : [];
        }

        public static function findByAlias($alias)
        {
            return self::$module && self::$module->getAlias() === $alias ? self::$module : null;
        }

        public static function getPublicPath($alias)
        {
            return '/modules/'.$alias;
        }
    }

    function public_path($path = '')
    {
        $root = $GLOBALS['module_install_public_path'];

        return $path ? $root.DIRECTORY_SEPARATOR.$path : $root;
    }

    function assertModuleInstall($condition, $message)
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function removeModuleInstallFixture($path)
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
                removeModuleInstallFixture($path.DIRECTORY_SEPARATOR.$item);
            }
        }
        rmdir($path);
    }

    class ModuleInstallDescriptor
    {
        private $path;

        public function __construct($path)
        {
            $this->path = $path;
        }

        public function getName()
        {
            return 'ReplyRabbit';
        }

        public function getAlias()
        {
            return 'replyrabbit';
        }

        public function getExtraPath($path)
        {
            return $this->path.DIRECTORY_SEPARATOR.$path;
        }
    }

    require dirname(__DIR__, 2).'/app/Module.php';
    require dirname(__DIR__, 2).'/app/Console/Commands/ModuleInstall.php';

    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'freescout-module-install-'.uniqid('', true);

    try {
        $GLOBALS['module_install_public_path'] = $root.DIRECTORY_SEPARATOR.'public';
        mkdir(public_path('modules'), 0755, true);
        $module_path = $root.DIRECTORY_SEPARATOR.'ReplyRabbit';
        $public = $module_path.DIRECTORY_SEPARATOR.'Public';
        mkdir($public, 0755, true);
        file_put_contents($public.DIRECTORY_SEPARATOR.'asset.txt', 'asset');
        \Module::$module = new ModuleInstallDescriptor($module_path);

        $command = new \App\Console\Commands\ModuleInstall();
        $command->arguments = ['module_alias' => 'replyrabbit'];
        $status = $command->handle();
        $alias = public_path('modules').DIRECTORY_SEPARATOR.'replyrabbit';

        assertModuleInstall($status === 0, 'Module install must return zero for a valid Public directory');
        assertModuleInstall(is_link($alias), 'Module install must create the public alias');
        assertModuleInstall(realpath($alias) === realpath($public), 'Created alias must resolve to Public');

        unlink($alias);
        mkdir($alias);
        file_put_contents($alias.DIRECTORY_SEPARATOR.'keep.txt', 'keep');

        $command = new \App\Console\Commands\ModuleInstall();
        $command->arguments = ['module_alias' => 'replyrabbit'];
        $status = $command->handle();

        assertModuleInstall($status === 1, 'Module install must return nonzero when the alias cannot be repaired');
        assertModuleInstall(file_exists($alias.DIRECTORY_SEPARATOR.'keep.txt'), 'Failed repair must preserve a real alias directory');
        assertModuleInstall(count($command->errors) === 1, 'Failed repair must report an installer error');
        assertModuleInstall(in_array('freescout:clear-cache', $command->calls, true), 'Failed repair must still clear cache');

        echo "Module install symlink harness passed\n";
    } finally {
        removeModuleInstallFixture($root);
    }
}
