<?php

namespace Illuminate\Container {
    class Container
    {
    }
}

namespace Illuminate\Support {
    class ServiceProvider
    {
        protected $app;

        public function __construct($app)
        {
            $this->app = $app;
        }

        protected function loadTranslationsFrom($path, $namespace)
        {
        }
    }

    class Str
    {
        public static function studly($value)
        {
            return $value;
        }

        public static function snake($value)
        {
            return $value;
        }
    }
}

namespace Illuminate\Support\Traits {
    trait Macroable
    {
    }
}

namespace Nwidart\Modules {
    class Json
    {
        public $values = [];

        public function set($key, $value)
        {
            $this->values[$key] = $value;
        }
    }

    class Repository
    {
        public static $active_cache = [];
    }
}

namespace App {
    class Module
    {
        public static $modules;
        public static $active = [];
        public static $deactivated = [];

        public static function setActive($alias, $active)
        {
            self::$active[$alias] = $active;
        }

        public static function deactiveModule($alias)
        {
            self::$deactivated[] = $alias;
            \Artisan::call('freescout:clear-cache');
        }
    }

    class User
    {
        const ROLE_ADMIN = 1;
    }
}

namespace {
    function array_get($array, $key, $default = null)
    {
        return $default instanceof \Closure ? $default() : $default;
    }

    function config($key = null, $default = null)
    {
        return $default;
    }

    function app()
    {
        return HarnessRuntime::$application;
    }

    function __($message, $replacements = [])
    {
        return strtr($message, $replacements);
    }

    class Eventy
    {
        public static $filter;

        public static function addFilter($name, $filter)
        {
            self::$filter = $filter;
        }

        public static function filter($name, $exception, $module)
        {
            return call_user_func(self::$filter, $exception, $module);
        }
    }

    class Log
    {
        public static $messages = [];

        public static function error($message)
        {
            self::$messages[] = $message;
        }
    }

    class Session
    {
        public static $flashes = [];

        public static function flash($key, $value)
        {
            self::$flashes[$key] = $value;
        }
    }

    class Module
    {
        public static $cacheCleared = 0;

        public static function clearCache()
        {
            self::$cacheCleared++;
        }
    }

    class Artisan
    {
        public static $calls = 0;

        public static function call()
        {
            self::$calls++;
        }
    }

    class Helper
    {
        public static function isHttps()
        {
            return false;
        }
    }

    class Config
    {
        public static function get($key)
        {
            return $key === 'app.key' ? 'harness-key' : null;
        }
    }

    class HarnessRuntime
    {
        public static $application;
    }

    class HarnessApplication extends \Illuminate\Container\Container
    {
        public $console = true;

        public function runningInConsole()
        {
            return $this->console;
        }
    }

    class ModuleDescriptor
    {
        public $manifest;
        public $providers;

        public function __construct($providers)
        {
            $this->providers = $providers;
            $this->manifest = new \Nwidart\Modules\Json();
        }

        public function getName()
        {
            return 'Broken';
        }

        public function getAlias()
        {
            return 'broken';
        }

        public function get($key, $default = null)
        {
            return $key === 'providers' ? $this->providers : $default;
        }

        public function json()
        {
            return $this->manifest;
        }
    }

    function assertHarness($condition, $message)
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    HarnessRuntime::$application = new HarnessApplication();

    require dirname(__DIR__, 2).'/overrides/nwidart/laravel-modules/src/Module.php';

    class ProviderFailureModule extends \Nwidart\Modules\Module
    {
        public $providersCalled = 0;
        public $events = [];
        public $manifest;
        public $exception;
        public $providers = [];

        public function __construct($app)
        {
            parent::__construct($app, 'Broken', __DIR__);
            $this->manifest = new \Nwidart\Modules\Json();
        }

        public function get($key, $default = null)
        {
            if ($key === 'files') {
                return [];
            }

            if ($key === 'alias') {
                return 'broken';
            }

            return $key === 'providers' ? $this->providers : $default;
        }

        public function json($file = null) : \Nwidart\Modules\Json
        {
            return $this->manifest;
        }

        public function registerAliases()
        {
        }

        public function registerProviders()
        {
            $this->providersCalled++;
            throw $this->exception ?: new \Error('Class "Modules\\Broken\\Providers\\MissingProvider" not found');
        }

        public function getCachedServicesPath()
        {
            return '';
        }

        protected function fireEvent($event)
        {
            $this->events[] = $event;
        }
    }

