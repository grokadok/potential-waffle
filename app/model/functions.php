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

/**
 * If is gmail.com email address, returns the cleaned from periods address.
 */
function gmailNoPeriods(String $string)
{
    if (substr($string, -9) === 'gmail.com') {
        $email = explode('@', $string);
        $noDots = str_replace('.', '', $email[0]);
        $emailFinal = $noDots . '@' . $email[1];
    }
    return $emailFinal ?? $string;
}

function getFileExtensionFromContentType($contentType)
{
    $mimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        // Add any other image formats you want to support
    ];

    return isset($mimeTypes[$contentType]) ? $mimeTypes[$contentType] : null;
}

function getFileContentAndExtension($url)
{
    // Initialize a cURL session
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
    curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in the response

    // Execute the cURL session and get the response
    $response = curl_exec($ch);

    // Check for errors
    if ($response === false) {
        echo 'Error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    // Get the header size
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    // Close the cURL session
    curl_close($ch);

    // Split the response into headers and content
    $headers = substr($response, 0, $headerSize);
    $content = substr($response, $headerSize);

    // Get the Content-Type header
    preg_match('/Content-Type:\s*([\w\/]+);?/i', $headers, $contentTypeMatches);
    $contentType = $contentTypeMatches[1];

    // Get the file extension based on the Content-Type header
    $fileExtension = getFileExtensionFromContentType($contentType);

    return ['content' => $content, 'extension' => $fileExtension];
}

/**
 * Sort an associative array of associative arrays by a parameter in the inner arrays
 * and include the outer array key as a parameter in the resulting array of inner arrays.
 *
 * @param array $arrayA The associative array of associative arrays to be sorted.
 * @param string $sortParam The parameter in the inner arrays by which to sort.
 * @param string $outerKeyParam The parameter name to store the outer array key in the inner arrays.
 * @return array The sorted and modified array of inner arrays.
 */
function sortAndIncludeOuterKey(array $arrayA, string $sortParam, string $outerKeyParam): array
{
    uasort($arrayA, function ($arrayB1, $arrayB2) use ($sortParam) {
        return $arrayB1[$sortParam] <=> $arrayB2[$sortParam];
    });

    $newArrayA = [];
    foreach ($arrayA as $key => $arrayB) {
        $arrayB[$outerKeyParam] = $key;
        $newArrayA[] = $arrayB;
    }

    return $newArrayA;
}

/**
 * Changes the index of an item in the given array.
 *
 * @param array $items    The array containing the items.
 * @param int   $oldIndex The original index of the item to be moved.
 * @param int   $newIndex The new index where the item should be placed.
 *
 * @throws InvalidArgumentException If the provided indices are invalid.
 */
function changeItemIndex(array &$items, int $oldIndex, int $newIndex)
{
    if ($oldIndex < 0 || $oldIndex >= count($items) || $newIndex < 0 || $newIndex >= count($items)) {
        throw new InvalidArgumentException("Invalid index values");
    }

    // Store the item in a temporary variable
    $item = $items[$oldIndex];

    // Remove the item from the original index
    array_splice($items, $oldIndex, 1);

    // Insert the item at the new index
    array_splice($items, $newIndex, 0, $item);
}
