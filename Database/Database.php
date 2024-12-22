<?php
namespace Fast\Back\Database;

use PDO;
use PDOException;
use Exception;
use Fast\Back\Database\Config;

class Database {
    private static $instance = null;
    private $conn;
    private $config;

    private function __construct() {
        $this->config = Config::get();
        $dbConfig = $this->config['database'];
        $driver = $dbConfig['driver'];

        try {
            switch ($driver) {
                case 'mysql':
                    $mysqlConfig = $dbConfig['mysql'];
                    $dsn = "mysql:host={$mysqlConfig['host']};dbname={$mysqlConfig['db_name']};charset={$mysqlConfig['charset']}";
                    $this->conn = new PDO($dsn, $mysqlConfig['username'], $mysqlConfig['password'], [PDO::ATTR_PERSISTENT => true]);
                    break;
                case 'sqlite':
                    $sqliteConfig = $dbConfig['sqlite'];
                    $dsn = "sqlite:{$sqliteConfig['path']}";
                    $this->conn = new PDO($dsn, null, null, [PDO::ATTR_PERSISTENT => true]);
                    break;
                case 'sqlsrv':
                    $sqlsrvConfig = $dbConfig['sqlsrv'];
                    $dsn = "sqlsrv:Server={$sqlsrvConfig['host']};Database={$sqlsrvConfig['db_name']}";
                    $this->conn = new PDO($dsn, $sqlsrvConfig['username'], $sqlsrvConfig['password'], [PDO::ATTR_PERSISTENT => true]);
                    break;
                case 'pgsql':
                    $pgsqlConfig = $dbConfig['pgsql'];
                    $dsn = "pgsql:host={$pgsqlConfig['host']};port={$pgsqlConfig['port']};dbname={$pgsqlConfig['db_name']};user={$pgsqlConfig['username']};password={$pgsqlConfig['password']}";
                    $this->conn = new PDO($dsn);
                    break;
            }

            if (in_array($driver, ['mysql', 'sqlite', 'sqlsrv', 'pgsql'])) {
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch(PDOException $exception) {
            echo "Erro de conexão: " . $exception->getMessage();
        } catch(Exception $exception) {
            echo "Erro de conexão : " . $exception->getMessage();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }

}