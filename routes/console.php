<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

if (class_exists(Artisan::class)) {
    Artisan::command('inspire', static function (): void {
        $this->comment('Ticketing Architecture Laravel Portfolio');
    })->purpose('Exibe uma mensagem padrão de diagnóstico');
}
