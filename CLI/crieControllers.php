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
            $methods = [
                'create' => "#[Router('/$rotas', methods: ['POST'])]\n    public function create(\$data) {\n        \$model = new $modelClassName(\$data);\n        \$result = \$this->repository->create(\$model);\n        return \$this->jsonResponse(\$result, 'Registro criado com sucesso.', 'Erro ao criar registro.', !\$result);\n    }\n",
                'findAll' => "#[Router('/$rotas', methods: ['GET'])]\n    public function findAll() {\n        \$result = \$this->repository->findAll();\n        return \$this->jsonResponse(\$result, 'Registros encontrados.', 'Nenhum registro encontrado.', empty(\$result));\n    }\n",
                'findById' => "#[Router('/$rotas/{id}', methods: ['GET'])]\n    public function findById(\$id) {\n        \$result = \$this->repository->findById(\$id);\n        return \$this->jsonResponse(\$result, 'Registro encontrado.', 'Registro não encontrado.', !\$result);\n    }\n",
                'update' => "#[Router('/$rotas/{id}', methods: ['PUT'])]\n    public function update(\$id, \$data) {\n        \$model = new $modelClassName(\$data);\n        \$model->setId(\$id);\n        \$result = \$this->repository->update(\$id,\$model);\n        return \$this->jsonResponse(\$result, 'Registro atualizado com sucesso.', 'Erro ao atualizar registro.', !\$result);\n    }\n",
                'delete' => "#[Router('/$rotas/{id}', methods: ['DELETE'])]\n    public function delete(\$id) {\n        \$result = \$this->repository->delete(\$id);\n        return \$this->jsonResponse(\$result, 'Registro deletado com sucesso.', 'Erro ao deletar registro.');\n    }\n",
            ];

            $content = "<?php\n\nnamespace {$this->controllerNamespace};\n\nuse {$this->repositoryNamespace}\\$repositoryShortName;\nuse {$this->modelNamespace}\\$modelClassName;\nuse {$this->rotaNamespace};\n\nclass $controllerClassName {\n    private \$repository;\n\n    public function __construct() {\n        \$this->repository = new $repositoryShortName();\n    }\n\n    private function jsonResponse(\$data, string \$successMessage, string \$errorMessage, bool \$isError = false) {\n        \$response = [\n            'status' => !\$isError,\n            'message' => \$isError ? \$errorMessage : \$successMessage,\n            'data' => \$data\n        ];\n        http_response_code(\$isError ? 400 : 200);\n        echo json_encode(\$response, JSON_PRETTY_PRINT);\n    }\n\n";

            foreach ($methods as $methodName => $methodCode) {
                if ($reflection->hasMethod($methodName)) {
                    $content .= "    $methodCode\n";
                }
            }

            $content .= "}";

            file_put_contents($controllerFilePath, $content);
            echo "Controlador $controllerClassName gerado com sucesso!\n";
        }
    }
}

$generator = new ControllerGenerator();
$generator->generateControllers();
