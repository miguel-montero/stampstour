<?php
// includes/reconcile_getnet.php
// Recovers 'pendiente' reservations that Getnet actually approved but whose
// webhook notification (getnet_notify.php) never arrived. See
// docs/superpowers/specs/2026-08-05-getnet-reconciliation-design.md
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

/**
 * Checks up to $limit 'pendiente' reservations (that have a process_id and
 * were created within the last day) against Getnet's live session API, and
 * corrects any Getnet actually resolved (approved or refunded) that our
 * webhook never recorded.
 *
 * Note: if a customer's first Getnet payment attempt is approved by Getnet
 * but the webhook never arrives, and the customer then retries and creates
 * a second Getnet session, shopping.php overwrites the reservation's
 * process_id with the new session's ID. This function only ever checks the
 * CURRENT process_id, so the original approved-but-unnotified session
 * becomes permanently unreachable by this feature (the schema retains no
 * history of prior process_id values). A "0 corrected" result therefore
 * does not guarantee no approved-but-unnotified payments exist - only that
 * none exist among reservations whose CURRENT process_id is the one Getnet
 * actually approved. Properly closing this gap would require a
 * session-attempt-history table and is out of scope for this feature.
 *
 * @return array{checked:int, corrected:int, failed:int, corrections:array<int,array{reference:string,from:string,to:string}>}
 */
function reconcile_getnet_pending(mysqli $conn, int $limit = 50): array
{
    @mkdir(__DIR__ . '/../../logs', 0775, true);
    ini_set('error_log', __DIR__ . '/../../logs/getnet_reconcile.log');

    $statusMap = [
        'APPROVED' => 'realizado',
        'PENDING'  => 'pendiente',
        'REJECTED' => 'fallido',
        'FAILED'   => 'fallido',
        'EXPIRED'  => 'fallido',
        'REFUNDED' => 'refund',
    ];

    $checked = 0;
    $corrected = 0;
    $failed = 0;
    $corrections = [];

    $stmt = $conn->prepare("
        SELECT id_reserva, reference_id, process_id
        FROM reservas
        WHERE estado = 'pendiente'
          AND process_id IS NOT NULL
          AND fecha_reserva >= NOW() - INTERVAL 1 DAY
        ORDER BY id_reserva ASC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) === $limit) {
        error_log("reconcile_getnet: batch full (limit=$limit) - eligible reservations may exceed batch size, some may not have been checked this run");
    }

    foreach ($rows as $row) {
        $checked++;
        $reference = (string)$row['reference_id'];
        $processId = (string)$row['process_id'];

        try {
            $session = getSessionInfo((int)$processId);
        } catch (Throwable $e) {
            $failed++;
            error_log("reconcile_getnet: getSessionInfo threw for ref=$reference process_id=$processId: " . $e->getMessage());
            continue;
        }

        if (isset($session['ok']) && $session['ok'] === false) {
            $failed++;
            error_log("reconcile_getnet: Getnet query failed for ref=$reference process_id=$processId: " . json_encode($session, JSON_UNESCAPED_SLASHES));
            continue;
        }

        // Proven field-extraction pattern (payment status takes priority
        // over session status) - see Global Constraints.
        $status = $session['status']['status'] ?? null;
        $payment0 = $session['payment'][0] ?? null;
        $paymentStatus = $payment0['status']['status'] ?? null;
        $norm = strtoupper((string)($paymentStatus ?? $status ?? ''));

        if ($norm === '' || !isset($statusMap[$norm])) {
            continue; // unknown or empty status - nothing safe to do
        }

        $nuevoEstado = $statusMap[$norm];

        // Only a real resolution is worth writing; a still-'pendiente' or
        // 'fallido' mapped result doesn't need reconciling here.
        if ($nuevoEstado !== 'realizado' && $nuevoEstado !== 'refund') {
            continue;
        }

        // Same guarded UPDATE as getnet_notify.php - defends against a
        // race with a real webhook resolving this row between our SELECT
        // and this UPDATE. See
        // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
        $upd = $conn->prepare("
            UPDATE reservas
            SET estado = CASE
                           WHEN estado IN ('realizado', 'refund') AND ? <> 'refund' THEN estado
                           ELSE ?
                         END,
                updated_at = NOW()
            WHERE reference_id = ?
              AND process_id = ?
            LIMIT 1
        ");
        $upd->bind_param('ssss', $nuevoEstado, $nuevoEstado, $reference, $processId);
        $upd->execute();
        $rowsAffected = $upd->affected_rows;
        $upd->close();

        if ($rowsAffected > 0) {
            $corrected++;
            $corrections[] = ['reference' => $reference, 'from' => 'pendiente', 'to' => $nuevoEstado];
            error_log("reconcile_getnet: corrected ref=$reference pendiente -> $nuevoEstado (process_id=$processId)");
        }
    }

    return ['checked' => $checked, 'corrected' => $corrected, 'failed' => $failed, 'corrections' => $corrections];
}
