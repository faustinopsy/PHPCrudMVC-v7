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

            $filteredColumns = array_filter($columns, fn($col) => $col['Field'] !== 'id');

            $repositoryName = $this->pascalCase($table) . 'Repository';
            $repositoryFileName = __DIR__ . "/../Repositories/$repositoryName.php";

            $repositoryContent = "<?php\n\n";
            $repositoryContent .= "namespace Fast\\Back\\Repositories;\n\n";
            $repositoryContent .= "use Fast\\Back\\Database\\Database;\n";
            $repositoryContent .= "use PDO;\n";
            $repositoryContent .= "use PDOException;\n\n";
            $repositoryContent .= "class $repositoryName {\n";
            $repositoryContent .= "    private \$pdo;\n\n";
            $repositoryContent .= "    public function __construct() {\n";
            $repositoryContent .= "        \$this->pdo = Database::getInstance();\n";
            $repositoryContent .= "    }\n\n";

            $repositoryContent .= $this->generateCreateMethod($table, $filteredColumns);
            $repositoryContent .= $this->generateFindByIdMethod($table);
            $repositoryContent .= $this->generateFindAllMethod($table);
            $repositoryContent .= $this->generateUpdateMethod($table, $filteredColumns);
            $repositoryContent .= $this->generateDeleteMethod($table);
            $repositoryContent .= $this->generateFindWithDetailsMethod($table);
            $repositoryContent .= $this->generateErrorResponseMethod();

            $repositoryContent .= "}\n";

            if (!is_dir(__DIR__ . '/../Repositories')) {
                mkdir(__DIR__ . '/../Repositories', 0777, true);
            }
            file_put_contents($repositoryFileName, $repositoryContent);

            echo "Repositório $repositoryName gerado com sucesso!\n";
        }
    }

    private function generateFindWithDetailsMethod(string $masterTable): string
    {
        $relationships = $this->detectRelationships($masterTable);
        $oneToManyRelations = array_filter($relationships, fn($rel) => $rel['master_table'] === $masterTable);

        if (empty($oneToManyRelations)) {
            return '';
        }

        $method = "    public function findWithDetails(int \$id): ?array\n    {\n";
        $method .= "        try {\n";
        $method .= "            \$master = \$this->findById(\$id);\n";
        $method .= "            if (!\$master) {\n";
        $method .= "                return null;\n";
        $method .= "            }\n\n";

        foreach ($oneToManyRelations as $relation) {
            $detailTable = $relation['detail_table'];
            $detailColumn = $relation['detail_column'];
            $detailKey = lcfirst($this->pascalCase($detailTable));

            $method .= "            \$detailQuery = \"SELECT * FROM `$detailTable` WHERE `$detailColumn` = :master_id\";\n";
            $method .= "            \$stmtDetail = \$this->pdo->prepare(\$detailQuery);\n";
            $method .= "            \$stmtDetail->bindValue(':master_id', \$id, PDO::PARAM_INT);\n";
            $method .= "            \$stmtDetail->execute();\n";
            $method .= "            \$master['$detailKey'] = \$stmtDetail->fetchAll(PDO::FETCH_ASSOC);\n\n";
        }

        $method .= "            return \$master;\n";
        $method .= "        } catch (PDOException \$e) {\n";
        $method .= "            return \$this->generateErrorResponse(\$e);\n";
        $method .= "        }\n";
        $method .= "    }\n\n";

        return $method;
    }

    private function generateCreateMethod($table, $columns): string
    {
        $fields = array_column($columns, 'Field');
        $placeholders = array_map(fn($col) => ":$col", $fields);
        $method = "    public function create(\$data): bool\n    {\n";
        $method .= "        \$query = \"INSERT INTO `$table` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")\";\n";
        $method .= "        try {\n";
        $method .= "            \$stmt = \$this->pdo->prepare(\$query);\n";
        foreach ($fields as $field) {
            $method .= "            \$stmt->bindValue(':$field', \$data['$field'] ?? null);\n";
        }
        $method .= "            return \$stmt->execute();\n";
        $method .= "        } catch (PDOException \$e) {\n";
        $method .= "            return \$this->generateErrorResponse(\$e);\n";
        $method .= "        }\n";
        $method .= "    }\n\n";
        return $method;
    }

    private function generateFindByIdMethod($table): string
    {
        return "    public function findById(\$id): ?array\n    {\n" .
               "        \$query = \"SELECT * FROM `$table` WHERE id = :id\";\n" .
               "        try {\n" .
               "            \$stmt = \$this->pdo->prepare(\$query);\n" .
               "            \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n" .
               "            \$stmt->execute();\n" .
               "            \$result = \$stmt->fetch(PDO::FETCH_ASSOC);\n" .
               "            return \$result ?: null;\n" .
               "        } catch (PDOException \$e) {\n" .
               "            return \$this->generateErrorResponse(\$e);\n" .
               "        }\n" .
               "    }\n\n";
    }

    private function generateFindAllMethod($table): string
    {
        return "    public function findAll(): array\n    {\n" .
               "        \$query = \"SELECT * FROM `$table`\";\n" .
               "        try {\n" .
               "            \$stmt = \$this->pdo->query(\$query);\n" .
               "            return \$stmt->fetchAll(PDO::FETCH_ASSOC);\n" .
               "        } catch (PDOException \$e) {\n" .
               "            return \$this->generateErrorResponse(\$e);\n" .
               "        }\n" .
               "    }\n\n";
    }

    private function generateUpdateMethod($table, $columns): string
    {
        $fields = array_column($columns, 'Field');
        $setClause = implode(', ', array_map(fn($col) => "`$col` = :$col", $fields));
        $method = "    public function update(\$id, \$data): bool\n    {\n";
        $method .= "        \$query = \"UPDATE `$table` SET $setClause WHERE id = :id\";\n";
        $method .= "        try {\n";
        $method .= "            \$stmt = \$this->pdo->prepare(\$query);\n";
        foreach ($fields as $field) {
            $method .= "            \$stmt->bindValue(':$field', \$data['$field'] ?? null);\n";
        }
        $method .= "            \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n";
        $method .= "            return \$stmt->execute();\n";
        $method .= "        } catch (PDOException \$e) {\n";
        $method .= "            return \$this->generateErrorResponse(\$e);\n";
        $method .= "        }\n" .
                   "    }\n\n";
        return $method;
    }

    private function generateDeleteMethod($table): string
    {
        return "    public function delete(\$id): bool\n    {\n" .
               "        \$query = \"DELETE FROM `$table` WHERE id = :id\";\n" .
               "        try {\n" .
               "            \$stmt = \$this->pdo->prepare(\$query);\n" .
               "            \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n" .
               "            return \$stmt->execute();\n" .
               "        } catch (PDOException \$e) {\n" .
               "            return \$this->generateErrorResponse(\$e);\n" .
               "        }\n" .
               "    }\n\n";
    }

    private function generateErrorResponseMethod(): string
    {
        return "    private function generateErrorResponse(PDOException \$e): array\n    {\n" .
               "        return [\n" .
               "            'success' => false,\n" .
               "            'message' => \$e->getMessage(),\n" .
               "            'code' => \$e->getCode(),\n" .
               "        ];\n" .
               "    }\n";
    }

    private function detectRelationships(string $table): array
    {
        $query = "
            SELECT 
                TABLE_NAME AS detail_table, 
                COLUMN_NAME AS detail_column, 
                REFERENCED_TABLE_NAME AS master_table
            FROM 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE() 
                AND (TABLE_NAME = :table_name OR REFERENCED_TABLE_NAME = :referenced_table_name)
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
        $stmt->bindValue(':referenced_table_name', $table, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}

(new CrieRepository())->generateRepositories();