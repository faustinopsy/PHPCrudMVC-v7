<?php

namespace Fast\Back\View;

class ViewRenderer
{
    private string $viewPath;

    public function __construct(string $basePath = __DIR__ . '/../Views/')
    {
        $this->viewPath = rtrim($basePath, '/') . '/';
    }

    /**
     * Renderiza uma view.
     *
     * @param string $view O nome do arquivo da view (ex: 'user/show').
     * @param array $data Os dados a serem extraídos para a view.
     * @return string O conteúdo HTML renderizado.
     */
    public function render(string $view, array $data = []): string
    {
        $viewFile = $this->viewPath . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            return "Erro: View '{$viewFile}' não encontrada.";
        }

        // A "mágica" acontece aqui:
        // extract() transforma as chaves do array em variáveis (ex: $data['user'] vira $user).
        extract($data);

        // Inicia o buffer de saída para capturar o HTML.
        ob_start();

        include $viewFile;

        // Pega o conteúdo do buffer e o limpa.
        return ob_get_clean();
    }
}