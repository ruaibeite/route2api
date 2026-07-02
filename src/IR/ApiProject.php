<?php

declare(strict_types=1);

namespace Route2Api\IR;

final class ApiProject
{
    /**
     * @param ApiEndpoint[] $endpoints
     */
    public function __construct(
        public string $name,
        public string $baseUrl,
        public array $endpoints = [],
    ) {
    }
}
