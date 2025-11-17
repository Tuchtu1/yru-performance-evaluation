<?php

/**
 * config/api-config.php
 * API Configuration for JavaScript
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

header('Content-Type: application/javascript');
?>
// API Configuration
window.APP_CONFIG = {
baseUrl: '<?php echo APP_URL; ?>',
apiUrl: '<?php echo APP_URL; ?>/api/notifications.php',
assetsUrl: '<?php echo APP_URL; ?>/assets'
};

console.log('📡 API Config Loaded:', window.APP_CONFIG);