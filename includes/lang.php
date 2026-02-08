<?php
// includes/lang.php

function set_language_cookie($lang) {
    if (in_array($lang, ['pt', 'en'])) {
        setcookie('lang', $lang, time() + 3600 * 24 * 365, '/');
    }
}

function get_lang() {
    // Ordem de prioridade: GET > Cookie > pt
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
        $lang = $_GET['lang'];
        // Não definir cookie aqui para evitar warning de headers
    } elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['pt', 'en'])) {
        $lang = $_COOKIE['lang'];
    } else {
        $lang = 'pt';
    }
    return $lang;
}

function t($key) {
    static $dict = null;
    if ($dict === null) {
        $lang = get_lang();
        $file = __DIR__ . "/lang_{$lang}.php";
        if (file_exists($file)) {
            $dict = include $file;
        } else {
            $dict = include __DIR__ . '/lang_pt.php';
        }
    }
    return $dict[$key] ?? $key;
} 