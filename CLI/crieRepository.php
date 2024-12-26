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

            // Filtra as colunas para ignorar o campo `id`
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

    private function camelCase($string)
    {
        $string = str_replace('_', ' ', strtolower($string));
        $string = ucwords($string);
        return str_replace(' ', '', lcfirst($string));
    }
}

$generator = new CrieRepository();
$generator->generateRepositories();
