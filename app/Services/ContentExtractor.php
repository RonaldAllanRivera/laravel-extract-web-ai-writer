<?php

namespace App\Services;

use andreskrey\Readability\Configuration;
use andreskrey\Readability\Readability;
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

        // Pre-clean obvious noise before readability
        $html = $this->stripNoiseWithCrawler($html);

        // Extract main content using Readability
        $config = new Configuration();
        $config->setFixRelativeURLs(true);
        $config->setOriginalURL($url);
        $readability = new Readability($config);
        $readability->parse($html);

        $contentHtml = $readability->getContent() ?? '';
        $contentText = $this->toText($contentHtml);

        // Post-clean CTA and footer related phrases
        $contentText = $this->removeCtaPhrases($contentText);

        return [
            'cleaned_text' => trim($contentText),
            'title' => $readability->getTitle() ?: null,
        ];
    }

    private function stripNoiseWithCrawler(string $html): string
    {
        $html5 = new HTML5();
        $dom = $html5->loadHTML($html);
        $crawler = new Crawler($dom);

        $selectors = [
            'script', 'style', 'noscript', 'form',
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

        return $html5->saveHTML($dom);
    }

    private function toText(string $html): string
    {
        // Convert HTML to plaintext while preserving paragraphs
        $text = preg_replace('/<\/(p|div|br|li)>/i', "$0\n", $html);
        $text = strip_tags($text);
        // Normalize whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/[\t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
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
}
