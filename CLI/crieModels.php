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
            $modelName = $this->pascalCase($table);
            $modelFileName = __DIR__ . "/../Models/$modelName.php";

            $modelContent = "<?php\n\n";
            $modelContent .= "namespace Fast\\Back\\Models;\n\n";
            $modelContent .= "class $modelName {\n";

            foreach ($columns as $column) {
                $modelContent .= "    private \${$column['Field']};\n";
            }
            $modelContent .= "\n";

            $modelContent .= "    public function __construct(?object \$data = null)\n    {\n";
            foreach ($columns as $column) {
                $field = $column['Field'];
                $modelContent .= "        \$this->$field = \$data->$field ?? null;\n";
            }
            $modelContent .= "    }\n\n";

            foreach ($columns as $column) {
                $field = $column['Field'];
                $pascalField = $this->pascalCase($field);
                $modelContent .= "    public function get$pascalField()\n    {\n";
                $modelContent .= "        return \$this->{$field};\n";
                $modelContent .= "    }\n\n";
                $modelContent .= "    public function set$pascalField(\$value): void\n    {\n";
                $modelContent .= "        \$this->{$field} = \$value;\n";
                $modelContent .= "    }\n\n";
            }
            
            $modelContent .= "    public function toArray(): array\n    {\n";
            $modelContent .= "        \$data = [];\n";
            foreach ($columns as $column) {
                $field = $column['Field'];
                $pascalField = $this->pascalCase($field);
                $modelContent .= "        \$data['{$field}'] = \$this->get{$pascalField}();\n";
            }
            $modelContent .= "        return \$data;\n";
            $modelContent .= "    }\n";
            $modelContent .= "}\n";

            if (!is_dir(__DIR__ . '/../Models')) {
                mkdir(__DIR__ . '/../Models', 0755, true);
            }
            file_put_contents($modelFileName, $modelContent);

            echo "Model $modelName gerada com sucesso!\n";
        }
    }

    private function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}

$generator = new CrieModels();
$generator->generateModels();