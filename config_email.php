<?php
// config_email.php — SMTP settings from config.local.php
require_once __DIR__ . '/includes/config.php';

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', (string) sigdoc_config('smtp.host'));
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', (string) sigdoc_config('smtp.user'));
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', (string) sigdoc_config('smtp.pass'));
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int) sigdoc_config('smtp.port', 587));
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', (string) sigdoc_config('smtp.secure', 'tls'));
}
