<?php
// includes/travel_agents.php
// Travel-agent records, 4-digit codes, statuses and code-check rate limit.

const TA_RATE_LIMIT_MAX = 10;
const TA_RATE_LIMIT_WINDOW_MIN = 15;
const TA_DAILY_LIMIT_MAX = 30;
const TA_APPLY_LIMIT_MAX = 3;
const TA_APPLY_LIMIT_WINDOW_MIN = 60;

const TA_DB_DATETIME = 'Y-m-d H:i:s';

const TA_SELECT_COLUMNS = 'id_vendedor, nombre_vendedor, email, telefono, ta_code, ta_status, ta_applied_at, ta_approved_at';

function ta_apply(mysqli $conn, string $name, string $email, string $phone, DateTimeImmutable $now): array
{
    $name  = trim($name);
    $email = strtolower(trim($email));
    $phone = trim($phone);

    if ($name === '' || mb_strlen($name) > 255) {
        return ['ok' => false, 'error' => 'Please enter your name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (mb_strlen($phone) > 50) {
        return ['ok' => false, 'error' => 'Phone number is too long.'];
    }

    $stmt = $conn->prepare("SELECT 1 FROM vendedores WHERE LOWER(email) = ? AND ta_status IN ('pending','active') LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($exists) {
        return ['ok' => false, 'error' => 'An application with this email already exists. We will contact you soon.'];
    }

    $phoneValue = $phone === '' ? null : $phone;
    $applied = $now->format(TA_DB_DATETIME);
    $stmt = $conn->prepare("INSERT INTO vendedores (nombre_vendedor, canal_venta, email, telefono, ta_status, ta_applied_at)
                            VALUES (?, 'TA', ?, ?, 'pending', ?)");
    $stmt->bind_param('ssss', $name, $email, $phoneValue, $applied);
    $ok = $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $ok ? ['ok' => true, 'id' => $id] : ['ok' => false, 'error' => 'Could not save your application. Please try again.'];
}

function ta_generate_code(mysqli $conn): string
{
    $stmt = $conn->prepare('SELECT 1 FROM vendedores WHERE ta_code = ? LIMIT 1');
    for ($i = 0; $i < 50; $i++) {
        $code = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $taken = $stmt->get_result()->num_rows > 0;
        if (!$taken) {
            $stmt->close();
            return $code;
        }
    }
    $stmt->close();
    throw new RuntimeException('Could not generate a unique travel agent code.');
}

function ta_get(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT ' . TA_SELECT_COLUMNS . ' FROM vendedores WHERE id_vendedor = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ta_approve(mysqli $conn, int $id, DateTimeImmutable $now): ?array
{
    $code = ta_generate_code($conn);
    $approved = $now->format(TA_DB_DATETIME);
    $stmt = $conn->prepare("UPDATE vendedores SET ta_code = ?, ta_status = 'active', ta_approved_at = ?
                            WHERE id_vendedor = ? AND canal_venta = 'TA' AND ta_status = 'pending'");
    $stmt->bind_param('ssi', $code, $approved, $id);
    $stmt->execute();
    $changed = $stmt->affected_rows === 1;
    $stmt->close();
    return $changed ? ta_get($conn, $id) : null;
}

function ta_reject(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare("UPDATE vendedores SET ta_status = 'rejected' WHERE id_vendedor = ? AND ta_status = 'pending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $changed = $stmt->affected_rows === 1;
    $stmt->close();
    return $changed;
}

function ta_set_active(mysqli $conn, int $id, bool $active): bool
{
    $to   = $active ? 'active' : 'inactive';
    $from = $active ? 'inactive' : 'active';
    $stmt = $conn->prepare('UPDATE vendedores SET ta_status = ? WHERE id_vendedor = ? AND ta_status = ?');
    $stmt->bind_param('sis', $to, $id, $from);
    $stmt->execute();
    $changed = $stmt->affected_rows === 1;
    $stmt->close();
    return $changed;
}

function ta_find_active_by_code(mysqli $conn, string $code): ?array
{
    $code = trim($code);
    if (!preg_match('/^\d{4}$/', $code)) {
        return null;
    }
    $stmt = $conn->prepare("SELECT " . TA_SELECT_COLUMNS . " FROM vendedores WHERE ta_code = ? AND ta_status = 'active' LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ta_list(mysqli $conn): array
{
    $out = ['pending' => [], 'active' => [], 'inactive' => []];
    $sql = "SELECT v.id_vendedor, v.nombre_vendedor, v.email, v.telefono, v.ta_code, v.ta_status,
                   v.ta_applied_at, v.ta_approved_at,
                   (SELECT COUNT(*) FROM reservas r WHERE r.id_vendedor = v.id_vendedor) AS bookings
            FROM vendedores v
            WHERE v.canal_venta = 'TA' AND v.ta_status IN ('pending','active','inactive')
            ORDER BY v.ta_applied_at DESC, v.id_vendedor DESC";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $out[$row['ta_status']][] = $row;
    }
    return $out;
}

/** Rate-limit bucket for an address: IPv6 -> its /64 prefix, IPv4 (and anything else) unchanged. */
function ta_ip_bucket(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
        return $ip;
    }
    $bin = inet_pton($ip);
    if ($bin === false || strlen($bin) !== 16) {
        return $ip;
    }
    if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        return implode('.', array_map('ord', str_split(substr($bin, 12))));
    }
    return implode(':', str_split(bin2hex(substr($bin, 0, 8)), 4)) . '::/64';
}

function ta_attempts_since(mysqli $conn, string $bucket, string $kind, DateTimeImmutable $now, string $interval): int
{
    $since = $now->modify('-' . $interval)->format(TA_DB_DATETIME);
    $stmt = $conn->prepare('SELECT COUNT(*) FROM ta_code_attempts WHERE ip = ? AND kind = ? AND attempted_at > ?');
    $stmt->bind_param('sss', $bucket, $kind, $since);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

function ta_log_attempt(mysqli $conn, string $ip, string $kind, DateTimeImmutable $now): void
{
    $bucket = ta_ip_bucket($ip);
    $at = $now->format(TA_DB_DATETIME);
    $stmt = $conn->prepare('INSERT INTO ta_code_attempts (ip, kind, attempted_at) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $bucket, $kind, $at);
    $stmt->execute();
    $stmt->close();

    $cutoff = $now->modify('-24 hours')->format(TA_DB_DATETIME);
    $stmt = $conn->prepare('DELETE FROM ta_code_attempts WHERE attempted_at < ?');
    $stmt->bind_param('s', $cutoff);
    $stmt->execute();
    $stmt->close();
}

/** 10 failed code checks / 15 min, or 30 / rolling 24h, per IP bucket. No site-wide lockout by design. */
function ta_is_rate_limited(mysqli $conn, string $ip, DateTimeImmutable $now): bool
{
    $bucket = ta_ip_bucket($ip);
    return ta_attempts_since($conn, $bucket, 'code', $now, TA_RATE_LIMIT_WINDOW_MIN . ' minutes') >= TA_RATE_LIMIT_MAX
        || ta_attempts_since($conn, $bucket, 'code', $now, '24 hours') >= TA_DAILY_LIMIT_MAX;
}

function ta_record_failure(mysqli $conn, string $ip, DateTimeImmutable $now): void
{
    ta_log_attempt($conn, $ip, 'code', $now);
}

function ta_apply_is_limited(mysqli $conn, string $ip, DateTimeImmutable $now): bool
{
    return ta_attempts_since($conn, ta_ip_bucket($ip), 'apply', $now, TA_APPLY_LIMIT_WINDOW_MIN . ' minutes') >= TA_APPLY_LIMIT_MAX;
}

/**
 * Public application entry point: honeypot, per-IP limit, then ta_apply().
 * A filled honeypot looks like success but creates and records nothing
 * (result has 'id' absent, so callers send no email).
 */
function ta_handle_application(mysqli $conn, array $post, string $ip, DateTimeImmutable $now): array
{
    if (trim((string)($post['website'] ?? '')) !== '') {
        return ['ok' => true];
    }
    if (ta_apply_is_limited($conn, $ip, $now)) {
        return ['ok' => false, 'error' => 'Too many applications from your connection. Please try again later.'];
    }
    $result = ta_apply($conn, (string)($post['name'] ?? ''), (string)($post['email'] ?? ''), (string)($post['phone'] ?? ''), $now);
    if ($result['ok']) {
        ta_log_attempt($conn, $ip, 'apply', $now);
    }
    return $result;
}

function ta_mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false || $at === 0) {
        return $email;
    }
    $local = substr($email, 0, $at);
    $stars = min(4, strlen($local) - 1);
    return $local[0] . str_repeat('*', $stars) . substr($email, $at);
}

function ta_legacy_vendor_lookup(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT id_vendedor, nombre_vendedor FROM vendedores
                            WHERE id_vendedor = ? AND canal_venta = 'TA' AND (ta_status IS NULL OR ta_status = 'active')
                            LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? ['id_vendedor' => (int)$row['id_vendedor'], 'nombre_vendedor' => $row['nombre_vendedor']] : null;
}
