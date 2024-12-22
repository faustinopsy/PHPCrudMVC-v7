<?php
namespace Fast\Back\Rotas;

use ReflectionClass;
use ReflectionMethod;

class AttributeRouter {
    private array $rotas = [];

    public function passaControlador(string $controllerClass) {
        $controllerReflection = new ReflectionClass($controllerClass);
        $methods = $controllerReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Router::class);
            foreach ($attributes as $attribute) {
                /** @var Router $atributoRota */
                $atributoRota = $attribute->newInstance();
                foreach ($atributoRota->methods as $httpMethod) {
                    $this->rotas[$httpMethod][$atributoRota->path] = [$controllerClass, $method->getName()];
                }
            }
        }
    }

    public function resolve($method, $uri) {
        if (!isset($this->rotas[$method])) {
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Método não permitido']);
            exit();
        }
    
        $uri = parse_url($uri, PHP_URL_PATH);
    
        foreach ($this->rotas[$method] as $route => $action) {
            $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', function ($matches) {
                $paramName = $matches[1];
                $regex = isset($matches[2]) ? $matches[2] : '[^/]+';
                return '(?P<' . $paramName . '>' . $regex . ')';
            }, $route);
            
            $pattern = '#^' . $pattern . '$#u';
    
            // Logs para depuração
            error_log("Route: $route");
            error_log("Generated Pattern: $pattern");
            error_log("URI: $uri");
    
            if (preg_match($pattern, $uri, $matches)) {
                error_log("Matches: " . print_r($matches, true));
    
                $intanciaController = new $action[0]();
                $nomeMetodo = $action[1];
    
                $params = array_filter(
                    $matches,
                    fn($key) => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );
    
                $data = json_decode(file_get_contents('php://input'),true);
    
                $metodoRefletido = new ReflectionMethod($intanciaController, $nomeMetodo);
                $parametros = $metodoRefletido->getParameters();
                
                $args = [];
                foreach ($parametros as $param) {
                    $nome = $param->getName();
                    if (isset($params[$nome])) {
                        $args[] = $params[$nome];
                    } elseif ($nome === 'data') {
                        $args[] = $data;
                    }elseif (count($parametros)==2) {
                        $args[] = $params[$nome];
                        $args[] = $data;
                    } else {
                        $args[] = null;
                    }
                }
                return call_user_func_array([$intanciaController, $nomeMetodo], $args);
            }
        }
    
        http_response_code(404);
        echo json_encode(['status' => false, 'message' => 'Rota não encontrada']);
        exit();
    }
    
    
}
