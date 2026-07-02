<?php

declare(strict_types=1);

namespace Route2Api\Exporter;

use Route2Api\IR\ApiEndpoint;
use Route2Api\IR\ApiProject;

final class PostmanExporter
{
    public function exportJson(ApiProject $project): string
    {
        $groups = [];
        foreach ($project->endpoints as $endpoint) {
            $groups[$endpoint->group][] = $this->item($endpoint);
        }

        $items = [];
        foreach ($groups as $group => $endpoints) {
            $items[] = [
                'name' => $group,
                'item' => $endpoints,
            ];
        }

        $collection = [
            'info' => [
                'name' => $project->name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                [
                    'key' => 'baseUrl',
                    'value' => $project->baseUrl,
                ],
            ],
            'item' => $items,
        ];

        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(ApiEndpoint $endpoint): array
    {
        $query = [];
        foreach ($endpoint->parameters as $parameter) {
            if ($parameter['in'] !== 'query') {
                continue;
            }

            $query[] = [
                'key' => $parameter['name'],
                'value' => '',
                'description' => $parameter['description'],
                'disabled' => !$parameter['required'],
            ];
        }

        return [
            'name' => $endpoint->title,
            'request' => [
                'method' => $endpoint->method === 'ANY' ? 'GET' : $endpoint->method,
                'header' => [],
                'description' => trim($endpoint->description . "\n\nController: " . $endpoint->controller),
                'url' => [
                    'raw' => '{{baseUrl}}' . $endpoint->path,
                    'host' => ['{{baseUrl}}'],
                    'path' => array_values(array_filter(explode('/', trim($endpoint->path, '/')))),
                    'query' => $query,
                ],
            ],
            'response' => [],
        ];
    }
}
