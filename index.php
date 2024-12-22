<?php
namespace Fast\Back;
use Fast\Back\Rotas\Router;
use Fast\Back\Http\HttpHeader;
use Fast\Back\Rotas\AttributeRouter;
use Exception;

require_once 'vendor/autoload.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);
 
if (!file_exists(__DIR__ . '/logs')) {
   mkdir(__DIR__ . '/logs', 0755, true);
}

HttpHeader::setDefaultHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$roteador = new AttributeRouter();

$metodoHttp = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$caminhoControladores = __DIR__ . '/Controllers';
$namespaceBase = 'Fast\\Back\\Controllers';

$classesControladoras = obterClassesControladoras($caminhoControladores, $namespaceBase);

foreach ($classesControladoras as $classeControladora) {
    $roteador->passaControlador($classeControladora);
}

$roteador->resolve($metodoHttp, $uri);

/**
 * Função para obter todas as classes de controladores no diretório Controllers
 * 
 * @param string $caminhoControladores Caminho para o diretório de controladores
 * @param string $namespaceBase Namespace base dos controladores
 * @return array Lista de nomes completos das classes de controladores
 */
    function obterClassesControladoras($caminhoControladores, $namespaceBase) {
        $classesControladoras = [];

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($caminhoControladores)
        );

        foreach ($iterador as $arquivo) {
            if ($arquivo->isFile() && $arquivo->getExtension() === 'php') {
                $caminhoRelativo = substr($arquivo->getPathname(), strlen($caminhoControladores));
                $caminhoRelativo = ltrim($caminhoRelativo, DIRECTORY_SEPARATOR);
                $caminhoRelativo = substr($caminhoRelativo, 0, -4);
                $parteNomeClasse = str_replace(DIRECTORY_SEPARATOR, '\\', $caminhoRelativo);
                $nomeClasse = $namespaceBase . '\\' . $parteNomeClasse;

            if (!class_exists($nomeClasse)) {
                require_once $arquivo->getPathname();
            }
            if (class_exists($nomeClasse)) {
                $classesControladoras[] = $nomeClasse;
            }
        }
    }

    return $classesControladoras;
}
