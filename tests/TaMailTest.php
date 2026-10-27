<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaMailTest extends TestCase
{
    private function agent(array $o = []): array
    {
        return $o + [
            'id_vendedor' => 7, 'nombre_vendedor' => 'Ana Perez', 'email' => 'ana@agency.com',
            'telefono' => '+1 555 0100', 'ta_code' => '0427', 'ta_status' => 'approved',
        ];
    }

    public function testOwnerNotification(): void
    {
        $cfg = require __DIR__ . '/../includes/ta_config.php';
        $m = ta_owner_notification_email($this->agent(), 'https://x.test/admin/ta.php');
        $this->assertSame($cfg['owner_email'], $m['to']);
        $this->assertSame('New travel agent application: Ana Perez', $m['subject']);
        foreach (['Ana Perez', 'ana@agency.com', '+1 555 0100', 'https://x.test/admin/ta.php'] as $s) {
            $this->assertStringContainsString($s, $m['html']);
            $this->assertStringContainsString($s, $m['text']);
        }
    }

    public function testOwnerNotificationNoPhone(): void
    {
        $m = ta_owner_notification_email($this->agent(['telefono' => null]), 'https://x.test/a');
        $this->assertStringContainsString('not given', $m['html']);
        $this->assertStringContainsString('not given', $m['text']);
    }

    public function testCodeEmail(): void
    {
        $m = ta_code_email($this->agent(), 'https://x.test/agents/book.php');
        $this->assertSame('ana@agency.com', $m['to']);
        $this->assertSame("Your Stamp's Tour agent code", $m['subject']);
        foreach (['0427', 'https://x.test/agents/book.php'] as $s) {
            $this->assertStringContainsString($s, $m['html']);
            $this->assertStringContainsString($s, $m['text']);
        }
    }

    public function testHtmlEscapesNames(): void
    {
        $a = $this->agent(['nombre_vendedor' => '<script>alert(1)</script>']);
        foreach ([ta_owner_notification_email($a, 'https://x.test/a'), ta_code_email($a, 'https://x.test/b')] as $m) {
            $this->assertStringNotContainsString('<script>', $m['html']);
        }
        $this->assertStringContainsString('&lt;script&gt;', ta_owner_notification_email($a, 'https://x.test/a')['html']);
    }
}
