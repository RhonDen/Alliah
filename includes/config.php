<?php
if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $sessionPath = trim((string) ini_get('session.save_path'));

    if (str_contains($sessionPath, ';')) {
        $parts = explode(';', $sessionPath);
        $sessionPath = trim((string) end($parts));
    }

    if ($sessionPath === '' || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        $fallbackSessionPath = dirname(__DIR__) . '/.tmp/sessions';
        if (!is_dir($fallbackSessionPath)) {
            mkdir($fallbackSessionPath, 0777, true);
        }
        session_save_path($fallbackSessionPath);
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secureCookie,
    ]);
    session_start();
}

date_default_timezone_set('Asia/Manila');

if (!defined('APP_NAME')) {
    define('APP_NAME', getenv('APP_NAME') ?: 'Alliah Dental');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'aws-1-ap-northeast-1.pooler.supabase.com');
}

if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '6543');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'postgres.ebkqgnjmrovdnpwdvghp');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'vD0CJU7FOTDxv8lF');
}

if (!defined('UNISMS_ACCESS_KEY')) {
    define('UNISMS_ACCESS_KEY', getenv('UNISMS_ACCESS_KEY') ?: 'sk_b27982d7-8017-47b3-9433-b338b61a5fae');
}

if (!defined('UNISMS_SENDER')) {
    define('UNISMS_SENDER', getenv('UNISMS_SENDER') ?: 'DentsCity');
}

if (!function_exists('detectBaseUrl')) {
    function detectBaseUrl(): string
    {
        $configured = getenv('APP_BASE_URL');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/') . '/';
        }

        if (PHP_SAPI === 'cli') {
            return 'http://localhost/alliah/public/';
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $publicRoot = str_replace('\\', '/', (string) realpath(dirname(__DIR__) . '/public'));
        $documentRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $basePath = null;

        if ($publicRoot !== '' && $documentRoot !== '') {
            $publicRootNormalized = rtrim(strtolower($publicRoot), '/');
            $documentRootNormalized = rtrim(strtolower($documentRoot), '/');

            if ($publicRootNormalized === $documentRootNormalized) {
                $basePath = '/';
            } elseif (str_starts_with($publicRootNormalized, $documentRootNormalized . '/')) {
                $relativePath = trim(substr($publicRoot, strlen($documentRoot)), '/');
                $basePath = '/' . ($relativePath !== '' ? $relativePath . '/' : '');
            }
        }

        if ($basePath === null) {
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/alliah/public/index.php');
            if (preg_match('#^(.*?/public)/#i', $scriptName, $matches)) {
                $basePath = $matches[1];
            } else {
                $basePath = preg_replace('#/[^/]+$#', '/', $scriptName);
            }
        }

        return $scheme . '://' . $host . $basePath;
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', detectBaseUrl());
}

// Check for required PostgreSQL extension before attempting connection
if (!extension_loaded('pdo_pgsql')) {
    die(
        '<h2>Missing PHP Extension: pdo_pgsql</h2>' .
        '<p>This project uses a PostgreSQL database (Supabase). ' .
        'Your PHP installation is missing the <code>pdo_pgsql</code> extension.</p>' .
        '<h3>How to fix:</h3>' .
        '<ol>' .
        '<li>Open your <code>php.ini</code> file (in XAMPP: <code>C:\xampp\php\php.ini</code>)</li>' .
        '<li>Find the line <code>;extension=pdo_pgsql</code> and remove the <code>;</code> at the start</li>' .
        '<li>Save the file and restart Apache</li>' .
        '<li>Refresh this page</li>' .
        '</ol>' .
        '<p>If you are using shared hosting, contact your host and ask them to enable the PostgreSQL PDO extension.</p>'
    );
}

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    @error_log(
        '[' . date('c') . '] Database connection failed: ' . $e->getMessage() . PHP_EOL,
        3,
        $logDir . '/database.log'
    );
    die(
        '<h2>Database Connection Failed</h2>' .
        '<p>Could not connect to the database. Common causes:</p>' .
        '<ul>' .
        '<li><strong>No internet connection</strong> — Supabase is cloud-hosted and requires internet</li>' .
        '<li><strong>Firewall blocking port 6543</strong> — some networks block this port</li>' .
        '<li><strong>Supabase IP restriction</strong> — your IP may not be allowlisted in Supabase</li>' .
        '<li><strong>Supabase project paused</strong> — free-tier projects auto-pause after inactivity</li>' .
        '</ul>' .
        '<p>Check <code>logs/database.log</code> for the exact error message.</p>'
    );
}
