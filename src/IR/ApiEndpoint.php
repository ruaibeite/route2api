<?php

declare(strict_types=1);

namespace Route2Api\IR;

final class ApiEndpoint
{
    /**
     * @param string[] $middlewares
     * @param array<int, array{name:string,in:string,required:bool,type:string,description:string}> $parameters
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $title,
        public string $controller = '',
        public string $group = 'Default',
        public string $description = '',
        public array $middlewares = [],
        public array $parameters = [],
    ) {
    }
}
