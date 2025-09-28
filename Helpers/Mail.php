<?php

namespace Fast\Back\Helpers;

// Configure com os dados do seu servidor SMTP.
// Para testes, recomendo usar o Mailtrap.io, é gratuito e excelente.
class Mail
{
    public static function get(): array
    {
        return [
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'username' => 'seu-usuario-mailtrap',
            'password' => 'sua-senha-mailtrap',
            'from_address' => 'nao-responda@meuprojeto.com',
            'from_name' => 'Meu Projeto'
        ];
    }
}