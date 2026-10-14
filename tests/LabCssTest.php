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
        // Tailwind auto-emits an `@layer properties` fallback (old browsers only,
        // sets --tw-* initial values); every other @layer is forbidden.
        $withoutFallback = preg_replace('/@layer properties\{(?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*\}/', '', $css);
        $this->assertStringNotContainsString('@layer', (string)$withoutFallback);
        foreach (glob(__DIR__ . '/../css/src/*.css') as $src) {
            $this->assertStringNotContainsString('@layer', (string)preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents($src)), basename($src)); // comments may mention it
        }
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
