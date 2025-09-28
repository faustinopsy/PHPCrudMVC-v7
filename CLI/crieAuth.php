<?php

namespace Fast\Back\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use Fast\Back\Database\Database;

/**
 * AuthGenerator gera um sistema de autenticação completo (Login/Registro).
 * Ele detecta a tabela de usuários, ou pergunta ao usuário, e gera dinamicamente
 * todos os controllers, services, validadores e views necessários, incluindo
 * proteção de rotas (middleware) e controle de acesso por nível de usuário.
 */
class AuthGenerator
{
    private \PDO $pdo;
    private array $paths;

    // Propriedades que serão descobertas dinamicamente
    private string $userTableName;
    private string $userModelName;
    private string $primaryKeyField;
    private string $nameField;
    private string $emailField;
    private string $passwordField;
    private ?string $roleField = null;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->paths = [
            'services' => __DIR__ . '/../Services',
            'controllers' => __DIR__ . '/../Controllers',
            'validators' => __DIR__ . '/../Validators',
            'views' => __DIR__ . '/../Views',
            'middleware' => __DIR__ . '/../Middleware',
        ];
    }

    public function run(): void
    {
        if (!$this->discoverUserTableAndColumns()) {
            exit(1);
        }

        $this->createDirectories();
        $this->generateAuthMiddleware();
        $this->generateAuthService();
        $this->generateUserValidator();
        $this->generateAuthController();
        $this->generateViews();
        $this->updateSidebar();
        
        echo "\n\nAVISO IMPORTANTE:\n";
        echo "Para proteger seus outros controllers (CRUDs), você precisa que o 'crieControllers.php' adicione o AuthMiddleware a eles.\n";
        echo "Adicione 'use Fast\\Back\\Middleware\\AuthMiddleware;' e 'use AuthMiddleware;' no controller gerado, e chame 'self::handle();' no construtor.\n";
    }

    private function ask(string $prompt): string
    {
        echo $prompt . " ";
        return trim(fgets(STDIN));
    }

    private function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    private function discoverUserTableAndColumns(): bool
    {
        echo "Procurando por uma tabela de usuários...\n";
        $allTables = $this->pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $candidates = [];

        foreach ($allTables as $table) {
            $columnsStmt = $this->pdo->query("DESCRIBE `{$table}`");
            $columns = $columnsStmt->fetchAll(\PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            
            $emailFields = array_intersect($columnNames, ['email', 'user_email', 'login']);
            $passwordFields = array_intersect($columnNames, ['password', 'senha', 'user_password']);
            
            if (!empty($emailFields) && !empty($passwordFields)) {
                $candidates[$table] = $columns;
            }
        }

        if (count($candidates) === 1) {
            $this->userTableName = array_key_first($candidates);
            echo "Tabela de usuários detectada automaticamente: '{$this->userTableName}'\n";
        } elseif (count($candidates) > 1) {
            echo "Múltiplas tabelas candidatas encontradas:\n";
            $i = 1;
            $options = [];
            foreach ($candidates as $table => $cols) {
                echo "  {$i}. {$table}\n";
                $options[$i] = $table;
                $i++;
            }
            $choice = (int)$this->ask("Qual tabela de usuários devemos usar? (digite o número)");
            if (!isset($options[$choice])) {
                echo "Opção inválida.\n";
                return false;
            }
            $this->userTableName = $options[$choice];
        } else {
            $this->userTableName = $this->ask("Nenhuma tabela de usuários encontrada. Por favor, digite o nome da tabela manualmente:");
            if (!in_array($this->userTableName, $allTables)) {
                echo "Erro: A tabela '{$this->userTableName}' não existe no banco de dados.\n";
                return false;
            }
        }

        $columnsStmt = $this->pdo->query("DESCRIBE `{$this->userTableName}`");
        $columns = $candidates[$this->userTableName] ?? $columnsStmt->fetchAll(\PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');

        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI') $this->primaryKeyField = $col['Field'];
        }
        $this->emailField = current(array_intersect($columnNames, ['email', 'user_email', 'login','email_usuario'])) ?: 'email';
        $this->passwordField = current(array_intersect($columnNames, ['password', 'senha', 'user_password','senha_usuario'])) ?: 'password';
        $this->nameField = current(array_intersect($columnNames, ['name', 'nome', 'user_name', 'fullname','nome_usuario'])) ?: 'name';
        
        $roleCandidates = array_intersect($columnNames, ['role', 'level', 'access_level', 'is_admin', 'perfil']);
        if (!empty($roleCandidates)) {
            $this->roleField = current($roleCandidates);
        }

        if (empty($this->primaryKeyField)) {
             echo "Erro: A tabela '{$this->userTableName}' não parece ter uma chave primária definida.\n";
             return false;
        }

        $this->userModelName = $this->pascalCase($this->userTableName);
        echo "--------------------------------------------------\n";
        echo "Configuração de autenticação a ser usada:\n";
        echo "  - Tabela: {$this->userTableName}\n";
        echo "  - Model: {$this->userModelName}\n";
        echo "  - Campo de ID: {$this->primaryKeyField}\n";
        echo "  - Campo de Nome: {$this->nameField}\n";
        echo "  - Campo de Email: {$this->emailField}\n";
        echo "  - Campo de Senha: {$this->passwordField}\n";
        if ($this->roleField) {
            echo "  - Campo de Nível (Role): {$this->roleField} (Detectado!)\n";
        } else {
            echo "  - Campo de Nível (Role): Nenhum detectado. Apenas a verificação de login será aplicada.\n";
        }
        echo "--------------------------------------------------\n";

        return true;
    }

    private function createDirectories(): void
    {
        foreach ($this->paths as $path) {
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
        $authViewDir = $this->paths['views'] . '/auth';
        if (!is_dir($authViewDir)) mkdir($authViewDir, 0755, true);
    }

    private function generateAuthMiddleware(): void
    {
        $content = <<<PHP
        <?php

        namespace Fast\\Back\\Middleware;

        use Fast\\Back\\Services\\AuthService;
        use Fast\\Back\\Helpers\\Flash;

        trait AuthMiddleware
        {
            protected static function handle(array \$roles = []): void
            {
                if (!AuthService::check()) {
                    (new Flash())->add('error', 'Você precisa estar logado para acessar esta página.');
                    header('Location: /login');
                    exit();
                }

                if (!empty(\$roles) && !AuthService::hasRole(\$roles)) {
                    (new Flash())->add('error', 'Você não tem permissão para acessar esta página.');
                    header('Location: /'); // Redireciona para a home se não tiver permissão
                    exit();
                }
            }
        }
        PHP;
                file_put_contents($this->paths['middleware'] . '/AuthMiddleware.php', $content);
                echo "Arquivo gerado: Middleware/AuthMiddleware.php\n";
    }

    private function generateAuthService(): void
    {
        $userRepositoryName = $this->userModelName . 'Repository';
        $pkGetter = 'get' . $this->pascalCase($this->primaryKeyField);
        $nameGetter = 'get' . $this->pascalCase($this->nameField);
        $emailGetter = 'get' . $this->pascalCase($this->emailField);
        $passwordGetter = 'get' . $this->pascalCase($this->passwordField);

        $roleGetterLogic = $this->roleField ? 'get' . $this->pascalCase($this->roleField) : null;
        $loginRoleLogic = $roleGetterLogic ? "\$_SESSION['user_role'] = \$user->{$roleGetterLogic}();" : "";
        
        $hasRoleMethod = $this->roleField 
            ? <<<PHP
    public static function hasRole(array \$roles): bool
    {
        if (!self::check() || !isset(\$_SESSION['user_role'])) {
            return false;
        }
        // Permite múltiplos tipos de admin, ex: 'admin', 'superadmin' ou no DB: 1, 2
        // A lógica de verificação pode ser mais complexa dependendo do caso de uso.
        return in_array(\$_SESSION['user_role'], \$roles);
    }
PHP
            : "    // Nenhum campo de nível de acesso detectado, o método hasRole não foi gerado.\n";

        $content = <<<PHP
        <?php

        namespace Fast\\Back\\Services;

        use Fast\\Back\\Models\\{$this->userModelName};
        use Fast\\Back\\Repositories\\{$userRepositoryName};
        use Fast\\Back\\Services\\Mailer;

        class AuthService
        {
            private {$userRepositoryName} \$userRepository;

            public function __construct()
            {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                \$this->userRepository = new {$userRepositoryName}();
            }

            public function attempt(string \$email, string \$password): bool
            {
                \$user = \$this->userRepository->findByEmail(\$email);

                if (!\$user || !password_verify(\$password, \$user->{$passwordGetter}())) {
                    return false;
                }

                \$this->login(\$user);
                return true;
            }

            public function register(array \$data): ?{$this->userModelName}
            {
                // MUDANÇA: Mapeia os nomes dos campos do formulário para os nomes das colunas
                \$mappedData = [
                    '{$this->nameField}' => \$data['{$this->nameField}'],
                    '{$this->emailField}' => \$data['{$this->emailField}'],
                    '{$this->passwordField}' => password_hash(\$data['{$this->passwordField}'], PASSWORD_DEFAULT)
                ];
                
                // Se o campo de role existir e for enviado, adicione-o também
                if (!empty('{$this->roleField}') && isset(\$data['{$this->roleField}'])) {
                    \$mappedData['{$this->roleField}'] = \$data['{$this->roleField}'];
                }
                
                \$userModel = new {$this->userModelName}((object) \$mappedData);
                \$newId = \$this->userRepository->create(\$userModel);

                if (\$newId) {
                    return \$this->userRepository->findById(\$newId);
                }
                return null;
            }

            public function login({$this->userModelName} \$user): void
            {
                session_regenerate_id(true);
                \$_SESSION['user_id'] = \$user->{$pkGetter}();
                \$_SESSION['user_name'] = \$user->{$nameGetter}();
                {$loginRoleLogic}
            }

            public function logout(): void
            {
                session_unset();
                session_destroy();
            }

            public static function check(): bool
            {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                return isset(\$_SESSION['user_id']);
            }

            public static function user(): ?array
            {
                if (!self::check()) {
                    return null;
                }
                return [
                    'id' => \$_SESSION['user_id'],
                    'name' => \$_SESSION['user_name']
                ];
            }

            {$hasRoleMethod}
        }
        PHP;
        file_put_contents($this->paths['services'] . '/AuthService.php', $content);
        echo "Arquivo gerado: Services/AuthService.php\n";
    }

    private function generateUserValidator(): void
    {
        $validatorClassName = $this->userModelName . 'Validator';
        $content = <<<PHP
        <?php

        namespace Fast\\Back\\Validators;
        use Fast\\Back\\Helpers\\Validator;
        class {$validatorClassName} extends Validator
        {
            protected array \$rules = [
                '{$this->nameField}' => ['required'],
                '{$this->emailField}' => ['required', 'email'], // Para unicidade, adicione uma regra 'unique:{$this->userTableName},{$this->emailField}' e implemente-a no Validator base
                '{$this->passwordField}' => ['required', 'min:8'],
            ];
        }
        PHP;
                file_put_contents($this->paths['validators'] . '/' . $validatorClassName . '.php', $content);
                echo "Arquivo gerado: Validators/{$validatorClassName}.php\n";
            }
            
            private function generateAuthController(): void
            {
                $validatorClassName = $this->userModelName . 'Validator';
                
                $showRegisterLogic = $this->roleField ? "        self::handle(['admin']); // Apenas admins podem ver o formulário de registro" : "        // Rota pública. Se desejar proteger, adicione self::handle();";
                $registerLogic = $this->roleField ? "        self::handle(['admin']); // Apenas admins podem registrar novos usuários" : "";

                $content = <<<PHP
        <?php

        namespace Fast\\Back\\Controllers;

        use Fast\\Back\\Middleware\\AuthMiddleware;
        use Fast\\Back\\Services\\AuthService;
        use Fast\\Back\\Validators\\{$validatorClassName};
        use Fast\\Back\\Helpers\\Flash;
        use Fast\\Back\\Rotas\\Router;
        use Fast\\Back\\Helpers\\ViewRenderer;

        class AuthController
        {
            use AuthMiddleware;

            private AuthService \$authService;
            private ViewRenderer \$view;
            private {$validatorClassName} \$validator;

            public function __construct()
            {
                \$this->authService = new AuthService();
                \$this->view = new ViewRenderer();
                \$this->validator = new {$validatorClassName}();
            }
            
            #[Router('/login', methods: ['GET'])]
            public function showLoginForm() 
            {
                \$this->viewResponse('auth/login');
            }
            
            #[Router('/register', methods: ['GET'])]
            public function showRegisterForm() 
            {
                \$this->viewResponse('auth/register');
            }

            #[Router('/login', methods: ['POST'])]
            public function login()
            {
                \$data = \$_POST;
                if (\$this->authService->attempt(\$data['{$this->emailField}'], \$data['{$this->passwordField}'])) {
                    (new Flash())->add('success', 'Login realizado com sucesso!');
                    \$this->redirect('/');
                } else {
                    (new Flash())->add('error', 'E-mail ou senha inválidos.');
                    \$this->redirect('/login');
                }
            }

            #[Router('/register', methods: ['POST'])]
            public function register()
            {
                \$data = \$_POST;
                if (!\$this->validator->validate(\$data)) {
                    (new Flash())->add('error', 'Por favor, corrija os erros no formulário.');
                    return \$this->viewResponse('auth/register', ['errors' => \$this->validator->getErrors(), 'old' => \$data]);
                }

                \$user = \$this->authService->register(\$data);
                if (\$user) {
                    (new Flash())->add('success', 'Usuário registrado com sucesso!');
                    // Se o admin registrou, não faz login automático, redireciona para a lista
                    \$this->redirect('/{$this->userTableName}');
                } else {
                    (new Flash())->add('error', 'Não foi possível realizar o registro. O e-mail já pode estar em uso.');
                    \$this->redirect('/register');
                }
            }
            
            #[Router('/logout', methods: ['POST'])]
            public function logout()
            {
                \$this->authService->logout();
                (new Flash())->add('success', 'Você foi desconectado.');
                \$this->redirect('/login');
            }
            
            private function viewResponse(string \$view, array \$data = []) {
                header('Content-Type: text/html; charset=utf-8');
                echo \$this->view->render(\$view, \$data);
                exit();
            }

            private function redirect(string \$url) {
                header("Location: {\$url}");
                exit();
            }
        }
        PHP;
        file_put_contents($this->paths['controllers'] . '/AuthController.php', $content);
        echo "Arquivo gerado: Controllers/AuthController.php\n";
    }

    private function generateViews(): void
    {
        $loginView = <<<HTML
            <?php \$title = 'Login'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>
            <h1>Login</h1>
            <form action="/login" method="POST">
                <div style="margin-bottom:15px;">
                    <label for="{$this->emailField}">E-mail</label><br>
                    <input type="email" id="{$this->emailField}" name="{$this->emailField}" required style="width:300px;padding:5px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label for="{$this->passwordField}">Senha</label><br>
                    <input type="password" id="{$this->passwordField}" name="{$this->passwordField}" required style="width:300px;padding:5px;">
                </div>
                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
            <?php include __DIR__ . '/../partials/footer.php'; ?>
            HTML;
                    file_put_contents($this->paths['views'] . '/auth/login.php', $loginView);
                    echo "Arquivo gerado: Views/auth/login.php\n";

                    $registerView = <<<HTML
            <?php \$title = 'Registrar Novo Usuário'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>
            <h1>Registrar Novo Usuário</h1>
            <?php if (!empty(\$errors)): ?>
                <div class="flash-message flash-error">
                    <p>Foram encontrados erros no seu formulário:</p>
                    <ul>
                    <?php foreach (\$errors as \$field => \$messages): ?>
                        <?php foreach (\$messages as \$message): ?>
                            <li><?= htmlspecialchars(\$message) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form action="/register" method="POST">
                <div style="margin-bottom:15px;">
                    <label for="{$this->nameField}">Nome</label><br>
                    <input type="text" id="{$this->nameField}" name="{$this->nameField}" value="<?= htmlspecialchars(\$old['{$this->nameField}'] ?? '') ?>" required style="width:300px;padding:5px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label for="{$this->emailField}">E-mail</label><br>
                    <input type="email" id="{$this->emailField}" name="{$this->emailField}" value="<?= htmlspecialchars(\$old['{$this->emailField}'] ?? '') ?>" required style="width:300px;padding:5px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label for="{$this->passwordField}">Senha (mínimo 8 caracteres)</label><br>
                    <input type="password" id="{$this->passwordField}" name="{$this->passwordField}" required style="width:300px;padding:5px;">
                </div>
                <button type="submit" class="btn btn-primary">Registrar</button>
            </form>
            <?php include __DIR__ . '/../partials/footer.php'; ?>
            HTML;
        file_put_contents($this->paths['views'] . '/auth/register.php', $registerView);
        echo "Arquivo gerado: Views/auth/register.php\n";
    }

    private function updateSidebar(): void
    {
        $sidebarPath = $this->paths['views'] . '/partials/sidebar.php';
        if (!file_exists($sidebarPath)) return;
        
        $content = file_get_contents($sidebarPath);
        
        $registerLink = !$this->roleField
            ? "<?php if (\\Fast\\Back\\Services\\AuthService::hasRole(['admin'])): ?>\n
                            <li><a href=\"/register\">Registrar Usuário</a></li>\n        
                            <?php endif; ?>"
            : "            <li><a href=\"/register\">Registrar</a></li>";

        $authLinks = <<<PHP

    <hr>
    <h3 style="margin-top:20px;">Autenticação</h3>
    <ul>
        <?php if (\\Fast\\Back\\Services\\AuthService::check()): ?>
            <li>Olá, <?= htmlspecialchars(\\Fast\\Back\\Services\\AuthService::user()['name']) ?></li>
            <li>
                <form action="/logout" method="POST" style="display:inline;">
                    <button type="submit" style="background:none;border:none;padding:0;color:#007bff;cursor:pointer;font-size:1em;text-align:left;">Sair</button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="/login">Login</a></li>
        {$registerLink}
        <?php endif; ?>
    </ul>
PHP;
        if (!str_contains($content, 'Autenticação')) {
            $content = str_replace('</ul>', "</ul>\n{$authLinks}", $content);
            file_put_contents($sidebarPath, $content);
            echo "Sidebar atualizado com links de autenticação e controle de acesso.\n";
        }
    }
}

// Executa o gerador
(new AuthGenerator())->run();