    Eventy::$filter = function ($exception) {
        return null;
    };
    $failedModule = new ProviderFailureModule(HarnessRuntime::$application);
    $failedModule->register();
    $failedModule->boot();
    assertHarness($failedModule->providersCalled === 1, 'The provider boundary was not reached.');
    assertHarness($failedModule->events === [], 'Registration continued after the handled provider failure.');

    Eventy::$filter = function ($exception) {
        return $exception;
    };
    try {
        $failedModule = new ProviderFailureModule(HarnessRuntime::$application);
        $failedModule->register();
        throw new \RuntimeException('An unhandled registration failure was swallowed.');
    } catch (\Error $exception) {
        assertHarness($exception->getMessage() === 'Class "Modules\\Broken\\Providers\\MissingProvider" not found', 'The original registration error changed.');
    }

    require dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';

    $provider = new \App\Providers\AppServiceProvider(HarnessRuntime::$application);
    $provider->register();
    $descriptor = new ModuleDescriptor(['Modules\\Broken\\Providers\\MissingProvider']);
    \App\Module::$modules = ['warm' => true];
    \Nwidart\Modules\Repository::$active_cache = ['broken' => true];
    $_POST = ['action' => 'deactivate'];
    $missingProviderModule = new ProviderFailureModule(HarnessRuntime::$application);
    $missingProviderModule->providers = ['Modules\\Broken\\Providers\\MissingProvider'];
    $missingProviderModule->register();
    $missingProviderModule->boot();
    assertHarness($missingProviderModule->providersCalled === 0, 'The missing provider was not caught before provider registration.');
    assertHarness($missingProviderModule->events === [], 'Registration continued after the manifest provider preflight failed.');
    assertHarness(\App\Module::$active === ['broken' => false], 'The affected module was not deactivated.');
    assertHarness(\App\Module::$modules === null, 'The in-memory module records were not refreshed.');
    assertHarness(\Nwidart\Modules\Repository::$active_cache === [], 'The in-memory active-state cache was not cleared.');
    assertHarness(Module::$cacheCleared === 1, 'The persistent module cache was not evicted.');
    assertHarness($missingProviderModule->manifest->values['active'] === 0, 'The current module manifest remained active.');
    assertHarness(Artisan::$calls === 0, 'Bootstrap recovery invoked Artisan.');

    \App\Module::$deactivated = [];
    $_POST = ['action' => 'activate'];
    $activationError = new \Exception('Activation failed');
    assertHarness(call_user_func(Eventy::$filter, $activationError, $descriptor) === null, 'The legacy activation recovery did not handle an Exception.');
    assertHarness(\App\Module::$deactivated === ['broken'], 'The legacy activation recovery did not deactivate the affected module.');
    assertHarness(Artisan::$calls === 1, 'The legacy activation recovery did not clear the application cache.');

    $_POST = [];
    $missingFolderError = new \Exception('failed to open stream: No such file or directory');
    assertHarness(call_user_func(Eventy::$filter, $missingFolderError, $descriptor) === null, 'The legacy missing-folder recovery did not handle the exception.');
    assertHarness(\App\Module::$deactivated === ['broken', 'broken'], 'The legacy missing-folder recovery did not deactivate the affected module.');
    assertHarness(Artisan::$calls === 2, 'The legacy missing-folder recovery did not clear the application cache.');

    $_POST = ['action' => 'activate'];
    $typeError = new \TypeError('Provider received an invalid value');
    assertHarness(call_user_func(Eventy::$filter, $typeError, $descriptor) === $typeError, 'An unrelated TypeError was swallowed.');
    assertHarness(\App\Module::$deactivated === ['broken', 'broken'], 'An unrelated TypeError deactivated the module during activation.');
    $typeErrorModule = new ProviderFailureModule(HarnessRuntime::$application);
    $typeErrorModule->exception = $typeError;
    try {
        $typeErrorModule->register();
        throw new \RuntimeException('An unrelated TypeError did not leave module registration.');
    } catch (\TypeError $exception) {
        assertHarness($exception === $typeError, 'Module registration changed the unrelated TypeError.');
    }
    assertHarness($typeErrorModule->events === [], 'Module registration ran events after an unrelated TypeError.');
    $otherClassError = new \Error('Class "Modules\\Other\\Providers\\OtherProvider" not found');
    assertHarness(call_user_func(Eventy::$filter, $otherClassError, $descriptor) === $otherClassError, 'An undeclared missing class was treated as a module provider.');

    unset($_POST);
    fwrite(STDOUT, "Module provider recovery harness passed\n");
}
