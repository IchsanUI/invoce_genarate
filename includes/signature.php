<?php
/**
 * HMAC Signature Generation & Verification
 *
 * Used by FASE 7/8 to sign invoice data with HMAC-SHA256.
 * The secret key lives ONLY in /config/config.php — never in the database
 * or in URLs.
 *
 * Flow:
 *   generate_signature($data) -> returns 64-char hex HMAC
 *   verify_signature($data, $expected) -> bool
 *
 * The short URL token (8 chars) is just the first 8 hex characters of the
 * full HMAC; this lets the QR code URL stay short while still being
 * tamper-evident (the full HMAC is recomputed server-side during verify).
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Compute the canonical JSON representation of the data snapshot.
 * Use this both at write-time and verify-time so the bytes are identical.
 */
function canonicalize_snapshot(array $data): string
{
    // Ensure consistent key ordering so the HMAC bytes match across runs.
    $sorted = [];
    foreach ($data as $k => $v) {
        $sorted[$k] = is_array($v) ? canonicalize_snapshot($v) : $v;
    }
    ksort($sorted);
    return json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Generate a 64-character HMAC-SHA256 hex signature.
 */
function generate_signature(string $data): string
{
    return hash_hmac('sha256', $data, HMAC_SECRET);
}

/**
 * Verify that a given signature matches the data.
 * Uses hash_equals() to prevent timing attacks.
 */
function verify_signature(string $data, string $expected): bool
{
    if (empty($expected) || strlen($expected) !== 64) {
        return false;
    }
    return hash_equals(generate_signature($data), $expected);
}

/**
 * Build a 64-character public verification URL token from a full HMAC.
 * Used in QR codes so URLs stay short.
 */
function short_token(string $full_signature): string
{
    return substr($full_signature, 0, 8);
}

/**
 * Build the public verification URL for an invoice.
 * Example output:
 *   https://localhost/verify.php?inv=INV/ASSIG-20260616-0007&t=ab12cd34
 */
function build_verify_url(string $invoice_number, string $full_signature): string
{
    $base = rtrim(APP_BASE_URL, '/');
    $token = short_token($full_signature);
    return $base . '/verify.php?inv=' . rawurlencode($invoice_number) . '&t=' . $token;
}