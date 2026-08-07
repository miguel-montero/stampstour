<?php
// includes/reprocess_paypal_events.php
// Recovers paypal_webhook_events rows that never reached status='handled' -
// a transient signature-verification failure, or an exception during
// processing. Duplicates paypal_webhook.php's event-handling logic
// (deliberately - paypal_webhook.php is never modified by this file). See
// docs/superpowers/specs/2026-08-06-paypal-reprocessing-design.md
declare(strict_types=1);

function _reprocess_paypal_find_reference_by_order_id(mysqli $conn, string $orderId): ?string
{
    $stmt = $conn->prepare("SELECT reference_id FROM reservas WHERE order_id=? LIMIT 1");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->bind_result($ref);
    $got = $stmt->fetch();
    $stmt->close();
    return $got ? $ref : null;
}

function _reprocess_paypal_try_prepare(mysqli $conn, string $sql)
{
    return @mysqli_prepare($conn, $sql) ?: null;
}

function _reprocess_paypal_try_update_amounts(mysqli $conn, string $referenceId, ?string $amountVal, ?string $currency): void
{
    if ($amountVal === null && $currency === null) {
        return;
    }
    if ($stmt = _reprocess_paypal_try_prepare($conn, "UPDATE reservas SET monto_pagado=IFNULL(monto_pagado, ?), moneda=IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, 'sss', $amountVal, $currency, $referenceId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Applies one decoded PayPal event to reservas, using the exact same
 * event-type logic as paypal_webhook.php (duplicated, not shared).
 * Throws on unexpected DB errors - caller decides what "failed" means.
 */
function _reprocess_paypal_apply_event(mysqli $conn, string $eventType, array $event): void
{
    switch ($eventType) {
        case 'CHECKOUT.ORDER.APPROVED': {
            $r = $event['resource'] ?? [];
            $orderId = $r['id'] ?? null;
            $pu = $r['purchase_units'][0] ?? [];
            $referenceId = $pu['custom_id'] ?? ($pu['invoice_id'] ?? null);
            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }

            if ($referenceId && $orderId) {
                // Intentionally MORE protective than paypal_webhook.php's own
                // CHECKOUT.ORDER.APPROVED handler (which only guards
                // 'realizado'): this reprocessing script can replay a stuck
                // ORDER.APPROVED event up to 30 days after it was originally
                // received, by which time a reservation could plausibly have
                // been refunded. The live webhook doesn't need this guard -
                // ORDER.APPROVED always arrives before any capture/refund
                // could exist there. This is a deliberate, user-approved
                // deviation from this file's "exact duplicate" principle,
                // scoped to this one file only - paypal_webhook.php is not
                // touched. See
                // docs/superpowers/specs/2026-08-06-paypal-reprocessing-design.md
                $stmt = $conn->prepare("
                    UPDATE reservas
                       SET order_id = IFNULL(order_id, ?),
                           estado   = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END
                     WHERE TRIM(reference_id)=TRIM(?) LIMIT 1
                ");
                $stmt->bind_param('ss', $orderId, $referenceId);
                $stmt->execute();
                $stmt->close();
            }
            break;
        }

        case 'PAYMENT.CAPTURE.COMPLETED': {
            $r = $event['resource'] ?? [];
            $captureId = $r['id'] ?? null;
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            $amountVal = $r['amount']['value'] ?? null;
            $currency = $r['amount']['currency_code'] ?? null;

            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }

            if ($captureId && $referenceId) {
                $stmt = $conn->prepare("
                    UPDATE reservas
                       SET estado='realizado',
                           capture_id=?,
                           order_id=IFNULL(order_id, ?)
                     WHERE TRIM(reference_id)=TRIM(?)
                     LIMIT 1
                ");
                $stmt->bind_param('sss', $captureId, $orderId, $referenceId);
                $stmt->execute();
                $rows = $stmt->affected_rows;
                $stmt->close();

                if ($rows === 0 && $orderId) {
                    if ($stmt = $conn->prepare("
                        UPDATE reservas
                           SET estado='realizado',
                               capture_id=?,
                               order_id=IFNULL(order_id, ?)
                         WHERE order_id=?
                         LIMIT 1
                    ")) {
                        $stmt->bind_param('sss', $captureId, $orderId, $orderId);
                        $stmt->execute();
                        $rows = $stmt->affected_rows;
                        $stmt->close();
                    }
                }

                error_log("reprocess_paypal CAPTURE.UPDATE rows={$rows} ref={$referenceId} order={$orderId} capture={$captureId}");
                _reprocess_paypal_try_update_amounts($conn, $referenceId, $amountVal, $currency);
            }
            break;
        }

        case 'PAYMENT.CAPTURE.PENDING': {
            $r = $event['resource'] ?? [];
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }
            if ($referenceId) {
                // Same downgrade guard as paypal_webhook.php - see
                // docs/superpowers/specs/2026-08-05-payment-status-downgrade-guard-design.md
                $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'pendiente' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
                $stmt->bind_param('s', $referenceId);
                $stmt->execute();
                $stmt->close();
            }
            break;
        }

        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.DECLINED': {
            $r = $event['resource'] ?? [];
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }
            if ($referenceId) {
                // Same downgrade guard as paypal_webhook.php.
                $stmt = $conn->prepare("UPDATE reservas SET estado = CASE WHEN estado IN ('realizado', 'refund') THEN estado ELSE 'fallido' END WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
                $stmt->bind_param('s', $referenceId);
                $stmt->execute();
                $stmt->close();
            }
            break;
        }

        case 'PAYMENT.CAPTURE.REFUNDED': {
            $r = $event['resource'] ?? [];
            $orderId = $r['supplementary_data']['related_ids']['order_id'] ?? null;
            $referenceId = $r['custom_id'] ?? ($r['invoice_id'] ?? null);
            $refundId = $r['id'] ?? null;
            $amountVal = $r['amount']['value'] ?? null;
            $currency = $r['amount']['currency_code'] ?? null;

            if (!$referenceId && $orderId) {
                $referenceId = _reprocess_paypal_find_reference_by_order_id($conn, $orderId);
            }
            if ($referenceId) {
                $stmt = $conn->prepare("UPDATE reservas SET estado='refund' WHERE TRIM(reference_id)=TRIM(?) LIMIT 1");
                $stmt->bind_param('s', $referenceId);
                $stmt->execute();
                $stmt->close();

                if ($stmt = _reprocess_paypal_try_prepare($conn, "UPDATE reservas SET refund_id = IFNULL(refund_id, ?), refund_monto = IFNULL(refund_monto, ?), moneda = IFNULL(moneda, ?) WHERE TRIM(reference_id)=TRIM(?) LIMIT 1")) {
                    $stmt->bind_param('ssss', $refundId, $amountVal, $currency, $referenceId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            break;
        }

        default:
            // no-op; still marked handled by the caller
            break;
    }
}

function _reprocess_paypal_real_token_fetcher(array $paypalConfig): string
{
    $mode = ($paypalConfig['mode'] === 'live') ? 'live' : 'sandbox';
    $env = $paypalConfig[$mode];
    $base = ($mode === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $env['client_id'] . ':' . $env['client_secret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        throw new RuntimeException('OAuth curl: ' . curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("OAuth HTTP $code");
    }
    $json = json_decode($res, true);
    if (empty($json['access_token'])) {
        throw new RuntimeException('No access token');
    }
    return $json['access_token'];
}

function _reprocess_paypal_real_signature_verifier(array $paypalConfig, string $token, array $headers, array $event): bool
{
    $mode = ($paypalConfig['mode'] === 'live') ? 'live' : 'sandbox';
    $env = $paypalConfig[$mode];
    $base = ($mode === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

    $H = array_change_key_case($headers, CASE_UPPER);
    $verifyBody = [
        'transmission_id'   => $H['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_time' => $H['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'cert_url'          => $H['PAYPAL-CERT-URL'] ?? '',
        'auth_algo'         => $H['PAYPAL-AUTH-ALGO'] ?? '',
        'transmission_sig'  => $H['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'webhook_id'        => $env['webhook_id'],
        'webhook_event'     => $event,
    ];
    $ch = curl_init("$base/v1/notifications/verify-webhook-signature");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_POSTFIELDS => json_encode($verifyBody, JSON_UNESCAPED_SLASHES),
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        return false;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) && (json_decode($resp, true)['verification_status'] ?? '') === 'SUCCESS';
}

/**
 * Reprocesses up to $limit paypal_webhook_events rows stuck below
 * status='handled'. Never bypasses signature verification - a row already
 * marked verified='SUCCESS' skips re-verification; anything else is
 * re-verified against PayPal's real endpoint (or the injected fake in
 * tests) before any reservas write is attempted.
 *
 * Rows that failed verification more than once (specifically: whose most
 * recent verification attempt failed AND are more than 24 hours old) stop
 * being retried automatically. This bounds retries to several attempts
 * within the first day - enough to recover from a transient issue (bad
 * OAuth token, network blip) - without retrying a permanently-broken row
 * (unparseable payload, a transmission whose PayPal cert has since
 * rotated) roughly 720 times over the full 30-day window, which would
 * waste API calls and, since rows are processed oldest-first, could crowd
 * out newer stuck events once the batch limit is reached.
 *
 * @param callable $tokenFetcher Defaults to a real PayPal OAuth token
 *                  fetch. Injectable for testing.
 * @param callable $signatureVerifier Defaults to a real call to PayPal's
 *                  verify-webhook-signature endpoint. Injectable for testing.
 * @return array{checked:int, reprocessed:int, failed:int, details:array<int,array{event_id:string,event_type:string,result:string}>}
 */
function reprocess_paypal_stuck_events(
    mysqli $conn,
    array $paypalConfig,
    int $limit = 50,
    ?callable $tokenFetcher = null,
    ?callable $signatureVerifier = null
): array {
    @mkdir(__DIR__ . '/../../logs', 0775, true);
    ini_set('error_log', __DIR__ . '/../../logs/paypal_reprocess.log');

    $tokenFetcher = $tokenFetcher ?? '_reprocess_paypal_real_token_fetcher';
    $signatureVerifier = $signatureVerifier ?? '_reprocess_paypal_real_signature_verifier';

    $checked = 0;
    $reprocessed = 0;
    $failed = 0;
    $details = [];

    $stmt = $conn->prepare("
        SELECT id, event_id, event_type, status, verified, payload, headers
        FROM paypal_webhook_events
        WHERE status NOT IN ('handled', 'mailed')
          AND received_at <= NOW() - INTERVAL 5 MINUTE
          AND received_at >= NOW() - INTERVAL 30 DAY
          AND NOT (verified = 'FAILURE' AND received_at < NOW() - INTERVAL 1 DAY)
        ORDER BY received_at ASC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) === $limit) {
        error_log("reprocess_paypal: batch full (limit=$limit) - eligible events may exceed batch size, some may not have been checked this run");
    }

    foreach ($rows as $row) {
        $checked++;
        $id = (int)$row['id'];
        $eventId = (string)$row['event_id'];
        $eventType = (string)$row['event_type'];
        $event = json_decode((string)$row['payload'], true);
        $headers = json_decode((string)$row['headers'], true);

        if (!is_array($event) || !is_array($headers)) {
            $failed++;
            error_log("reprocess_paypal: unparseable stored payload/headers for event_id=$eventId id=$id");
            continue;
        }

        if ($row['verified'] !== 'SUCCESS') {
            try {
                $token = $tokenFetcher($paypalConfig);
            } catch (Throwable $e) {
                $failed++;
                error_log("reprocess_paypal: token fetch failed for event_id=$eventId: " . $e->getMessage());
                continue;
            }

            try {
                $verifiedOk = (bool)$signatureVerifier($paypalConfig, $token, $headers, $event);
            } catch (Throwable $e) {
                $verifiedOk = false;
                error_log("reprocess_paypal: signature verification threw for event_id=$eventId: " . $e->getMessage());
            }

            $verStatus = $verifiedOk ? 'SUCCESS' : 'FAILURE';
            $upd = $conn->prepare("UPDATE paypal_webhook_events SET verified = ? WHERE id = ? LIMIT 1");
            $upd->bind_param('si', $verStatus, $id);
            $upd->execute();
            $upd->close();

            if (!$verifiedOk) {
                $failed++;
                error_log("reprocess_paypal: signature re-verification failed for event_id=$eventId, leaving queued");
                continue;
            }
        }

        try {
            _reprocess_paypal_apply_event($conn, $eventType, $event);
            $upd = $conn->prepare("UPDATE paypal_webhook_events SET status='handled', handled_at=NOW() WHERE id=? LIMIT 1");
            $upd->bind_param('i', $id);
            $upd->execute();
            $upd->close();
            $reprocessed++;
            $details[] = ['event_id' => $eventId, 'event_type' => $eventType, 'result' => 'handled'];
            error_log("reprocess_paypal: reprocessed event_id=$eventId type=$eventType");
        } catch (Throwable $e) {
            $failed++;
            error_log("reprocess_paypal: processing threw for event_id=$eventId type=$eventType: " . $e->getMessage());
        }
    }

    return ['checked' => $checked, 'reprocessed' => $reprocessed, 'failed' => $failed, 'details' => $details];
}
