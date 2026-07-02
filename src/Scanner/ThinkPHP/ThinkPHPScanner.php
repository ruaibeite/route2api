<?php

declare(strict_types=1);

namespace Route2Api\Scanner\ThinkPHP;

use Route2Api\IR\ApiEndpoint;
use Route2Api\IR\ApiProject;

final class ThinkPHPScanner
{
    public function scan(string $projectPath): ApiProject
    {
        $projectPath = rtrim($projectPath, '/');
        $projectName = basename($projectPath) ?: 'ThinkPHP API';
        $routes = [];

        foreach (glob($projectPath . '/route/*.php') ?: [] as $routeFile) {
            $routes = array_merge($routes, $this->scanRouteFile($routeFile, $projectPath));
        }

        usort($routes, static fn (ApiEndpoint $a, ApiEndpoint $b): int => [$a->path, $a->method] <=> [$b->path, $b->method]);

        return new ApiProject($projectName, 'http://localhost', $routes);
    }

    /**
     * @return ApiEndpoint[]
     */
    private function scanRouteFile(string $routeFile, string $projectPath): array
    {
        $content = (string) file_get_contents($routeFile);
        $endpoints = [];
        $groupPrefixes = $this->findGroupPrefixes($content);
        $contentWithoutGroups = $this->stripGroups($content);

        foreach ($this->findSimpleRoutes($contentWithoutGroups) as $route) {
            $path = $this->normalizePath($route['path']);
            $controller = $this->normalizeController($route['handler']);
            $doc = $this->readControllerDoc($controller, $projectPath);
            $endpoints[] = new ApiEndpoint(
                strtoupper($route['method']),
                $path,
                $doc['title'] ?: $this->titleFromPath($path),
                $controller,
                $this->groupFromPath($path),
                $doc['description'],
                $this->middlewaresFromRoute($route['chain']),
                $doc['parameters'],
            );
        }

        foreach ($this->findResourceRoutes($content) as $resource) {
            $prefix = $this->normalizePath($resource['path']);
            $controller = $this->normalizeController($resource['handler']);
            foreach ($this->resourceActions($prefix, $controller, $projectPath) as $endpoint) {
                $endpoints[] = $endpoint;
            }
        }

        foreach ($groupPrefixes as $prefix) {
            foreach ($this->findGroupedRoutes($content, $prefix['body']) as $route) {
                $path = $this->normalizePath($prefix['prefix'] . '/' . $route['path']);
                $controller = $this->normalizeController($route['handler']);
                $doc = $this->readControllerDoc($controller, $projectPath);
                $endpoints[] = new ApiEndpoint(
                    strtoupper($route['method']),
                    $path,
                    $doc['title'] ?: $this->titleFromPath($path),
                    $controller,
                    $this->groupFromPath($path),
                    $doc['description'],
                    $this->middlewaresFromRoute($route['chain']),
                    $doc['parameters'],
                );
            }
        }

        return $this->uniqueEndpoints($endpoints);
    }

