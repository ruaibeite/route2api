<?php

declare(strict_types=1);

namespace Route2Api\Exporter;

use Route2Api\IR\ApiEndpoint;
use Route2Api\IR\ApiProject;

final class OpenApiExporter
{
    public function exportJson(ApiProject $project): string
    {
        return json_encode($this->document($project), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    public function exportYaml(ApiProject $project): string
    {
        return $this->yaml($this->document($project));
    }

    /**
     * @return array<string, mixed>
     */
    private function document(ApiProject $project): array
    {
        $paths = [];

        foreach ($project->endpoints as $endpoint) {
            $openApiPath = $this->toOpenApiPath($endpoint->path);
            $method = strtolower($endpoint->method === 'ANY' ? 'get' : $endpoint->method);

            $operation = [
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

            $requestBody = $this->requestBody($endpoint);
            if ($requestBody !== []) {
                $operation['requestBody'] = $requestBody;
            }

            $paths[$openApiPath][$method] = $operation;
        }

        return [
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
            if ($parameter['in'] === 'body') {
                continue;
            }

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

    /**
     * @return array<string, mixed>
     */
    private function requestBody(ApiEndpoint $endpoint): array
    {
        $properties = [];
        $required = [];

        foreach ($endpoint->parameters as $parameter) {
            if ($parameter['in'] !== 'body') {
                continue;
            }

            $properties[$parameter['name']] = [
                'type' => $parameter['type'],
                'description' => $parameter['description'],
            ];
            if ($parameter['required']) {
                $required[] = $parameter['name'];
            }
        }

        if ($properties === []) {
            return [];
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return [
            'required' => $required !== [],
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * @param mixed $value
     */
    private function yaml($value, int $indent = 0): string
    {
        $lines = [];
        $prefix = str_repeat('  ', $indent);

        if (!is_array($value)) {
            return $prefix . $this->yamlScalar($value) . PHP_EOL;
        }

        foreach ($value as $key => $item) {
            if (is_int($key)) {
                if (is_array($item)) {
                    $lines[] = $prefix . '-';
                    $lines[] = rtrim($this->yaml($item, $indent + 1));
                } else {
                    $lines[] = $prefix . '- ' . $this->yamlScalar($item);
                }
                continue;
            }

            if (is_array($item)) {
                $lines[] = $prefix . $key . ':';
                $lines[] = rtrim($this->yaml($item, $indent + 1));
            } else {
                $lines[] = $prefix . $key . ': ' . $this->yamlScalar($item);
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param mixed $value
     */
    private function yamlScalar($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        if ($string === '' || preg_match('/[^A-Za-z0-9_.\\/-]/u', $string) === 1) {
            return '"' . str_replace('"', '\\"', $string) . '"';
        }

        return $string;
    }
}
