<?php

return [
    'guard' => 'web', 'passwords' => 'users',
    'username' => 'email', 'email' => 'email', 'lowercase_usernames' => true,
    'home' => '/dashboard', 'prefix' => '', 'domain' => null,
    'middleware' => ['web'], 'auth_middleware' => 'auth',
    'views' => true, 'limiters' => ['login' => 'login'],
    'redirects' => ['logout' => '/login'],
    'features' => [],
];
