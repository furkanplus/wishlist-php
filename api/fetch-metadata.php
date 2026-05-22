<?php
// api/fetch-metadata.php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Only allow admin access
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin login required.']);
    exit;
}

// Get the POST payload
$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or empty URL provided.']);
    exit;
}

// Helper to resolve relative URLs
function resolveUrl($relative, $base) {
    if (empty($relative)) return '';
    
    // If it already has a scheme, it is absolute
    if (parse_url($relative, PHP_URL_SCHEME) != '') {
        return $relative;
    }
    
    // Handle protocol-relative URLs (e.g. //example.com/image.jpg)
    if (substr($relative, 0, 2) === '//') {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'http';
        return $scheme . ':' . $relative;
    }
    
    $baseParts = parse_url($base);
    $scheme = isset($baseParts['scheme']) ? $baseParts['scheme'] . '://' : 'http://';
    $host = $baseParts['host'] ?? '';
    
    if (empty($host)) return '';
    
    // Absolute path on domain (e.g. /images/img.jpg)
    if ($relative[0] === '/') {
        return $scheme . $host . $relative;
    }
    
    // Relative path (e.g. images/img.jpg)
    $path = $baseParts['path'] ?? '/';
    // Get directory of path
    $dir = (substr($path, -1) === '/') ? $path : dirname($path);
    if ($dir === '.') $dir = '/';
    // Clean directory
    $dir = rtrim($dir, '/') . '/';
    
    return $scheme . $host . $dir . $relative;
}

// cURL setup to fetch page content
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // Masquerade as a popular modern desktop browser
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');
    // Accept language configuration
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5'
    ]);
    // Allow scraping pages with SSL issues safely
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($html === false || $httpCode >= 400) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to connect to the site or received an error response (HTTP ' . $httpCode . ').'
        ]);
        exit;
    }
    
    // Disable standard parsing warnings/errors
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    
    // Convert to UTF-8 before loading if needed
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($doc);
    
    $title = '';
    $image = '';
    
    // 1. Try OpenGraph Title
    $ogTitleQuery = $xpath->query('//meta[@property="og:title"]/@content');
    if ($ogTitleQuery->length > 0) {
        $title = trim($ogTitleQuery->item(0)->nodeValue);
    }
    
    // 2. Try Twitter Title
    if (empty($title)) {
        $twitterTitleQuery = $xpath->query('//meta[@name="twitter:title"]/@content');
        if ($twitterTitleQuery->length > 0) {
            $title = trim($twitterTitleQuery->item(0)->nodeValue);
        }
    }
    
    // 3. Fallback to standard <title> tag
    if (empty($title)) {
        $titleTags = $doc->getElementsByTagName('title');
        if ($titleTags->length > 0) {
            $title = trim($titleTags->item(0)->nodeValue);
        }
    }
    
    // 4. Try OpenGraph Image
    $ogImageQuery = $xpath->query('//meta[@property="og:image"]/@content');
    if ($ogImageQuery->length > 0) {
        $image = trim($ogImageQuery->item(0)->nodeValue);
    }
    
    // 5. Try Twitter Image
    if (empty($image)) {
        $twitterImageQuery = $xpath->query('//meta[@name="twitter:image"]/@content');
        if ($twitterImageQuery->length > 0) {
            $image = trim($twitterImageQuery->item(0)->nodeValue);
        }
    }
    
    // 6. Try standard image link tag
    if (empty($image)) {
        $linkImageQuery = $xpath->query('//link[@rel="image_src"]/@href');
        if ($linkImageQuery->length > 0) {
            $image = trim($linkImageQuery->item(0)->nodeValue);
        }
    }
    
    // 7. Fallback to first high likelihood image tag in body
    if (empty($image)) {
        $images = $doc->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            
            // Exclude small assets like icons or spacer gifs
            if ($src && (!empty($width) && (int)$width > 100) && (!empty($height) && (int)$height > 100)) {
                $image = $src;
                break;
            }
        }
        
        // If still empty, grab the very first img with a src
        if (empty($image) && $images->length > 0) {
            foreach ($images as $img) {
                $src = $img->getAttribute('src');
                if ($src && !preg_match('/\.(gif|svg|ico)/i', $src)) {
                    $image = $src;
                    break;
                }
            }
        }
    }
    
    // Resolve relative image URLs to absolute
    if (!empty($image)) {
        $image = resolveUrl($image, $url);
    }
    
    echo json_encode([
        'success' => true,
        'title' => $title,
        'image' => $image,
        'url' => $url
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Scraper encountered an internal error: ' . $e->getMessage()
    ]);
}
