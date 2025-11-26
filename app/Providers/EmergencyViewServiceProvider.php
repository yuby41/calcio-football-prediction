<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewServiceProvider as BaseViewServiceProvider;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Compilers\BladeCompiler;

class EmergencyViewServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Override the Blade compiler to handle permission errors gracefully
        $this->app->singleton("blade.compiler", function ($app) {
            return new class($app["files"], defined("EMERGENCY_VIEWS_PATH") ? EMERGENCY_VIEWS_PATH : $app["config"]["view.compiled"]) extends BladeCompiler {
                protected function ensureViewDirectoryExists($path)
                {
                    $directory = dirname($path);
                    if (!is_dir($directory)) {
                        @mkdir($directory, 0777, true);
                        @chmod($directory, 0777);
                    }
                    return $directory;
                }
                
                public function compile($path = null)
                {
                    if ($path) {
                        $this->ensureViewDirectoryExists($this->getCompiledPath($path));
                    }
                    
                    try {
                        return parent::compile($path);
                    } catch (\Exception $e) {
                        // If compilation fails, try with emergency path
                        if (defined("EMERGENCY_VIEWS_PATH")) {
                            $emergencyPath = EMERGENCY_VIEWS_PATH . "/" . basename($this->getCompiledPath($path));
                            $this->setCompilerPath($emergencyPath);
                            return parent::compile($path);
                        }
                        throw $e;
                    }
                }
            };
        });
    }
}