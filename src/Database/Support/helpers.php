<?php

if (! function_exists('collect')) {
    function collect($items = []) {
        return new \Arpon\Database\Support\Collection($items);
    }
}

if (! function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($haystack, $n) !== false) {
                    return true;
                }
            }
            return false;
        }
        return strpos($haystack, $needle) !== false;
    }
}

if (! function_exists('str_replace_array')) {
    function str_replace_array($search, array $replace, $subject) {
        $segments = explode($search, $subject);
        $result = array_shift($segments);

        foreach ($segments as $segment) {
            $result .= (array_shift($replace) ?? $search) . $segment;
        }

        return $result;
    }
}

if (! function_exists('tap')) {
    function tap($value, $callback) {
        $callback($value);
        return $value;
    }
}

if (! function_exists('array_add')) {
    function array_add($array, $key, $value) {
        if (is_null($array[$key] ?? null)) {
            $array[$key] = $value;
        }
        return $array;
    }
}

if (!function_exists('data_get')) {
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (! is_array($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = data_get($item, $key);
                }

                return in_array('*', $key) ? array_collapse($result) : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('value')) {
    function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }
}

if (! function_exists('array_except')) {
    function array_except($array, $keys) {
        return array_diff_key($array, array_flip((array) $keys));
    }
}

if (! function_exists('array_wrap')) {
    function array_wrap($value) {
        if (is_null($value)) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }
}

if (! function_exists('array_shuffle')) {
    function array_shuffle($array) {
        shuffle($array);
        return $array;
    }
}

if (! function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if (substr($haystack, -strlen($needle)) === (string) $needle) {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('array_flatten')) {
    function array_flatten($array, $depth = INF) {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                $result = array_merge($result, array_flatten($item, $depth - 1));
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }
}

if (! function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

if (! function_exists('snake_case')) {
    function snake_case($value) {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}

if (! function_exists('head')) {
    function head($array) {
        return reset($array);
    }
}

if (! function_exists('last')) {
    function last($array) {
        return end($array);
    }
}

if (! function_exists('str_plural')) {
    function str_plural(string $value): string {
        // Simple pluralization - add 's' if doesn't end with 's', 'x', 'z', 'ch', 'sh'
        if (preg_match('/[sxz]$|[^aeioudgkprt]h$/', $value)) {
            return $value . 'es';
        } elseif (preg_match('/[^aeiou]y$/', $value)) {
            return substr($value, 0, -1) . 'ies';
        }
        return $value . 's';
    }
}