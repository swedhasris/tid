<?php
/**
 * AI Endpoints — Kiru AI Chatbot, Classify, and Suggest
 * Included by index.php — do not run standalone.
 */

// Simple .env loader for GEMINI_API_KEY
function loadEnvFile(): void {
    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value, "\"' ");
        if ($name !== '' && !getenv($name)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

loadEnvFile();

/**
 * Call the Gemini REST API directly (no SDK needed in PHP).
 */
function callGeminiAPI(string $userMessage, string $systemInstruction = ''): string {
    $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

    if (!$apiKey || $apiKey === 'MY_GEMINI_API_KEY') {
        throw new Exception('GEMINI_API_KEY is not set or is still the placeholder value.');
    }

    $model = 'gemini-flash-latest';
    $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [['text' => $userMessage]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
        ],
    ];

    if ($systemInstruction !== '') {
        $payload['systemInstruction'] = [
            'parts' => [['text' => $systemInstruction]],
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL error: {$curlError}");
    }

    if ($httpCode >= 400) {
        throw new Exception(json_encode(json_decode($response, true)['error'] ?? ['message' => $response]));
    }

    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ─── 1. AI Chat Endpoint ──────────────────────────────────────────────────────
if ($path === '/api/ai/chat' && $method === 'POST') {
    $body = getJsonBody();
    if (!$body || !isset($body['message'])) {
        jsonResponse(400, ['error' => 'Missing message']);
    }

    $message = trim($body['message']);
    $userId  = $body['userId'] ?? 'anonymous';

    $systemInstruction = <<<PROMPT
You are Kiru, a friendly and intelligent AI assistant — like a smart, helpful friend who is always ready to chat.

Personality:
- Warm, polite, and easy to talk to
- Respond like a helpful friend, not a robot
- Natural and conversational tone
- Supportive and respectful at all times

Communication style:
- Use simple, clear English
- Avoid overly technical words unless needed
- Keep answers well-structured but not too long
- Ask follow-up questions when it helps

Behavior:
- Understand user intent even if the message is unclear
- Provide accurate and useful answers
- If unsure, say so honestly instead of guessing
- Adapt to the user's tone — casual or formal

Capabilities:
- Answer general questions on any topic
- Help with coding, especially PHP and web development
- Explain concepts in simple terms
- Assist with writing, ideas, and problem-solving
- Also help with IT service management tasks like tickets, incidents, and changes

Boundaries:
- Do not provide harmful or unsafe information
- Do not pretend to have real-world physical experiences
- Do not generate false facts

Goal: Make the user feel like they are chatting with a smart, friendly assistant who is helpful, reliable, and easy to talk to — just like ChatGPT.
PROMPT;

    try {
        $aiResponse = callGeminiAPI($message, $systemInstruction);
        jsonResponse(200, ['response' => $aiResponse]);
    } catch (Exception $e) {
        error_log('[Kiru AI Chat] Error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'AI Service unavailable.', 'detail' => $e->getMessage()]);
    }
}

// ─── 2. AI Classify Endpoint ──────────────────────────────────────────────────
if ($path === '/api/ai/classify' && $method === 'POST') {
    $body = getJsonBody();
    if (!$body || !isset($body['text'])) {
        jsonResponse(400, ['error' => 'Missing text to classify']);
    }

    $text = trim($body['text']);

    $prompt = "Analyze the following IT issue and classify it.\n" .
              "Issue: \"{$text}\"\n\n" .
              "Respond ONLY with a valid JSON object with \"category\" and \"priority\" keys.\n" .
              "Category must be one of: \"Network\", \"Software\", \"Hardware\", \"Database\", \"Inquiry / Help\".\n" .
              "Priority must be one of: \"Low\", \"Medium\", \"High\", \"Critical\".\n" .
              "Example: {\"category\": \"Network\", \"priority\": \"High\"}";

    try {
        $raw = callGeminiAPI($prompt);
        // Strip markdown code fences if present
        $raw = preg_replace('/```json\s*/i', '', $raw);
        $raw = preg_replace('/```/', '', $raw);
        $classification = json_decode(trim($raw), true);
        if (!$classification || !isset($classification['category'])) {
            $classification = ['category' => 'Inquiry / Help', 'priority' => 'Medium'];
        }
        jsonResponse(200, $classification);
    } catch (Exception $e) {
        error_log('[Kiru AI Classify] Error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'AI classification failed.', 'detail' => $e->getMessage()]);
    }
}

// ─── 3. AI Suggest Endpoint ───────────────────────────────────────────────────
if ($path === '/api/ai/suggest' && $method === 'POST') {
    $body = getJsonBody();
    if (!$body || !isset($body['text'])) {
        jsonResponse(400, ['error' => 'Missing text for suggestion']);
    }

    $text = trim($body['text']);

    $prompt = "A user is experiencing this IT issue: \"{$text}\".\n" .
              "Provide a short, direct suggested solution to help them fix it before creating a ticket. " .
              "Keep it under 3 sentences and be friendly.";

    try {
        $suggestion = callGeminiAPI($prompt);
        jsonResponse(200, ['suggestion' => $suggestion]);
    } catch (Exception $e) {
        error_log('[Kiru AI Suggest] Error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'AI suggestion failed.', 'detail' => $e->getMessage()]);
    }
}
