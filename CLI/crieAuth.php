<?php

namespace Fast\Back\CLI;

require_once __DIR__ . '/../vendor/autoload.php';

use Fast\Back\Database\Database;

class AuthGenerator
{
    private \PDO $pdo;
    private array $paths;
    private string $userTableName;
    private string $userModelName;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->paths = [
            'services' => __DIR__ . '/../Services',
            'controllers' => __DIR__ . '/../Controllers',
            'validators' => __DIR__ . '/../Validators',
            'views' => __DIR__ . '/../Views',
        ];
    }

    public function run(): void
    {
        if (!$this->findUserTable()) {
            echo "Erro: Nenhuma tabela de usuários ('users' ou 'usuarios') encontrada no banco de dados.\n";
            exit(1);
        }

        $this->createDirectories();
        $this->generateAuthService();
        $this->generateUserValidator();
        $this->generateAuthController();
        $this->generateViews();
        $this->updateSidebar();
    }

    private function findUserTable(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() > 0) {
                $this->userTableName = 'users';
                $this->userModelName = 'User';
                return true;
            }
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'usuarios'");
            if ($stmt->rowCount() > 0) {
                $this->userTableName = 'usuarios';
                $this->userModelName = 'Usuario';
                return true;
            }
        } catch (\PDOException $e) {
            echo "Erro de banco de dados: " . $e->getMessage() . "\n";
            exit(1);
        }
        return false;
    }

    private function createDirectories(): void
    {
        foreach ($this->paths as $path) {
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
        $authViewDir = $this->paths['views'] . '/auth';
        if (!is_dir($authViewDir)) mkdir($authViewDir, 0755, true);
    }
    
    private function generateAuthService(): void
    {
        $userRepositoryName = $this->userModelName . 'Repository';
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

        if (!\$user || !password_verify(\$password, \$user->getPassword())) {
            return false;
        }

        \$this->login(\$user);
        return true;
    }

    public function register(array \$data): ?{$this->userModelName}
    {
        \$data['password'] = password_hash(\$data['password'], PASSWORD_DEFAULT);
        
        \$userModel = new {$this->userModelName}((object) \$data);
        \$newId = \$this->userRepository->create(\$userModel);

        if (\$newId) {
            \$user = \$this->userRepository->findById(\$newId);
            // (Opcional) Enviar e-mail de boas-vindas
            // \$mailer = new Mailer();
            // \$mailer->send(\$user->getEmail(), \$user->getName(), 'Bem-vindo(a)!', '<h1>Olá!</h1><p>Seu registro foi concluído.</p>');
            return \$user;
        }
        return null;
    }

    public function login({$this->userModelName} \$user): void
    {
        session_regenerate_id(true);
        \$_SESSION['user_id'] = \$user->getId();
        \$_SESSION['user_name'] = \$user->getName();
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

class {$validatorClassName} extends Validator
{
    protected array \$rules = [
        'name' => ['required'],
        'email' => ['required', 'email'], // Para unicidade, adicione uma regra 'unique:users,email' e implemente-a no Validator base
        'password' => ['required', 'min:8'],
        // Adicione uma regra 'matches:password' para confirmação de senha
    ];
}
PHP;
        file_put_contents($this->paths['validators'] . '/' . $validatorClassName . '.php', $content);
        echo "Arquivo gerado: Validators/{$validatorClassName}.php\n";
    }
    
    private function generateAuthController(): void
    {
        $validatorClassName = $this->userModelName . 'Validator';
        $content = <<<PHP
<?php

namespace Fast\\Back\\Controllers;

use Fast\\Back\\Services\\AuthService;
use Fast\\Back\\Validators\\{$validatorClassName};
use Fast\\Back\\Helpers\\Flash;
use Fast\\Back\\Rotas\\Router;
use Fast\\Back\\View\\ViewRenderer;

class AuthController
{
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
    public function showLoginForm() { echo \$this->view->render('auth/login'); }
    
    #[Router('/register', methods: ['GET'])]
    public function showRegisterForm() { echo \$this->view->render('auth/register'); }

    #[Router('/login', methods: ['POST'])]
    public function login()
    {
        \$email = \$_POST['email'];
        \$password = \$_POST['password'];

        if (\$this->authService->attempt(\$email, \$password)) {
            (new Flash())->add('success', 'Login realizado com sucesso!');
            header('Location: /');
        } else {
            (new Flash())->add('error', 'E-mail ou senha inválidos.');
            header('Location: /login');
        }
        exit();
    }

    #[Router('/register', methods: ['POST'])]
    public function register()
    {
        \$data = \$_POST;
        if (!\$this->validator->validate(\$data)) {
            // Lógica para lidar com falha na validação
            (new Flash())->add('error', 'Por favor, corrija os erros.');
            header('Location: /register'); // Simplificado, idealmente repopulava o form
            exit();
        }

        \$user = \$this->authService->register(\$data);
        if (\$user) {
            \$this->authService->login(\$user);
            (new Flash())->add('success', 'Registro e login realizados com sucesso!');
            header('Location: /');
        } else {
            (new Flash())->add('error', 'Não foi possível realizar o registro.');
            header('Location: /register');
        }
        exit();
    }
    
    #[Router('/logout', methods: ['POST'])]
    public function logout()
    {
        \$this->authService->logout();
        (new Flash())->add('success', 'Você foi desconectado.');
        header('Location: /login');
        exit();
    }
}
PHP;
        file_put_contents($this->paths['controllers'] . '/AuthController.php', $content);
        echo "Arquivo gerado: Controllers/AuthController.php\n";
    }

    private function generateViews(): void
    {
        // View de Login
        $loginView = <<<HTML
<?php \$title = 'Login'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>
<h1>Login</h1>
<form action="/login" method="POST">
    <div style="margin-bottom:15px;">
        <label for="email">E-mail</label><br>
        <input type="email" id="email" name="email" required style="width:300px;padding:5px;">
    </div>
    <div style="margin-bottom:15px;">
        <label for="password">Senha</label><br>
        <input type="password" id="password" name="password" required style="width:300px;padding:5px;">
    </div>
    <button type="submit" class="btn btn-primary">Entrar</button>
</form>
<?php include __DIR__ . '/../partials/footer.php'; ?>
HTML;
        file_put_contents($this->paths['views'] . '/auth/login.php', $loginView);
        echo "Arquivo gerado: Views/auth/login.php\n";

        // View de Registro
        $registerView = <<<HTML
<?php \$title = 'Registrar'; include __DIR__ . '/../partials/header.php'; include __DIR__ . '/../partials/sidebar.php'; ?>
<h1>Registrar Nova Conta</h1>
<form action="/register" method="POST">
    <div style="margin-bottom:15px;">
        <label for="name">Nome</label><br>
        <input type="text" id="name" name="name" required style="width:300px;padding:5px;">
    </div>
    <div style="margin-bottom:15px;">
        <label for="email">E-mail</label><br>
        <input type="email" id="email" name="email" required style="width:300px;padding:5px;">
    </div>
    <div style="margin-bottom:15px;">
        <label for="password">Senha (mínimo 8 caracteres)</label><br>
        <input type="password" id="password" name="password" required style="width:300px;padding:5px;">
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
        $content = file_get_contents($sidebarPath);
        
        $authLinks = <<<PHP

    <hr>
    <h3>Autenticação</h3>
    <ul>
        <?php if (\\Fast\\Back\\Services\\AuthService::check()): ?>
            <li>Olá, <?= \\Fast\\Back\\Services\\AuthService::user()['name'] ?></li>
            <li>
                <form action="/logout" method="POST" style="display:inline;">
                    <button type="submit" style="background:none;border:none;padding:0;color:#007bff;cursor:pointer;font-size:1em;">Sair</button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="/login">Login</a></li>
            <li><a href="/register">Registrar</a></li>
        <?php endif; ?>
    </ul>
PHP;
        // Adiciona os links apenas se já não existirem
        if (!str_contains($content, 'Autenticação')) {
            $content = str_replace('</ul>', "</ul>\n{$authLinks}", $content);
            file_put_contents($sidebarPath, $content);
            echo "Sidebar atualizado com links de autenticação.\n";
        }
    }
}

// Executa o gerador
(new AuthGenerator())->run();