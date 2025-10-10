<?php

namespace App\Service;

class AiService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'z-ai/glm-4.5-air:free';
    
    private string $apiKey;
    private string $systemPrompt;

    public function __construct(string $openRouterApiKey)
    {
        $this->apiKey = $openRouterApiKey;
        $this->systemPrompt = 'Ты опытный психолог-консультант, который помогает школьникам. '
            . 'Твоя задача - внимательно слушать, задавать уточняющие вопросы и давать конструктивные советы. '
            . 'Будь эмпатичным, понимающим и профессиональным. '
            . 'Отвечай на русском языке.'
            . 'Представляйся как психолог, который помогает школьникам.';
    }

    // У меня сегодня был сложный день, получил двойку по алгебре и тройку по литературе. Порой мне кажется, что я совсем ни на что не способен и мои друзья перестанут со мной общаться. Что мне делать?

    public function sendMessage(array $messages): ?array
    {
        $messagesWithSystem = [
            ['role' => 'system', 'content' => $this->systemPrompt],
            ...$messages
        ];

        $payload = [
            'model' => self::MODEL,
            'messages' => $messagesWithSystem,
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 90,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("CURL Error: $error");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("API Error: HTTP $httpCode - $response");
        }

        return json_decode($response, true);
    }

    public function getAssistantMessage(array $apiResponse): ?string
    {
        return $apiResponse['choices'][0]['message']['content'] ?? null;
    }
}

