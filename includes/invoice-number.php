<?php
/**
 * Invoice Number Generator
 *
 * Format: INV/{COMPANY_CODE}-{YYYYMMDD}-{NNNN}
 * Example: INV/ASSIG-20260617-0001
 *
 * The daily counter resets every day. The next number is computed as
 * MAX(sequence for today) + 1. If a duplicate is encountered at insert
 * time, the caller can call this again to retry.
 *
 * IMPORTANT: This function does NOT insert into the database. It only
 * computes a unique candidate. The caller must:
 *   1. Call generate_invoice_number() to get a candidate
 *   2. Try to INSERT it
 *   3. If INSERT fails with a UNIQUE constraint error, retry from (1)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Generate the next invoice number for today.
 *
 * Returns a candidate number computed from the current database state.
 * Two concurrent callers will get the SAME number; the UNIQUE constraint
 * on `invoices.invoice_number` is the actual safety net — the second
 * insert will fail and must retry.
 *
 * @return string New invoice number candidate, e.g. "INV/ASSIG-20260617-0001"
 */
function generate_invoice_number(): string
{
    $pdo     = db();
    $today   = date_yyyymmdd();      // YYYYMMDD, e.g. "20260617"
    $prefix  = 'INV/' . COMPANY_CODE . '-' . $today . '-';

    // Find the highest existing sequence for today using a numeric trick:
    // strip the prefix, then the remainder is the 4-digit sequence as a
    // string. MAX() will return the highest sequence (lexicographic
    // order matches numeric for fixed-width zero-padded values).
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING(`invoice_number`, -4) AS UNSIGNED)) AS max_seq
         FROM `invoices`
         WHERE `invoice_number` LIKE ?"
    );
    $stmt->execute([$prefix . '%']);

    $maxSeq  = (int) $stmt->fetchColumn();
    $nextSeq = $maxSeq + 1;

    return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
}

/**
 * Atomically generate and "reserve" an invoice number by inserting a
 * placeholder row in the invoices table. This is the safest pattern for
 * concurrent inserts — the UNIQUE constraint guarantees no duplicates
 * even if two callers race.
 *
 * Returns the invoice number. The row has `status = 'void'` until the
 * real data is filled in by the caller (which updates the same row).
 *
 * If a duplicate is detected, this function retries up to N times before
 * giving up.
 *
 * @return string Successfully reserved invoice number
 * @throws RuntimeException if unable to reserve a unique number
 */
function reserve_invoice_number(int $created_by): string
{
    $pdo = db();
    $maxAttempts = 50;

    for ($i = 0; $i < $maxAttempts; $i++) {
        $candidate = generate_invoice_number();

        $pdo->beginTransaction();
        try {
            // Insert a placeholder row. The UNIQUE constraint will fail
            // with a PDOException if there's a race collision.
            $stmt = $pdo->prepare(
                "INSERT INTO `invoices`
                    (`invoice_number`, `customer_name`, `customer_address`,
                     `customer_phone`, `invoice_date`, `created_by`,
                     `status`, `total_qty`, `total_amount`, `discount`,
                     `grand_total`)
                 VALUES (?, '', '', '', CURRENT_DATE, ?, 'void', 0, 0, 0, 0)"
            );
            $stmt->execute([$candidate, $created_by]);
            $pdo->commit();
            return $candidate;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // MySQL error 1062 = duplicate key
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                // Retry with a fresh candidate
                continue;
            }
            // Some other error
            throw new RuntimeException(
                'Invoice number reservation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    throw new RuntimeException(
        "Could not reserve a unique invoice number after {$maxAttempts} attempts."
    );
}

/**
 * Parse an invoice number into its components.
 *
 * @return array{prefix: string, date: string, sequence: int}|null
 *               Returns null if the format is invalid.
 */
function parse_invoice_number(string $invoice_number): ?array
{
    if (!preg_match('/^INV\/([A-Z0-9]+)-(\d{8})-(\d{4})$/', $invoice_number, $m)) {
        return null;
    }
    return [
        'prefix'   => 'INV/' . $m[1],
        'date'     => $m[2],
        'sequence' => (int) $m[3],
    ];
}

/**
 * Validate that a given string is in the correct invoice number format.
 */
function is_valid_invoice_number(string $invoice_number): bool
{
    return parse_invoice_number($invoice_number) !== null;
}
