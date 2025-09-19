<?php

namespace Fast\Back\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use DirectoryIterator;
use ReflectionClass;
use Fast\Back\Database\Database;
use Fast\Back\Rotas\Router;
use Fast\Back\View\ViewRenderer;

class ControllerGenerator
{
    private string $repositoryNamespace;
    private string $controllerNamespace;
    private string $modelNamespace;
    private string $outputDir;
    private string $repositoryDir;
    private string $viewsDir;
    private $pdo;

    public function __construct(
        string $repositoryNamespace = 'Fast\\Back\\Repositories',
        string $controllerNamespace = 'Fast\\Back\\Controllers',
        string $modelNamespace = 'Fast\\Back\\Models',
        string $outputDir = __DIR__ . '/../Controllers',
        string $repositoryDir = __DIR__ . '/../Repositories',
        string $viewsDir = __DIR__ . '/../Views'
    ) {
        $this->repositoryNamespace = $repositoryNamespace;
        $this->controllerNamespace = $controllerNamespace;
        $this->modelNamespace = $modelNamespace;
        $this->outputDir = $outputDir;
        $this->repositoryDir = $repositoryDir;
        $this->viewsDir = $viewsDir;
        $this->pdo = Database::getInstance();
    
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    public function generateControllers(): void
    {
        $this->generateLayoutPartials();
        foreach (new DirectoryIterator($this->repositoryDir) as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->getExtension() !== 'php' || str_contains($fileInfo->getBasename(), 'Base')) {
                continue;
            }
    
            $repositoryClassName = $this->repositoryNamespace . '\\' . $fileInfo->getBasename('.php');
            $repositoryShortName = $fileInfo->getBasename('.php');
    
            $controllerClassName = str_replace('Repository', 'Controller', $repositoryShortName);
            $modelClassName = str_replace('Repository', '', $repositoryShortName);
    
            if (!class_exists($repositoryClassName)) {
                echo "Erro: Classe $repositoryClassName não encontrada. Verifique os namespaces e o autoload.\n";
                continue;
            }
    
            $reflection = new ReflectionClass($repositoryClassName);

            $rotasBase = str_replace('_', '-', $this->snakeCase($modelClassName));
    
            $content = $this->generateControllerContent($controllerClassName, $repositoryShortName, $modelClassName, $rotasBase, $reflection);
            
            file_put_contents($this->outputDir . '/' . $controllerClassName . '.php', $content);
            echo "Controlador $controllerClassName gerado com sucesso!\n";
    
            $this->generateViewFiles($rotasBase, $modelClassName, $reflection);
        }
    }

    private function generateControllerContent(string $controllerClassName, string $repositoryShortName, string $modelClassName, string $rotasBase, ReflectionClass $reflection): string
    {
        $methods = $this->generateMethods($reflection, $rotasBase, $modelClassName);

        $content = "<?php\n\nnamespace {$this->controllerNamespace};\n\n";
        $content .= "use {$this->repositoryNamespace}\\{$repositoryShortName};\n";
        $content .= "use {$this->modelNamespace}\\{$modelClassName};\n";
        $content .= "use Fast\\Back\\Rotas\\Router;\n";
        $content .= "use Fast\\Back\\View\\ViewRenderer;\n";
        $content .= "use PDOException;\n\n";
        $content .= "class {$controllerClassName} {\n";
        $content .= "    private \$repository;\n";
        $content .= "    private \$view;\n\n";
        $content .= "    public function __construct() {\n";
        $content .= "        \$this->repository = new {$repositoryShortName}();\n";
        $content .= "        \$this->view = new ViewRenderer();\n";
        $content .= "    }\n\n";
        $content .= "    // Métodos Helper para Respostas\n";
        $content .= "    private function jsonResponse(\$data, int \$statusCode = 200) {\n";
        $content .= "        header('Content-Type: application/json; charset=utf-8');\n";
        $content .= "        http_response_code(\$statusCode);\n";
        $content .= "        echo json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);\n";
        $content .= "    }\n\n";
        $content .= "    private function viewResponse(string \$view, array \$data = [], int \$statusCode = 200) {\n";
        $content .= "        header('Content-Type: text/html; charset=utf-8');\n";
        $content .= "        http_response_code(\$statusCode);\n";
        $content .= "        echo \$this->view->render(\$view, \$data);\n";
        $content .= "    }\n\n";
        $content .= "    private function redirect(string \$url) {\n";
        $content .= "        header(\"Location: {\$url}\");\n";
        $content .= "        exit();\n";
        $content .= "    }\n\n";
        $content .= "    private function wantsJson(): bool {\n";
        $content .= "        return isset(\$_SERVER['HTTP_ACCEPT']) && str_contains(\$_SERVER['HTTP_ACCEPT'], 'application/json');\n";
        $content .= "    }\n\n";
        $content .= implode("\n", $methods);
        $content .= "}\n";

        return $content;
    }

