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
            $columnsQuery = $this->pdo->query("DESCRIBE $table");
            $columns = $columnsQuery->fetchAll(PDO::FETCH_ASSOC);

            $filteredColumns = array_filter($columns, fn($col) => $col['Field'] !== 'id');

            $repositoryName = ucfirst($this->camelCase($table)) . 'Repository';
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
            $repositoryContent .= $this->generateErrorResponse();
            $repositoryContent .= $this->generateRelationshipMethods($table);

            $repositoryContent .= "}\n";

            if (!is_dir(__DIR__ . '/../Repositories')) {
                mkdir(__DIR__ . '/../Repositories', 0777, true);
            }
            file_put_contents($repositoryFileName, $repositoryContent);

            echo "Repositório $repositoryName gerado com sucesso!\n";
        }
    }

    private function generateCreateMethod($table, $columns)
    {
        $fields = array_column($columns, 'Field');
        $placeholders = array_map(fn($col) => ":$col", $fields);

        $method = "    public function create(\$data) {\n";
        $method .= "        \$query = \"INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")\";\n";
        $method .= "        try {\n";
        $method .= "            \$stmt = \$this->pdo->prepare(\$query);\n";

        foreach ($fields as $field) {
            $method .= "            \$stmt->bindValue(':$field', \$data->$field);\n";
        }

        $method .= "            return \$stmt->execute();\n";
        $method .= "        } catch (PDOException \$e) {\n";
        $method .= "            return \$this->generateErrorResponse(\$e);\n";
        $method .= "        }\n";
        $method .= "    }\n\n";

        return $method;
    }

    private function generateFindByIdMethod($table)
    {
        return "    public function findById(\$id) {\n" .
               "        \$query = \"SELECT * FROM $table WHERE id = :id\";\n" .
               "        try {\n" .
               "            \$stmt = \$this->pdo->prepare(\$query);\n" .
               "            \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n" .
               "            \$stmt->execute();\n" .
               "            return \$stmt->fetch(PDO::FETCH_ASSOC) ?: null;\n" .
               "        } catch (PDOException \$e) {\n" .
               "            return \$this->generateErrorResponse(\$e);\n" .
               "        }\n" .
               "    }\n\n";
    }

    private function generateFindAllMethod($table)
    {
        return "    public function findAll() {\n" .
               "        \$query = \"SELECT * FROM $table\";\n" .
               "        try {\n" .
               "            \$stmt = \$this->pdo->query(\$query);\n" .
               "            return \$stmt->fetchAll(PDO::FETCH_ASSOC);\n" .
               "        } catch (PDOException \$e) {\n" .
               "            return \$this->generateErrorResponse(\$e);\n" .
               "        }\n" .
               "    }\n\n";
    }

    private function generateUpdateMethod($table, $columns)
    {
        $fields = array_column($columns, 'Field');
        $setClause = implode(', ', array_map(fn($col) => "$col = :$col", $fields));

        $method = "    public function update(\$id, \$data) {\n";
        $method .= "        \$query = \"UPDATE $table SET $setClause WHERE id = :id\";\n";
        $method .= "        try {\n";
        $method .= "            \$stmt = \$this->pdo->prepare(\$query);\n";

        foreach ($fields as $field) {
            $method .= "            \$stmt->bindValue(':$field', \$data->$field);\n";
        }
        $method .= "            \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n";
        $method .= "            return \$stmt->execute();\n";
        $method .= "        } catch (PDOException \$e) {\n";
        $method .= "           return \$this->generateErrorResponse(\$e);\n";
        $method .= "        }\n";
        $method .= "    }\n\n";

        return $method;
    }

    private function generateDeleteMethod($table)
    {
        return "    public function delete(\$id) {\n" .
               "        \$query = \"DELETE FROM $table WHERE id = :id\";\n" .
               "        try {\n" .
               "            \$stmt = \$this->pdo->prepare(\$query);\n" .
               "            \$stmt->bindValue(':id', \$id, PDO::PARAM_INT);\n" .
               "            return \$stmt->execute();\n" .
               "        } catch (PDOException \$e) {\n" .
               "            return \$this->generateErrorResponse(\$e);\n" .
               "        }\n" .
               "    }\n\n";
    }
    private function generateErrorResponse()
    {
        return "    private function generateErrorResponse(\$e)\n" .
               "     {\n" .
               "         return [\n" .
               "             'success' => false,\n" .
               "             'message' => \$e->getMessage(),\n" .
               "             'code' => \$e->getCode(),\n" .
               "         ];\n" .
               "     }\n" .
               "  \n";
    }

    private function detectRelationships($table)
    {
        $query = "
            SELECT 
                TABLE_NAME AS detail_table, 
                COLUMN_NAME AS detail_column, 
                REFERENCED_TABLE_NAME AS master_table, 
                REFERENCED_COLUMN_NAME AS master_column
            FROM 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE() 
                AND (TABLE_NAME = :table OR REFERENCED_TABLE_NAME = :table)
                AND REFERENCED_TABLE_NAME IS NOT NULL;
            ;
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':table', $table, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTableColumns($table)
    {
        $query = "DESCRIBE $table";
        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateMasterDetailMethods($masterTable, $detailTable, $relation)
    {

        $masterColumns = $this->getTableColumns($masterTable);
        $detailColumns = $this->getTableColumns($detailTable);
    
        $masterColumns = array_filter($masterColumns, fn($col) => !in_array($col['Field'], ['id', 'criado_em', 'atualizado_em', 'deletado_em','deletado','aprovado_por']));
        $masterFields = array_filter($masterColumns, fn($col) => $col['Field'] !== 'id');
        $detailFields = array_filter($detailColumns, fn($col) => $col['Field'] !== 'id');
    
        $masterSetClause = implode(', ', array_map(fn($col) => "{$col['Field']} = :{$col['Field']}", $masterFields));
        $detailInsertColumns = implode(', ', array_column($detailFields, 'Field'));
        $detailInsertPlaceholders = implode(', ', array_map(fn($col) => ":{$col['Field']}", $detailFields));
    
        $methods = "";
    
        $methods .= "    public function saveMasterDetail(\$masterData, \$detailsData) {\n";
        $methods .= "        \$this->pdo->beginTransaction();\n";
        $methods .= "        try {\n";
    
        $methods .= "            if (isset(\$masterData->id) && !empty(\$masterData->id)) {\n";
        $methods .= "                \$queryMaster = \"UPDATE $masterTable SET $masterSetClause WHERE id = :id\";\n";
        $methods .= "                \$stmtMaster = \$this->pdo->prepare(\$queryMaster);\n";
        $methods .= "                \$stmtMaster->bindValue(':id', \$masterData->id, PDO::PARAM_INT);\n";
        $methods .= "            } else {\n";
        $methods .= "                \$queryMaster = \"INSERT INTO $masterTable (" . implode(', ', array_column($masterFields, 'Field')) . ") VALUES (" . implode(', ', array_map(fn($col) => ":{$col['Field']}", $masterFields)) . ")\";\n";
        $methods .= "                \$stmtMaster = \$this->pdo->prepare(\$queryMaster);\n";
        $methods .= "            }\n";
    
        foreach ($masterFields as $field) {
            $methods .= "            \$stmtMaster->bindValue(':{$field['Field']}', \$masterData->{$field['Field']} ?? null);\n";
        }
        $methods .= "            \$stmtMaster->execute();\n";
        $methods .= "            \$masterId = \$masterData->id ?? \$this->pdo->lastInsertId();\n\n";
    
        $methods .= "            \$queryInsertDetails = \"INSERT INTO $detailTable ($detailInsertColumns) VALUES ($detailInsertPlaceholders)\";\n";
        $methods .= "            \$stmtInsertDetails = \$this->pdo->prepare(\$queryInsertDetails);\n";
        $methods .= "            foreach (\$detailsData as \$detail) {\n";
        foreach ($detailFields as $field) {
            $methods .= "                \$stmtInsertDetails->bindValue(':{$field['Field']}', \$detail->{$field['Field']} ?? null);\n";
        }
        $methods .= "                \$stmtInsertDetails->execute();\n";
        $methods .= "            }\n\n";
    
        $methods .= "            \$this->pdo->commit();\n";
        $methods .= "            return true;\n";
        $methods .= "        } catch (PDOException \$e) {\n";
        $methods .= "            \$this->pdo->rollBack();\n";
        $methods .= "            return \$this->generateErrorResponse(\$e);\n";
        $methods .= "        }\n";
        $methods .= "    }\n\n";
    
        $methods .= "    public function deleteDetail(\$detailId) {\n";
        $methods .= "        \$query = \"DELETE FROM $detailTable WHERE id = :id\";\n";
        $methods .= "        try {\n";
        $methods .= "            \$stmt = \$this->pdo->prepare(\$query);\n";
        $methods .= "            \$stmt->bindValue(':id', \$detailId, PDO::PARAM_INT);\n";
        $methods .= "            return \$stmt->execute();\n";
        $methods .= "        } catch (PDOException \$e) {\n";
        $methods .= "            return \$this->generateErrorResponse(\$e);\n";
        $methods .= "        }\n";
        $methods .= "    }\n\n";
    
        $methods .= "    public function updateDetail(\$detailId, \$data) {\n";
        $methods .= "        \$setClause = implode(', ', array_map(fn(\$key) => \"\$key = :\$key\", array_keys((array) \$data)));\n";
        $methods .= "        \$query = \"UPDATE $detailTable SET \$setClause WHERE id = :id\";\n";
        $methods .= "        try {\n";
        $methods .= "            \$stmt = \$this->pdo->prepare(\$query);\n";
        $methods .= "            foreach (\$data as \$key => \$value) {\n";
        $methods .= "                \$stmt->bindValue(\":\$key\", \$value);\n";
        $methods .= "            }\n";
        $methods .= "            \$stmt->bindValue(':id', \$detailId, PDO::PARAM_INT);\n";
        $methods .= "            return \$stmt->execute();\n";
        $methods .= "        } catch (PDOException \$e) {\n";
        $methods .= "            return \$this->generateErrorResponse(\$e);\n";
        $methods .= "        }\n";
        $methods .= "    }\n\n";
    
        return $methods;
    }
    
    
    private function generateRelationshipMethods($table)
    {
        $relationships = $this->detectRelationships($table);
        $methods = "";
    
        foreach ($relationships as $relation) {
            if ($relation['master_table'] === $table) {
                $methods .= $this->generateMasterDetailMethods($table, $relation['detail_table'], $relation);
            }
        }
    
        return $methods;
    }

    private function camelCase($string)
    {
        $string = str_replace('_', ' ', strtolower($string));
        $string = ucwords($string);
        return str_replace(' ', '', lcfirst($string));
    }
}

$generator = new CrieRepository();
$generator->generateRepositories();
