# Route2API

Route2API is an open-source API documentation generator for ThinkPHP projects.
It scans routes and controller comments, then exports OpenAPI JSON and Markdown documents.

中文：Route2API 是一个面向 ThinkPHP 项目的接口文档自动生成工具。第一版支持扫描 `route/*.php`、读取控制器方法注释，并导出 OpenAPI JSON 和 Markdown。

## Install

During local development:

```bash
git clone https://github.com/your-name/route2api.git
cd route2api
composer install
```

Install in a ThinkPHP project after publishing to Packagist:

```bash
composer require route2api/route2api --dev
```

## Usage

Run inside a ThinkPHP project:

```bash
vendor/bin/route2api scan --framework=thinkphp
```

Scan another project path:

```bash
vendor/bin/route2api scan \
  --path=/www/wwwroot/demo \
  --framework=thinkphp \
  --output=docs/api \
  --format=openapi,markdown
```

Generated files:

```text
docs/api/openapi.json
docs/api/api.md
```

## Supported Route Syntax

```php
Route::get('user/:id', 'User/read');
Route::post('user/login', 'User/login')->middleware('auth');
Route::group('api', function () {
    Route::get('user/:id', 'User/read');
});
Route::resource('users', 'User');
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

## Output Formats

First version:

- OpenAPI 3.0 JSON
- Markdown

Planned:

- OpenAPI YAML
- Postman Collection v2.1
- HTML static document
- Laravel scanner
- Manual YAML override file

## Publish

1. Create a GitHub repository, for example `route2api`.
2. Push this project to GitHub.
3. Create an account on [Packagist](https://packagist.org/).
4. Submit your GitHub repository URL to Packagist.
5. After Packagist indexes the package, users can install it with Composer.

Recommended package name:

```text
route2api/route2api
```

## License

MIT
