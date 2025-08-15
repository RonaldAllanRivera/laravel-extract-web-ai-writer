<?php

namespace App\Services;

use GuzzleHttp\Client;
use Masterminds\HTML5;
use Symfony\Component\DomCrawler\Crawler;

class ContentExtractor
{
    public function extract(string $url): array
    {
        $client = new Client(['timeout' => 20]);

        $response = $client->get($url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);
        $html = (string) $response->getBody();

        // Clean and reduce to <body> content only (remove HTML/JS)
        $bodyHtml = $this->stripNoiseWithCrawler($html);

        // Convert to plaintext
        $contentText = $this->toText($bodyHtml);

        // Post-clean CTA and footer related phrases
        $contentText = $this->removeCtaPhrases($contentText);

        return [
            'cleaned_text' => trim($contentText),
            'title' => $this->extractTitle($html),
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
        // First, normalize <br> tags to newlines
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        // Add newlines after common block-level closing tags
        $text = preg_replace('/<\/(p|div|li|section|article|h[1-6]|tr)>/i', "$0\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/[\t\x{00A0}]+/u', ' ', $text);
        // Collapse repeated spaces/tabs but preserve newlines
        $text = preg_replace('/[ \t\x{00A0}]{2,}/u', ' ', $text);
        return trim($text);
    }

    private function removeCtaPhrases(string $text): string
    {
        $patterns = [
            '/order\s*now.*$/mi',
            '/buy\s*now.*$/mi',
            '/add\s*to\s*cart.*$/mi',
            '/checkout.*$/mi',
            '/discount.*$/mi',
            '/coupon.*$/mi',
            '/shipping.*$/mi',
            '/free\s*shipping.*$/mi',
        ];
        return preg_replace($patterns, '', $text) ?? $text;
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

