<?php

declare(strict_types=1);

header("Content-type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: GET,PUT,OPTIONS");
header('Access-Control-Allow-Origin: *');

$baseURL = "https://www.seismos.gr";
$url = $baseURL . "/seismoi-lista";

$quakes = [];

$html = fetchHTML($url);

if ($html === null) {
    die(json_encode(['error' => 'Failed to retrieve the webpage.']));
}

$quakes = parseQuakes($html, $baseURL);

$response = handleRequest($_SERVER['REQUEST_METHOD'], $quakes);

echo json_encode($response);

/**
 * Fetches HTML content from a URL.
 *
 * @param string $url
 * @return string|null
 */
function fetchHTML(string $url): ?string
{
    $html = file_get_contents($url);
    if ($html === false) {
        return null;
    }

    return mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
}

/**
 * Parses earthquake data from HTML content.
 *
 * @param string $html
 * @param string $baseURL
 * @return array
 */
function parseQuakes(string $html, string $baseURL): array
{
    $dom = new DOMDocument;

    libxml_use_internal_errors(true);

    if (!$dom->loadHTML($html)) {
        return [];
    }

    $xpath = new DOMXPath($dom);

    $query = "//div[contains(@class, 'list-group')]//a[contains(@class, 'list-group-item')]";
    $items = $xpath->query($query);

    $quakes = [];

    if ($items->length > 0) {
        foreach ($items as $item) {
            $nodes = [
                'title' => $xpath->query('.//h4', $item),
                'magnitude' => $xpath->query('.//span', $item),
                'timeago' => $xpath->query('.//p', $item),
            ];

            $titleParts = explodeTitle($nodes['title']->item(0)->textContent);

            $quakes[] = [
                'link' => $baseURL . $item->getAttribute('href'),
                'title' => $titleParts['title'],
                'magnitude' => $nodes['magnitude']->item(0)->textContent,
                'timeago' => $nodes['timeago']->item(0)->textContent,
                'date' => $titleParts['date'],
                'time' => $titleParts['time']
            ];
        }
    }

    return $quakes;
}

/**
 * Explodes the title string into parts.
 *
 * @param string $title
 * @return array
 */
function explodeTitle(string $title): array
{
    $parts = explode(" - ", $title);
    $dateTime = explode(" ", $parts[0]);

    return [
        'title' => $parts[1] ?? '',
        'date' => $dateTime[0] ?? '',
        'time' => $dateTime[1] ?? ''
    ];
}

/**
 * Handles the HTTP request and returns the appropriate response.
 *
 * @param string $method
 * @param array $quakes
 * @return array
 */
function handleRequest(string $method, array $quakes): array
{
    if ($method === 'GET') {
        return [
            'total' => count($quakes),
            'list' => $quakes
        ];
    }

    http_response_code(405); // Method Not Allowed
    return [
        'error' => 'Not allowed'
    ];
}
