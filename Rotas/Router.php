<?php
namespace Fast\Back\Rotas;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Router {
    public string $path;
    public array $methods;

    public function __construct(string $path, array $methods = ['GET']) {
        $this->path = $path;
        $this->methods = $methods;
    }
}
