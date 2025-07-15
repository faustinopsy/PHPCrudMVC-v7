<?php

namespace Fast\Back\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use DirectoryIterator;
use ReflectionClass;
use Fast\Back\Rotas\Router;

class ControllerGenerator
{
    private string $repositoryNamespace;
    private string $controllerNamespace;
    private string $modelNamespace;
    private string $rotaNamespace;
    private string $outputDir;
    private string $repositoryDir;

    public function __construct(
        string $repositoryNamespace = 'Fast\\Back\\Repositories',
        string $controllerNamespace = 'Fast\\Back\\Controllers',
        string $modelNamespace = 'Fast\\Back\\Models',
        string $rotaNamespace = 'Fast\\Back\\Rotas\\Router',
        string $outputDir = __DIR__ . '/../Controllers',
        string $repositoryDir = __DIR__ . '/../Repositories'
    ) {
        $this->repositoryNamespace = $repositoryNamespace;
        $this->controllerNamespace = $controllerNamespace;
        $this->modelNamespace = $modelNamespace;
        $this->rotaNamespace = $rotaNamespace;
        $this->outputDir = $outputDir;
        $this->repositoryDir = $repositoryDir;

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }

    public function generateControllers(): void
    {
        foreach (new DirectoryIterator($this->repositoryDir) as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->getExtension() !== 'php' || $fileInfo->getBasename() === 'BaseRepository.php') {
                continue;
            }

            $repositoryClassName = $this->repositoryNamespace . '\\' . $fileInfo->getBasename('.php');
            $repositoryShortName = $fileInfo->getBasename('.php');

            $controllerClassName = str_replace('Repository', 'Controller', $repositoryShortName);
            $modelClassName = str_replace('Repository', '', $repositoryShortName);

            $controllerFilePath = $this->outputDir . '/' . $controllerClassName . '.php';

            if (!class_exists($repositoryClassName)) {
                echo "Erro: Classe $repositoryClassName não encontrada. Verifique os namespaces.\n";
                continue;
            }

            $reflection = new ReflectionClass($repositoryClassName);
            $rotasBase = lcfirst($modelClassName);

            $methods = $this->generateMethods($reflection, $rotasBase);

            $content = "<?php\n\nnamespace {$this->controllerNamespace};\n\nuse {$this->repositoryNamespace}\\$repositoryShortName;\nuse {$this->rotaNamespace};\n\nclass $controllerClassName {\n    private \$repository;\n\n    public function __construct() {\n        \$this->repository = new $repositoryShortName();\n    }\n\n    private function jsonResponse(\$data, int \$statusCode = 200) {\n        header('Content-Type: application/json; charset=utf-8');\n        http_response_code(\$statusCode);\n        echo json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);\n    }\n\n";

            foreach ($methods as $methodCode) {
                $content .= "    $methodCode\n";
            }

            $content .= "}";

            file_put_contents($controllerFilePath, $content);
            echo "Controlador $controllerClassName gerado com sucesso!\n";
        }
    }

    private function generateMethods(ReflectionClass $reflection, string $rotasBase): array
    {
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $methodName = $method->getName();
                
                $httpMethod = 'POST';
                $route = "/$rotasBase";
                $parameters = '($data)';
                $callParameters = '$data';

                if (stripos($methodName, 'findAll') !== false) {
                    $httpMethod = 'GET';
                    $route = "/$rotasBase";
                    $parameters = '()';
                    $callParameters = '';
                } elseif (stripos($methodName, 'findWithDetails') !== false) {
                    $httpMethod = 'GET';
                    $route = "/$rotasBase/{id}/details";
                    $parameters = '($id)';
                    $callParameters = '$id';
                } elseif (stripos($methodName, 'findById') !== false) {
                    $httpMethod = 'GET';
                    $route = "/$rotasBase/{id}";
                    $parameters = '($id)';
                    $callParameters = '$id';
                } elseif (stripos($methodName, 'update') !== false) {
                    $httpMethod = 'PUT';
                    $route = "/$rotasBase/{id}";
                    $parameters = '($id, $data)';
                    $callParameters = '$id, $data';
                } elseif (stripos($methodName, 'delete') !== false) {
                    $httpMethod = 'DELETE';
                    $route = "/$rotasBase/{id}";
                    $parameters = '($id)';
                    $callParameters = '$id';
                } elseif (stripos($methodName, 'create') !== false) {
                    $httpMethod = 'POST';
                    $route = "/$rotasBase";
                    $parameters = '($data)';
                    $callParameters = '$data';
                }
                
                $methods[] = "#[Router('$route', methods: ['$httpMethod'])]\n    public function {$methodName}{$parameters} {\n        \$result = \$this->repository->{$methodName}($callParameters);\n        if (\$result === null) {\n            return \$this->jsonResponse(['error' => 'Recurso não encontrado.'], 404);\n        }\n        if (isset(\$result['success']) && !\$result['success']) {\n            return \$this->jsonResponse(['error' => \$result['message']], 400);\n        }\n        return \$this->jsonResponse(\$result, 200);\n    }";
            }
        }
        return $methods;
    }
}

(new ControllerGenerator())->generateControllers();