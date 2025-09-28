<?php

namespace Fast\Back\Helpers;

class Flash
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Adiciona uma mensagem flash para a sessão.
     * @param string $key O tipo da mensagem (ex: 'success', 'error').
     * @param string $message A mensagem a ser exibida.
     */
    public function add(string $key, string $message): void
    {
        $_SESSION['flash_messages'][$key][] = $message;
    }

    /**
     * Verifica se existe uma mensagem de um determinado tipo.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION['flash_messages'][$key]);
    }

    /**
     * Pega todas as mensagens de um tipo, limpando-as da sessão.
     * @return array As mensagens, ou um array vazio.
     */
    public function get(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }

        $messages = $_SESSION['flash_messages'][$key];
        unset($_SESSION['flash_messages'][$key]); 

        return $messages;
    }
}