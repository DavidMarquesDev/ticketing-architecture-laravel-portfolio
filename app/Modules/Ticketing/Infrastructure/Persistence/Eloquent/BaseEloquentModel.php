<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Eloquent;

if (class_exists('\Illuminate\Database\Eloquent\Model')) {
    class_alias('\Illuminate\Database\Eloquent\Model', __NAMESPACE__ . '\FrameworkModel');
} else {
    class FrameworkModel
    {
        public static function query(): object
        {
            throw new \RuntimeException('Eloquent não está disponível neste ambiente.');
        }

        public function getAttribute(string $key): mixed
        {
            return null;
        }
    }
}

abstract class BaseEloquentModel extends FrameworkModel
{
}
