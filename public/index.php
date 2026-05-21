<?php

use App\Kernel;

ini_set('max_execution_time', 120); // 👈 i-move sa TAAS

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};