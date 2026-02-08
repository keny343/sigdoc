<?php
/**
 * Serve ficheiros estáticos com MIME type correto (contorno para InfinityFree)
 */
$file = $_GET['f'] ?? '';
$type = $_GET['t'] ?? '';

if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
    http_response_code(400);
    exit;
}

$path = __DIR__ . '/assets/' . $file;
if (!file_exists($path) || !is_file($path)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimes = [
    'js' => 'application/javascript',
    'mjs' => 'application/javascript',
    'css' => 'text/css',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime . '; charset=utf-8');
header('Cache-Control: public, max-age=31536000');
readfile($path);
