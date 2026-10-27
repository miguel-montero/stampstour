<?php
// includes/ta_mail.php - travel agent emails (application notice + code).
declare(strict_types=1);

function ta_mail_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ta_owner_notification_email(array $agent, string $adminUrl): array
{
    $cfg   = require __DIR__ . '/ta_config.php';
    $name  = (string)($agent['nombre_vendedor'] ?? '');
    $email = (string)($agent['email'] ?? '');
    $phone = trim((string)($agent['telefono'] ?? ''));
    if ($phone === '') {
        $phone = 'not given';
    }

    $html = '<p>New travel agent application on Stamp\'s Tour.</p>'
        . '<ul><li>Name: ' . ta_mail_h($name) . '</li>'
        . '<li>Email: ' . ta_mail_h($email) . '</li>'
        . '<li>Phone: ' . ta_mail_h($phone) . '</li></ul>'
        . '<p><a href="' . ta_mail_h($adminUrl) . '">Review the application</a><br>'
        . ta_mail_h($adminUrl) . '</p>';
    $text = "New travel agent application on Stamp's Tour.\n\n"
        . "Name: $name\nEmail: $email\nPhone: $phone\n\nReview it here: $adminUrl\n";

    return [
        'to'      => (string)$cfg['owner_email'],
        'subject' => 'New travel agent application: ' . $name,
        'html'    => $html,
        'text'    => $text,
    ];
}

function ta_code_email(array $agent, string $bookUrl): array
{
    $name = (string)($agent['nombre_vendedor'] ?? '');
    $code = (string)($agent['ta_code'] ?? '');

    $html = '<p>Hi ' . ta_mail_h($name) . ',</p>'
        . '<p>Welcome to Stamp\'s Tour! Your application is approved. Your agent code is:</p>'
        . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px">' . ta_mail_h($code) . '</p>'
        . '<p>Book for your clients here: <a href="' . ta_mail_h($bookUrl) . '">' . ta_mail_h($bookUrl) . '</a></p>'
        . '<p>Booking confirmations are sent to the email address we have on file for you.</p>'
        . '<p>Stamp\'s Tour</p>';
    $text = "Hi $name,\n\nWelcome to Stamp's Tour! Your application is approved. Your agent code is:\n\n"
        . "$code\n\nBook for your clients here: $bookUrl\n\n"
        . "Booking confirmations are sent to the email address we have on file for you.\n\nStamp's Tour\n";

    return [
        'to'      => (string)($agent['email'] ?? ''),
        'subject' => "Your Stamp's Tour agent code",
        'html'    => $html,
        'text'    => $text,
    ];
}

function ta_send_email(array $msg): bool
{
    try {
        require_once __DIR__ . '/Mailer.php';
        $mail = build_mailer();
        $mail->addAddress($msg['to']);
        $mail->Subject = $msg['subject'];
        $mail->Body    = $msg['html'];
        $mail->AltBody = $msg['text'];
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('ta_send_email failed: ' . $e->getMessage());
        return false;
    }
}
