<?php

declare(strict_types=1);

namespace Route2Api\Scanner\ThinkPHP;

final class ControllerAnalyzer
{
    /**
     * @return array<int, array{name:string,in:string,required:bool,type:string,description:string}>
     */
    public function extractParameters(string $methodBody, string $httpMethod): array
    {
        $parameters = [];
        $arrayVars = $this->arrayAssignments($methodBody);
        $inputVars = $this->inputVariables($methodBody);

        foreach ($this->requiredFields($methodBody, $arrayVars) as $name) {
            $parameters[] = $this->parameter($name, $this->inputLocation($httpMethod), true, '', 'Inferred from required field validation.');
        }

        foreach ($this->inputArrayFields($methodBody, $inputVars) as $name) {
            $parameters[] = $this->parameter($name, $this->inputLocation($httpMethod), false, '', 'Inferred from controller input.');
        }

        foreach ($this->allowedArrayFields($methodBody, $arrayVars, $inputVars) as $name) {
            $parameters[] = $this->parameter($name, $this->inputLocation($httpMethod), false, '', 'Allowed update field.');
        }

        foreach ($this->requestAccessorFields($methodBody) as $field) {
            $parameters[] = $this->parameter($field['name'], $field['in'], false, $field['type'], 'Inferred from request accessor.');
        }

        foreach ($this->inlineValidationRules($methodBody) as $field) {
            $parameters[] = $this->parameter($field['name'], $this->inputLocation($httpMethod), $field['required'], $field['type'], 'Inferred from validation rule.');
        }

        if (strpos($methodBody, 'queryParams()') !== false) {
            foreach ($this->commonQueryParameters() as $parameter) {
                $parameters[] = $parameter;
            }
        }

        return $this->mergeParameters($parameters);
    }

    /**
     * @return string[]
     */
    private function inputVariables(string $methodBody): array
    {
        $vars = [];

        preg_match_all('/\\$(\\w+)\\s*=\\s*\\$this->input\\s*\\(\\s*\\)\\s*;/', $methodBody, $matches);
        $vars = array_merge($vars, $matches[1] ?? []);

        preg_match_all('/\\$(\\w+)\\s*=\\s*\\$this->\\w*Input\\s*\\(\\s*\\$this->input\\s*\\(\\s*\\)\\s*\\)\\s*;/', $methodBody, $matches);
        $vars = array_merge($vars, $matches[1] ?? []);

        if (strpos($methodBody, '$this->input()') !== false) {
            $vars[] = '__direct_input__';
        }

        return array_values(array_unique($vars));
    }

    /**
     * @return array<string, string[]>
     */
    private function arrayAssignments(string $methodBody): array
    {
        preg_match_all('/\\$(\\w+)\\s*=\\s*\\[([^\\]]*)\\]\\s*;/s', $methodBody, $matches, PREG_SET_ORDER);
        $arrays = [];

        foreach ($matches as $match) {
            $values = $this->stringsFromArrayLiteral($match[2]);
            if ($values !== []) {
                $arrays[$match[1]] = $values;
            }
        }

        return $arrays;
    }

    /**
     * @param array<string, string[]> $arrayVars
     * @return string[]
     */
    private function requiredFields(string $methodBody, array $arrayVars): array
    {
        $fields = [];

        preg_match_all('/requireFields\\s*\\(\\s*\\$\\w+\\s*,\\s*\\[([^\\]]*)\\]\\s*\\)/s', $methodBody, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $fields = array_merge($fields, $this->stringsFromArrayLiteral($match[1]));
        }

        preg_match_all('/requireFields\\s*\\(\\s*\\$\\w+\\s*,\\s*\\$(\\w+)\\s*\\)/', $methodBody, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $fields = array_merge($fields, $arrayVars[$match[1]] ?? []);
        }

        return array_values(array_unique($fields));
    }

