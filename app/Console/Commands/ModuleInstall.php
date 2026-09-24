<?php
/**
 * php artisan freescout:module-install modulealias.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ModuleInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'freescout:module-install {module_alias?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install module or all modules (if module_alias is empty): run migrations and create a symlink';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $install_all = false;
        $modules = [];
        $failed = false;

        // We have to clear modules cache first to update modules cache
        $this->call('cache:clear');

        // Create a symlink for the module (or all modules)
        $module_alias = $this->argument('module_alias');
        if (!$module_alias) {
            $modules = \Module::all();

            $modules_aliases = [];
            foreach ($modules as $module) {
                $modules_aliases[] = $module->name;
            }
            if (!$modules_aliases) {
                $this->error('No modules found');

                return 1;
            }
            $install_all = $this->confirm('You have not specified a module alias, would you like to install all available modules ('.implode(', ', $modules_aliases).')?');
            if (!$install_all) {
                return 0;
            }
        }

        if ($install_all) {
            foreach ($modules as $module) {
                $this->line('Module: '.$module->getName());
                if ($this->call('module:migrate', ['module' => $module->getName()]) !== 0) {
                    $this->error('Module migration failed: '.$module->getName());
                    $failed = true;
                    continue;
                }
                if (!$this->createModulePublicSymlink($module)) {
                    $failed = true;
                }
            }
        } else {
            $module = \Module::findByAlias($module_alias);
            if (!$module) {
                $this->error('Module with the specified alias not found: '.$module_alias);

                return 1;
            }
            if ($this->call('module:migrate', ['module' => $module->getName(), '--force' => true]) !== 0) {
                $this->error('Module migration failed: '.$module->getName());
                $failed = true;
            } elseif (!$this->createModulePublicSymlink($module)) {
                $failed = true;
            }
        }
        $this->line('Clearing cache...');
        if ($this->call('freescout:clear-cache') !== 0) {
            $failed = true;
        }

        return $failed ? 1 : 0;
    }

    // There is similar function in \App\Module.
    public function createModulePublicSymlink($module)
    {
        $result = \App\Module::ensureModulePublicSymlink($module->getAlias());
        if (!$result['success']) {
            $this->error('Error occurred creating ['.$result['from'].' » '.$result['to'].'] symlink: '.$result['message']);

            return false;
        }

        $this->info($result['message']);

        return true;
    }
}
