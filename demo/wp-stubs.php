<?php

define('ABSPATH', '/wp/');

if (!defined('WC_DOC_UL_PATH')) {
    define('WC_DOC_UL_PATH', dirname(__DIR__) . '/');
}
if (!defined('WC_DOC_UL_URL')) {
    define('WC_DOC_UL_URL', '/');
}

function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return '/'; }
function add_action($hook, $callback, $priority = 10, $args = 1) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function remove_filter($hook, $callback, $priority = 10) {}
function sanitize_text_field($str) { return htmlspecialchars(strip_tags($str)); }
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function __($text, $domain = 'default') { return $text; }

require_once WC_DOC_UL_PATH . 'includes/class-wc-document-unique-login.php';
