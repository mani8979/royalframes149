<?php
/**
 * config.php
 * Central config loading from .env with fallback for local/production.
 */

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '192.168.') === 0);
define('IS_LOCAL', $isLocal);

$envFile = __DIR__ . '/.env';
$env = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value,-1) === '"') || ($value[0] === '\'' && substr($value,-1) === '\''))) {
            $value = substr($value, 1, -1);
        }
        $env[$name] = $value;
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

function env($key, $default = null) {
    global $env;
    if (array_key_exists($key, $env) && $env[$key] !== '') {
        return $env[$key];
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $default;
}

define('DB_HOST', env('DB_HOST', IS_LOCAL ? 'localhost' : 'sql313.infinityfree.com'));
define('DB_USER', env('DB_USER', IS_LOCAL ? 'root' : 'if0_41227955'));
define('DB_PASSWORD', env('DB_PASSWORD', IS_LOCAL ? '' : 'E3uc9adSEa'));
define('DB_NAME', env('DB_NAME', IS_LOCAL ? 'royal_frames' : 'if0_41227955_maindb'));

define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN', ''));
define('TELEGRAM_CHAT_ID', env('TELEGRAM_CHAT_ID', ''));

// Guarantee the URL used by Kali VM when host is 192.168.x.x.
$effectiveHost = 'localhost';
if (IS_LOCAL && strpos($host, '192.168.') === 0) {
    $effectiveHost = $host;
}

$defaultFrontend = IS_LOCAL ? "http://$effectiveHost/anti/royal-frames/" : 'https://royalframes149.ct.ws/';
$defaultBackend = IS_LOCAL ? "http://$effectiveHost/anti/royal-frames/" : 'https://royalframes149.ct.ws/';

define('FRONTEND_URL', rtrim(env('FRONTEND_URL', $defaultFrontend), '/') . '/');
define('BACKEND_URL', rtrim(env('BACKEND_URL', $defaultBackend), '/') . '/');

define('GITHUB_BYPASS_URL', env('GITHUB_BYPASS_URL', 'https://royalframes149.ct.ws/github_bypass.html'));

define('TELEGRAM_API_BASE', IS_LOCAL ? 'https://api.telegram.org/bot' : 'https://api.pwrtelegram.xyz/bot');

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
if (IS_LOCAL) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}
