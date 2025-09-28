<?php

namespace Fast\Back\Services;

use Fast\Back\Models\TblUsuario;
use Fast\Back\Repositories\TblUsuarioRepository;
use Fast\Back\Services\Mailer;

class AuthService
{
    private TblUsuarioRepository $userRepository;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->userRepository = new TblUsuarioRepository();
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user->getSenhaUsuario())) {
            return false;
        }

        $this->login($user);
        return true;
    }

    public function register(array $data): ?TblUsuario
    {
        // MUDANÇA: Mapeia os nomes dos campos do formulário para os nomes das colunas
        $mappedData = [
            'nome_usuario' => $data['nome_usuario'],
            'email_usuario' => $data['email_usuario'],
            'senha_usuario' => password_hash($data['senha_usuario'], PASSWORD_DEFAULT)
        ];
        
        // Se o campo de role existir e for enviado, adicione-o também
        if (!empty('role') && isset($data['role'])) {
            $mappedData['role'] = $data['role'];
        }
        
        $userModel = new TblUsuario((object) $mappedData);
        $newId = $this->userRepository->create($userModel);

        if ($newId) {
            return $this->userRepository->findById($newId);
        }
        return null;
    }

    public function login(TblUsuario $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->getIdUsuario();
        $_SESSION['user_name'] = $user->getNomeUsuario();
        $_SESSION['user_role'] = $user->getRole();
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
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name']
        ];
    }

    public static function hasRole(array $roles): bool
    {
        if (!self::check() || !isset($_SESSION['user_role'])) {
            return false;
        }
        // Permite múltiplos tipos de admin, ex: 'admin', 'superadmin' ou no DB: 1, 2
        // A lógica de verificação pode ser mais complexa dependendo do caso de uso.
        return in_array($_SESSION['user_role'], $roles);
    }
}