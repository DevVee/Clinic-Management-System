<?php
/**
 * SSCMS Local AI Endpoint
 * Calls llama-cli.exe with the GGUF model and returns a JSON response.
 *
 * POST params:
 *   prompt  (string) — The user's message / full prompt
 *   system  (string) — Optional system prompt override
 *
 * Response JSON:
 *   { "success": true,  "message": "...", "backend": "local" }
 *   { "success": false, "error": "...", "backend": "local" }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

// ─── Auth check ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'backend' => 'local']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed', 'backend' => 'local']);
    exit;
}

// ─── Input validation ────────────────────────────────────────────────────────
$prompt = trim($_POST['prompt'] ?? '');
$system = trim($_POST['system'] ?? AI_SYSTEM_PROMPT);

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'No prompt provided', 'backend' => 'local']);
    exit;
}

// Limit prompt length to prevent overload
if (strlen($prompt) > 1000) {
    $prompt = substr($prompt, 0, 1000);
}

// ─── Check llama.cpp availability ────────────────────────────────────────────
$llama_exe  = realpath(LLAMA_EXE);
$model_path = get_model_path();

if (!$llama_exe || !file_exists($llama_exe)) {
    echo json_encode([
        'success' => false,
        'error'   => 'llama-cli.exe not found. See /llama/README.md for setup instructions.',
        'backend' => 'local',
        'setup'   => true
    ]);
    exit;
}

if (!$model_path) {
    echo json_encode([
        'success' => false,
        'error'   => 'No GGUF model found in /ai-model/. See /ai-model/README.md for download instructions.',
        'backend' => 'local',
        'setup'   => true
    ]);
    exit;
}

// ─── Build the prompt in Llama chat format ────────────────────────────────────
// TinyLlama / Mistral use [INST] ... [/INST] format
// Phi-2 uses "Instruct: ... Output:" format — for broad compatibility use INST
$full_prompt = "<s>[INST] <<SYS>>\n{$system}\n<</SYS>>\n\n{$prompt} [/INST]";

// ─── Build and run the command ───────────────────────────────────────────────
$cmd = implode(' ', [
    escapeshellarg($llama_exe),
    '-m', escapeshellarg($model_path),
    '--threads', (int) LLAMA_THREADS,
    '-n',        (int) LLAMA_MAX_TOKENS,
    '--temp',    number_format(LLAMA_TEMP, 2, '.', ''),
    '--repeat-penalty', number_format(LLAMA_REPEAT_PENALTY, 2, '.', ''),
    '--ctx-size', (int) LLAMA_CTX_SIZE,
    '--log-disable',       // suppress verbose logging
    '-p', escapeshellarg($full_prompt),
    '2>NUL'                // redirect stderr on Windows
]);

$raw_output = @shell_exec($cmd);

if ($raw_output === null || $raw_output === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'llama-cli.exe produced no output. Check that it is a valid Windows executable and the model is compatible.',
        'backend' => 'local'
    ]);
    exit;
}

// ─── Extract AI response (strip echoed prompt) ───────────────────────────────
$response = $raw_output;

// llama-cli echoes the prompt; extract content after [/INST]
if (strpos($response, '[/INST]') !== false) {
    $parts    = explode('[/INST]', $response);
    $response = trim(end($parts));
}

// Remove any trailing <s>, </s>, or [end of text] markers
$response = preg_replace('/<\/?s>|\[end of text\]|<\|endoftext\|>/i', '', $response);
$response = trim($response);

if (empty($response)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Model returned an empty response. Try a different model or reduce --ctx-size.',
        'backend' => 'local'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $response,
    'backend' => 'local'
]);
