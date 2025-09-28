<?php

namespace Fast\Back\Middleware;

use Fast\Back\Services\AuthService;
use Fast\Back\Helpers\Flash;

trait AuthMiddleware
{
    protected static function handle(array $roles = []): void
    {
        if (!AuthService::check()) {
            (new Flash())->add('error', 'Você precisa estar logado para acessar esta página.');
            header('Location: /login');
            exit();
        }

        if (!empty($roles) && !AuthService::hasRole($roles)) {
            (new Flash())->add('error', 'Você não tem permissão para acessar esta página.');
            header('Location: /'); 
            exit();
        }
    }
}