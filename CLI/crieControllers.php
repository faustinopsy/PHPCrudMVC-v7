<?php

namespace Fast\Back\CLI;

require_once __DIR__ .'/../vendor/autoload.php';

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
            if ($fileInfo->isDot() || $fileInfo->getExtension() !== 'php') {
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
            $rotas = lcfirst($modelClassName);

            $methods = $this->generateMethods($reflection, $modelClassName, $rotas);

            $content = "<?php\n\nnamespace {$this->controllerNamespace};\n\nuse {$this->repositoryNamespace}\\$repositoryShortName;\nuse {$this->modelNamespace}\\$modelClassName;\nuse {$this->rotaNamespace};\n\nclass $controllerClassName {\n    private \$repository;\n\n    public function __construct() {\n        \$this->repository = new $repositoryShortName();\n            }\n\n    private function jsonResponse(\$data, string \$successMessage, string \$errorMessage, bool \$isError = false) {\n        \$response = [\n            'status' => \$isError,\n            'message' => \$isError ? \$successMessage : \$errorMessage ,\n            'data' => \$data\n        ];\n        header('Content-Type: application/json; charset=utf-8');\n        http_response_code(\$isError ? 200 : 400);\n        echo json_encode(\$response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);\n    }\n\n";

            foreach ($methods as $methodCode) {
                $content .= "    $methodCode\n";
            }

            $content .= "}";

            file_put_contents($controllerFilePath, $content);
            echo "Controlador $controllerClassName gerado com sucesso!\n";
        }
    }

    private function generateMethods(ReflectionClass $reflection, string $modelClassName, string $rotas): array
    {
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !in_array($method->getName(), ['__construct'])) {
                $methodName = $method->getName();
                $httpMethod = 'POST';
                $route = "/$rotas";
                $parameters = "\$data";

                if (stripos($methodName, 'findAll') !== false) {
                    $httpMethod = 'GET';
                    $route = "/$rotas";
                    $parameters = '';
                } elseif (stripos($methodName, 'findById') !== false) {
                    $httpMethod = 'GET';
                    $route = "/$rotas/{id}";
                    $parameters = "\$id";
                }elseif (stripos($methodName, 'saveMasterDetail') !== false) {
                    $httpMethod = 'POST';
                    $route = "/$rotas/savemasterdetail";
                    $parameters = "\$data";
                } elseif (stripos($methodName, 'atualizaDetail') !== false) {
                    $httpMethod = 'PUT';
                    $route = "/$rotas/{id}/updatedetail";
                    $parameters = "\$id";
                } elseif (stripos($methodName, 'excluiDetail') !== false) {
                    $httpMethod = 'DELETE';
                    $route = "/$rotas/{id}/deletedetail";
                    $parameters = "\$id";
                } elseif (stripos($methodName, 'update') !== false) {
                    $httpMethod = 'PUT';
                    $route = "/$rotas/{id}";
                    $parameters = "\$id, \$data";
                } elseif (stripos($methodName, 'delete') !== false) {
                    $httpMethod = 'DELETE';
                    $route = "/$rotas/{id}";
                    $parameters = "\$id";
                }

                $methods[] = "#[Router('$route', methods: ['$httpMethod'])]\n    public function {$methodName}($parameters) {\n        \$result = \$this->repository->{$methodName}($parameters);\n        if (!is_array(\$result) && !\$result['success']) {\n            return \$this->jsonResponse(null, '', \$result['message'], \$result['success']);\n        }\n        return \$this->jsonResponse(\$result, 'Operação realizada com sucesso.', '', true);\n    }";
            }
        }
        return $methods;
    }
}

$generator = new ControllerGenerator();
$generator->generateControllers();
