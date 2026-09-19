<?php
// Copy this file to config.php and fill in your real values.
// config.php is gitignored — never commit real credentials.
//
// Put it OUTSIDE the web root, beside public_html:
//     /home/<account>/domains/<site>/private/config.php
// Apache has no path to that directory, so the database password and the API
// key cannot be fetched by any URL. api/config.php still works if you have not
// moved it yet, but it sits inside the served folder; the private/ copy wins
// when both exist.

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

// Cover reads a non-owner account may spend per calendar month, since they run
// on the key above — your credit. 0 means unlimited. The owner is never capped.
define('AI_MONTHLY_READS', 50);

// Shown on your public shelf and beside your name. Optional.
define('OWNER_DISPLAY_NAME', '');
