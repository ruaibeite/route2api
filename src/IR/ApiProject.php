<?php

declare(strict_types=1);

namespace Route2Api\IR;

final class ApiProject
{
    public $name;
    public $baseUrl;
    public $endpoints;

    /**
     * @param ApiEndpoint[] $endpoints
     */
    public function __construct(string $name, string $baseUrl, array $endpoints = [])
    {
        $this->name = $name;
        $this->baseUrl = $baseUrl;
        $this->endpoints = $endpoints;
    }
}
