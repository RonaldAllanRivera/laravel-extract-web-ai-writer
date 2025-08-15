<?php

namespace Tests\Unit;

use App\Services\ContentExtractor;
use PHPUnit\Framework\TestCase;

class ContentExtractorTest extends TestCase
{
    private function callPrivate(object $object, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionClass($object);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($object, ...$args);
    }

    public function test_toText_cleans_and_preserves_linebreaks(): void
    {
        $svc = new ContentExtractor();
        $html = '<html><body>' .
            '<script>console.log(1)</script>' .
            '<h1>Title</h1>' .
            '<p>Line&nbsp;one</p>' .
            '<div>Line two<br>more</div>' .
            '<ul><li>Item 1</li><li>Item  2</li></ul>' .
            "\t" .
            '</body></html>';

        /** @var string $text */
        $text = $this->callPrivate($svc, 'toText', $html);

        $expected = "Title\n\nLine one\n\nLine two\nmore\n\nItem 1\nItem 2";
        $this->assertSame($expected, $text);
    }

    public function test_removeCtaPhrases_drops_noise_and_keeps_faq(): void
    {
        $svc = new ContentExtractor();
        $input = implode("\n", [
            'Buy now and save 50%!',
            '4.8/5 123 verified reviews',
            'Ships by USPS with tracking',
            '2025-08-15',
            '30-day money back guarantee',
            'Q: Is it waterproof?',
            'A: Yes',
            'Viral on TikTok',
            'Content keeps here',
        ]);

        /** @var string $out */
        $out = $this->callPrivate($svc, 'removeCtaPhrases', $input);

        $expected = implode("\n", [
            'Q: Is it waterproof?',
            'A: Yes',
            'Content keeps here',
        ]);

        // Normalize multiple blank lines to one blank line segments in expected as method does
        $this->assertSame($expected, trim($out));
    }
}
