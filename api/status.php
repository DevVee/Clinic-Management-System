<?php
/**
 * AI Status endpoint — returns which AI backend is active.
 * Used by the chat UI to show a badge (Local AI / Cloud AI).
 */

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$local_ready = local_ai_available();
$model_file  = get_model_path();
$model_name  = $model_file ? basename($model_file) : null;

$active_backend = match(AI_MODE) {
    'local' => 'local',
    'cloud' => 'cloud',
    default => $local_ready ? 'local' : 'cloud',
};

echo json_encode([
    'mode'            => AI_MODE,
    'active_backend'  => $active_backend,
    'local_available' => $local_ready,
    'model'           => $model_name,
    'llama_exe'       => file_exists(LLAMA_EXE) ? 'found' : 'missing',
]);
