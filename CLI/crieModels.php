<?php

namespace Fast\Back\CLI;
require_once __DIR__ .'/../vendor/autoload.php';
use Fast\Back\Database\Database;
use PDO;

class CrieModels
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function generateModels(): void
    {
        $query = $this->pdo->query("SHOW TABLES");
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $columnsQuery = $this->pdo->query("DESCRIBE $table");
            $columns = $columnsQuery->fetchAll(PDO::FETCH_ASSOC);

            $modelName = ucfirst($this->camelCase($table));
            $modelFileName = __DIR__ . "/../Models/$modelName.php";

            $modelContent = "<?php\n\n";
            $modelContent .= "namespace Fast\\Back\\Models;\n\n";
            $modelContent .= "class $modelName {\n";

            foreach ($columns as $column) {
                $modelContent .= "    public \${$column['Field']};\n";
            }

            $modelContent .= "\n    public function __construct(array \$data = []) {\n";
            foreach ($columns as $column) {
                $field = $column['Field'];
                $modelContent .= "        \$this->$field = \$data['$field'] ?? null;\n";
            }
            $modelContent .= "    }\n";

            foreach ($columns as $column) {
                $field = $column['Field'];
                $camelField = ucfirst($this->camelCase($field));
                $modelContent .= "\n    public function get$camelField() {\n";
                $modelContent .= "        return \$this->$field;\n";
                $modelContent .= "    }\n";
                $modelContent .= "\n    public function set$camelField(\$value): void {\n";
                $modelContent .= "        \$this->$field = \$value;\n";
                $modelContent .= "    }\n";
            }

            $modelContent .= "}\n";

            if (!is_dir(__DIR__ . '/../Models')) {
                mkdir(__DIR__ . '/../Models', 0777, true);
            }
            file_put_contents($modelFileName, $modelContent);

            echo "Model $modelName gerada com sucesso!\n";
        }
    }

    private function camelCase(string $string): string
    {
        $string = str_replace('_', ' ', strtolower($string));
        $string = ucwords($string);
        return str_replace(' ', '', lcfirst($string));
    }
}

$generator = new CrieModels();
$generator->generateModels();
