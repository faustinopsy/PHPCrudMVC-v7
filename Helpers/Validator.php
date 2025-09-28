<?php

namespace Fast\Back\Helpers;

class Validator
{
    protected array $data;
    protected array $rules = [];
    protected array $errors = [];

    public function validate(array $data): bool
    {
        $this->data = $data;
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            foreach ($rules as $rule) {
                // Separa a regra do parâmetro, ex: 'min:8'
                $ruleName = $rule;
                $param = null;
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                }

                $methodName = 'validate' . ucfirst($ruleName);
                if (method_exists($this, $methodName)) {
                    $this->$methodName($field, $param);
                }
            }
        }
        
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getSanitizedData(): array
    {
        $sanitized = [];
        foreach ($this->data as $key => $value) {
           // Aplica uma sanitização básica em todos os campos
            $sanitized[$key] = htmlspecialchars(stripslashes(trim($value)), ENT_QUOTES, 'UTF-8');
        }
        return $sanitized;
    }

    // --- Regras de Validação ---

    protected function validateRequired(string $field): void
    {
        if (empty($this->data[$field])) {
            $this->errors[$field][] = "O campo {$field} é obrigatório.";
        }
    }

    protected function validateEmail(string $field): void
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "O campo {$field} não é um e-mail válido.";
        }
    }

    protected function validateNumeric(string $field): void
    {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = "O campo {$field} deve ser numérico.";
        }
    }

    protected function validateMin(string $field, ?string $param): void
    {
        if (!empty($this->data[$field]) && is_numeric($param) && strlen($this->data[$field]) < (int)$param) {
            $this->errors[$field][] = "O campo {$field} deve ter no mínimo {$param} caracteres.";
        }
    }
    protected function validateMax(string $field, ?string $param): void
    {
        if (!empty($this->data[$field]) && is_numeric($param) && strlen($this->data[$field]) > (int)$param) {
            $this->errors[$field][] = "O campo {$field} deve ter no máximo {$param} caracteres.";
        }
    }
    protected function validateMatch(string $field, ?string $param): void
    {
        if (!empty($this->data[$field]) && isset($this->data[$param]) && $this->data[$field] !== $this->data[$param]) {
            $this->errors[$field][] = "O campo {$field} deve corresponder ao campo {$param}.";
        }
    }
    protected function validateDate(string $field): void
    {
        if (!empty($this->data[$field]) && !strtotime($this->data[$field])) {
            $this->errors[$field][] = "O campo {$field} não é uma data válida.";
        }
    }
    protected function validateUrl(string $field): void
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = "O campo {$field} não é uma URL válida.";
        }
    }
    protected function validateBoolean(string $field): void
    {
        if (!empty($this->data[$field]) && !in_array($this->data[$field], [0, 1, '0', '1', true, false], true)) {
            $this->errors[$field][] = "O campo {$field} deve ser booleano.";
        }
    }
}