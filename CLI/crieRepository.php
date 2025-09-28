<?php

namespace Fast\Back\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use Fast\Back\Database\Database;
use PDO;
use PDOException;

class CrieRepository
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function generateRepositories()
    {
        $query = $this->pdo->query("SHOW TABLES");
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $columnsQuery = $this->pdo->query("DESCRIBE `$table`");
            $columns = $columnsQuery->fetchAll(PDO::FETCH_ASSOC);

            $primaryKey = 'id';
            foreach ($columns as $column) {
                if ($column['Key'] === 'PRI') {
                    $primaryKey = $column['Field'];
                    break;
                }
            }
            
            $filteredColumns = array_filter($columns, fn($col) => $col['Key'] !== 'PRI');
            $modelName = $this->pascalCase($table);
            $repositoryName = $modelName . 'Repository';
            $repositoryFileName = __DIR__ . "/../Repositories/$repositoryName.php";

            $repositoryContent = "<?php\n\n";
            $repositoryContent .= "namespace Fast\\Back\\Repositories;\n\n";
            $repositoryContent .= "use Fast\\Back\\Models\\{$modelName};\n";
            $repositoryContent .= "use Fast\\Back\\Database\\Database;\n";
            $repositoryContent .= "use PDO;\n";
            $repositoryContent .= "use PDOException;\n\n";
            $repositoryContent .= "class $repositoryName {\n";
            $repositoryContent .= "    private \$pdo;\n\n";
            $repositoryContent .= "    public function __construct() {\n";
            $repositoryContent .= "        \$this->pdo = Database::getInstance();\n";
            $repositoryContent .= "    }\n\n";

            $repositoryContent .= $this->generateFindByIdMethod($table, $modelName, $primaryKey);
            $repositoryContent .= $this->generateFindAllMethod($table, $modelName);
            $repositoryContent .= $this->generatePaginateMethod($table, $modelName);
            $columnNames = array_column($columns, 'Field');
            $emailColumn = current(array_intersect($columnNames, ['email', 'user_email', 'email_usuario']));    
            $repositoryContent .= $this->generateFindByEmailMethod($table, $modelName, $emailColumn);
            $repositoryContent .= $this->generateCreateMethod($table, $modelName, $filteredColumns);
            $repositoryContent .= $this->generateUpdateMethod($table, $modelName, $filteredColumns, $primaryKey);
            $repositoryContent .= $this->generateDeleteMethod($table, $primaryKey);

            $repositoryContent .= "}\n";

            if (!is_dir(__DIR__ . '/../Repositories')) {
                mkdir(__DIR__ . '/../Repositories', 0755, true);
            }
            file_put_contents($repositoryFileName, $repositoryContent);

            echo "Repositório $repositoryName gerado com sucesso!\n";
        }
    }

    private function generateFindByEmailMethod(string $table, string $modelName, string $emailColumn): string
    {
        return "    /**\n     * Encontra um registro pelo campo de e-mail.\n" .
            "     * @param string \$email\n     * @return {$modelName}|null\n     */\n" .
            "    public function findByEmail(string \$email): ?{$modelName}\n    {\n" .
            "        \$query = \"SELECT * FROM `{$table}` WHERE `{$emailColumn}` = :email\";\n" .
            "        \$stmt = \$this->pdo->prepare(\$query);\n" .
            "        \$stmt->bindValue(':email', \$email, PDO::PARAM_STR);\n" .
            "        \$stmt->execute();\n" .
            "        \$data = \$stmt->fetch(PDO::FETCH_OBJ);\n\n" .
            "        if (!\$data) {\n" .
            "            return null;\n" .
            "        }\n\n" .
            "        return new {$modelName}(\$data);\n" .
            "    }\n\n";
    }

    private function generateFindByIdMethod(string $table, string $modelName, string $primaryKey): string
    {
        return "    /**\n     * @param int \$id\n     * @return {$modelName}|null\n     */\n" .
            "    public function findById(int \$id): ?{$modelName}\n    {\n" .
            "        \$query = \"SELECT * FROM `{$table}` WHERE `{$primaryKey}` = :id\";\n" .
            "        \$stmt = \$this->pdo->prepare(\$query);\n" .
            "        \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n" .
            "        \$stmt->execute();\n" .
            "        \$data = \$stmt->fetch(PDO::FETCH_OBJ);\n\n" .
            "        if (!\$data) {\n" .
            "            return null;\n" .
            "        }\n\n" .
            "        return new {$modelName}(\$data);\n" .
            "    }\n\n";
    }

    private function generateFindAllMethod(string $table, string $modelName): string
    {
        return "    /**\n     * @return {$modelName}[]\n     */\n" .
            "    public function findAll(): array\n    {\n" .
            "        \$query = \"SELECT * FROM `{$table}`\";\n" .
            "        \$stmt = \$this->pdo->query(\$query);\n" .
            "        \$results = \$stmt->fetchAll(PDO::FETCH_OBJ);\n\n" .
            "        return array_map(fn(\$row) => new {$modelName}(\$row), \$results);\n" .
            "    }\n\n";
    }

    private function generatePaginateMethod(string $table, string $modelName): string
    {
        return "    /**\n" .
            "     * Busca registros de forma paginada.\n" .
            "     * @param int \$page O número da página atual.\n" .
            "     * @param int \$perPage O número de itens por página.\n" .
            "     * @return array Um array estruturado com os dados da paginação.\n" .
            "     */\n" .
            "    public function paginate(int \$page = 1, int \$perPage = 15): array\n    {\n" .
            "        // 1. Obter o total de registros\n" .
            "        \$totalQuery = \"SELECT COUNT(*) FROM `{$table}`\";\n" .
            "        \$totalStmt = \$this->pdo->query(\$totalQuery);\n" .
            "        \$total = \$totalStmt->fetchColumn();\n\n" .
            "        // 2. Calcular o offset\n" .
            "        \$offset = (\$page - 1) * \$perPage;\n\n" .
            "        // 3. Obter os registros da página atual\n" .
            "        \$dataQuery = \"SELECT * FROM `{$table}` LIMIT :limit OFFSET :offset\";\n" .
            "        \$dataStmt = \$this->pdo->prepare(\$dataQuery);\n" .
            "        \$dataStmt->bindValue(':limit', \$perPage, PDO::PARAM_INT);\n" .
            "        \$dataStmt->bindValue(':offset', \$offset, PDO::PARAM_INT);\n" .
            "        \$dataStmt->execute();\n" .
            "        \$results = \$dataStmt->fetchAll(PDO::FETCH_OBJ);\n\n" .
            "        // 4. Hidratar os resultados\n" .
            "        \$data = array_map(fn(\$row) => new {$modelName}(\$row), \$results);\n\n" .
            "        // 5. Montar o array de retorno\n" .
            "        \$lastPage = ceil(\$total / \$perPage);\n\n" .
            "        return [\n" .
            "            'data' => \$data,\n" .
            "            'total' => (int) \$total,\n" .
            "            'per_page' => (int) \$perPage,\n" .
            "            'current_page' => (int) \$page,\n" .
            "            'last_page' => (int) \$lastPage,\n" .
            "            'from' => \$offset + 1,\n" .
            "            'to' => \$offset + count(\$data)\n" .
            "        ];\n" .
            "    }\n\n";
    }

    private function generateCreateMethod(string $table, string $modelName, array $columns): string
    {
        $fields = array_column($columns, 'Field');
        $placeholders = array_map(fn($col) => ":$col", $fields);
        $method = "    public function create({$modelName} \$model): int|false\n    {\n";
        $method .= "        \$query = \"INSERT INTO `{$table}` (" . implode(', ', array_map(fn($f) => "`$f`", $fields)) . ") VALUES (" . implode(', ', $placeholders) . ")\";\n";
        $method .= "        \$stmt = \$this->pdo->prepare(\$query);\n\n";
        foreach ($fields as $field) {
            $pascalField = $this->pascalCase($field);
            $method .= "        \$stmt->bindValue(':$field', \$model->get{$pascalField}());\n";
        }
        $method .= "\n        \$success = \$stmt->execute();\n";
        $method .= "        return \$success ? (int)\$this->pdo->lastInsertId() : false;\n";
        $method .= "    }\n\n";
        return $method;
    }

    private function generateUpdateMethod(string $table, string $modelName, array $columns, string $primaryKey): string
    {
        $fields = array_column($columns, 'Field');
        $setClause = implode(', ', array_map(fn($col) => "`$col` = :$col", $fields));
        $method = "    public function update(int \$id, {$modelName} \$model): bool\n    {\n";
        $method .= "        \$query = \"UPDATE `{$table}` SET {$setClause} WHERE `{$primaryKey}` = :id\";\n";
        $method .= "        \$stmt = \$this->pdo->prepare(\$query);\n\n";
        foreach ($fields as $field) {
            $pascalField = $this->pascalCase($field);
            $method .= "        \$stmt->bindValue(':$field', \$model->get{$pascalField}());\n";
        }
        $method .= "        \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n\n";
        $method .= "        return \$stmt->execute();\n";
        $method .= "    }\n\n";
        return $method;
    }

    private function generateDeleteMethod(string $table, string $primaryKey): string
    {
        return "    public function delete(int \$id): bool\n    {\n" .
            "        \$query = \"DELETE FROM `{$table}` WHERE `{$primaryKey}` = :id\";\n" .
            "        \$stmt = \$this->pdo->prepare(\$query);\n" .
            "        \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n" .
            "        return \$stmt->execute();\n" .
            "    }\n\n";
    }

    public function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}

(new CrieRepository())->generateRepositories();