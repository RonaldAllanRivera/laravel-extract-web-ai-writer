<?php

namespace App\Services;

use GuzzleHttp\Client;
use Masterminds\HTML5;
use Symfony\Component\DomCrawler\Crawler;

class ContentExtractor
{
    public function extract(string $url): array
    {
        $client = new Client([
            'timeout' => 20,
            'allow_redirects' => true,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);

        $response = $client->get($url);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            // Attach HTTP status as exception code for callers to persist
            throw new \RuntimeException("Request failed (HTTP {$status}).", $status);
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) {
            throw new \RuntimeException('The URL did not return HTML content.');
        }

        $body = $response->getBody();
        $size = $body->getSize();
        if ($size !== null && $size > 3 * 1024 * 1024) { // > 3 MB
            throw new \RuntimeException('The page is too large to process.');
        }
        $html = (string) $body;
        if ($size === null) {
            $size = strlen($html);
        }

        // Clean and reduce to <body> content only (remove HTML/JS)
        $bodyHtml = $this->stripNoiseWithCrawler($html);

        // Convert to plaintext
        $contentText = $this->toText($bodyHtml);

        // Post-clean CTA and footer related phrases
        $contentText = $this->removeCtaPhrases($contentText);

        return [
            'cleaned_text' => trim($contentText),
            'title' => $this->extractTitle($html),
            'http_status' => $status,
            'content_length' => $size,
        ];
    }

