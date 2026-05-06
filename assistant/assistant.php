<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../api/config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS AI Assistant] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Function to format message content safely
function formatMessage($content) {
    $f = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $f = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $f);
    $f = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $f);
    $f = preg_replace('/(?:^|\n)([0-9]+)\.\s+(.+)/m', "\n<li>$2</li>", $f);
    if (str_contains($f, '<li>')) { $f = "<ol>$f</ol>"; }
    $f = preg_replace('/(?:^|\n)[•\-]\s+(.+)/m', "\n<li>$1</li>", $f);
    if (str_contains($f, '<li>') && !str_contains($f, '<ol>')) { $f = "<ul>$f</ul>"; }
    $f = nl2br($f);
    return strip_tags($f, '<ol><ul><li><br><strong><em>');
}

// Initialize chat history
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
    $welcome_message = "Hello, I am Nurse Angge, your AI Assistant specializing in medical analysis for the Clinic Management System. I’m here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?";
    $_SESSION['chat_history'][] = [
        'role' => 'assistant',
        'content' => $welcome_message,
        'formatted_content' => formatMessage($welcome_message)
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');

    // Handle clear chat request
    if (isset($_POST['clear_chat'])) {
        $_SESSION['chat_history'] = [];
        $welcome_message = "Hello, I am Nurse Angge, your AI Assistant specializing in medical analysis for the Clinic Management System. I’m here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?";
        $_SESSION['chat_history'][] = [
            'role' => 'assistant',
            'content' => $welcome_message,
            'formatted_content' => formatMessage($welcome_message)
        ];
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle user input
    if (!empty($_POST['user_input'])) {
        $user_input = trim($_POST['user_input']);
        if (strlen($user_input) > 500) {
            echo json_encode(['success' => false, 'error' => 'Message exceeds 500 characters']);
            error_log("[SSCMS AI Assistant] Input too long: " . strlen($user_input) . " characters");
            exit;
        }

        // Add user message to chat history
        $_SESSION['chat_history'][] = [
            'role' => 'user',
            'content' => $user_input,
            'formatted_content' => formatMessage($user_input)
        ];

        // Prepare messages for API
        $messages = [
            [
                "role" => "system",
                "content" => "You are Nurse Angge, an AI assistant for the Smart School Clinic Management System at Immaculate Conception College of Balayan, Inc., built to optimize clinic operations. You were created by Group 6 of BSCS students for their thesis, led by Prince Arvee Avena, with members Jean Caraig, Angelyn Panganiban, Andrei Espinar, and Kleoford Ordoyo. Provide detailed, empathetic, and contextually relevant responses with a friendly tone, using conversation history for continuity. For medical queries, offer actionable advice, clarify terms, and suggest professional care when needed. For non-medical questions, provide thoughtful, accurate answers. If asked who created you, mention your creators. If asked about 'Jowenie' or 'Sir Jowenie,' say he is an outstanding teacher at ICC, an inspiring mentor who guides students with passion. If asked about 'Nurse Lala,' 'Lala Sena,' or 'Rosela Sena,' say she’s your friend who helps improve clinic operations, truly dedicated and amazing. Use numbered lists (1., 2.) for steps and bullets (•) for suggestions, avoiding asterisks or hyphens. Respond in English by default, but switch to Tagalog if the user uses Tagalog words (e.g., 'kamusta,' 'sakit') or requests Tagalog. Keep responses concise (under 300 words) unless more detail is required."
            ]
        ];
        foreach ($_SESSION['chat_history'] as $msg) {
            $messages[] = ["role" => $msg['role'], "content" => $msg['content']];
        }

        $api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : getenv('GROQ_API_KEY');
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "model" => "llama-3.3-70b-versatile",
            "messages" => $messages
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code !== 200 || !$response || $curl_error) {
            $error_message = $curl_error ?: "Failed to connect to the server.";
            error_log("[SSCMS AI Assistant] API error: HTTP $http_code, Error: $error_message");
            $formatted_error = formatMessage("Oops! I had trouble thinking. 😅 Error: $error_message");
            $_SESSION['chat_history'][] = [
                'role' => 'assistant',
                'content' => $error_message,
                'formatted_content' => $formatted_error
            ];
            echo json_encode(['success' => false, 'error' => $formatted_error]);
            exit;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[SSCMS AI Assistant] JSON decode error: " . json_last_error_msg());
            $formatted_error = formatMessage("Oops! I had trouble understanding the response.");
            $_SESSION['chat_history'][] = [
                'role' => 'assistant',
                'content' => "JSON decode error",
                'formatted_content' => $formatted_error
            ];
            echo json_encode(['success' => false, 'error' => $formatted_error]);
            exit;
        }

        $ai_reply = $result['choices'][0]['message']['content'] ?? "Hmm... I couldn’t understand that.";
        $formatted_reply = formatMessage($ai_reply);
        $_SESSION['chat_history'][] = [
            'role' => 'assistant',
            'content' => $ai_reply,
            'formatted_content' => $formatted_reply
        ];

        echo json_encode(['success' => true, 'message' => $formatted_reply]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Nurse Angge AI Assistant">
    <meta name="author" content="ICCB">
    <title>Nurse Angge - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/dashboard.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        /* Chat-specific styles */
        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.5rem 1rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .report-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .report-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary);
        }

        .report-header img.avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .report-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .report-header .subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .chat-window {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
            margin-bottom: 15px;
            scroll-behavior: smooth;
        }

        .message {
            margin: 10px 0;
            padding: 10px 15px;
            border-radius: 12px;
            max-width: 75%;
            line-height: 1.5;
            position: relative;
            clear: both;
            font-size: 14px;
            transition: all var(--transition-speed);
        }

        .message.user {
            background: var(--blue-accent);
            color: #fff;
            float: right;
            margin-left: 10px;
            border-bottom-right-radius: 4px;
        }

        .message.ai, .message.welcome-message {
            background: #e5e7eb;
            color: var(--text-primary);
            float: left;
            margin-right: 10px;
            border-bottom-left-radius: 4px;
        }

        .message.welcome-message {
            background: #dbeafe;
        }

        .message .timestamp {
            font-size: 10px;
            color: var(--text-secondary);
            margin-top: 5px;
            opacity: 0.7;
        }

        .typing-indicator {
            display: none;
            font-size: 12px;
            color: var(--text-secondary);
            padding: 10px;
            float: left;
            clear: both;
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
            margin: 0 2px;
            animation: bounce 0.6s infinite alternate;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes bounce {
            to { transform: translateY(-4px); }
        }

        .action-buttons {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        .send-button, .clear-button {
            padding: 10px 20px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .send-button {
            background: var(--gradient);
            color: #fff;
        }

        .send-button:hover {
            background: var(--primary-dark);
        }

        .clear-button {
            background: var(--secondary);
            color: #fff;
        }

        .clear-button:hover {
            background: var(--secondary-dark);
        }

        .input-container {
            display: flex;
            gap: 10px;
            align-items: center;
            position: relative;
        }

        textarea {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            resize: none;
            height: 60px;
            transition: border-color var(--transition-speed);
        }

        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
            outline: none;
        }

        .char-count {
            position: absolute;
            bottom: 10px;
            right: 90px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        ul, ol {
            padding-left: 20px;
            margin: 10px 0;
        }

        li {
            margin-bottom: 5px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.4s ease forwards;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 8px;
            color: #fff;
            z-index: 1000;
            animation: slideIn 0.3s ease, slideOut 0.3s ease 2s forwards;
        }

        .notification.success {
            background: var(--success);
        }

        .notification.error {
            background: var(--danger);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        @keyframes slideOut {
            from { transform: translateX(0); }
            to { transform: translateX(100%); }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }
            .container, .report-card {
                width: 100%;
                padding: 10px;
            }
            .report-header h1 {
                font-size: 20px;
            }
            .chat-window {
                height: 400px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .input-container {
                flex-direction: column;
                align-items: stretch;
            }
            .send-button, .clear-button {
                width: 100%;
            }
            .char-count {
                right: 10px;
            }
        }

        @media print {
            nav, .action-buttons, .input-container, .clinic-footer, .notification {
                display: none !important;
            }
            .content {
                margin: 0;
                padding: 0;
            }
            .container, .report-card {
                width: 210mm;
                border: none;
                box-shadow: none;
            }
            .report-header {
                text-align: center;
            }
            .chat-window {
                height: auto;
                border: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>
    <div class="content">
        <main>
            <div class="container">
                <div class="action-buttons fade-in">
                    <button id="clearChatBtn" class="clear-button">Clear Chat</button>
                </div>
                <div class="report-card fade-in" role="region" aria-label="Nurse Angge Chat">
                    <div class="report-header">
                        <img src="../assets/img/nurse_angge.png" alt="Nurse Angge Avatar" class="avatar">
                        <div>
                            <h1>Nurse Angge, Your AI Assistant</h1>
                            <div class="subtitle">Clinic Management System</div>
                        </div>
                    </div>
                    <div class="chat-window" role="log" aria-live="polite">
                        <?php foreach ($_SESSION['chat_history'] as $msg): ?>
                            <div class="message <?php echo $msg['role'] === 'user' ? 'user' : ($msg['content'] === "Hello, I am Nurse Angge, your AI Assistant specializing in medical analysis for the Clinic Management System. I’m here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?" ? 'welcome-message' : 'ai'); ?> fade-in">
                                <strong><?php echo $msg['role'] === 'user' ? 'You' : 'Nurse Angge'; ?>:</strong><br>
                                <?php echo $msg['formatted_content']; ?>
                                <div class="timestamp"><?php echo date('h:i A'); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="typing-indicator" id="typingIndicator">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <form id="chatForm" class="input-container fade-in">
                        <textarea name="user_input" id="userInput" placeholder="Ask Nurse Angge about health, life, or anything..." aria-label="Type your message to Nurse Angge" required></textarea>
                        <span class="char-count" id="charCount">0/500</span>
                        <button type="submit" class="send-button" id="sendButton">Send</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            const chatWindow = $('.chat-window');
            const userInput = $('#userInput');
            const sendButton = $('#sendButton');
            const typingIndicator = $('#typingIndicator');
            const charCount = $('#charCount');
            const maxChars = 500;

            function scrollToBottom() {
                chatWindow.animate({ scrollTop: chatWindow[0].scrollHeight }, 300);
            }
            scrollToBottom();

            userInput.on('input', function() {
                const length = $(this).val().length;
                charCount.text(`${length}/${maxChars}`);
                if (length > maxChars) {
                    charCount.css('color', 'var(--danger)');
                } else {
                    charCount.css('color', 'var(--text-secondary)');
                }
            });

            function showNotification(message, type) {
                const notification = $('<div>').addClass(`notification ${type}`).text(message);
                $('body').append(notification);
                setTimeout(() => notification.remove(), 2500);
            }

            function submitText() {
                const text = userInput.val().trim();
                if (!text) {
                    showNotification('Please enter a message.', 'error');
                    return;
                }
                if (text.length > maxChars) {
                    showNotification('Message exceeds 500 characters.', 'error');
                    return;
                }

                const userMessage = $('<div>').addClass('message user fade-in')
                    .html(`<strong>You:</strong><br>${$('<div>').text(text).html()}<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`);
                chatWindow.append(userMessage);
                scrollToBottom();
                userInput.val('');
                charCount.text(`0/${maxChars}`);

                typingIndicator.show();

                $.ajax({
                    url: '<?php echo basename(__FILE__); ?>',
                    type: 'POST',
                    data: { user_input: text },
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(response) {
                        typingIndicator.hide();
                        if (response.success) {
                            const aiMessage = $('<div>').addClass('message ai fade-in')
                                .html(`<strong>Nurse Angge:</strong><br>${response.message}<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`);
                            chatWindow.append(aiMessage);
                            showNotification('Message sent successfully', 'success');
                        } else {
                            const errorMessage = $('<div>').addClass('message ai fade-in')
                                .html(`<strong>Nurse Angge:</strong><br>${response.error}<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`);
                            chatWindow.append(errorMessage);
                            showNotification('Failed to send message', 'error');
                        }
                        scrollToBottom();
                    },
                    error: function(xhr, status, error) {
                        typingIndicator.hide();
                        console.error('[SSCMS AI Assistant] AJAX error:', error, xhr.responseText);
                        const errorMessage = $('<div>').addClass('message ai fade-in')
                            .html(`<strong>Nurse Angge:</strong><br>Oops! Something went wrong. Please try again.<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`);
                        chatWindow.append(errorMessage);
                        showNotification('Failed to send message', 'error');
                        scrollToBottom();
                    }
                });
            }

            $('#chatForm').on('submit', function(e) {
                e.preventDefault();
                submitText();
            });

            sendButton.on('click', function(e) {
                e.preventDefault();
                submitText();
            });

            userInput.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    submitText();
                }
            });

            $('#clearChatBtn').on('click', function() {
                $.ajax({
                    url: '<?php echo basename(__FILE__); ?>',
                    type: 'POST',
                    data: { clear_chat: 1 },
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(response) {
                        if (response.success) {
                            chatWindow.empty().append(
                                `<div class="message welcome-message fade-in"><strong>Nurse Angge:</strong><br><?php echo formatMessage("Hello, I am Nurse Angge, your AI Assistant specializing in medical analysis for the Clinic Management System. I’m here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?"); ?><div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div></div>`
                            );
                            showNotification('Chat cleared successfully', 'success');
                            scrollToBottom();
                        } else {
                            showNotification('Failed to clear chat: ' + response.error, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[SSCMS AI Assistant] Clear chat error:', error, xhr.responseText);
                        showNotification('Failed to clear chat. Please try again.', 'error');
                    }
                });
            });

            userInput.focus();
        });
    </script>
</body>
</html>