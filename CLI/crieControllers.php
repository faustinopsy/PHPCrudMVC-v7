<?php

namespace Fast\Back\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use DirectoryIterator;
use ReflectionClass;
use Fast\Back\Database\Database;
use Fast\Back\Rotas\Router;
use Fast\Back\Helpers\ViewRenderer;

class ControllerGenerator
{
    private string $repositoryNamespace;
    private string $controllerNamespace;
    private string $modelNamespace;
    private string $outputDir;
    private string $repositoryDir;
    private string $viewsDir;
    private string $validatorNamespace;
    private string $validatorDir;
    private $pdo;

    public function __construct(
        string $repositoryNamespace = 'Fast\\Back\\Repositories',
        string $controllerNamespace = 'Fast\\Back\\Controllers',
        string $modelNamespace = 'Fast\\Back\\Models',
        string $outputDir = __DIR__ . '/../Controllers',
        string $repositoryDir = __DIR__ . '/../Repositories',
        string $validatorNamespace = 'Fast\\Back\\Validators',
        string $validatorDir = __DIR__ . '/../Validators',
        string $viewsDir = __DIR__ . '/../Views'
    ) {
        $this->repositoryNamespace = $repositoryNamespace;
        $this->controllerNamespace = $controllerNamespace;
        $this->modelNamespace = $modelNamespace;
        $this->outputDir = $outputDir;
        $this->repositoryDir = $repositoryDir;
        $this->validatorNamespace = $validatorNamespace;
        $this->validatorDir = $validatorDir;
        $this->viewsDir = $viewsDir;
        $this->pdo = Database::getInstance();
    
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        if (!is_dir($this->validatorDir)) mkdir($this->validatorDir, 0755, true); 
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
            $this->generateValidator($modelClassName);
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
         $authMiddlewarePath = __DIR__ . '/../Middleware/AuthMiddleware.php';
        $authExists = file_exists($authMiddlewarePath);

        $authUseStatement = $authExists ? "use Fast\\Back\\Middleware\\AuthMiddleware;\n" : "";
        $authTrait = $authExists ? "    use AuthMiddleware;\n\n" : "";
        $userControllerName = $this->pascalCase($modelClassName) . 'Controller';
        $handleCall = "self::handle();";
        if ($controllerClassName === $userControllerName) {
            $handleCall = "self::handle(['admin']); // Apenas admins podem gerenciar usuários";
        }
        $authHandleCall = $authExists ? "        {$handleCall}\n" : "";

        $methods = $this->generateMethods($reflection, $rotasBase, $modelClassName);
        $validatorClassName = $modelClassName . 'Validator';
        $content = "<?php\n\nnamespace {$this->controllerNamespace};\n\n";
        $content .= "use {$this->repositoryNamespace}\\{$repositoryShortName};\n";
        $content .= "use {$this->modelNamespace}\\{$modelClassName};\n";
        $content .= "use {$this->validatorNamespace}\\{$validatorClassName};\n";
        $content .= "use Fast\\Back\\Middleware\\AuthMiddleware;\n";
        $content .= "use Fast\\Back\\Helpers\\Validator;\n";
        $content .= "use Fast\\Back\\Helpers\\Flash;\n";
        $content .= "use Fast\\Back\\Rotas\\Router;\n";
        $content .= "use Fast\\Back\\Helpers\\ViewRenderer;\n";
        $content .= "use PDOException;\n\n";
        $content .= "class {$controllerClassName} {\n";
        $content .= "    private \$repository;\n";
        $content .= "    private \$view;\n\n";
        $content .= "    private \$validator;\n\n";
        $content .=      $authTrait;
        $content .= "    public function __construct() {\n";
        $content .=          $authHandleCall;
        $content .= "        \$this->repository = new {$repositoryShortName}();\n";
        $content .= "        \$this->view = new ViewRenderer();\n";
        $content .= "        \$this->validator = new {$validatorClassName}();\n";
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
                case 'paginate':
                    $methodCode = "#[Router('/{$rotasBase}', methods: ['GET'])]\n" .
                                  "    public function index() {\n" .
                                  "        // 1. Pega os parâmetros da URL com valores padrão\n" .
                                  "        \$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);\n" .
                                  "        \$perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT, ['options' => ['default' => 15]]);\n\n" .
                                  "        // 2. Chama o método de paginação do repositório\n" .
                                  "        \$pagination = \$this->repository->paginate(\$page, \$perPage);\n\n" .
                                  "        // 3. Retorna JSON ou renderiza a View\n" .
                                  "        if (\$this->wantsJson()) {\n" .
                                  "            return \$this->jsonResponse(\$pagination);\n" .
                                  "        }\n" .
                                  "        return \$this->viewResponse('{$rotasBase}/index', ['pagination' => \$pagination]);\n" .
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
                                  "        if (!\$this->validator->validate(\$data)) {\n" .
                                  "            (new Flash())->add('error', 'Por favor, corrija os erros no formulário.');\n" .
                                  "            if (\$this->wantsJson()) {\n" .
                                  "                return \$this->jsonResponse(['errors' => \$this->validator->getErrors()], 400);\n" .
                                  "            }\n" .
                                  "            // Em uma app real, você passaria os erros e dados antigos de volta para a view\n" .
                                  "            return \$this->viewResponse('{$rotasBase}/create', ['errors' => \$this->validator->getErrors(), 'old' => \$data]);\n" .
                                  "        }\n" .
                                  "        \$sanitizedData = \$this->validator->getSanitizedData();\n" .
                                  "        \$model = new {$modelClassName}((object)\$data);\n" .
                                  "        \$newId = \$this->repository->create(\$model);\n" .
                                  "        (new Flash())->add('success', 'Registro criado com sucesso!');\n" . 
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
                                  "        if (!\$this->validator->validate(\$data)) {\n" .
                                  "            (new Flash())->add('error', 'Por favor, corrija os erros no formulário.');\n" .
                                  "            if (\$this->wantsJson()) {\n" .
                                  "                return \$this->jsonResponse(['errors' => \$this->validator->getErrors()], 400);\n" .
                                  "            }\n" .
                                  "            \$item = \$this->repository->findById(\$id);\n" .
                                  "            return \$this->viewResponse('{$rotasBase}/edit', ['errors' => \$this->validator->getErrors(), 'item' => \$item]);\n" .
                                  "        }\n" .
                                  "        \$sanitizedData = \$this->validator->getSanitizedData();\n" .
                                  "        \$model = new {$modelClassName}((object)\$data);\n" .
                                  "        \$success = \$this->repository->update(\$id, \$model);\n" .
                                  "        (new Flash())->add('success', 'Registro atualizado com sucesso!');\n" .
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
                                  "        (new Flash())->add('success', 'Registro deletado com sucesso!');\n" . 
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
        $messagesBlock = "<?php\n" .
                             "use Fast\\Back\\Helpers\\Flash;\n" .
                             "\$flash = new Flash();\n" .
                             "if (\$flash->has('success')): ?>\n" .
                             "    <div class=\"flash-message flash-success\">\n" .
                             "        <?php foreach(\$flash->get('success') as \$message): ?>\n" .
                             "            <p><?= \$message ?></p>\n" .
                             "        <?php endforeach; ?>\n" .
                             "    </div>\n" .
                             "<?php endif; ?>\n" .
                             "<?php if (\$flash->has('error')): ?>\n" .
                             "    <div class=\"flash-message flash-error\">\n" .
                             "        <?php foreach(\$flash->get('error') as \$message): ?>\n" .
                             "            <p><?= \$message ?></p>\n" .
                             "        <?php endforeach; ?>\n" .
                             "    </div>\n" .
                             "<?php endif; ?>\n";

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
            file_put_contents($footerPath, $messagesBlock."</div>\n</body>\n</html>");
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

        $primaryKeyField = 'id';
        foreach ($columns as $column) {
            if ($column['Key'] === 'PRI') {
                $primaryKeyField = $column['Field'];
                break;
            }
        }
        $primaryKeyGetter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $primaryKeyField)));


        $publicMethods = array_map(fn($m) => $m->getName(), $reflection->getMethods(\ReflectionMethod::IS_PUBLIC));

         if (in_array('paginate', $publicMethods)) { 
            $path = $resourceDir . '/index.php';
            if (!file_exists($path)) {
                $tableContent = "<table>\n    <thead>\n        <tr>\n";
                foreach ($columns as $column) {
                    $tableContent .= "            <th>" . ucwords(str_replace('_', ' ', $column['Field'])) . "</th>\n";
                }
                $tableContent .= "            <th>Ações</th>\n        </tr>\n    </thead>\n    <tbody>\n";
                $tableContent .= "        <?php foreach(\$pagination['data'] as \$item): ?>\n        <tr>\n";
                foreach ($columns as $column) {
                    $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $column['Field'])));
                    $tableContent .= "            <td><?= htmlspecialchars(\$item->{$getter}() ?? '') ?></td>\n";
                }
                $tableContent .= "            <td class=\"action-links\">\n";
                $tableContent .= "                <a href=\"/{$resourceName}/<?= \$item->{$primaryKeyGetter}() ?>\">Ver</a>\n";
                $tableContent .= "                <a href=\"/{$resourceName}/<?= \$item->{$primaryKeyGetter}() ?>/edit\">Editar</a>\n";
                $tableContent .= "            </td>\n        </tr>\n        <?php endforeach; ?>\n    </tbody>\n</table>\n\n";

                $paginationControls = "<div class=\"pagination-controls\" style=\"display:flex; justify-content:space-between; align-items:center; margin-top:20px;\">\n";
                $paginationControls .= "    <div>Mostrando de <?= \$pagination['from'] ?> a <?= \$pagination['to'] ?> de <?= \$pagination['total'] ?> resultados.</div>\n";
                $paginationControls .= "    <div class=\"page-selector\" style=\"display:flex; align-items:center;\">\n";
                $paginationControls .= "        <form action=\"/{$resourceName}\" method=\"GET\" style=\"margin-right:20px;\">\n";
                $paginationControls .= "            <select name=\"per_page\" onchange=\"this.form.submit()\">\n";
                $paginationControls .= "                <option value=\"15\" <?= (\$pagination['per_page'] == 15 ? 'selected' : '') ?>>15 por página</option>\n";
                $paginationControls .= "                <option value=\"25\" <?= (\$pagination['per_page'] == 25 ? 'selected' : '') ?>>25 por página</option>\n";
                $paginationControls .= "                <option value=\"50\" <?= (\$pagination['per_page'] == 50 ? 'selected' : '') ?>>50 por página</option>\n";
                $paginationControls .= "                <option value=\"100\" <?= (\$pagination['per_page'] == 100 ? 'selected' : '') ?>>100 por página</option>\n";
                $paginationControls .= "            </select>\n";
                $paginationControls .= "        </form>\n";
                $paginationControls .= "        <div class=\"page-nav\">\n";
                $paginationControls .= "            <?php if (\$pagination['current_page'] > 1): ?>\n";
                $paginationControls .= "                <a href=\"/{$resourceName}?page=<?= \$pagination['current_page'] - 1 ?>&per_page=<?= \$pagination['per_page'] ?>\">Anterior</a>\n";
                $paginationControls .= "            <?php endif; ?>\n";
                $paginationControls .= "            <span style=\"margin:0 10px;\">Página <?= \$pagination['current_page'] ?> de <?= \$pagination['last_page'] ?></span>\n";
                $paginationControls .= "            <?php if (\$pagination['current_page'] < \$pagination['last_page']): ?>\n";
                $paginationControls .= "                <a href=\"/{$resourceName}?page=<?= \$pagination['current_page'] + 1 ?>&per_page=<?= \$pagination['per_page'] ?>\">Próximo</a>\n";
                $paginationControls .= "            <?php endif; ?>\n";
                $paginationControls .= "        </div>\n";
                $paginationControls .= "    </div>\n</div>\n";

                $content = "<?php \$title = 'Lista de {$modelClassName}s'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>\n\n";
                $content .= "<h1><?= \$title ?></h1>\n";
                $content .= "<p><a href=\"/{$resourceName}/create\" class=\"btn btn-primary\">Criar Novo</a></p>\n\n";
                $content .= "<?php if (empty(\$pagination['data'])): ?>\n";
                $content .= "    <p>Nenhum registro encontrado.</p>\n";
                $content .= "<?php else: ?>\n";
                $content .= $tableContent;
                $content .= $paginationControls;
                $content .= "<?php endif; ?>\n\n";
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
                $content .= "<br>\n<a href=\"/{$resourceName}/<?= \$item->{$primaryKeyGetter}() ?>/edit\">Editar</a> | <a href=\"/{$resourceName}\">Voltar para a Lista</a>\n\n";
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
                $content .= "<h1><?= \$title ?> #<?= \$item->{$primaryKeyGetter}() ?></h1>\n\n";
                $content .= "<form action=\"/{$resourceName}/<?= \$item->{$primaryKeyGetter}() ?>\" method=\"POST\">\n";
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
                $content .= "<form action=\"/{$resourceName}/<?= \$item->{$primaryKeyGetter}() ?>/delete\" method=\"POST\" onsubmit=\"return confirm('Tem certeza que deseja deletar este item?');\">\n";
                $content .= "    <button type=\"submit\" class=\"btn btn-danger\">Deletar</button>\n";
                $content .= "</form>\n\n";
                
                $content .= "<?php include __DIR__ . '/../partials/footer.php'; ?>";
                file_put_contents($path, $content);
                echo "View gerada: {$path}\n";
            }
        }
    }

    private function generateValidator(string $modelClassName): void
    {
        $validatorClassName = $modelClassName . 'Validator';
        $path = $this->validatorDir . '/' . $validatorClassName . '.php';

        if (file_exists($path)) {
            return;
        }

        $table = $this->snakeCase($modelClassName);
        $columnsQuery = $this->pdo->query("DESCRIBE `{$table}`");
        $columns = $columnsQuery->fetchAll(\PDO::FETCH_ASSOC);

        $rulesContent = "protected array \$rules = [\n";
        foreach ($columns as $column) {
            $field = $column['Field'];
            // Ignora campos que não devem ser validados
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            $fieldRules = [];
            // Regra: obrigatório (se não for nulo no DB)
            if ($column['Null'] === 'NO' && $column['Default'] === null) {
                $fieldRules[] = "'required'";
            }
            // Regra: email (se o nome do campo for 'email')
            if ($field === 'email') {
                 $fieldRules[] = "'email'";
            }
            // Regra: numérico (se o tipo do campo for int, float, etc.)
            if (str_contains(strtolower($column['Type']), 'int') || str_contains(strtolower($column['Type']), 'decimal')) {
                $fieldRules[] = "'numeric'";
            }

            if (!empty($fieldRules)) {
                $rulesContent .= "        '{$field}' => [" . implode(', ', $fieldRules) . "],\n";
            }
        }
        $rulesContent .= "    ];";

        $content = "<?php\n\nnamespace {$this->validatorNamespace};\n\n";
        $content .= "use Fast\\Back\\Helpers\\Validator;\n\n";
        $content .= "class {$validatorClassName} extends Validator\n{\n";
        $content .= "    {$rulesContent}\n";
        $content .= "}\n";

        file_put_contents($path, $content);
        echo "Validator {$validatorClassName} gerado com sucesso!\n";
    }

    private function snakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    public function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}

(new ControllerGenerator())->generateControllers();