    /**
     * @param string[] $inputVars
     * @return string[]
     */
    private function inputArrayFields(string $methodBody, array $inputVars): array
    {
        $fields = [];

        foreach ($inputVars as $var) {
            if ($var === '__direct_input__') {
                preg_match_all('/\\$this->input\\s*\\(\\s*\\)\\s*\\[\\s*[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*\\](?!\\s*=)/', $methodBody, $matches);
                $fields = array_merge($fields, $matches[1] ?? []);
                continue;
            }

            $quoted = preg_quote($var, '/');
            $patterns = [
                '/\\$' . $quoted . '\\s*\\[\\s*[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*\\](?!\\s*=)/',
                '/array_key_exists\\s*\\(\\s*[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*,\\s*\\$' . $quoted . '\\s*\\)/',
                '/isset\\s*\\(\\s*\\$' . $quoted . '\\s*\\[\\s*[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*\\]\\s*\\)/',
                '/empty\\s*\\(\\s*\\$' . $quoted . '\\s*\\[\\s*[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*\\]\\s*\\)/',
            ];

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $methodBody, $matches);
                $fields = array_merge($fields, $matches[1] ?? []);
            }
        }

        return array_values(array_unique($this->filterFieldNames($fields)));
    }

    /**
     * @param array<string, string[]> $arrayVars
     * @param string[] $inputVars
     * @return string[]
     */
    private function allowedArrayFields(string $methodBody, array $arrayVars, array $inputVars): array
    {
        $fields = [];

        foreach ($inputVars as $inputVar) {
            if ($inputVar === '__direct_input__') {
                continue;
            }

            $quotedInputVar = preg_quote($inputVar, '/');
            preg_match_all('/foreach\\s*\\(\\s*\\$(\\w+)\\s+as\\s+\\$(\\w+)\\s*\\).*?array_key_exists\\s*\\(\\s*\\$\\2\\s*,\\s*\\$' . $quotedInputVar . '\\s*\\)/s', $methodBody, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $fields = array_merge($fields, $arrayVars[$match[1]] ?? []);
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return array<int, array{name:string,in:string,type:string}>
     */
    private function requestAccessorFields(string $methodBody): array
    {
        preg_match_all('/\\$this->request->(get|post|put|param)\\s*\\(\\s*[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*(?:,\\s*([^\\)]*))?\\)/', $methodBody, $matches, PREG_SET_ORDER);
        $fields = [];

        foreach ($matches as $match) {
            $accessor = $match[1];
            $name = $match[2];
            if ($this->isInternalField($name)) {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'in' => $accessor === 'get' ? 'query' : 'body',
                'type' => $this->typeFromDefault($match[3] ?? ''),
            ];
        }

        return $fields;
    }

    /**
     * @return array<int, array{name:string,required:bool,type:string}>
     */
    private function inlineValidationRules(string $methodBody): array
    {
        preg_match_all('/validate\\s*\\(\\s*\\$\\w+\\s*,\\s*\\[([^\\]]*)\\]\\s*\\)/s', $methodBody, $matches, PREG_SET_ORDER);
        $fields = [];

        foreach ($matches as $match) {
            preg_match_all('/[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]\\s*=>\\s*[\'"]([^\'"]+)[\'"]/', $match[1], $ruleMatches, PREG_SET_ORDER);
            foreach ($ruleMatches as $ruleMatch) {
                $fields[] = [
                    'name' => $ruleMatch[1],
                    'required' => strpos($ruleMatch[2], 'require') !== false,
                    'type' => $this->typeFromRule($ruleMatch[2]),
                ];
            }
        }

        return $fields;
    }

    /**
     * @return string[]
     */
    private function stringsFromArrayLiteral(string $literal): array
    {
        preg_match_all('/[\'"]([A-Za-z_][A-Za-z0-9_.]*)[\'"]/', $literal, $matches);
        return $this->filterFieldNames($matches[1] ?? []);
    }

    /**
     * @param string[] $fields
     * @return string[]
     */
    private function filterFieldNames(array $fields): array
    {
        return array_values(array_filter(array_unique($fields), function (string $field): bool {
            return !$this->isInternalField($field);
        }));
    }

    private function isInternalField(string $field): bool
    {
        return strncmp($field, '__', 2) === 0 || in_array($field, ['password_hash', 'token_hash'], true);
    }

    private function inputLocation(string $httpMethod): string
    {
        return in_array(strtoupper($httpMethod), ['POST', 'PUT', 'PATCH'], true) ? 'body' : 'query';
    }

    /**
     * @return array<int, array{name:string,in:string,required:bool,type:string,description:string}>
     */
    private function commonQueryParameters(): array
    {
        return [
            $this->parameter('page', 'query', false, 'integer', 'Page number.'),
            $this->parameter('page_size', 'query', false, 'integer', 'Page size.'),
            $this->parameter('paginate', 'query', false, 'boolean', 'Enable pagination.'),
        ];
    }

    /**
     * @return array{name:string,in:string,required:bool,type:string,description:string}
     */
    private function parameter(string $name, string $in, bool $required, string $type = '', string $description = ''): array
    {
        return [
            'name' => $name,
            'in' => $in,
            'required' => $required,
            'type' => $type !== '' ? $type : $this->typeFromName($name),
            'description' => $description,
        ];
    }

    private function typeFromName(string $name): string
    {
        if ($this->endsWith($name, '_ids') || in_array($name, ['ids', 'resources', 'experimenters'], true)) {
            return 'array';
        }

        if ($name === 'id' || $this->endsWith($name, '_id') || in_array($name, ['page', 'page_size'], true)) {
            return 'integer';
        }

        if (strncmp($name, 'is_', 3) === 0 || in_array($name, ['status', 'paginate', 'refresh'], true)) {
            return $name === 'status' ? 'string' : 'boolean';
        }

        if (in_array($name, ['value', 'quantity', 'amount', 'price'], true) || $this->endsWith($name, '_value')) {
            return 'number';
        }

        return 'string';
    }

    private function typeFromDefault(string $default): string
    {
        $default = trim($default);
        if ($default === '') {
            return '';
        }

        if (in_array(strtolower($default), ['true', 'false'], true)) {
            return 'boolean';
        }

        if (is_numeric($default)) {
            return strpos($default, '.') !== false ? 'number' : 'integer';
        }

        if (strncmp($default, '[', 1) === 0) {
            return 'array';
        }

        return '';
    }

    private function typeFromRule(string $rule): string
    {
        $rule = strtolower($rule);
        if (strpos($rule, 'integer') !== false || strpos($rule, 'number') !== false) {
            return 'integer';
        }
        if (strpos($rule, 'float') !== false || strpos($rule, 'double') !== false) {
            return 'number';
        }
        if (strpos($rule, 'array') !== false) {
            return 'array';
        }
        if (strpos($rule, 'bool') !== false) {
            return 'boolean';
        }

        return 'string';
    }

    private function endsWith(string $value, string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return substr($value, -strlen($suffix)) === $suffix;
    }

    /**
     * @param array<int, array{name:string,in:string,required:bool,type:string,description:string}> $parameters
     * @return array<int, array{name:string,in:string,required:bool,type:string,description:string}>
     */
    private function mergeParameters(array $parameters): array
    {
        $merged = [];

        foreach ($parameters as $parameter) {
            $key = $parameter['in'] . ':' . $parameter['name'];
            if (!isset($merged[$key])) {
                $merged[$key] = $parameter;
                continue;
            }

            $merged[$key]['required'] = $merged[$key]['required'] || $parameter['required'];
            if ($merged[$key]['description'] === '' && $parameter['description'] !== '') {
                $merged[$key]['description'] = $parameter['description'];
            }
            if ($merged[$key]['type'] === 'string' && $parameter['type'] !== 'string') {
                $merged[$key]['type'] = $parameter['type'];
            }
        }

        return array_values($merged);
    }
}
