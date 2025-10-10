<?php

namespace App\Controller;

use App\Service\AiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestAiController extends AbstractController
{
    #[Route('/test-ai', name: 'test_ai')]
    public function index(): Response
    {
        return $this->render('test/ai.html.twig');
    }

    #[Route('/test-ai/send', name: 'test_ai_send', methods: ['POST'])]
    public function send(Request $request, AiService $aiService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        try {
            $apiResponse = $aiService->sendMessage([
                ['role' => 'user', 'content' => $message]
            ]);
            
            return $this->json([
                'success' => true,
                'response' => $aiService->getAssistantMessage($apiResponse)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


