<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Message;
use App\Service\AiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/chat')]
class ChatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiService $aiService,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private RateLimiterFactory $aiRequestsLimiter
    ) {}

    #[Route('', name: 'chat_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $user = $this->getUser();
        $chats = $this->em->getRepository(Chat::class)
            ->findBy(['user' => $user], ['updatedAt' => 'DESC']);
        
        return $this->render('chat/index.html.twig', [
            'chats' => $chats
        ]);
    }

    #[Route('/new', name: 'chat_new')]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $chats = $this->em->getRepository(Chat::class)
            ->findBy(['user' => $this->getUser()], ['updatedAt' => 'DESC']);
        
        return $this->render('chat/new.html.twig', [
            'greeting' => $this->aiService->getGreetingMessage(),
            'chats' => $chats
        ]);
    }
    
    #[Route('/create', name: 'chat_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if (!$this->validateCsrfToken($request)) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
        
        // Rate limiting
        $limiter = $this->aiRequestsLimiter->create($this->getUser()->getUserIdentifier());
        $limit = $limiter->consume(1);
        
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $minutes = (int) ceil($retryAfter->getTimestamp() - time()) / 60;
            
            return $this->json([
                'success' => false,
                'error' => "Превышен лимит запросов. Попробуйте через {$minutes} минут."
            ], 429);
        }
        
        $data = json_decode($request->getContent(), true);
        $content = trim($data['message'] ?? '');
        
        if (empty($content)) {
            return $this->json(['success' => false, 'error' => 'Сообщение не может быть пустым'], 400);
        }
        
        if (mb_strlen($content) > 2000) {
            return $this->json(['success' => false, 'error' => 'Сообщение не может быть длиннее 2000 символов'], 400);
        }
        
        try {
            // Получаем приветствие от ассистента
            $greeting = $this->aiService->getGreetingMessage();
            
            // Создаем историю для AI
            $history = [
                ['role' => 'assistant', 'content' => $greeting],
                ['role' => 'user', 'content' => $content]
            ];
            
            // Получаем ответ от AI
            $apiResponse = $this->aiService->sendMessage($history);
            $aiContent = $this->aiService->getAssistantMessage($apiResponse);
            
            // Генерируем название чата
            $chatTitle = $this->aiService->generateChatTitle($content);
            if (!$chatTitle || mb_strlen($chatTitle) > 50) {
                $chatTitle = mb_substr($content, 0, 50);
            }
            
            // Создаем чат
            $chat = new Chat();
            $chat->setUser($this->getUser())
                ->setTitle($chatTitle);
            $this->em->persist($chat);
            $this->em->flush();
            
            // Сохраняем приветствие ассистента
            $greetingMessage = new Message();
            $greetingMessage->setChat($chat)
                ->setRole('assistant')
                ->setContent($greeting);
            $this->em->persist($greetingMessage);
            
            // Сохраняем сообщение пользователя
            $userMessage = new Message();
            $userMessage->setChat($chat)
                ->setRole('user')
                ->setContent($content);
            $this->em->persist($userMessage);
            
            // Сохраняем ответ ассистента
            $assistantMessage = new Message();
            $assistantMessage->setChat($chat)
                ->setRole('assistant')
                ->setContent($aiContent);
            $this->em->persist($assistantMessage);
            
            $this->em->flush();
            
            return $this->json([
                'success' => true,
                'chatId' => $chat->getId()
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $this->getReadableErrorMessage($e->getMessage())
            ], 500);
        }
    }

    #[Route('/{id}', name: 'chat_show', requirements: ['id' => '\d+'])]
    public function show(Chat $chat): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        $chats = $this->em->getRepository(Chat::class)
            ->findBy(['user' => $this->getUser()], ['updatedAt' => 'DESC']);
        
        return $this->render('chat/show.html.twig', [
            'chat' => $chat,
            'chats' => $chats
        ]);
    }

    #[Route('/{id}/messages', name: 'chat_messages', methods: ['GET'])]
    public function messages(Chat $chat): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        $messages = $chat->getMessages()->map(fn($msg) => [
            'id' => $msg->getId(),
            'role' => $msg->getRole(),
            'content' => $msg->getContent(),
            'createdAt' => $msg->getCreatedAt()->format('Y-m-d H:i:s')
        ])->toArray();
        
        return $this->json([
            'success' => true,
            'messages' => array_values($messages)
        ]);
    }

    #[Route('/{id}/send', name: 'chat_send', methods: ['POST'])]
    public function send(Chat $chat, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if (!$this->validateCsrfToken($request)) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
        
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        // Rate limiting
        $limiter = $this->aiRequestsLimiter->create($this->getUser()->getUserIdentifier());
        $limit = $limiter->consume(1);
        
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $minutes = (int) ceil($retryAfter->getTimestamp() - time()) / 60;
            
            return $this->json([
                'success' => false,
                'error' => "Превышен лимит запросов. Попробуйте через {$minutes} минут."
            ], 429);
        }
        
        $remainingTokens = $limit->getRemainingTokens();
        $availableAt = $limit->getRetryAfter() ? $limit->getRetryAfter()->getTimestamp() : null;
        
        $data = json_decode($request->getContent(), true);
        $content = trim($data['message'] ?? '');
        
        if (empty($content)) {
            return $this->json([
                'success' => false,
                'error' => 'Сообщение не может быть пустым'
            ], 400);
        }
        
        if (mb_strlen($content) > 2000) {
            return $this->json([
                'success' => false,
                'error' => 'Сообщение не может быть длиннее 2000 символов'
            ], 400);
        }
        
        // Сохраняем сообщение пользователя
        $userMessage = new Message();
        $userMessage->setChat($chat)
            ->setRole('user')
            ->setContent($content);
        
        $this->em->persist($userMessage);
        
        try {
            // Получаем историю сообщений для контекста
            $history = [];
            foreach ($chat->getMessages() as $msg) {
                $history[] = [
                    'role' => $msg->getRole(),
                    'content' => $msg->getContent()
                ];
            }
            $history[] = ['role' => 'user', 'content' => $content];
            
            // Отправляем запрос к AI
            $apiResponse = $this->aiService->sendMessage($history);
            $aiContent = $this->aiService->getAssistantMessage($apiResponse);
            
            // Сохраняем ответ AI
            $assistantMessage = new Message();
            $assistantMessage->setChat($chat)
                ->setRole('assistant')
                ->setContent($aiContent);
            
            $this->em->persist($assistantMessage);
            
            // Обновляем время последнего обновления чата
            $chat->setUpdatedAt(new \DateTimeImmutable());
            
            // Генерируем название чата после первого сообщения пользователя
            $userMessagesCount = 0;
            foreach ($chat->getMessages() as $msg) {
                if ($msg->getRole() === 'user') {
                    $userMessagesCount++;
                }
            }
            
            $isFirstUserMessage = $userMessagesCount === 0;
            $newTitle = null;
            
            if ($isFirstUserMessage) {
                try {
                    $generatedTitle = $this->aiService->generateChatTitle($content);
                    if ($generatedTitle && mb_strlen($generatedTitle) <= 50) {
                        $chat->setTitle($generatedTitle);
                        $newTitle = $generatedTitle;
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибку генерации названия
                }
            }
            
            $this->em->flush();
            
            $response = [
                'success' => true,
                'userMessage' => [
                    'id' => $userMessage->getId(),
                    'role' => 'user',
                    'content' => $content,
                    'createdAt' => $userMessage->getCreatedAt()->format('Y-m-d H:i:s')
                ],
                'assistantMessage' => [
                    'id' => $assistantMessage->getId(),
                    'role' => 'assistant',
                    'content' => $aiContent,
                    'createdAt' => $assistantMessage->getCreatedAt()->format('Y-m-d H:i:s')
                ],
                'rateLimit' => [
                    'remaining' => $remainingTokens,
                    'total' => 20,
                    'resetAt' => $availableAt
                ]
            ];
            
            if ($newTitle) {
                $response['newTitle'] = $newTitle;
            }
            
            return $this->json($response);
            
        } catch (\Exception $e) {
            $errorMessage = $this->getReadableErrorMessage($e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => $errorMessage
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'chat_delete', methods: ['DELETE'])]
    public function delete(Chat $chat, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if (!$this->validateCsrfToken($request)) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }
        
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        $this->em->remove($chat);
        $this->em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Чат успешно удален'
        ]);
    }
    
    private function validateCsrfToken(Request $request): bool
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$token) {
            return false;
        }
        
        return $this->csrfTokenManager->isTokenValid(new CsrfToken('chat', $token));
    }
    
    private function getReadableErrorMessage(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'Rate limit exceeded')) {
            return 'Превышен дневной лимит запросов к AI. Попробуйте завтра.';
        }
        
        if (str_contains($errorMessage, 'HTTP 429')) {
            return 'Слишком много запросов к AI. Попробуйте позже.';
        }
        
        if (str_contains($errorMessage, 'HTTP 503') || str_contains($errorMessage, 'HTTP 502')) {
            return 'AI сервис временно недоступен. Попробуйте через несколько минут.';
        }
        
        if (str_contains($errorMessage, 'CURL Error') || str_contains($errorMessage, 'timeout')) {
            return 'Не удалось подключиться к AI. Проверьте интернет-соединение.';
        }
        
        return 'Произошла ошибка при обращении к AI. Попробуйте еще раз.';
    }
}
