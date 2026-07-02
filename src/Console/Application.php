<?php

declare(strict_types=1);

namespace Route2Api\Console;

use Route2Api\Exporter\MarkdownExporter;
use Route2Api\Exporter\OpenApiExporter;
use Route2Api\Scanner\ThinkPHP\ThinkPHPScanner;
use Throwable;

final class Application
{
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        try {
            return match ($command) {
                'scan' => $this->scan(array_slice($argv, 2)),
                'init' => $this->init(array_slice($argv, 2)),
                default => $this->help(),
            };
        } catch (Throwable $exception) {
            fwrite(STDERR, 'Route2API error: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }
    }

    private function scan(array $args): int
    {
        $options = $this->parseOptions($args);
        $projectPath = $options['path'] ?? getcwd();
        $framework = strtolower((string) ($options['framework'] ?? 'thinkphp'));
        $outputDir = $this->absolutePath((string) ($options['output'] ?? $projectPath . '/route2api'), $projectPath);
        $formats = array_filter(array_map('trim', explode(',', (string) ($options['format'] ?? 'openapi,markdown'))));

        if ($framework !== 'thinkphp') {
            throw new \InvalidArgumentException('Only thinkphp is supported in v0.1.0.');
        }

        if (!is_dir($projectPath)) {
            throw new \InvalidArgumentException("Project path does not exist: {$projectPath}");
        }

        $project = (new ThinkPHPScanner())->scan($projectPath);

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new \RuntimeException("Cannot create output directory: {$outputDir}");
        }

        $generated = [];
        foreach ($formats as $format) {
            if ($format === 'openapi' || $format === 'openapi-json') {
                $file = $outputDir . '/openapi.json';
                file_put_contents($file, (new OpenApiExporter())->exportJson($project));
                $generated[] = $file;
            }

            if ($format === 'markdown' || $format === 'md') {
                $file = $outputDir . '/api.md';
                file_put_contents($file, (new MarkdownExporter())->export($project));
                $generated[] = $file;
            }
        }

        echo 'Scanned endpoints: ' . count($project->endpoints) . PHP_EOL;
        foreach ($generated as $file) {
            echo 'Generated: ' . $file . PHP_EOL;
        }

        return 0;
    }

    private function init(array $args): int
    {
        $options = $this->parseOptions($args);
        $projectPath = $options['path'] ?? getcwd();
        $file = $projectPath . '/route2api.yaml';

        if (is_file($file)) {
            echo "Already exists: {$file}" . PHP_EOL;
            return 0;
        }

        $content = <<<YAML
project:
  name: Demo API
  base_url: http://localhost

framework: thinkphp

scan:
  routes:
    - route/*.php
  controllers:
    - app/controller

output:
  dir: route2api
  formats:
    - openapi
    - markdown

YAML;

        file_put_contents($file, $content);
        echo "Created: {$file}" . PHP_EOL;

        return 0;
    }

    private function help(): int
    {
        echo <<<TXT
Route2API v0.1.0

Usage:
  route2api init [--path=/path/to/project]
  route2api scan [--path=/path/to/project] [--framework=thinkphp] [--output=route2api] [--format=openapi,markdown]

Examples:
  vendor/bin/route2api scan --framework=thinkphp
  vendor/bin/route2api scan --path=/www/wwwroot/demo --output=docs/api --format=openapi,markdown

TXT;

        return 0;
    }

    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }

            $pair = explode('=', substr($arg, 2), 2);
            $options[$pair[0]] = $pair[1] ?? true;
        }

        return $options;
    }

    private function absolutePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . $path;
    }
}
