<?php

namespace Fast\Back\CLI;
require_once __DIR__ .'/../vendor/autoload.php';
use Fast\Back\Database\Database;
use PDO;

class CrieRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function generateRepositories(): void
    {
        $query = $this->pdo->query("SHOW TABLES");
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $columnsQuery = $this->pdo->query("DESCRIBE $table");
            $columns = $columnsQuery->fetchAll(PDO::FETCH_ASSOC);

            $repositoryName = ucfirst($this->camelCase($table)) . 'Repository';
            $repositoryFileName = __DIR__ . "/../Repositories/$repositoryName.php";

            $repositoryContent = "<?php\n\n";
            $repositoryContent .= "namespace Fast\\Back\\Repositories;\n\n";
            $repositoryContent .= "use Fast\\Back\\Database\\Database;\n";
            $repositoryContent .= "use PDO;\n\n";
            $repositoryContent .= "class $repositoryName {\n";
            $repositoryContent .= "    private PDO \$pdo;\n\n";
            $repositoryContent .= "    public function __construct() {\n";
            $repositoryContent .= "        \$this->pdo = Database::getInstance();\n";
            $repositoryContent .= "    }\n";

            $columnsList = implode(', ', array_column($columns, 'Field'));
            $placeholders = ':' . implode(', :', array_column($columns, 'Field'));
            $repositoryContent .= "\n    public function create(array \$data): bool {\n";
            $repositoryContent .= "        \$query = \"INSERT INTO $table ($columnsList) VALUES ($placeholders)\";\n";
            $repositoryContent .= "        \$stmt = \$this->pdo->prepare(\$query);\n";
            $repositoryContent .= "        return \$stmt->execute(\$data);\n";
            $repositoryContent .= "    }\n";

            $repositoryContent .= "\n    public function findById(int \$id): ?array {\n";
            $repositoryContent .= "        \$query = \"SELECT * FROM $table WHERE id = :id\";\n";
            $repositoryContent .= "        \$stmt = \$this->pdo->prepare(\$query);\n";
            $repositoryContent .= "        \$stmt->execute(['id' => \$id]);\n";
            $repositoryContent .= "        return \$stmt->fetch(PDO::FETCH_ASSOC) ?: null;\n";
            $repositoryContent .= "    }\n";

            $repositoryContent .= "\n    public function findAll(): array {\n";
            $repositoryContent .= "        \$query = \"SELECT * FROM $table\";\n";
            $repositoryContent .= "        \$stmt = \$this->pdo->query(\$query);\n";
            $repositoryContent .= "        return \$stmt->fetchAll(PDO::FETCH_ASSOC);\n";
            $repositoryContent .= "    }\n";

            $setClause = implode(', ', array_map(fn($col) => "$col = :$col", array_column($columns, 'Field')));
            $repositoryContent .= "\n    public function update(int \$id, array \$data): bool {\n";
            $repositoryContent .= "        \$query = \"UPDATE $table SET $setClause WHERE id = :id\";\n";
            $repositoryContent .= "        \$stmt = \$this->pdo->prepare(\$query);\n";
            $repositoryContent .= "        \$data['id'] = \$id;\n";
            $repositoryContent .= "        return \$stmt->execute(\$data);\n";
            $repositoryContent .= "    }\n";

            $repositoryContent .= "\n    public function delete(int \$id): bool {\n";
            $repositoryContent .= "        \$query = \"DELETE FROM $table WHERE id = :id\";\n";
            $repositoryContent .= "        \$stmt = \$this->pdo->prepare(\$query);\n";
            $repositoryContent .= "        return \$stmt->execute(['id' => \$id]);\n";
            $repositoryContent .= "    }\n";

            $repositoryContent .= "}\n";

            if (!is_dir(__DIR__ . '/../Repositories')) {
                mkdir(__DIR__ . '/../Repositories', 0777, true);
            }
            file_put_contents($repositoryFileName, $repositoryContent);

            echo "Repositório $repositoryName gerado com sucesso!\n";
        }
    }

    private function camelCase(string $string): string
    {
        $string = str_replace('_', ' ', strtolower($string));
        $string = ucwords($string);
        return str_replace(' ', '', lcfirst($string));
    }
}

$generator = new CrieRepository();
$generator->generateRepositories();
