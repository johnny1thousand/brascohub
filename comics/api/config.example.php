<?php
// Copy this file to config.php and fill in your real values.
// config.php is gitignored — never commit real credentials.

// Database connection (hPanel → Databases → MySQL Databases)
define('DB_HOST', 'localhost');
define('DB_NAME', 'REPLACE_WITH_DB_NAME');
define('DB_USER', 'REPLACE_WITH_DB_USER');
define('DB_PASS', 'REPLACE_WITH_DB_PASSWORD');

// Shared household login
define('APP_USERNAME', 'REPLACE_WITH_USERNAME');
// Generate with: php -r 'echo password_hash("yourpassword", PASSWORD_BCRYPT), PHP_EOL;'
define('APP_PASSWORD_HASH', 'REPLACE_WITH_BCRYPT_HASH');

// ---- Reading covers with Claude (optional) ----
// Leave the key empty and the app simply hides the feature: you type the
// fields in yourself, exactly as before. Key from platform.claude.com.
define('ANTHROPIC_API_KEY', '');
// Optional. Defaults to claude-opus-5 when omitted.
define('AI_MODEL', 'claude-opus-5');
