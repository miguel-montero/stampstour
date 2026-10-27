<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TravelAgentsTest extends TestCase
{
    private mysqli $conn;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->now = new DateTimeImmutable('2026-10-27 12:00:00', new DateTimeZone('UTC'));
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->query("DELETE FROM reservas WHERE reference_id LIKE 'TEST\\_%'");
        $this->conn->query("DELETE FROM titulares WHERE email LIKE '%@example.test'");
        $this->conn->query("DELETE FROM vendedores WHERE nombre_vendedor LIKE 'TEST\\_%'");
        $this->conn->query("DELETE FROM ta_code_attempts WHERE ip LIKE 'TEST%'");
    }

    private function apply(string $n = 'a', string $phone = ''): int
    {
        $r = ta_apply($this->conn, "TEST_$n", "$n@example.test", $phone, $this->now);
        $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
        return $r['id'];
    }

    private function setStatus(int $id, string $status): void
    {
        $this->conn->query("UPDATE vendedores SET ta_status = '$status' WHERE id_vendedor = $id");
    }

    public function test_apply_creates_pending_row(): void
    {
        $r = ta_apply($this->conn, '  TEST_Joyce ', '  Joyce@Example.TEST ', '', $this->now);
        $this->assertTrue($r['ok']);
        $row = ta_get($this->conn, $r['id']);
        $this->assertSame('pending', $row['ta_status']);
        $this->assertNull($row['ta_code']);
        $this->assertSame('joyce@example.test', $row['email']);
        $this->assertSame('TEST_Joyce', $row['nombre_vendedor']);
        $this->assertNull($row['telefono']);
        $this->assertSame('2026-10-27 12:00:00', $row['ta_applied_at']);
        $ch = $this->conn->query("SELECT canal_venta FROM vendedores WHERE id_vendedor = {$r['id']}")->fetch_row();
        $this->assertSame('TA', $ch[0]);
    }

    public function test_apply_rejects_invalid_email_and_missing_name(): void
    {
        $this->assertFalse(ta_apply($this->conn, 'TEST_x', 'not-an-email', '', $this->now)['ok']);
        $this->assertFalse(ta_apply($this->conn, '   ', 'x@example.test', '', $this->now)['ok']);
        $this->assertFalse(ta_apply($this->conn, str_repeat('T', 256), 'x@example.test', '', $this->now)['ok']);
        $this->assertFalse(ta_apply($this->conn, 'TEST_x', 'x@example.test', str_repeat('1', 51), $this->now)['ok']);
    }

    public function test_apply_rejects_duplicate_email_case_insensitive_while_pending_or_active(): void
    {
        $id = $this->apply('dup');
        $msg = 'An application with this email already exists. We will contact you soon.';
        $r = ta_apply($this->conn, 'TEST_dup2', 'DUP@example.test', '', $this->now);
        $this->assertFalse($r['ok']);
        $this->assertSame($msg, $r['error']);

        $this->setStatus($id, 'active');
        $this->assertFalse(ta_apply($this->conn, 'TEST_dup2', 'dup@example.test', '', $this->now)['ok']);

        $this->setStatus($id, 'rejected');
        $this->assertTrue(ta_apply($this->conn, 'TEST_dup2', 'dup@example.test', '', $this->now)['ok']);
    }

    public function test_approve_assigns_unique_4_digit_code(): void
    {
        $id = $this->apply('one');
        $row = ta_approve($this->conn, $id, $this->now);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $row['ta_code']);
        $this->assertSame('active', $row['ta_status']);
        $this->assertSame('2026-10-27 12:00:00', $row['ta_approved_at']);

        $codes = [$row['ta_code']];
        for ($i = 0; $i < 29; $i++) {
            $codes[] = ta_approve($this->conn, $this->apply("m$i"), $this->now)['ta_code'];
        }
        $this->assertCount(30, array_unique($codes));
    }

    public function test_approve_twice_keeps_code(): void
    {
        $id = $this->apply('twice');
        $first = ta_approve($this->conn, $id, $this->now);
        $this->assertNull(ta_approve($this->conn, $id, $this->now->modify('+1 day')));
        $row = ta_get($this->conn, $id);
        $this->assertSame($first['ta_code'], $row['ta_code']);
        $this->assertSame('2026-10-27 12:00:00', $row['ta_approved_at']);
    }

    public function test_generate_code_skips_taken_codes(): void
    {
        $taken = [];
        for ($i = 0; $i < 50; $i++) {
            $code = str_pad((string)$i, 4, '0', STR_PAD_LEFT);
            $taken[] = $code;
            $this->conn->query("INSERT INTO vendedores (nombre_vendedor, canal_venta, ta_code, ta_status) VALUES ('TEST_t$i', 'TA', '$code', 'active')");
        }
        for ($i = 0; $i < 200; $i++) {
            $c = ta_generate_code($this->conn);
            $this->assertMatchesRegularExpression('/^\d{4}$/', $c);
            $this->assertNotContains($c, $taken);
        }
    }

    public function test_find_active_by_code(): void
    {
        $id = $this->apply('find');
        $this->conn->query("UPDATE vendedores SET ta_code = '0427', ta_status = 'active' WHERE id_vendedor = $id");
        $this->assertSame($id, (int)ta_find_active_by_code($this->conn, ' 0427 ')['id_vendedor']);
        foreach (['427', '04271', 'abcd', '', '0428'] as $bad) {
            $this->assertNull(ta_find_active_by_code($this->conn, $bad), $bad);
        }
        $this->assertTrue(ta_set_active($this->conn, $id, false));
        $this->assertNull(ta_find_active_by_code($this->conn, '0427'));
        $this->assertTrue(ta_set_active($this->conn, $id, true));
        $this->assertSame('0427', ta_find_active_by_code($this->conn, '0427')['ta_code']);
    }

    public function test_set_active_only_between_active_and_inactive(): void
    {
        $id = $this->apply('sa');
        $this->assertFalse(ta_set_active($this->conn, $id, true));
        $this->assertFalse(ta_set_active($this->conn, $id, false));
        $this->assertSame('pending', ta_get($this->conn, $id)['ta_status']);
    }

    public function test_reject_only_from_pending(): void
    {
        $a = $this->apply('rej');
        $this->assertTrue(ta_reject($this->conn, $a));
        $this->assertSame('rejected', ta_get($this->conn, $a)['ta_status']);
        $this->assertFalse(ta_reject($this->conn, $a));

        $b = $this->apply('rej2');
        ta_approve($this->conn, $b, $this->now);
        $this->assertFalse(ta_reject($this->conn, $b));
        $this->assertSame('active', ta_get($this->conn, $b)['ta_status']);
    }

    public function test_rate_limit_after_10_failures_in_15_minutes(): void
    {
        $ip = 'TEST-1.2.3.4';
        for ($i = 0; $i < 9; $i++) {
            ta_record_failure($this->conn, $ip, $this->now);
        }
        $this->assertFalse(ta_is_rate_limited($this->conn, $ip, $this->now));
        ta_record_failure($this->conn, $ip, $this->now);
        $this->assertTrue(ta_is_rate_limited($this->conn, $ip, $this->now));
        $this->assertFalse(ta_is_rate_limited($this->conn, 'TEST-9.9.9.9', $this->now));
        $this->assertFalse(ta_is_rate_limited($this->conn, $ip, $this->now->modify('+16 minutes')));
    }

    public function test_daily_cap_limits_slow_attacker(): void
    {
        $ip = 'TEST-7.7.7.7';
        for ($i = 0; $i < 29; $i++) {
            ta_record_failure($this->conn, $ip, $this->now->modify('-' . (20 * 60 - $i * 40) . ' minutes'));
        }
        $this->assertFalse(ta_is_rate_limited($this->conn, $ip, $this->now));
        ta_record_failure($this->conn, $ip, $this->now->modify('-1 minute'));
        $this->assertTrue(ta_is_rate_limited($this->conn, $ip, $this->now));
        $this->assertFalse(ta_is_rate_limited($this->conn, 'TEST-7.7.7.8', $this->now));
        $this->assertFalse(ta_is_rate_limited($this->conn, $ip, $this->now->modify('+25 hours')));
    }

    public function test_ip_bucket_groups_ipv6_by_slash_64_and_keeps_ipv4(): void
    {
        $this->assertSame('203.0.113.9', ta_ip_bucket('203.0.113.9'));
        $this->assertSame('203.0.113.9', ta_ip_bucket('::ffff:203.0.113.9'));
        $a = ta_ip_bucket('2001:db8:1:2:aaaa:bbbb:cccc:dddd');
        $this->assertSame($a, ta_ip_bucket('2001:0db8:0001:0002::1'));
        $this->assertNotSame($a, ta_ip_bucket('2001:db8:1:3::1'));
        $this->assertNotSame('', $a);

        $ip1 = '2001:db8:1:2:aaaa:bbbb:cccc:dddd';
        $ip2 = '2001:db8:1:2:1111:2222:3333:4444';
        for ($i = 0; $i < 10; $i++) {
            ta_record_failure($this->conn, $i % 2 ? $ip1 : $ip2, $this->now);
        }
        $this->assertTrue(ta_is_rate_limited($this->conn, $ip1, $this->now));
        $this->assertTrue(ta_is_rate_limited($this->conn, $ip2, $this->now));
        $this->conn->query("DELETE FROM ta_code_attempts WHERE ip LIKE '2001:db8:%'");
    }

    private function applyPost(string $n, array $o = []): array
    {
        return array_merge(['name' => "TEST_$n", 'email' => "$n@example.test", 'phone' => ''], $o);
    }

    public function test_honeypot_creates_nothing_and_looks_successful(): void
    {
        $r = ta_handle_application($this->conn, $this->applyPost('bot', ['website' => 'http://spam.example']), 'TEST-8.8.8.8', $this->now);
        $this->assertTrue($r['ok']);
        $this->assertArrayNotHasKey('id', $r);
        $n = $this->conn->query("SELECT COUNT(*) FROM vendedores WHERE email = 'bot@example.test'")->fetch_row()[0];
        $this->assertSame(0, (int)$n);
        $m = $this->conn->query("SELECT COUNT(*) FROM ta_code_attempts WHERE ip = 'TEST-8.8.8.8'")->fetch_row()[0];
        $this->assertSame(0, (int)$m);
    }

    public function test_apply_limited_to_3_per_ip_per_hour(): void
    {
        $ip = 'TEST-8.1.1.1';
        for ($i = 1; $i <= 3; $i++) {
            $r = ta_handle_application($this->conn, $this->applyPost("lim$i"), $ip, $this->now);
            $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
        }
        $r = ta_handle_application($this->conn, $this->applyPost('lim4'), $ip, $this->now->modify('+30 minutes'));
        $this->assertFalse($r['ok']);
        $this->assertSame('Too many applications from your connection. Please try again later.', $r['error']);
        $this->assertSame(0, (int)$this->conn->query("SELECT COUNT(*) FROM vendedores WHERE email = 'lim4@example.test'")->fetch_row()[0]);

        $r = ta_handle_application($this->conn, $this->applyPost('other'), 'TEST-8.2.2.2', $this->now->modify('+30 minutes'));
        $this->assertTrue($r['ok']);

        $r = ta_handle_application($this->conn, $this->applyPost('lim4'), $ip, $this->now->modify('+61 minutes'));
        $this->assertTrue($r['ok'], (string)($r['error'] ?? ''));
    }

    public function test_apply_attempts_do_not_count_toward_code_rate_limit(): void
    {
        $ip = 'TEST-8.3.3.3';
        for ($i = 1; $i <= 3; $i++) {
            ta_handle_application($this->conn, $this->applyPost("sep$i"), $ip, $this->now);
        }
        $this->assertFalse(ta_is_rate_limited($this->conn, $ip, $this->now));
    }

    public function test_record_failure_purges_old_rows(): void
    {
        $ip = 'TEST-5.5.5.5';
        ta_record_failure($this->conn, $ip, $this->now->modify('-25 hours'));
        ta_record_failure($this->conn, $ip, $this->now);
        $n = $this->conn->query("SELECT COUNT(*) FROM ta_code_attempts WHERE ip = '$ip'")->fetch_row()[0];
        $this->assertSame(1, (int)$n);
    }

    public function test_list_groups_by_status_with_booking_counts(): void
    {
        $p = $this->apply('lp');
        $a = $this->apply('la');
        $i = $this->apply('li');
        $rj = $this->apply('lr');
        ta_approve($this->conn, $a, $this->now);
        ta_approve($this->conn, $i, $this->now);
        ta_set_active($this->conn, $i, false);
        ta_reject($this->conn, $rj);

        $this->conn->query("INSERT INTO titulares (nombre, apellido, email) VALUES ('T', 'T', 't@example.test')");
        $tid = $this->conn->insert_id;
        foreach (['TEST_R1', 'TEST_R2'] as $ref) {
            $this->conn->query("INSERT INTO reservas (reference_id, fecha_reserva, fecha_actividad, id_titular, id_vendedor)
                                VALUES ('$ref', '2026-10-27', '2026-11-01', $tid, $a)");
        }

        $list = ta_list($this->conn);
        $ids = fn(array $rows) => array_map(fn($r) => (int)$r['id_vendedor'], $rows);
        $this->assertSame([$p], array_values(array_intersect($ids($list['pending']), [$p, $a, $i, $rj])));
        $this->assertSame([$a], array_values(array_intersect($ids($list['active']), [$p, $a, $i, $rj])));
        $this->assertSame([$i], array_values(array_intersect($ids($list['inactive']), [$p, $a, $i, $rj])));
        $this->assertArrayNotHasKey('rejected', $list);
        foreach ($list['active'] as $r) {
            if ((int)$r['id_vendedor'] === $a) {
                $this->assertSame(2, (int)$r['bookings']);
            }
        }
        $this->assertSame(0, (int)$list['pending'][array_search($p, $ids($list['pending']), true)]['bookings']);
    }

    public function test_legacy_lookup_allows_null_status_and_active_only(): void
    {
        $this->conn->query("INSERT INTO vendedores (nombre_vendedor, canal_venta) VALUES ('TEST_legacy', 'TA')");
        $legacy = $this->conn->insert_id;
        $r = ta_legacy_vendor_lookup($this->conn, $legacy);
        $this->assertSame(['id_vendedor' => $legacy, 'nombre_vendedor' => 'TEST_legacy'], $r);

        $id = $this->apply('lg');
        $this->assertNull(ta_legacy_vendor_lookup($this->conn, $id));
        ta_approve($this->conn, $id, $this->now);
        $this->assertNotNull(ta_legacy_vendor_lookup($this->conn, $id));
        ta_set_active($this->conn, $id, false);
        $this->assertNull(ta_legacy_vendor_lookup($this->conn, $id));
        $this->setStatus($id, 'rejected');
        $this->assertNull(ta_legacy_vendor_lookup($this->conn, $id));

        $this->conn->query("INSERT INTO vendedores (nombre_vendedor, canal_venta) VALUES ('TEST_web', 'WEB')");
        $this->assertNull(ta_legacy_vendor_lookup($this->conn, $this->conn->insert_id));
    }

    public function test_mask_email(): void
    {
        $this->assertSame('j****@agency.com', ta_mask_email('joyce@agency.com'));
        $this->assertSame('a*@x.com', ta_mask_email('ab@x.com'));
        $this->assertSame('a@x.com', ta_mask_email('a@x.com'));
        $this->assertSame('j****@agency.com', ta_mask_email('jonathanlongname@agency.com'));
    }
}
