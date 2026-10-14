<?php
/**
 * Vercel Serverless Unified Front Controller for PHP
 * Dispatches requests to PHP files and static assets while keeping
 * function count to 1 (within Vercel Hobby plan limit of 12).
 */

// Set working directory to project root so relative includes and requires work smoothly
$projectRoot = dirname(__DIR__);
chdir($projectRoot);

// Ensure session directory exists in writable /tmp directory on serverless
$sessionPath = sys_get_temp_dir() . '/php_sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

// Parse requested URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($parsedPath, '/');

// Default page for root
if ($path === '' || $path === '/') {
    if (file_exists($projectRoot . '/index.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($projectRoot . '/index.html');
        exit;
    }
    $path = 'index.php';
}

$targetFile = $projectRoot . '/' . $path;

// Support clean URLs without extension (e.g., /login -> /login.php)
if (!file_exists($targetFile) && file_exists($targetFile . '.php')) {
    $targetFile .= '.php';
    $path .= '.php';
}

// Security: Validate file is inside project root
$realTarget = realpath($targetFile);
$realRoot   = realpath($projectRoot);

if ($realTarget && strpos($realTarget, $realRoot) === 0 && file_exists($realTarget)) {
    $extension = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));

    if ($extension === 'php') {
        // Mock server variables expected by standard PHP scripts
        $_SERVER['SCRIPT_FILENAME'] = $realTarget;
        $_SERVER['SCRIPT_NAME']     = '/' . $path;
        $_SERVER['PHP_SELF']        = '/' . $path;

        require $realTarget;
        exit;
    } else {
        // Serve static file if it reached the router
        $mimes = [
            'html'  => 'text/html; charset=UTF-8',
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'json'  => 'application/json',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'pdf'   => 'application/pdf',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
        ];
        $contentType = $mimes[$extension] ?? (function_exists('mime_content_type') ? @mime_content_type($realTarget) : 'application/octet-stream');
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($realTarget));
        readfile($realTarget);
        exit;
    }
}

// 404 Response
http_response_code(404);
echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested file <code>" . htmlspecialchars($path) . "</code> was not found.</p><p><a href='/'>Return to Home</a></p></body></html>";
exit;
