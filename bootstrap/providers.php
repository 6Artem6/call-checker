<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,

    // Dusk только если пакет установлен (в dev/CI)
    class_exists(\Laravel\Dusk\DuskServiceProvider::class) ? \Laravel\Dusk\DuskServiceProvider::class : null,
];
