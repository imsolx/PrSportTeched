<?php
declare(strict_types=1);

final class GeminiClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(?string $model = null)
    {
        $this->apiKey = (string) getenv('GEMINI_API_KEY');
        $this->model = trim((string) ($model ?: getenv('GEMINI_MODEL') ?: getenv('REPORT_AI_MODEL') ?: 'gemini-3.5-flash'));
        $this->endpoint = rtrim((string) (getenv('GEMINI_API_ENDPOINT') ?: 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->model !== '' && $this->model !== 'local-summary';
    }

    public function model(): string
    {
        return $this->isConfigured() ? $this->model : 'local-summary';
    }

    public function diagnostics(): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: 'generativelanguage.googleapis.com';
        $started = microtime(true);
        $probe = @fsockopen('ssl://' . $host, 443, $errno, $error, 5);
        if (is_resource($probe)) {
            fclose($probe);
        }

        return [
            'configured' => $this->isConfigured(),
            'provider' => $this->isConfigured() ? 'google-gemini' : 'local-summary',
            'model' => $this->model(),
            'endpoint_host' => $host,
            'dns' => gethostbyname($host),
            'php_curl' => function_exists('curl_init'),
            'tcp_443' => is_resource($probe),
            'tcp_error' => is_resource($probe) ? '' : trim(($errno ? $errno . ' ' : '') . $error),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    public function generateText(string $systemInstruction, string $prompt, array $options = []): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Gemini API key is not configured');
        }

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.35),
                'maxOutputTokens' => (int) ($options['max_output_tokens'] ?? 1800),
            ],
        ];

        $url = $this->endpoint . '/models/' . rawurlencode($this->model) . ':generateContent';
        $response = $this->postJson($url, $payload);
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            $reason = $response['candidates'][0]['finishReason'] ?? 'empty response';
            throw new RuntimeException('Gemini returned no text: ' . (is_string($reason) ? $reason : 'unknown'));
        }
        return trim($text);
    }

    private function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Cannot encode Gemini request');
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => (int) (getenv('GEMINI_TIMEOUT') ?: 25),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $this->apiKey,
                ],
                CURLOPT_POSTFIELDS => $body,
            ];
            $proxy = self::proxy();
            if ($proxy !== '') {
                $options[CURLOPT_PROXY] = $proxy;
            }
            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => (int) (getenv('GEMINI_TIMEOUT') ?: 25),
                    'header' => "Content-Type: application/json\r\nx-goog-api-key: {$this->apiKey}\r\n",
                    'content' => $body,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? 'HTTP/1.1 0';
            preg_match('/\s(\d{3})\s/', $statusLine, $match);
            $status = (int) ($match[1] ?? 0);
            $error = $raw === false ? 'HTTP request failed' : '';
        }

        if ($raw === false || $raw === '') {
            throw new RuntimeException('Gemini request failed' . ($error ? ': ' . $error : ''));
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Gemini response is not valid JSON');
        }
        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $status);
            throw new RuntimeException('Gemini request failed: ' . (string) $message);
        }
        return $decoded;
    }

    private static function proxy(): string
    {
        return trim((string) (getenv('GEMINI_PROXY') ?: getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: ''));
    }
}
