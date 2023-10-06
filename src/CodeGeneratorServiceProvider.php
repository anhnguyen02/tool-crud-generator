<?php

namespace Anhnguyen02\CodeGenerator;

use Illuminate\Support\ServiceProvider;

class CodeGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            'Anhnguyen02\CodeGenerator\Commands\ModelMakeCommand',
            'Anhnguyen02\CodeGenerator\Commands\MigrateMakeCommand',
            'Anhnguyen02\CodeGenerator\Commands\ControllerMakeCommand',
            'Anhnguyen02\CodeGenerator\Commands\ApiMakeCommand'
        ]);
    }
}
