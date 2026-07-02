<?php

declare(strict_types=1);

namespace Route2Api\IR;

final class ApiEndpoint
{
    public $method;
    public $path;
    public $title;
    public $controller;
    public $group;
    public $description;
    public $middlewares;
    public $parameters;

    /**
     * @param string[] $middlewares
     * @param array<int, array{name:string,in:string,required:bool,type:string,description:string}> $parameters
     */
    public function __construct(
        string $method,
        string $path,
        string $title,
        string $controller = '',
        string $group = 'Default',
        string $description = '',
        array $middlewares = [],
        array $parameters = []
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->title = $title;
        $this->controller = $controller;
        $this->group = $group;
        $this->description = $description;
        $this->middlewares = $middlewares;
        $this->parameters = $parameters;
    }
}
