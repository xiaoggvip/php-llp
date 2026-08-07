<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load test environment variables if available
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key)) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

// Test configuration
define('PHPLLP_TEST_BASE_URL', getenv('PHPLLP_TEST_BASE_URL') ?: 'http://localhost:8000');
define('PHPLLP_TEST_API_KEY', getenv('PHPLLP_TEST_API_KEY') ?: 'test-key');
define('PHPLLP_TEST_MODEL', getenv('PHPLLP_TEST_MODEL') ?: 'gpt-4');
define('PHPLLP_TEST_TIMEOUT', (int)(getenv('PHPLLP_TEST_TIMEOUT') ?: 30));