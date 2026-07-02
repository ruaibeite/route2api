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

        $bodyParameters = array_values(array_filter(
            $endpoint->parameters,
            static function (array $parameter): bool {
                return $parameter['in'] === 'body';
            }
        ));
        $body = [];
        foreach ($bodyParameters as $parameter) {
            $body[$parameter['name']] = $this->exampleValue($parameter['type']);
        }

        $request = [
            'method' => $endpoint->method === 'ANY' ? 'GET' : $endpoint->method,
            'header' => [],
            'description' => trim($endpoint->description . "\n\nController: " . $endpoint->controller),
            'url' => [
                'raw' => '{{baseUrl}}' . $endpoint->path,
                'host' => ['{{baseUrl}}'],
                'path' => array_values(array_filter(explode('/', trim($endpoint->path, '/')))),
                'query' => $query,
            ],
        ];

        if ($body !== []) {
            $request['header'][] = [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ];
            $request['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => [
                    'raw' => [
                        'language' => 'json',
                    ],
                ],
            ];
        }

        return [
            'name' => $endpoint->title,
            'request' => $request,
            'response' => [],
        ];
    }

    /**
     * @return mixed
     */
    private function exampleValue(string $type)
    {
        if ($type === 'integer') {
            return 0;
        }
        if ($type === 'number') {
            return 0.0;
        }
        if ($type === 'boolean') {
            return false;
        }
        if ($type === 'array') {
            return [];
        }

        return '';
    }
}
