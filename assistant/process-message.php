<?php
/**
 * Nurse Angge — AI message processor
 *
 * Routes to local llama.cpp OR Groq cloud API based on AI_MODE in /api/config.php.
 * Mode 'auto': tries local first, falls back to cloud automatically.
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../api/config.php';

header('Content-Type: application/json');

// ─── Auth ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// ─── Chat history in session ──────────────────────────────────────────────────
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// ─── Format helper ────────────────────────────────────────────────────────────
// Converts plain AI text (markdown-lite) to safe HTML.
function formatMessage(string $content): string {
    // 1. Escape HTML entities first (prevent XSS)
    $f = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    // 2. Bold: **text** → <strong>text</strong>
    $f = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $f);

    // 3. Italic: *text* (single, not preceded/followed by another *)
    $f = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $f);

    // 4. Numbered lists: lines starting with "1. " etc.
    $f = preg_replace('/(?:^|\n)([0-9]+)\.\s+(.+)/m', "\n<li>$2</li>", $f);
    if (str_contains($f, '<li>')) {
        $f = '<ol>' . $f . '</ol>';
    }

    // 5. Bullet lists: lines starting with "• " or "- "
    $f = preg_replace('/(?:^|\n)[•\-]\s+(.+)/m', "\n<li>$1</li>", $f);
    if (str_contains($f, '<li>') && !str_contains($f, '<ol>')) {
        $f = '<ul>' . $f . '</ul>';
    }

    // 6. Remaining newlines → <br>
    $f = nl2br($f);

    // 7. Strip anything that isn't our safe allowed tags
    return strip_tags($f, '<ol><ul><li><br><strong><em><p>');
}

// ─── Clear chat ───────────────────────────────────────────────────────────────
if (isset($_POST['clear_chat'])) {
    $_SESSION['chat_history'] = [];
    echo json_encode(['success' => true]);
    exit;
}

// ─── Process message ──────────────────────────────────────────────────────────
if (empty($_POST['user_input'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$user_input = trim(substr($_POST['user_input'], 0, 1000));

$_SESSION['chat_history'][] = [
    'role'    => 'user',
    'content' => $user_input,
];

// Keep chat history manageable (last 10 exchanges = 20 entries)
if (count($_SESSION['chat_history']) > 20) {
    $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -20);
}

// ─── Decide backend ───────────────────────────────────────────────────────────
$use_local = false;
if (AI_MODE === 'local') {
    $use_local = true;
} elseif (AI_MODE === 'auto') {
    $use_local = local_ai_available();
}
// AI_MODE === 'cloud' → $use_local remains false

// ─── LOCAL AI (llama.cpp) ─────────────────────────────────────────────────────
if ($use_local) {
    $llama_exe  = realpath(LLAMA_EXE);
    $model_path = get_model_path();

    // Build full conversation context as a single prompt
    $history_text = '';
    foreach ($_SESSION['chat_history'] as $msg) {
        if ($msg['role'] === 'user') {
            $history_text .= "User: {$msg['content']}\n";
        } elseif ($msg['role'] === 'assistant') {
            $history_text .= "Assistant: {$msg['content']}\n";
        }
    }

    $full_prompt = "<s>[INST] <<SYS>>\n" . AI_SYSTEM_PROMPT . "\n<</SYS>>\n\n{$history_text}Assistant: [/INST]";

    $cmd = implode(' ', [
        escapeshellarg($llama_exe),
        '-m', escapeshellarg($model_path),
        '--threads', (int) LLAMA_THREADS,
        '-n',        (int) LLAMA_MAX_TOKENS,
        '--temp',    number_format(LLAMA_TEMP, 2, '.', ''),
        '--repeat-penalty', number_format(LLAMA_REPEAT_PENALTY, 2, '.', ''),
        '--ctx-size', (int) LLAMA_CTX_SIZE,
        '--log-disable',
        '-p', escapeshellarg($full_prompt),
        '2>NUL'
    ]);

    $raw = @shell_exec($cmd);

    if ($raw !== null && $raw !== '') {
        // Extract response after [/INST]
        $ai_reply = $raw;
        if (strpos($ai_reply, '[/INST]') !== false) {
            $parts    = explode('[/INST]', $ai_reply);
            $ai_reply = trim(end($parts));
        }
        $ai_reply = preg_replace('/<\/?s>|\[end of text\]|<\|endoftext\|>/i', '', $ai_reply);
        $ai_reply = trim($ai_reply);

        if (!empty($ai_reply)) {
            $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $ai_reply];
            echo json_encode([
                'success' => true,
                'message' => formatMessage($ai_reply),
                'backend' => 'local'
            ]);
            exit;
        }
    }

    // If local failed and mode is 'auto', fall through to cloud
    if (AI_MODE === 'local') {
        echo json_encode([
            'success' => false,
            'error'   => 'Local AI failed. Check /llama/README.md and /ai-model/README.md for setup.',
            'backend' => 'local'
        ]);
        exit;
    }
    // Fall through to cloud ↓
}

// ─── CLOUD AI (Groq API) ──────────────────────────────────────────────────────
$messages = [['role' => 'system', 'content' => AI_SYSTEM_PROMPT]];
foreach ($_SESSION['chat_history'] as $msg) {
    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
}

$payload = json_encode([
    'model'    => GROQ_MODEL,
    'messages' => $messages,
]);

$ch = curl_init(GROQ_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_POSTFIELDS    => $payload,
    CURLOPT_TIMEOUT       => 30,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    $err = $curl_err ?: "Groq API returned HTTP {$http_code}";
    $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => "Error: {$err}"];
    echo json_encode(['success' => false, 'error' => formatMessage("Oops! $err"), 'backend' => 'cloud']);
    exit;
}

$result   = json_decode($response, true);
$ai_reply = $result['choices'][0]['message']['content'] ?? "I couldn't understand that. Could you rephrase?";

$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $ai_reply];

echo json_encode([
    'success' => true,
    'message' => formatMessage($ai_reply),
    'backend' => 'cloud'
]);