    private function stripNoiseWithCrawler(string $html): string
    {
        $html5 = new HTML5();
        $dom = $html5->loadHTML($html);
        $body = $dom->getElementsByTagName('body')->item(0);
        $crawler = new Crawler($body ?: $dom);

        $selectors = [
            'script', 'style', 'noscript', 'template', 'svg', 'iframe', 'form',
            'header', 'nav', 'aside', 'footer',
            '.header', '.nav', '.navbar', '.menu', '.sidebar', '.breadcrumb',
            '.footer', '.subscribe', '.newsletter', '.cookie', '.banner',
            '[role="banner"]', '[role="navigation"]', '[role="contentinfo"]',
        ];

        foreach ($selectors as $selector) {
            foreach ($crawler->filter($selector) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Remove links/buttons that are obvious CTAs
        $ctaPatterns = [
            '/order\s*now/i', '/buy\s*now/i', '/add\s*to\s*cart/i', '/checkout/i',
            '/discount/i', '/coupon/i', '/shipping/i', '/free\s*shipping/i',
            '/limited\s*time/i', '/save\s*\d+%/i',
        ];
        foreach ($crawler->filter('a, button, input[type="submit"], .btn') as $node) {
            $text = trim($node->textContent ?? '');
            foreach ($ctaPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $node->parentNode?->removeChild($node);
                    break;
                }
            }
        }

        // Return the cleaned <body> HTML (or whole document if <body> is missing)
        return $html5->saveHTML($body ?: $dom);
    }

    private function toText(string $html): string
    {
        // Convert HTML to plaintext while preserving paragraphs
        // Remove <script>...</script> blocks including their contents
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        // First, normalize <br> tags to newlines
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        // Add newlines after common block-level closing tags
        $text = preg_replace('/<\/(p|div|li|section|article|h[1-6]|tr)>/i', "$0\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize whitespace: ensure at most one blank line between paragraphs
        $text = preg_replace('/\n{2,}/', "\n\n", $text);
        // Remove all tab characters completely
        $text = preg_replace('/\t+/', '', $text);
        // Normalize non-breaking spaces to regular spaces
        $text = preg_replace('/[\x{00A0}]+/u', ' ', $text);
        // Collapse repeated spaces/tabs but preserve newlines
        $text = preg_replace('/[ \x{00A0}]{2,}/u', ' ', $text);
        return trim($text);
    }

    private function removeCtaPhrases(string $text): string
    {
        // Remove emojis, arrows, and decorative symbols (e.g., 👉, ★, ✓, arrows)
        $text = preg_replace('/[\x{1F1E6}-\x{1F1FF}\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}]+/u', '', $text);

        $patterns = [
            // Remove any remaining <script>...</script> blocks including their contents (fallback)
            '/<script\b[^>]*>.*?<\/script>/is',

            // Menus / section labels
            '/^\s*(overview|features|reviews|faqs?)\s*$/mi',
            '/^\s*frequently\s+asked\s+questions\s*$/mi',
            '/^.*\bas\s*seen\s*on\b.*$/mi',
            '/^.*\bviral\s+on\s+tiktok\b.*$/mi',

            // Offers / promotions / discounts
            '/^.*\b(offer|deal|exclusive\s*offer|early\s*bird|promotion|promo|sale|today\s*only|limited\s*time)\b.*$/mi',
            '/^.*\b(\d{1,3}%\s*off|up\s*to\s*\d{1,3}%\s*off|get\s*(up\s*to\s*)?\d{1,3}%\s*off)\b.*$/mi',
            '/^.*\b(buy\s*now|order\s*now|add\s*to\s*cart|checkout)\b.*$/mi',
            '/^\s*off\s*$/mi',

            // Ratings / reviews counts
            '/^.*\b\d(\.\d+)?\s*\/\s*5\b.*(verified\s*reviews?|reviews?|ratings?)\b.*$/mi',
            '/^.*\(\s*\d{1,3}(,\d{3})*\s*verified\s*reviews\s*\)\s*$/mi',
            '/^.*\bverified\s*buyer\b.*$/mi',

            // Shipping / stock lines (including carriers and logistics terms)
            '/^.*\b(ship(s|ped|ping)?(\s+by)?|stock\s*level|low\s*stock|back\s*order|backorder|dispatch|deliver(y|ies)|arrives?|usps|fedex|ups|dhl|tracking|warehouse)\b.*$/mi',

            // Guarantees
            '/^.*\b(\d{1,3}\s*[-–—]?\s*day\s*money\s*[-–—]?\s*back\s*guarantee|money\s*back\s*guarantee)\b.*$/mi',

            // Dates
            '/^.*\b\d{4}-\d{2}-\d{2}\b.*$/m',
            '/^.*\b(Jan(uary)?|Feb(ruary)?|Mar(ch)?|Apr(il)?|May|Jun(e)?|Jul(y)?|Aug(ust)?|Sep(t(ember)?)?|Oct(ober)?|Nov(ember)?|Dec(ember)?)\s+\d{1,2}(st|nd|rd|th)?(,\s*\d{2,4})?\b.*$/mi',
            '/^.*\b\d{1,2}(st|nd|rd|th)?\s+(Jan(uary)?|Feb(ruary)?|Mar(ch)?|Apr(il)?|May|Jun(e)?|Jul(y)?|Aug(ust)?|Sep(t(ember)?)?|Oct(ober)?|Nov(ember)?|Dec(ember)?)(,?\s*\d{2,4})?\b.*$/mi',
        ];

        $text = preg_replace($patterns, '', $text) ?? $text;

        // Remove decorative separator-only lines
        $text = preg_replace('/^\s*[-–—•·*]+\s*$/m', '', $text);

        // Remove lines that are only punctuation/brackets common in JS blocks
        $text = preg_replace('/^\s*[\{\}\(\);\[\]\.,\|]+\s*$/m', '', $text);

        // Trim trailing spaces per line
        $text = preg_replace('/[ \t]+$/m', '', $text);

        // Ensure at most a single blank line between paragraphs
        $text = preg_replace('/\n{2,}/', "\n\n", $text);

        return $text;
    }

    /**
     * Re-run conservative cleaning on already extracted plaintext.
     * Useful when adjusting cleaning rules without refetching HTML.
     */
    public function recleanText(string $text): string
    {
        // Apply CTA/footer/etc. phrase cleanup
        $text = $this->removeCtaPhrases($text);

        // Normalize whitespace similar to toText() finishing steps
        $text = preg_replace('/\n{2,}/', "\n\n", $text ?? '') ?? '';
        $text = preg_replace('/\t+/', '', $text) ?? '';
        $text = preg_replace('/[\x{00A0}]+/u', ' ', $text) ?? '';
        $text = preg_replace('/[ \x{00A0}]{2,}/u', ' ', $text) ?? '';
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? '';

        return trim($text);
    }

    private function extractTitle(string $html): ?string
    {
        try {
            $html5 = new HTML5();
            $dom = $html5->loadHTML($html);
            $titleNode = $dom->getElementsByTagName('title')->item(0);
            return $titleNode ? trim($titleNode->textContent) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

