<?php

namespace Fast\Back;

class CLI {
    public static function run() {
        echo "FastBackPHP CLI\n";
        echo "=================\n";

        while (true) {
            echo "\nComandos disponíveis:\n";
            echo "1. Configurar Banco de Dados\n";
            echo "2. Gerar Models\n";
            echo "3. Gerar Repositories\n";
            echo "4. Gerar Controllers\n";
            echo "5. Executar Servidor\n";
            echo "6. Sair\n";
            echo "Escolha uma opção: ";

            $choice = trim(fgets(STDIN));

            switch ($choice) {
                case '1':
                    self::configureDatabase();
                    break;
                case '2':
                    self::generateModels();
                    break;
                case '3':
                    self::generateRepositories();
                    break;
                case '4':
                    self::generateControllers();
                    break;
                case '5':
                    exec("php -S localhost:8080");
                case '6':
                    echo "Saindo...\n";
                    exit;
                default:
                    echo "Opção inválida. Tente novamente.\n";
            }
        }
    }

    private static function configureDatabase() {
        echo "Configurar Banco de Dados:\n";

        echo "Escolha o driver (mysql, sqlite, sqlsrv, pgsql, mongodb): ";
        $driver = trim(fgets(STDIN));

        $config = [
            'driver' => $driver,
        ];

        switch ($driver) {
            case 'mysql':
            case 'sqlsrv':
            case 'pgsql':
                $config[$driver] = [
                    'host' => self::askInput("Digite o host do banco de dados: "),
                    'db_name' => self::askInput("Digite o nome do banco de dados: "),
                    'username' => self::askInput("Digite o usuário do banco de dados: "),
                    'password' => self::askInput("Digite a senha do banco de dados: "),
                    'charset' => $driver !== 'pgsql' ? 'utf8' : null,
                    'port' => $driver === 'pgsql' ? self::askInput("Digite a porta do banco de dados (default: 5432): ", '5432') : null,
                ];
                break;

            case 'sqlite':
                $config[$driver] = [
                    'path' => self::askInput("Digite o caminho do arquivo SQLite: "),
                ];
                break;

            case 'mongodb':
                $config[$driver] = [
                    'host' => self::askInput("Digite o host do MongoDB: "),
                    'port' => self::askInput("Digite a porta do MongoDB (default: 27017): ", '27017'),
                    'username' => self::askInput("Digite o usuário do MongoDB: "),
                    'password' => self::askInput("Digite a senha do MongoDB: "),
                    'db_name' => self::askInput("Digite o nome do banco de dados do MongoDB: "),
                ];
                break;

            default:
                echo "Driver inválido. Operação cancelada.\n";
                return;
        }

        $configFile = __DIR__ . "../Database/Config.php";

        $configContent = "<?php\n\nnamespace Fast\\Back\\Database;\n\nclass Config\n{\n    public static function get()\n    {\n        return [\n            'database' => " . var_export($config, true) . "\n        ];\n    }\n}\n";

        file_put_contents($configFile, $configContent);

        echo "Configuração salva em $configFile\n";
    }

    private static function generateModels() {
        echo "Gerando Models...\n";
        exec("php CLI/crieModels.php", $output, $returnVar);
        echo implode("\n", $output) . "\n";
        if ($returnVar === 0) {
            echo "Models gerados com sucesso.\n";
        } else {
            echo "Erro ao gerar Models.\n";
        }
    }

    private static function generateRepositories() {
        echo "Gerando Repositories...\n";
        exec("php CLI/crieRepository.php", $output, $returnVar);
        echo implode("\n", $output) . "\n";
        if ($returnVar === 0) {
            echo "Repositories gerados com sucesso.\n";
        } else {
            echo "Erro ao gerar Repositories.\n";
        }
    }

    private static function generateControllers() {
        echo "Gerando Controllers...\n";
        exec("php CLI/crieControllers.php", $output, $returnVar);
        echo implode("\n", $output) . "\n";
        if ($returnVar === 0) {
            echo "Controllers gerados com sucesso.\n";
        } else {
            echo "Erro ao gerar Controllers.\n";
        }
    }

    private static function askInput($prompt, $default = null) {
        echo $prompt;
        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }
}

CLI::run();
