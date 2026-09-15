<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: /auth/login.php', true, 302);
exit;
