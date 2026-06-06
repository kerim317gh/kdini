<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;

class FtpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('ftp', function ($app, $config) {
            $adapter = new FtpAdapter([
                'host' => $config['host'],
                'username' => $config['username'],
                'password' => $config['password'],
                'port' => $config['port'],
                'root' => $config['root'],
                'ssl' => $config['ssl'],
                'timeout' => $config['timeout'],
            ]);

            return new Filesystem($adapter);
        });
    }
}
