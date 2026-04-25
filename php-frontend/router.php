<?php
/**
 * Router script for PHP built-in development server
 * This replaces .htaccess functionality
 */

// If file exists, serve it directly
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = __DIR__ . $uri;

if (file_exists($path) && is_file($path)) {
    return false; // Serve the existing file
}

// Otherwise, route to index.php
require __DIR__ . '/index.php';
