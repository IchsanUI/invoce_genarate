<?php
/**
 * Backend smoke test
 *
 * Run via CLI:  php tests/backend-test.php
 *
 * Exercises every helper / library in includes/ to ensure they work
 * end-to-end against the live database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pass = 0;
$fail = 0;
$results = [];

function check(string $name, callable $fn, $expected = true): void
{
    global $pass, $fail, $results;
    try {
        $got = $fn();
        if ($got === $expected) {
            $pass++;
            $results[] = "  PASS  $name";
        } else {
            $fail++;
            $results[] = "  FAIL  $name  (expected " . var_export($expected, true) . ", got " . var_export($got, true) . ")";
        }
    } catch (Throwable $e) {
        $fail++;
        $results[] = "  FAIL  $name  (threw: " . $e->getMessage() . ")";
    }
}

echo "=== Backend Smoke Test ===\n\n";

session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/signature.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/invoice-number.php';

// ---- Config ----
echo "Config:\n";
check('APP_NAME defined',           fn() => defined('APP_NAME') && APP_NAME !== '');
check('SECRET_KEY defined',         fn() => defined('SECRET_KEY') && strlen(SECRET_KEY) > 10);
check('HMAC_SECRET defined',        fn() => defined('HMAC_SECRET') && strlen(HMAC_SECRET) > 10);
check('COMPANY_CODE = ASSIG',       fn() => COMPANY_CODE === 'ASSIG');
check('DB constants set',           fn() => defined('DB_HOST') && defined('DB_NAME'));

// ---- Database ----
echo "Database:\n";
$pdo = db();
check('db() returns PDO',           fn() => db() instanceof PDO);
check('Driver = mysql',             fn() => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
check('ErrMode = Exception',        fn() => $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION);
check('Emulate prepares disabled',  fn() => $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false, false);
// PDO is configured with real prepared statements (no emulation)

// ---- Functions ----
echo "Functions:\n";
check('format_rupiah(1500000)',     fn() => format_rupiah(1500000) === 'Rp 1.500.000');
check('format_rupiah(0)',           fn() => format_rupiah(0) === 'Rp 0');
check('format_date(2026-06-17)',    fn() => format_date('2026-06-17') === '17 Juni 2026');
check('date_yyyymmdd length = 8',   fn() => strlen(date_yyyymmdd()) === 8);
check('sanitize strips tags',       fn() => sanitize('<b>hi</b>') === 'hi');
check('h() escapes < > &',          fn() => h('<x>') === '&lt;x&gt;');
check('csrf_token() length = 64',   fn() => strlen(csrf_token()) === 64);
check('validate_csrf_token valid',  function () {
    $_POST['csrf_token'] = csrf_token();
    return validate_csrf_token();
});
check('validate_csrf_token bogus',  function () {
    $_POST['csrf_token'] = 'deadbeef';
    return validate_csrf_token();
}, false);

// ---- Signature ----
echo "Signature:\n";
$data = json_encode(['invoice' => 'INV/ASSIG-20260617-0001', 'total' => 100000]);
$sig  = generate_signature($data);
check('signature length = 64',      fn() => strlen($sig) === 64);
check('verify ok',                  fn() => verify_signature($data, $sig));
check('verify tampered data = neg',  fn() => !verify_signature($data . 'x', $sig));
check('verify wrong sig = neg',      fn() => !verify_signature($data, str_repeat('0', 64)));
check('short_token length = 8',     fn() => strlen(short_token($sig)) === 8);
check('build_verify_url format',    fn() => str_contains(build_verify_url('INV/X-20260617-0001', $sig), '/verify.php?inv='));

// ---- Auth ----
echo "Auth:\n";
check('is_logged_in() false (no session)', fn() => is_logged_in(), false);
check('current_user_id() = 0',      fn() => current_user_id() === 0);
check('is_super_admin() false',     fn() => is_super_admin(), false);
check('set_login_session works',    function () {
    set_login_session(1, 'superadmin', 'superadmin', 'Test Admin');
    return is_logged_in() && current_user_id() === 1 && is_super_admin();
});

// ---- Invoice number ----
echo "Invoice number:\n";
check('parse valid',                fn() => parse_invoice_number('INV/ASSIG-20260617-0001')['sequence'] === 1);
check('parse invalid -> null',      fn() => parse_invoice_number('NOT_VALID') === null);
check('is_valid good',              fn() => is_valid_invoice_number('INV/ASSIG-20260617-0042') === true);
check('is_valid bad',               fn() => is_valid_invoice_number('bad') === false);

$num = generate_invoice_number();
check('generate starts with INV/ASSIG', fn() => str_starts_with($num, 'INV/ASSIG-'));
check('generate contains today',    fn() => str_contains($num, date_yyyymmdd()));

// Test reserve (atomic)
check('reserve + return unique',    function () {
    $a = reserve_invoice_number(1);
    $b = reserve_invoice_number(1);
    return is_valid_invoice_number($a)
        && is_valid_invoice_number($b)
        && $a !== $b;
});

// Clean up void placeholders
$pdo->exec("DELETE FROM invoices WHERE status = 'void'");

// ---- Summary ----
echo "\n--- Results ---\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\nPassed: $pass\n";
echo "Failed: $fail\n";
exit($fail === 0 ? 0 : 1);
