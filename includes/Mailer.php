<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Normalize secure/port combos so they don’t silently fail on shared hosting.
 */
function normalize_smtp(array $cfg): array {
    $secure = strtolower(trim((string)($cfg['secure'] ?? '')));
    $port   = (int)($cfg['port'] ?? 0);

    if ($secure === 'starttls') $secure = 'tls';
    if ($secure === 'smtps' || $secure === 'ssl/tls') $secure = 'ssl';

    if ($secure === 'ssl' && $port === 587) $port = 465;
    if ($secure === 'tls' && $port === 465) $port = 587;

    if (!$secure && !$port) { $secure = 'tls'; $port = 587; }

    $cfg['secure'] = $secure;
    $cfg['port']   = $port;
    return $cfg;
}

function build_mailer(): PHPMailer {
    $cfg = require __DIR__ . '/mailer_config.php';
    if (!is_array($cfg)) {
        throw new \RuntimeException('mailer_config.php must return an array');
    }

    $cfg = normalize_smtp($cfg);

    // Setup logging so errors don’t vanish
    $root = dirname(__DIR__);
    @mkdir($root . '/storage', 0775, true);
    ini_set('log_errors', '1');
    ini_set('error_log', $root . '/storage/php_errors.log');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];

    if ($cfg['secure'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($cfg['secure'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = false;
    }

    $mail->Port    = (int)$cfg['port'];
    $mail->CharSet = 'UTF-8';

    // Ensure From matches your mailbox/domain
    $fromEmail = $cfg['from_email'] ?? $cfg['username'];
    $fromName  = $cfg['from_name']  ?? 'Stamp\'s Tour';
    $mail->setFrom($fromEmail, $fromName);

    if (!empty($cfg['reply_to']['email'])) {
        $mail->addReplyTo($cfg['reply_to']['email'], $cfg['reply_to']['name'] ?? '');
    }

    $mail->isHTML(true);
    return $mail;
}

/**
 * Send booking email
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @param string $plainTextBody
 * @param array  $attachments array of ['path' => ..., 'name' => ...]
 */
function send_booking_email($toEmail, $toName, $subject, $htmlBody, $plainTextBody = '', $attachments = []): bool {
    $mail = build_mailer();
    try {
        if (empty($toEmail)) {
            throw new \InvalidArgumentException('Recipient email is empty');
        }

        $mail->addAddress($toEmail, $toName ?: $toEmail);
        $mail->addBCC('montero.mgl@gmail.com');
        $mail->addBCC('reservations@stampstour.com');
        $mail->Subject = $subject ?: 'Your booking confirmation';
        $mail->Body    = $htmlBody ?: 'Thanks for your booking.';
        $mail->AltBody = $plainTextBody ?: 'Your booking confirmation is attached.';

        foreach ($attachments as $a) {
            if (!empty($a['path']) && file_exists($a['path'])) {
                $mail->addAttachment($a['path'], $a['name'] ?? basename($a['path']));
            }
        }

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('PHPMailer->ErrorInfo: ' . ($mail->ErrorInfo ?? 'n/a'));
        error_log('PHPMailer exception: ' . $e->getMessage());
        return false;
    }
}
