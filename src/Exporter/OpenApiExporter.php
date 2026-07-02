<?php

declare(strict_types=1);

namespace Route2Api\Exporter;

use Route2Api\IR\ApiEndpoint;
use Route2Api\IR\ApiProject;

final class OpenApiExporter
{
    public function exportJson(ApiProject $project): string
    {
        $paths = [];

        foreach ($project->endpoints as $endpoint) {
            $openApiPath = $this->toOpenApiPath($endpoint->path);
            $method = strtolower($endpoint->method === 'ANY' ? 'get' : $endpoint->method);

            $paths[$openApiPath][$method] = [
                'summary' => $endpoint->title,
                'description' => $endpoint->description,
                'tags' => [$endpoint->group],
                'parameters' => $this->parameters($endpoint),
                'responses' => [
                    '200' => [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                ],
                            ],
                        ],
                    ],
                ],
                'x-route2api-controller' => $endpoint->controller,
                'x-route2api-middlewares' => $endpoint->middlewares,
            ];
        }

        $document = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $project->name,
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => $project->baseUrl],
            ],
            'paths' => $paths,
        ];

        return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    private function toOpenApiPath(string $path): string
    {
        return preg_replace('/:([A-Za-z_][A-Za-z0-9_]*)/', '{$1}', $path) ?? $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parameters(ApiEndpoint $endpoint): array
    {
        $parameters = [];

        if (preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $endpoint->path, $matches)) {
            foreach ($matches[1] as $name) {
                $parameters[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [
                        'type' => 'string',
                    ],
                ];
            }
        }

        foreach ($endpoint->parameters as $parameter) {
            $parameters[] = [
                'name' => $parameter['name'],
                'in' => $parameter['in'],
                'required' => $parameter['required'],
                'description' => $parameter['description'],
                'schema' => [
                    'type' => $parameter['type'],
                ],
            ];
        }

        return $parameters;
    }
}