    private function generateMethods(ReflectionClass $reflection, string $rotasBase, string $modelClassName): array
    {
        $methodsCode = [];
        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $method) {
            if ($method->isConstructor()) continue;

            $methodName = $method->getName();
            $methodCode = "";

            switch ($methodName) {
                case 'findAll':
                    $methodCode = "    #[Router('/{$rotasBase}', methods: ['GET'])]\n" .
                                  "    public function index() {\n" .
                                  "        \$results = \$this->repository->findAll();\n" .
                                  "        if (\$this->wantsJson()) {\n" .
                                  "            \$data = array_map(fn(\$item) => \$item->toArray(), \$results);\n" .
                                  "            return \$this->jsonResponse(\$data);\n" .
                                  "        }\n" .
                                  "        return \$this->viewResponse('{$rotasBase}/index', ['items' => \$results]);\n" .
                                  "    }\n\n";
                    break;
                case 'findById':
                    $methodCode = "    #[Router('/{$rotasBase}/create', methods: ['GET'])]\n" .
                                  "    public function create() {\n" .
                                  "        return \$this->viewResponse('{$rotasBase}/create');\n" .
                                  "    }\n".
                                  "    #[Router('/{$rotasBase}/{id}', methods: ['GET'])]\n" .
                                  "    public function show(\$id) {\n" .
                                  "        try {\n" .
                                  "            \$result = \$this->repository->findById(\$id);\n" .
                                  "            if (\$result === null) {\n" .
                                  "                if (\$this->wantsJson()) return \$this->jsonResponse(['error' => 'Recurso não encontrado.'], 404);\n" .
                                  "                return \$this->viewResponse('partials/404', [], 404);\n" .
                                  "            }\n" .
                                  "            if (\$this->wantsJson()) return \$this->jsonResponse(\$result->toArray());\n" .
                                  "            return \$this->viewResponse('{$rotasBase}/show', ['item' => \$result]);\n" .
                                  "        } catch (PDOException \$e) {\n" .
                                  "            error_log(\$e->getMessage());\n" .
                                  "            if (\$this->wantsJson()) return \$this->jsonResponse(['error' => 'Erro interno do servidor.'], 500);\n" .
                                  "            return \$this->viewResponse('partials/500', [], 500);\n" .
                                  "        }\n" .
                                  "    }\n\n" .
                                  "    #[Router('/{$rotasBase}/{id}/edit', methods: ['GET'])]\n" .
                                  "    public function edit(\$id) {\n" .
                                  "        \$item = \$this->repository->findById(\$id);\n" .
                                  "        if (\$item === null) {\n" .
                                  "            return \$this->viewResponse('partials/404', [], 404);\n" .
                                  "        }\n" .
                                  "        return \$this->viewResponse('{$rotasBase}/edit', ['item' => \$item]);\n" .
                                  "    }\n";
                    break;
                case 'create':
                    $methodCode = "    #[Router('/{$rotasBase}', methods: ['POST'])]\n" .
                                  "    public function store() {\n" .
                                  "        \$data = \$_POST;\n" .
                                  "        \$model = new {$modelClassName}((object)\$data);\n" .
                                  "        \$newId = \$this->repository->create(\$model);\n" .
                                  "        if (\$this->wantsJson()) {\n" .
                                  "            return \$this->jsonResponse(['id' => \$newId, 'message' => 'Criado com sucesso.'], 201);\n" .
                                  "        }\n" .
                                  "        return \$this->redirect('/{$rotasBase}/' . \$newId);\n" .
                                  "    }\n";
                    break;
                case 'update':
                     $methodCode = "   #[Router('/{$rotasBase}/{id}', methods: ['POST'])] // HTML forms use POST\n" .
                                  "    public function update(\$id) {\n" .
                                  "        \$data = \$_POST;\n" .
                                  "        \$model = new {$modelClassName}((object)\$data);\n" .
                                  "        \$success = \$this->repository->update(\$id, \$model);\n" .
                                  "        if (\$this->wantsJson()) {\n" .
                                  "            // APIs de verdade usariam PUT/PATCH aqui\n" .
                                  "            return \$this->jsonResponse(['success' => \$success]);\n" .
                                  "        }\n" .
                                  "        return \$this->redirect('/{$rotasBase}/' . \$id);\n" .
                                  "    }\n";
                    break;
                case 'delete':
                    $methodCode = "    #[Router('/{$rotasBase}/{id}/delete', methods: ['POST'])] // HTML forms use POST\n" .
                                  "    public function destroy(\$id) {\n" .
                                  "        \$success = \$this->repository->delete(\$id);\n" .
                                  "        if (\$this->wantsJson()) {\n" .
                                  "            // APIs de verdade usariam DELETE aqui\n" .
                                  "            return \$this->jsonResponse(['success' => \$success]);\n" .
                                  "        }\n" .
                                  "        return \$this->redirect('/{$rotasBase}');\n" .
                                  "    }\n";
                    break;
            }
            if (!empty($methodCode)) {
                $methodsCode[] = $methodCode;
            }
        }
        return $methodsCode;
    }

    private function generateLayoutPartials(): void
    {
        $partialsDir = $this->viewsDir . '/partials';
        if (!is_dir($partialsDir)) {
            mkdir($partialsDir, 0755, true);
        }

        $headerPath = $partialsDir . '/header.php';
        if (!file_exists($headerPath)) {
            $css = "<style>body{font-family:sans-serif;display:flex;margin:0;}.sidebar{width:200px;background:#f0f0f0;padding:20px;height:100vh;border-right:1px solid #ddd;}.content{flex-grow:1;padding:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:8px;text-align:left;}th{background:#e9e9e9;}a{color:#007bff;text-decoration:none;}a:hover{text-decoration:underline;}.action-links a{margin-right:10px;}.btn{padding:5px 10px;border-radius:4px;color:white;display:inline-block;}.btn-primary{background:#007bff;}.btn-danger{background:#dc3545;}</style>";
            $headerContent = "<!DOCTYPE html>\n<html lang=\"pt-br\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title><?= \$title ?? 'Meu Projeto' ?></title>\n    {$css}\n</head>\n<body>\n";
            file_put_contents($headerPath, $headerContent);
        }

        $sidebarPath = $partialsDir . '/sidebar.php';
        if (!file_exists($sidebarPath)) {
            $query = $this->pdo->query("SHOW TABLES");
            $tables = $query->fetchAll(\PDO::FETCH_COLUMN);
            $sidebarContent = "<div class=\"sidebar\">\n    <h2>Menu</h2>\n    <ul>\n";
            foreach ($tables as $table) {
                $resourceName = str_replace('_', '-', $table);
                $linkText = ucwords(str_replace('_', ' ', $table));
                $sidebarContent .= "        <li><a href=\"/{$resourceName}\">{$linkText}</a></li>\n";
            }
            $sidebarContent .= "    </ul>\n</div>\n<div class=\"content\">\n";
            file_put_contents($sidebarPath, $sidebarContent);
        }

        $footerPath = $partialsDir . '/footer.php';
        if (!file_exists($footerPath)) {
            file_put_contents($footerPath, "</div>\n</body>\n</html>");
        }
    }

    private function generateViewFiles(string $resourceName, string $modelClassName, ReflectionClass $reflection): void
    {
        $resourceDir = $this->viewsDir . '/' . $resourceName;
        if (!is_dir($resourceDir)) {
            mkdir($resourceDir, 0755, true);
        }
        
        $table = $this->snakeCase($modelClassName);
        $columnsQuery = $this->pdo->query("DESCRIBE `{$table}`");
        $columns = $columnsQuery->fetchAll(\PDO::FETCH_ASSOC);
        $publicMethods = array_map(fn($m) => $m->getName(), $reflection->getMethods(\ReflectionMethod::IS_PUBLIC));

        if (in_array('findAll', $publicMethods)) {
            $path = $resourceDir . '/index.php';
            if (!file_exists($path)) {
                $content = "<?php \$title = 'Lista de {$modelClassName}s'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>\n\n";
                $content .= "<h1><?= \$title ?></h1>\n";
                $content .= "<p><a href=\"/{$resourceName}/create\" class=\"btn btn-primary\">Criar Novo</a></p>\n\n";
                $content .= "<table>\n    <thead>\n        <tr>\n";
                foreach ($columns as $column) {
                    $content .= "            <th>" . ucwords(str_replace('_', ' ', $column['Field'])) . "</th>\n";
                }
                $content .= "            <th>Ações</th>\n        </tr>\n    </thead>\n    <tbody>\n";
                $content .= "        <?php foreach(\$items as \$item): ?>\n        <tr>\n";
                foreach ($columns as $column) {
                    $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $column['Field'])));
                    $content .= "            <td><?= htmlspecialchars(\$item->{$getter}() ?? '') ?></td>\n";
                }
                $content .= "            <td class=\"action-links\">\n";
                $content .= "                <a href=\"/{$resourceName}/<?= \$item->getId() ?>\">Ver</a>\n";
                $content .= "                <a href=\"/{$resourceName}/<?= \$item->getId() ?>/edit\">Editar</a>\n";
                $content .= "            </td>\n        </tr>\n        <?php endforeach; ?>\n    </tbody>\n</table>\n\n";
                $content .= "<?php include __DIR__ . '/../partials/footer.php'; ?>";
                file_put_contents($path, $content);
                echo "View gerada: {$path}\n";
            }
        }
        
        if (in_array('findById', $publicMethods)) {
            $path = $resourceDir . '/show.php';
             if (!file_exists($path)) {
                $content = "<?php \$title = 'Detalhes de {$modelClassName}'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>\n\n";
                $content .= "<h1><?= \$title ?></h1>\n";
                foreach ($columns as $column) {
                    $label = ucwords(str_replace('_', ' ', $column['Field']));
                    $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $column['Field'])));
                    $content .= "<p><strong>{$label}:</strong> <?= htmlspecialchars(\$item->{$getter}() ?? '') ?></p>\n";
                }
                $content .= "<br>\n<a href=\"/{$resourceName}/<?= \$item->getId() ?>/edit\">Editar</a> | <a href=\"/{$resourceName}\">Voltar para a Lista</a>\n\n";
                $content .= "<?php include __DIR__ . '/../partials/footer.php'; ?>";
                file_put_contents($path, $content);
                echo "View gerada: {$path}\n";
            }
        }
        
        if (in_array('create', $publicMethods)) {
            $path = $resourceDir . '/create.php';
            if (!file_exists($path)) {
                $content = "<?php \$title = 'Criar Novo {$modelClassName}'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>\n\n";
                $content .= "<h1><?= \$title ?></h1>\n\n";
                $content .= "<form action=\"/{$resourceName}\" method=\"POST\">\n";
                foreach ($columns as $column) {
                    if ($column['Key'] === 'PRI') continue;
                    $label = ucwords(str_replace('_', ' ', $column['Field']));
                    $content .= "    <div style=\"margin-bottom:15px;\">\n";
                    $content .= "        <label for=\"{$column['Field']}\">{$label}</label><br>\n";
                    $content .= "        <input type=\"text\" id=\"{$column['Field']}\" name=\"{$column['Field']}\" style=\"width:300px;padding:5px;\">\n";
                    $content .= "    </div>\n";
                }
                $content .= "    <button type=\"submit\" class=\"btn btn-primary\">Salvar</button>\n";
                $content .= "</form>\n\n";
                $content .= "<?php include __DIR__ . '/../partials/footer.php'; ?>";
                file_put_contents($path, $content);
                echo "View gerada: {$path}\n";
            }
        }
        
        if (in_array('update', $publicMethods)) {
            $path = $resourceDir . '/edit.php';
            if (!file_exists($path)) {
                $content = "<?php \$title = 'Editar {$modelClassName}'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>\n\n";
                $content .= "<h1><?= \$title ?> #<?= \$item->getId() ?></h1>\n\n";
                $content .= "<form action=\"/{$resourceName}/<?= \$item->getId() ?>\" method=\"POST\">\n";
                foreach ($columns as $column) {
                    if ($column['Key'] === 'PRI') continue;
                    $label = ucwords(str_replace('_', ' ', $column['Field']));
                    $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $column['Field'])));
                    $content .= "    <div style=\"margin-bottom:15px;\">\n";
                    $content .= "        <label for=\"{$column['Field']}\">{$label}</label><br>\n";
                    $content .= "        <input type=\"text\" id=\"{$column['Field']}\" name=\"{$column['Field']}\" value=\"<?= htmlspecialchars(\$item->{$getter}() ?? '') ?>\" style=\"width:300px;padding:5px;\">\n";
                    $content .= "    </div>\n";
                }
                $content .= "    <button type=\"submit\" class=\"btn btn-primary\">Atualizar</button>\n";
                $content .= "</form>\n\n<hr style=\"margin:20px 0;\">\n\n";
                
                $content .= "<h2>Deletar Registro</h2>\n";
                $content .= "<form action=\"/{$resourceName}/<?= \$item->getId() ?>/delete\" method=\"POST\" onsubmit=\"return confirm('Tem certeza que deseja deletar este item?');\">\n";
                $content .= "    <button type=\"submit\" class=\"btn btn-danger\">Deletar</button>\n";
                $content .= "</form>\n\n";
                
                $content .= "<?php include __DIR__ . '/../partials/footer.php'; ?>";
                file_put_contents($path, $content);
                echo "View gerada: {$path}\n";
            }
        }
    }

    private function snakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }
}

(new ControllerGenerator())->generateControllers();