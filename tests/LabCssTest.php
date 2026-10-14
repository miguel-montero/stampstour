<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LabCssTest extends TestCase
{
    private const CSS = __DIR__ . '/../css/lab.css';

    public function testCompiledCssIsUpToDate(): void
    {
        exec(escapeshellarg(__DIR__ . '/../bin/build-css') . ' --check 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function testNoPreflightNoLayersAndTokensPresent(): void
    {
        $css = (string)file_get_contents(self::CSS);
        $this->assertStringNotContainsString('@layer', $css);
        $this->assertStringNotContainsString('::file-selector-button', $css); // preflight marker
        $this->assertStringContainsString('--tw-color-teal:#008489', $css);
        $this->assertStringContainsString('--tw-color-coral:#e04f67', $css);
        $this->assertStringContainsString('.lab{', $css);
    }

    public function testSizeBudget(): void
    {
        $this->assertLessThanOrEqual(30 * 1024, filesize(self::CSS));
    }
}
