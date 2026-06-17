<?php
/**
 * Database Installer / Setup
 *
 * Run this script ONCE to import schema.sql + seed.sql into the database.
 * After successful install, DELETE this file or move it outside the
 * document root for security.
 *
 * Usage (CLI):
 *   php install.php
 *
 * Usage (browser):
 *   http://localhost/invoce_genarate/install.php  (then delete the file!)
 *
 * Optional flag:
 *   php install.php --fresh    # DROP and recreate the database
 */

require_once __DIR__ . '/config/config.php';

$schemaFile = __DIR__ . '/sql/schema.sql';
$seedFile   = __DIR__ . '/sql/seed.sql';
$fresh      = in_array('--fresh', $argv ?? [], true);

echo "=== Invoice System Database Installer ===\n\n";

// Connect without database first, just to create it if missing.
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✅ Connected to MySQL/MariaDB at " . DB_HOST . ":" . DB_PORT . "\n";
} catch (PDOException $e) {
    die("❌ Cannot connect to MySQL: " . htmlspecialchars($e->getMessage()) . "\n");
}

// Optionally drop existing database
if ($fresh) {
    echo "⚠️  --fresh flag: dropping database '" . DB_NAME . "'...\n";
    $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
    echo "✅ Dropped\n\n";
}

// Run schema.sql statement-by-statement
echo "--- Importing schema.sql ---\n";
try {
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        die("❌ Cannot read " . $schemaFile . "\n");
    }
    $executed = 0;
    foreach (split_sql($sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $executed++;
    }
    echo "✅ Schema imported ($executed statements)\n";
} catch (PDOException $e) {
    die("❌ Schema import failed: " . htmlspecialchars($e->getMessage()) . "\n");
}

// Run seed.sql
echo "\n--- Importing seed.sql ---\n";
try {
    $sql = file_get_contents($seedFile);
    if ($sql === false) {
        die("❌ Cannot read " . $seedFile . "\n");
    }
    $executed = 0;
    foreach (split_sql($sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $executed++;
    }
    echo "✅ Seed data imported ($executed statements)\n";
} catch (PDOException $e) {
    echo "⚠️  Seed import warning (safe to ignore if re-running):\n";
    echo "   " . htmlspecialchars($e->getMessage()) . "\n";
}

// Verify tables exist
echo "\n--- Verifying tables ---\n";
$tables = ['users', 'company_settings', 'invoices', 'invoice_items', 'activity_logs', 'verification_logs'];
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW TABLES FROM `" . DB_NAME . "` LIKE '$t'");
    if ($stmt->rowCount() > 0) {
        echo "✅ $t\n";
    } else {
        echo "❌ $t (missing!)\n";
    }
}

// Verify seed data
echo "\n--- Verifying seed data ---\n";
$stmt = $pdo->query("SELECT username, role, is_active FROM `" . DB_NAME . "`.`users` WHERE username = 'superadmin'");
$admin = $stmt->fetch();
if ($admin) {
    echo "✅ Super admin: {$admin['username']} ({$admin['role']}, active={$admin['is_active']})\n";
} else {
    echo "❌ Super admin not found\n";
}

$stmt = $pdo->query("SELECT company_name FROM `" . DB_NAME . "`.`company_settings` WHERE id = 1");
$company = $stmt->fetch();
if ($company) {
    echo "✅ Company: {$company['company_name']}\n";
} else {
    echo "❌ Company settings not found\n";
}

echo "\n=== Done! ===\n";
echo "Default super admin:\n";
echo "  Username: superadmin\n";
echo "  Password: superadmin123\n";
echo "\n⚠️  IMPORTANT: Change the password immediately after first login!\n";
echo "⚠️  Also: Delete this install.php file after running it.\n";

/**
 * Split a SQL file into individual statements.
 * Handles:
 *   - Single quotes (with ' and \\ escape)
 *   - Double quotes
 *   - Backticks
 *   - -- line comments
 *   - /* ... *​/ block comments
 *   - Semicolons as statement separators
 */
function split_sql(string $sql): array
{
    $statements = [];
    $buf        = '';
    $len        = strlen($sql);
    $i          = 0;
    $inSingle   = false;
    $inDouble   = false;
    $inBacktick = false;
    $inLineCmt  = false;
    $inBlkCmt   = false;

    while ($i < $len) {
        $ch  = $sql[$i];
        $nxt = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineCmt) {
            if ($ch === "\n") {
                $inLineCmt = false;
            }
            $i++;
            continue;
        }

        if ($inBlkCmt) {
            if ($ch === '*' && $nxt === '/') {
                $inBlkCmt = false;
                $i += 2;
                continue;
            }
            $i++;
            continue;
        }

        if ($inSingle) {
            $buf .= $ch;
            if ($ch === '\\' && $nxt !== '') {
                $buf .= $nxt;
                $i += 2;
                continue;
            }
            if ($ch === "'") {
                $inSingle = false;
            }
            $i++;
            continue;
        }

        if ($inDouble) {
            $buf .= $ch;
            if ($ch === '\\' && $nxt !== '') {
                $buf .= $nxt;
                $i += 2;
                continue;
            }
            if ($ch === '"') {
                $inDouble = false;
            }
            $i++;
            continue;
        }

        if ($inBacktick) {
            $buf .= $ch;
            if ($ch === '`') {
                $inBacktick = false;
            }
            $i++;
            continue;
        }

        if ($ch === '-' && $nxt === '-') {
            $inLineCmt = true;
            $i += 2;
            continue;
        }
        if ($ch === '/' && $nxt === '*') {
            $inBlkCmt = true;
            $i += 2;
            continue;
        }
        if ($ch === "'") {
            $inSingle = true;
            $buf .= $ch;
            $i++;
            continue;
        }
        if ($ch === '"') {
            $inDouble = true;
            $buf .= $ch;
            $i++;
            continue;
        }
        if ($ch === '`') {
            $inBacktick = true;
            $buf .= $ch;
            $i++;
            continue;
        }
        if ($ch === ';') {
            $statements[] = $buf;
            $buf = '';
            $i++;
            continue;
        }

        $buf .= $ch;
        $i++;
    }

    if (trim($buf) !== '') {
        $statements[] = $buf;
    }

    return $statements;
}
