<?php

use App\Models\CapstoneExpert;
use App\Models\Student;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'students'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'students',
        ],
        'expert' => [
            'driver' => 'session',
            'provider' => 'experts',
        ],
    ],

    'providers' => [
        'students' => [
            'driver' => 'eloquent',
            'model' => Student::class,
        ],
        'experts' => [
            'driver' => 'eloquent',
            'model' => CapstoneExpert::class,
        ],
    ],

    'passwords' => [
        'students' => [
            'provider' => 'students',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
