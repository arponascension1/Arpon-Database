<?php

namespace Arpon\Database;

class ConfigurationUrlParser
{
    /**
     * The drivers aliases map.
     *
     * @var array
     */
    protected static $driverAliases = [
        'mssql' => 'sqlsrv',
        'mysql2' => 'mysql', // RDS
        'postgres' => 'pgsql',
        'postgresql' => 'pgsql',
        'sqlite3' => 'sqlite',
        'redis' => 'redis',
        'rediss' => 'redis',
    ];

    /**
     * Parse the database configuration, hydrating options using a database configuration URL if possible.
     *
     * @param  array  $config
     * @return array
     */
    public function parseConfiguration($config)
    {
        if (empty($config['url'])) {
            return $config;
        }

        $url = parse_url($config['url']);

        if ($url === false || ! isset($url['scheme'])) {
            throw new \InvalidArgumentException('The database configuration URL is malformed.');
        }

        return $this->getPrimaryOptions($url, $config);
    }

    /**
     * Get the primary database connection options.
     *
     * @param  array  $url
     * @param  array  $config
     * @return array
     */
    protected function getPrimaryOptions($url, $config)
    {
        return array_merge($config, $this->parseComponents($url, $config), [
            'driver' => $this->getDriver($url),
            'database' => $this->getDatabase($url, $config),
        ]);
    }

    /**
     * Get the database driver from the URL.
     *
     * @param  array  $url
     * @return string|null
     */
    protected function getDriver($url)
    {
        $alias = $url['scheme'] ?? null;

        if (! $alias) {
            return;
        }

        return static::$driverAliases[$alias] ?? $alias;
    }

    /**
     * Get the database name from the URL.
     *
     * @param  array  $url
     * @param  array  $config
     * @return string|null
     */
    protected function getDatabase($url, $config)
    {
        $path = $url['path'] ?? null;

        if (! $path) {
            return $config['database'] ?? null;
        }

        return substr($path, 1);
    }

    /**
     * Get all of the additional database options from the query string.
     *
     * @param  array  $url
     * @param  array  $config
     * @return array
     */
    protected function parseComponents($url, $config)
    {
        return array_merge([
            'host' => $url['host'] ?? null,
            'port' => $url['port'] ?? null,
            'username' => $url['user'] ?? null,
            'password' => $url['pass'] ?? null,
        ], $this->parseStringsToNativeTypes(
            $this->parseQueryString($url)
        ));
    }

    /**
     * Parse the query string into an array.
     *
     * @param  array  $url
     * @return array
     */
    protected function parseQueryString($url)
    {
        $queryString = $url['query'] ?? '';

        parse_str($queryString, $queryArray);

        return $queryArray;
    }

    /**
     * Convert string casted values to their native types.
     *
     * @param  array  $value
     * @return array
     */
    protected function parseStringsToNativeTypes($value)
    {
        return array_map(function ($value) {
            return $this->parseStringToNativeType($value);
        }, $value);
    }

    /**
     * Convert a string casted value to its native type.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function parseStringToNativeType($value)
    {
        if (is_string($value)) {
            $lower = strtolower($value);

            if (in_array($lower, ['true', '(true)'])) {
                return true;
            } elseif (in_array($lower, ['false', '(false)'])) {
                return false;
            } elseif (in_array($lower, ['empty', '(empty)'])) {
                return '';
            } elseif (in_array($lower, ['null', '(null)'])) {
                return;
            }
        }

        return $value;
    }
}