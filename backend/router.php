<?php
// Simple router for PHP development server
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Remove query string
$path = parse_url($request_uri, PHP_URL_PATH);

// If the file exists, serve it directly
$file_path = __DIR__ . $path;
if (is_file($file_path)) {
    return false; // Let the server handle it
}

// For API routes, add .php extension if it doesn't exist
if (strpos($path, '/api/') === 0) {
    $php_file = __DIR__ . $path;
    if (!is_file($php_file) && !str_ends_with($path, '.php')) {
        $php_file .= '.php';
        if (is_file($php_file)) {
            require $php_file;
            return true;
        }
    }
}

// If no file found, return 404
http_response_code(404);
echo "404 Not Found";
return true;
?>