    /**
     * @return array<int, array{method:string,path:string,handler:string,chain:string}>
     */
    private function findSimpleRoutes(string $content): array
    {
        preg_match_all(
            "/Route::(get|post|put|delete|patch|any)\\s*\\(\\s*['\"]([^'\"]+)['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)([^;]*);/i",
            $content,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(static fn (array $match): array => [
            'method' => strtolower($match[1]),
            'path' => $match[2],
            'handler' => $match[3],
            'chain' => $match[4] ?? '',
        ], $matches);
    }

    /**
     * @return array<int, array{path:string,handler:string}>
     */
    private function findResourceRoutes(string $content): array
    {
        preg_match_all(
            "/Route::resource\\s*\\(\\s*['\"]([^'\"]+)['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/i",
            $content,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(static fn (array $match): array => [
            'path' => $match[1],
            'handler' => $match[2],
        ], $matches);
    }

    /**
     * @return array<int, array{prefix:string,body:string}>
     */
    private function findGroupPrefixes(string $content): array
    {
        preg_match_all(
            "/Route::group\\s*\\(\\s*['\"]([^'\"]*)['\"]\\s*,\\s*function\\s*\\(\\s*\\)\\s*\\{(.*?)\\}\\s*\\)([^;]*);/is",
            $content,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(static fn (array $match): array => [
            'prefix' => $match[1],
            'body' => $match[2],
        ], $matches);
    }

    /**
     * @return array<int, array{method:string,path:string,handler:string,chain:string}>
     */
    private function findGroupedRoutes(string $content, string $groupBody): array
    {
        return $this->findSimpleRoutes($groupBody);
    }

    private function stripGroups(string $content): string
    {
        return preg_replace(
            "/Route::group\\s*\\(\\s*['\"]([^'\"]*)['\"]\\s*,\\s*function\\s*\\(\\s*\\)\\s*\\{.*?\\}\\s*\\)([^;]*);/is",
            '',
            $content
        ) ?? $content;
    }

    /**
     * @return ApiEndpoint[]
     */
    private function resourceActions(string $prefix, string $controller, string $projectPath): array
    {
        $actions = [
            ['GET', $prefix, 'index', '列表'],
            ['GET', $prefix . '/:id', 'read', '详情'],
            ['POST', $prefix, 'save', '新增'],
            ['PUT', $prefix . '/:id', 'update', '更新'],
            ['DELETE', $prefix . '/:id', 'delete', '删除'],
        ];

        $endpoints = [];
        foreach ($actions as [$method, $path, $action, $fallbackTitle]) {
            $target = $controller . '/' . $action;
            $doc = $this->readControllerDoc($target, $projectPath);
            $endpoints[] = new ApiEndpoint(
                $method,
                $this->normalizePath($path),
                $doc['title'] ?: $fallbackTitle,
                $target,
                $this->groupFromPath($path),
                $doc['description'],
                [],
                $doc['parameters'],
            );
        }

        return $endpoints;
    }

    /**
     * @return array{title:string,description:string,parameters:array<int, array{name:string,in:string,required:bool,type:string,description:string}>}
     */
    private function readControllerDoc(string $controller, string $projectPath): array
    {
        [$class, $method] = $this->splitController($controller);
        if ($class === '' || $method === '') {
            return ['title' => '', 'description' => '', 'parameters' => []];
        }

        $file = $this->controllerToFile($class, $projectPath);
        if (!is_file($file)) {
            return ['title' => '', 'description' => '', 'parameters' => []];
        }

        $content = (string) file_get_contents($file);
        $quoted = preg_quote($method, '/');
        if (!preg_match('/public\\s+function\\s+' . $quoted . '\\s*\\(/i', $content, $match, PREG_OFFSET_CAPTURE)) {
            return ['title' => '', 'description' => '', 'parameters' => []];
        }

        $beforeMethod = substr($content, 0, $match[0][1]);
        $docStart = strrpos($beforeMethod, '/**');
        $docEnd = strrpos($beforeMethod, '*/');

        if ($docStart === false || $docEnd === false || $docEnd < $docStart) {
            return ['title' => '', 'description' => '', 'parameters' => []];
        }

        $docBlock = substr($beforeMethod, $docStart + 3, $docEnd - $docStart - 3);
        $lines = preg_split('/\\r\\n|\\r|\\n/', trim($docBlock)) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/^\\s*\\*\\s?/', '', $line));
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        $title = '';
        $description = [];
        $parameters = [];

        foreach ($clean as $line) {
            if (str_starts_with($line, '@param')) {
                $parameter = $this->parseParamDoc($line);
                if ($parameter !== null) {
                    $parameters[] = $parameter;
                }
                continue;
            }

            if (str_starts_with($line, '@')) {
                continue;
            }

            if ($title === '') {
                $title = $line;
            } else {
                $description[] = $line;
            }
        }

        return [
            'title' => $title,
            'description' => implode("\n", $description),
            'parameters' => $parameters,
        ];
    }

    /**
     * @return array{name:string,in:string,required:bool,type:string,description:string}|null
     */
    private function parseParamDoc(string $line): ?array
    {
        if (!preg_match('/@param\\s+([\\w\\[\\]|]+)\\s+\\$?([\\w.]+)\\s*(.*)$/', $line, $match)) {
            return null;
        }

        return [
            'name' => $match[2],
            'in' => 'query',
            'required' => !str_contains(strtolower($match[3] ?? ''), 'optional'),
            'type' => $this->normalizeType($match[1]),
            'description' => trim($match[3] ?? ''),
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = preg_replace('/<([a-zA-Z_][a-zA-Z0-9_]*)>/', ':$1', $path) ?? $path;
        $path = '/' . trim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function normalizeController(string $handler): string
    {
        return str_replace(['@', '\\\\'], ['/', '\\'], trim($handler));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitController(string $controller): array
    {
        if (str_contains($controller, '/')) {
            [$class, $method] = explode('/', $controller, 2);
            return [$class, $method];
        }

        return ['', ''];
    }

    private function controllerToFile(string $class, string $projectPath): string
    {
        $class = ltrim(str_replace('\\', '/', $class), '/');
        if (str_starts_with($class, 'app/')) {
            return $projectPath . '/' . $class . '.php';
        }

        return $projectPath . '/app/controller/' . $class . '.php';
    }

    private function titleFromPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        return $segments ? ucfirst((string) end($segments)) : 'API';
    }

    private function groupFromPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        return $segments[0] ?? 'Default';
    }

    /**
     * @return string[]
     */
    private function middlewaresFromRoute(string $chain): array
    {
        if (!preg_match('/middleware\\s*\\(\\s*([^)]+)\\)/i', $chain, $match)) {
            return [];
        }

        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $match[1], $middlewareMatches);
        return $middlewareMatches[1] ?? [];
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type, '[]|'));
        return match ($type) {
            'int', 'integer' => 'integer',
            'bool', 'boolean' => 'boolean',
            'float', 'double', 'decimal' => 'number',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * @param ApiEndpoint[] $endpoints
     * @return ApiEndpoint[]
     */
    private function uniqueEndpoints(array $endpoints): array
    {
        $seen = [];
        $unique = [];

        foreach ($endpoints as $endpoint) {
            $key = $endpoint->method . ' ' . $endpoint->path;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $endpoint;
        }

        return $unique;
    }
}
