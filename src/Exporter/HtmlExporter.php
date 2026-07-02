<?php

declare(strict_types=1);

namespace Route2Api\Exporter;

use Route2Api\IR\ApiEndpoint;
use Route2Api\IR\ApiProject;

final class HtmlExporter
{
    public function export(ApiProject $project): string
    {
        $groups = [];
        foreach ($project->endpoints as $endpoint) {
            $groups[$endpoint->group][] = $endpoint;
        }

        $sections = '';
        foreach ($groups as $group => $endpoints) {
            $sections .= '<section class="group"><h2>' . $this->escape($group) . '</h2>';
            foreach ($endpoints as $endpoint) {
                $sections .= $this->endpoint($endpoint);
            }
            $sections .= '</section>';
        }

        $title = $this->escape($project->name);
        $baseUrl = $this->escape($project->baseUrl);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} API</title>
  <style>
    body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f6f8fb; }
    header { padding: 32px 40px; background: #172033; color: #fff; }
    header h1 { margin: 0 0 8px; font-size: 30px; }
    header code { color: #dbe7ff; }
    main { max-width: 1100px; margin: 0 auto; padding: 28px 20px 48px; }
    .group { margin-bottom: 28px; }
    .endpoint { background: #fff; border: 1px solid #e1e7f0; border-radius: 8px; padding: 18px; margin: 14px 0; }
    .endpoint h3 { margin: 0 0 12px; font-size: 19px; }
    .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .badge { display: inline-flex; align-items: center; min-height: 24px; padding: 0 9px; border-radius: 5px; background: #eef3fb; color: #263d61; font-size: 13px; }
    .method { background: #166534; color: #fff; font-weight: 700; }
    pre { overflow: auto; padding: 12px; border-radius: 6px; background: #101827; color: #e8eefb; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 9px; border-bottom: 1px solid #e1e7f0; text-align: left; }
    th { color: #4d5f7a; font-size: 13px; }
  </style>
</head>
<body>
  <header>
    <h1>{$title}</h1>
    <div>Base URL: <code>{$baseUrl}</code></div>
  </header>
  <main>{$sections}</main>
</body>
</html>
HTML;
    }

    private function endpoint(ApiEndpoint $endpoint): string
    {
        $description = $endpoint->description !== '' ? '<p>' . nl2br($this->escape($endpoint->description)) . '</p>' : '';
        $controller = $endpoint->controller !== '' ? '<span class="badge">Controller: ' . $this->escape($endpoint->controller) . '</span>' : '';
        $middlewares = $endpoint->middlewares !== [] ? '<span class="badge">Middlewares: ' . $this->escape(implode(', ', $endpoint->middlewares)) . '</span>' : '';
        $parameters = $this->parameters($endpoint);

        return '<article class="endpoint">'
            . '<h3>' . $this->escape($endpoint->title) . '</h3>'
            . '<div class="meta"><span class="badge method">' . $this->escape($endpoint->method) . '</span><span class="badge">' . $this->escape($endpoint->path) . '</span>' . $controller . $middlewares . '</div>'
            . $description
            . $parameters
            . '</article>';
    }

    private function parameters(ApiEndpoint $endpoint): string
    {
        if ($endpoint->parameters === []) {
            return '';
        }

        $rows = '';
        foreach ($endpoint->parameters as $parameter) {
            $rows .= '<tr>'
                . '<td><code>' . $this->escape($parameter['name']) . '</code></td>'
                . '<td>' . $this->escape($parameter['in']) . '</td>'
                . '<td>' . $this->escape($parameter['type']) . '</td>'
                . '<td>' . ($parameter['required'] ? 'Yes' : 'No') . '</td>'
                . '<td>' . $this->escape($parameter['description']) . '</td>'
                . '</tr>';
        }

        return '<table><thead><tr><th>Name</th><th>In</th><th>Type</th><th>Required</th><th>Description</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
