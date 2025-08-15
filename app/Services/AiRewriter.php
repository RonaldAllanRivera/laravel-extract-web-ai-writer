<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AiRewriter
{
    public function __construct(
        private ?string $provider = null,
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?float $temperature = null,
        private ?int $maxOutputTokens = null,
        private ?int $inputTokenBudget = null,
    ) {
        $this->provider = $this->provider ?? (string) config('ai.provider', 'openai');
        $this->apiKey = $this->apiKey ?? (string) config('ai.openai.api_key');
        $this->model = $this->model ?? (string) config('ai.openai.model', 'gpt-4o-mini');
        $this->temperature = $this->temperature ?? (float) config('ai.openai.temperature', 0.7);
        $this->maxOutputTokens = $this->maxOutputTokens ?? (int) config('ai.openai.max_output_tokens', 800);
        $this->inputTokenBudget = $this->inputTokenBudget ?? (int) config('ai.openai.input_token_budget', 6000);
    }

    /**
     * Generate rewritten content for a given layout using the provided source text.
     *
     * @param string $layout        interstitial|advertorial
     * @param string $sourceText    cleaned page text
     * @return array{content:string, ai_model:string, tokens_input:int|null, tokens_output:int|null, temperature:float, provider:string, prompt_version:string}
     */
    public function generate(string $layout, string $sourceText): array
    {
        if ($this->provider !== 'openai') {
            throw new \RuntimeException('Unsupported AI provider: ' . $this->provider);
        }
        if (! $this->apiKey) {
            throw new \RuntimeException('Missing OpenAI API key. Set OPENAI_API_KEY in your environment.');
        }

        // Truncate source to budget (rough estimate of tokens)
        $truncated = $this->truncateToTokenBudget($sourceText, $this->inputTokenBudget);

        [$messages, $promptVersion] = $this->buildMessages($layout, $truncated);

        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 60,
        ]);

        // Allow per-layout overrides for output length
        $maxTokens = $this->maxOutputTokens;
        if ($layout === 'interstitial') {
            $maxTokens = (int) (config('ai.layouts.interstitial.max_output_tokens') ?? $maxTokens);
        } elseif ($layout === 'advertorial') {
            $maxTokens = (int) (config('ai.layouts.advertorial.max_output_tokens') ?? $maxTokens);
        }

        try {
            $resp = $client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => $this->temperature,
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('AI request failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $resp->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('AI request failed (HTTP ' . $status . ').');
        }

        $data = json_decode((string) $resp->getBody(), true);
        if (! is_array($data) || empty($data['choices'][0]['message']['content'])) {
            throw new \RuntimeException('AI response did not contain content.');
        }

        $content = (string) $data['choices'][0]['message']['content'];
        $usageIn = isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : null;
        $usageOut = isset($data['usage']['completion_tokens']) ? (int) $data['usage']['completion_tokens'] : null;

        return [
            'content' => trim($content),
            'ai_model' => $this->model,
            'tokens_input' => $usageIn,
            'tokens_output' => $usageOut,
            'temperature' => $this->temperature,
            'provider' => $this->provider,
            'prompt_version' => $promptVersion,
        ];
    }

    /**
     * Roughly truncate text to a token budget (approx 4 chars/token for English).
     */
    private function truncateToTokenBudget(string $text, int $budgetTokens): string
    {
        $approxChars = max(1, $budgetTokens * 4);
        if (mb_strlen($text, 'UTF-8') <= $approxChars) {
            return $text;
        }
        $slice = mb_substr($text, 0, $approxChars, 'UTF-8');
        // Try to cut at a boundary
        $lastBreak = max(mb_strrpos($slice, "\n"), mb_strrpos($slice, '.')); 
        if ($lastBreak !== false && $lastBreak > 1000) {
            return rtrim(mb_substr($slice, 0, $lastBreak + 1));
        }
        return rtrim($slice) . "…";
    }

    /**
     * Build chat messages for the chosen layout.
     * @return array{0: array<int, array{role:string, content:string}>, 1: string}
     */
    private function buildMessages(string $layout, string $sourceText): array
    {
        $layout = strtolower(trim($layout));
        $promptVersion = 'v1';

        $systemBase = "You are an expert direct-response copywriter. Rewrite content into a high-converting {LAYOUT} while preserving truthful claims and avoiding spammy language. Keep it clear, concise, and compliant. Output markdown or plain text only (no code blocks or triple backticks).";

        $system = match ($layout) {
            'interstitial' => str_replace('{LAYOUT}', 'interstitial page', $systemBase),
            'advertorial' => str_replace('{LAYOUT}', 'advertorial article', $systemBase),
            default => str_replace('{LAYOUT}', 'landing page', $systemBase),
        };

        // Interstitial: enforce strict numbered sections and labels to match UI formatter & user's desired output.
        if ($layout === 'interstitial') {
            $promptVersion = 'v2-interstitial-structured';

            $user = <<<MD
Source content (cleaned):

{$sourceText}

Rewrite into an interstitial using EXACTLY the following numbered sections and headings. Use markdown headings formatted exactly as shown (## **<number>. <Title>**). Under each heading, provide the requested sub-structure. Do NOT wrap the response in code fences. Replace every occurrence of the product name "Vita Feet Relieve" with {{productName}}.

## Required sections and structure (follow exactly):
## **1. Short Headline**
- One short headline with the main benefit (max 8 words).

## **2. The X-Factor of Our Product**
- A line starting with: **Sub-Title:** <short sub-title>
- A line starting with: **Body:** <single concise paragraph>

## **3. Main Product Benefits & Features (Benefit-Focused)**
- 4–6 bullet points using "* " prefix.

## **4. Authority Quote**
- **Testimonial Title:** <short sentence, no quotes>
- **Testimonial Body:** <1 short paragraph, no quotes>
- **<Full Name> | <Generic Title>** on its own line.

## **5. The X-Factor — Long Text Format**
- **Title:** Introducing {{productName}}
- **Sub-title:** <compelling sub-title>
- **Body:** <2–4 short paragraphs>

## **6. Four Biggest Benefits/Features**
- **Features Title:** <title>
- **Features Body:** <1 short paragraph>
- **Feature Blocks:**
  * <benefit 1>
  * <benefit 2>
  * <benefit 3>
  * <benefit 4>

## **7. Meet {{productName}}**
- **Body:** <2–3 short paragraphs reaffirming the X-factor>

## **8. Two Biggest Unique Selling Points**
- A bold sub-heading for USP 1 followed by 1–2 paragraphs.
- A bold sub-heading for USP 2 followed by 1–2 paragraphs.

## **9. One Benefit (4–6 Words)**
- A single short line.

## **10. Features (with Short Sentences)**
- **Features Body:** <short motivating line>
- 6 items using "* **<Feature Name>**" each followed on the next line by two spaces then a short descriptive sentence.

## **11. Simple “1-2-3” of How It Works**
- **Steps Body:** <short motivating line>
- 3 items using "* **Step X:** <text>"
- Optional single italic line reinforcing outcome.

## **12. Testimonials**
- 8 short testimonials using generic names. Each name bolded on its own line, followed by one sentence on the next line.

## **13. FAQ**
- 5–6 Q&A pairs. No shipping questions. Avoid anything that puts the product in a bad light.

Constraints:
- Keep language truthful and compliant. Avoid medical claims and unverifiable promises.
- Keep paragraphs short, scannable. No pricing.
MD;

            $messages = [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ];

            return [$messages, $promptVersion];
        }

        // Default generic layout prompt
        $user = <<<TXT
Source content (cleaned):

{$sourceText}

Rewrite into the selected format using:
- Strong headline and subhead.
- Clear benefits and credibility.
- Natural transitions, short paragraphs, scannable structure.
- A clear, non-pushy call to action.
- Avoid: price claims, medical claims, or unverifiable promises.
TXT;

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        return [$messages, $promptVersion];
    }
}
