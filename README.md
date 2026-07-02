# Route2API

Route2API is an open-source API documentation generator for PHP projects. The first version focuses on ThinkPHP: it scans routes and controller comments, then exports OpenAPI, Postman Collection, Markdown, and HTML documents.

中文：Route2API 是一个面向 PHP 项目的接口文档自动生成工具。第一版重点支持 ThinkPHP，可以扫描路由和控制器注释，生成 OpenAPI、Postman Collection、Markdown、HTML 文档，方便导入 Apifox、ApiPost、Postman、Swagger UI、Redoc 等工具。

## Features

- Scan ThinkPHP `route/*.php`
- Detect `Route::get/post/put/delete/patch/any`
- Detect simple `Route::group`
- Expand `Route::resource`
- Read controller method PHPDoc
- Export OpenAPI 3.0 JSON
- Export OpenAPI 3.0 YAML
- Export Postman Collection v2.1 JSON
- Export Markdown
- Export static HTML
- Generate a starter `route2api.yaml`

## Install

Install in a ThinkPHP project:

```bash
composer require route2api/route2api --dev
```

Use the development version directly from GitHub before Packagist release:

```bash
composer config repositories.route2api vcs https://github.com/ruaibeite/route2api.git
composer require route2api/route2api:dev-main --dev
```

Or run locally while developing Route2API:

```bash
git clone https://github.com/ruaibeite/route2api.git
cd route2api
php bin/route2api
```

## Quick Start

Run inside a ThinkPHP project:

```bash
vendor/bin/route2api scan --framework=thinkphp
```

Generate all supported formats:

```bash
vendor/bin/route2api scan \
  --framework=thinkphp \
  --output=docs/api \
  --format=openapi,yaml,postman,markdown,html
```

Generated files:

```text
docs/api/openapi.json
docs/api/openapi.yaml
docs/api/postman_collection.json
docs/api/api.md
docs/api/index.html
```

## Configuration

Create a starter config:

```bash
vendor/bin/route2api init
```

This creates `route2api.yaml`:

```yaml
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
    - yaml
    - postman
    - markdown
    - html
```

Then run:

```bash
vendor/bin/route2api scan
```

CLI options can override config values:

```bash
vendor/bin/route2api scan \
  --name="My API" \
  --base-url="https://api.example.com" \
  --output=docs/api
```

## Supported ThinkPHP Route Syntax

```php
use think\facade\Route;

Route::get('user/:id', 'User/read');
Route::post('user/login', 'User/login')->middleware('auth');

Route::group('api', function () {
    Route::get('profile', 'Profile/read');
});

Route::resource('articles', 'Article');
```

## Controller Comments

Route2API reads the PHPDoc above controller methods:

```php
/**
 * 用户登录
 * 使用账号密码登录。
 *
 * @param string username 用户名
 * @param string password 密码
 */
public function login()
{
    // ...
}
```

`@param` is converted into request parameters in OpenAPI, Markdown, HTML, and Postman output.

## Import Into API Tools

Apifox, ApiPost, Postman, Swagger UI, and Redoc can consume the generated output.

- Apifox: import `openapi.json`, `openapi.yaml`, or `postman_collection.json`
- ApiPost: import `openapi.json`, `openapi.yaml`, or `postman_collection.json`
- Postman: import `postman_collection.json` or `openapi.json`
- Swagger UI / Redoc: use `openapi.json` or `openapi.yaml`

## Current Limitations

Route2API v0.1 is intentionally small. It does not try to infer every request field from arbitrary PHP code.

- Complex nested route groups are not fully parsed yet
- Request body schemas are basic
- Validator and model field scanning are planned
- Laravel support is planned
- Manual endpoint overrides are planned

The recommended workflow is: scan code first, then use the generated OpenAPI or Markdown as the draft API document.

## Roadmap

- ThinkPHP validator rule extraction
- Request body schema generation
- Manual endpoint override file
- Laravel route and FormRequest scanner
- HTML theme improvements
- CI command for regenerating docs
- Optional web UI

## Development

Run the smoke test:

```bash
tests/smoke.sh
```

Run syntax checks:

```bash
find . -name '*.php' -exec php -l {} \;
```

## Publish To Packagist

1. Push this repository to GitHub.
2. Create an account on [Packagist](https://packagist.org/).
3. Submit `https://github.com/ruaibeite/route2api`.
4. Enable GitHub hook or auto-update in Packagist.

After Packagist indexes the package, users can install it with:

```bash
composer require route2api/route2api --dev
```

## License

MIT
