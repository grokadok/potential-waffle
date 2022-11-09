<?php

// source: Laravel Framework
// https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/Str.php
if (!function_exists("str_contains")) {
    // < php 8
    function str_contains($haystack, $needle)
    {
        return $needle !== "" && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists("str_ends_with")) {
    // < php 8
    function str_ends_with($haystack, $needle)
    {
        return $needle !== "" &&
            substr($haystack, -strlen($needle)) === (string) $needle;
    }
}
if (!function_exists("str_starts_with")) {
    // < php 8
    function str_starts_with($haystack, $needle)
    {
        return (string) $needle !== "" &&
            strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
/**
 * Adds value if not in array nor null.
 * @param mixed $needle Value to add
 * @param array $haystack Array to add value to
 */
function addIfNotNullNorExists($needle, $haystack)
{
    if (isset($needle) && !in_array($needle, $haystack, true)) {
        $haystack[] = $needle;
        return $haystack;
    }
}
/**
 * Filters multi dimensional associative array keys according to provided reference array of keys;
 * @param array $arrayAssoc The array to filter keys from.
 * @param array $ref The array of keys to match.
 */
function arrayAssocFilterKeys($arrayAssoc, $ref)
{
    $newArray = array_filter(
        $arrayAssoc,
        function ($k) use ($ref) {
            return array_key_exists($k, $ref);
        },
        ARRAY_FILTER_USE_KEY
    );
    foreach ($newArray as $k => $v) {
        if (is_array($v)) {
            $newArray[$k] = array_filter(
                $v,
                function ($k) use ($ref) {
                    return array_key_exists($k, $ref);
                },
                ARRAY_FILTER_USE_KEY
            );
        }
    }
    foreach ($newArray as $k => $v) {
        if (is_array($v)) {
            $newArray[$k] = arrayAssocFilterKeys($v, $ref);
        }
    }
    return $newArray;
}
