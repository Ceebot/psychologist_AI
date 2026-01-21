<?php

namespace App\Service;

class AiService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'z-ai/glm-4.5-air:free';
    private const GREETING_MESSAGE = 'Здравствуй! Я психолог-консультант. 😊 Я здесь, чтобы выслушать тебя и помочь разобраться в любой ситуации. Расскажи, что тебя беспокоит?';
    
    private string $apiKey;
    private string $systemPrompt;
    private string $projectDir;

    public function __construct(string $openRouterApiKey, string $projectDir)
    {
        $this->apiKey = $openRouterApiKey;
        $this->projectDir = $projectDir;
        $this->systemPrompt = $this->loadPrompt();
    }

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
            CURLOPT_TIMEOUT => 120,
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

    public function getGreetingMessage(): string
    {
        return self::GREETING_MESSAGE;
    }

    public function generateChatTitle(string $firstMessage): ?string
    {
        $titlePrompt = $this->loadPrompt('chat_title.txt');
        
        $payload = [
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $titlePrompt],
                ['role' => 'user', 'content' => $firstMessage]
            ],
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
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $result = json_decode($response, true);
        return trim($this->getAssistantMessage($result) ?? '');
    }

    private function loadPrompt(string $filename = 'psychologist.txt'): string
    {
        $promptPath = $this->projectDir . '/config/prompts/' . $filename;
        if (!file_exists($promptPath)) {
            throw new \RuntimeException("Prompt file not found: $promptPath");
        }
        
        $content = file_get_contents($promptPath);
        
        // Убираем служебные теги и символы
        $content = preg_replace('/\/n:/i', '', $content);
        $content = preg_replace('/<[^>]*>/', '', $content);
        
        // Заменяем множественные переносы строк на пробелы
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
}
