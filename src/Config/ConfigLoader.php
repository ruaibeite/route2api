<?php

declare(strict_types=1);

namespace Route2Api\Config;

final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(?string $file): array
    {
        if ($file === null || !is_file($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $config = [];
        $section = null;
        $listKey = null;

        foreach ($lines as $line) {
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if ($indent === 0 && preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\\s*$/', $trimmed, $match) === 1) {
                $section = $match[1];
                $listKey = null;
                $config[$section] ??= [];
                continue;
            }

            if ($indent === 0 && preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\\s*(.+)$/', $trimmed, $match) === 1) {
                $config[$match[1]] = $this->scalar($match[2]);
                $listKey = null;
                continue;
            }

            if ($section !== null && preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\\s*(.*)$/', $trimmed, $match) === 1) {
                if ($match[2] === '') {
                    $listKey = $match[1];
                    $config[$section][$listKey] = [];
                    continue;
                }

                $config[$section][$match[1]] = $this->scalar($match[2]);
                $listKey = null;
                continue;
            }

            if (preg_match('/^-\\s*(.+)$/', $trimmed, $match) === 1 && $section !== null && $listKey !== null) {
                $config[$section][$listKey][] = $this->scalar($match[1]);
            }
        }

        return $config;
    }

    private function scalar(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B\"'");
    }
}
