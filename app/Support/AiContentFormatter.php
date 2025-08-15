<?php

namespace App\Support;

class AiContentFormatter
{
    /**
     * Convert markdown-style AI output into a highlighted HTML table.
     * Supports sections like: "## **1. Short Headline**" followed by content
     * until the next heading or EOF.
     */
    public static function toHtmlTable(?string $markdown, string $type = 'interstitial'): ?string
    {
        if (! $markdown) {
            return null;
        }

        $text = trim($markdown);
        if ($text === '') {
            return null;
        }

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Find headings like: ## **1. Title**
        $pattern = '/^##\s*\*\*(\d+)\.[^\*]*?\s*(.*?)\s*\*\*\s*$/m';
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            // Fallback: single cell with the whole content
            return self::wrapTable('<tr><th style="vertical-align:top;white-space:nowrap;padding:8px;border:1px solid #e5e7eb;background:#f8fafc; color: #000;">AI Output</th><td style="padding:8px;border:1px solid #e5e7eb; color: #000;">' . self::escapeAndNl2Br($text) . '</td></tr>', $type);
        }

        $rowsHtml = '';
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $sectionNum = $matches[1][$i][0];
            $sectionTitle = $matches[2][$i][0];
            $startPos = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $endPos = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($text);
            $body = trim(substr($text, $startPos, $endPos - $startPos));

            // Remove horizontal rules and extra markers
            $body = preg_replace('/^---+$/m', '', $body);
            $body = trim($body);

            // Section-specific normalization: For section 10 (Features),
            // pair lines like "**Title**" followed by a description line into a single bullet
            if ((string) $sectionNum === '10') {
                $body = self::normalizeFeaturePairs($body);
            }

            // Convert bullet lists * ... to <ul>
            $body = self::bulletsToHtml($body);

            // For section 10, render features as compact, bullet-less list items
            if ((string) $sectionNum === '10') {
                $body = preg_replace('/<ul(\s+[^>]*)?>/i', '<ul style="list-style:none;margin:0;padding-left:0">', $body);
                $body = str_replace('<li>', '<li style="margin:2px 0">', $body);
            }

            // Convert bold markers **text** to <strong>
            $body = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $body);

            // Convert single-asterisk italics *text* to <em>
            // Avoid matching bold (** **) or bullets (* ) by ensuring no adjacent asterisks and no immediate space after the opening asterisk
            $body = preg_replace('/(?<!\*)\*(?!\s)([^\*]+?)\*(?!\*)/s', '<em>$1</em>', $body);

            // Convert remaining newlines to <br> only if body is plain text (no list HTML)
            if (stripos($body, '<ul') === false && stripos($body, '<ol') === false) {
                $body = nl2br($body);
            }

            $label = htmlspecialchars($sectionNum . '. ' . $sectionTitle, ENT_QUOTES, 'UTF-8');

            $rowsHtml .= '<tr>'
                . '<th style="vertical-align:top;white-space:nowrap;padding:8px;border:1px solid #e5e7eb;background:#f8fafc">' . $label . '</th>'
                . '<td style="padding:8px;border:1px solid #e5e7eb">' . $body . '</td>'
                . '</tr>';
        }

        return self::wrapTable($rowsHtml, $type);
    }

    private static function bulletsToHtml(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];
        $inList = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*\*\s+(.*)$/', $line, $m)) {
                if (!$inList) {
                    $out[] = '<ul style="margin:0 0 0.5rem 1.25rem;">';
                    $inList = true;
                }
                $out[] = '<li>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</li>';
            } else {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $out[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            }
        }
        if ($inList) {
            $out[] = '</ul>';
        }

        return implode("\n", $out);
    }

    /**
     * Normalize Feature pairs for section 10 by converting:
     *   **Title**\nDescription
     * into a single bullet line:
     *   * **Title** Description
     */
    private static function normalizeFeaturePairs(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            // Case A: Bold-only title line
            if (preg_match('/^\s*\*\*(.+?)\*\*\s*$/', $line, $m)) {
                // Find next non-empty line (skip blank lines)
                $j = $i + 1;
                while ($j < $count && trim($lines[$j]) === '') {
                    $j++;
                }
                $next = $lines[$j] ?? '';
                $nextTrim = trim($next);
                // Next line must be a plain description (not another heading/bullet/label)
                if ($nextTrim !== ''
                    && !preg_match('/^\s*(\*\s+|\*\*|##|\-\s+)/', $nextTrim)
                    && stripos($nextTrim, 'Features Body:') !== 0) {
                    $out[] = '* **' . trim($m[1]) . '** ' . $nextTrim;
                    $i = $j; // consume up to the description line
                    continue;
                }
            }
            // Case B: Bullet with bold title line
            if (preg_match('/^\s*\*\s+\*\*(.+?)\*\*\s*$/', $line, $m)) {
                $j = $i + 1;
                while ($j < $count && trim($lines[$j]) === '') {
                    $j++;
                }
                $next = $lines[$j] ?? '';
                $nextTrim = trim($next);
                if ($nextTrim !== ''
                    && !preg_match('/^\s*(\*\s+|\*\*|##|\-\s+)/', $nextTrim)
                    && stripos($nextTrim, 'Features Body:') !== 0) {
                    $out[] = '* **' . trim($m[1]) . '** ' . $nextTrim;
                    $i = $j;
                    continue;
                }
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }

    private static function escapeAndNl2Br(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    private static function wrapTable(string $rowsHtml, string $type): string
    {
        $title = $type === 'advertorial' ? 'AI Advertorial (latest)' : 'AI Interstitial (latest)';

        return '<div style="border:1px solid #f59e0b;background:#fffbeb;border-radius:8px;padding:10px;margin:10px 0">'
            . '<div style="font-weight:600;color:#92400e;margin-bottom:8px;display:flex;align-items:center;gap:6px">'
            . '<span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#fbbf24;color:#78350f;font-size:12px">AI</span>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div style="overflow:auto">'
            . '<table style="width:100%;border-collapse:collapse;background:#fff; color: #000;">'
            . $rowsHtml
            . '</table>'
            . '</div>'
            . '</div>';
    }
}
