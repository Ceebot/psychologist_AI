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

#[Route('/chat')]
class ChatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiService $aiService
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

    #[Route('/new', name: 'chat_new', methods: ['POST'])]
    public function new(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $chat = new Chat();
        $chat->setUser($this->getUser());
        
        $this->em->persist($chat);
        $this->em->flush();
        
        $chat->setTitle('Чат №' . $chat->getId());
        
        // Создаем первое приветственное сообщение от ассистента
        $greetingMessage = new Message();
        $greetingMessage->setChat($chat)
            ->setRole('assistant')
            ->setContent($this->aiService->getGreetingMessage());
        
        $this->em->persist($greetingMessage);
        $this->em->flush();
        
        return $this->json([
            'success' => true,
            'chatId' => $chat->getId()
        ]);
    }

    #[Route('/{id}', name: 'chat_show', requirements: ['id' => '\d+'])]
    public function show(Chat $chat): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        return $this->render('chat/show.html.twig', [
            'chat' => $chat
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
        
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
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
                ]
            ];
            
            if ($newTitle) {
                $response['newTitle'] = $newTitle;
            }
            
            return $this->json($response);
            
        } catch (\Exception $e) {
            // Откатываем сохранение сообщения пользователя в случае ошибки
            $this->em->rollback();
            
            return $this->json([
                'success' => false,
                'error' => 'Ошибка при обращении к AI: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'chat_delete', methods: ['DELETE'])]
    public function delete(Chat $chat): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
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
}
