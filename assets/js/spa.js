document.addEventListener('DOMContentLoaded', function() {
    console.log('[SSCMS SPA] Initializing SPA functionality');

    const contentContainer = document.getElementById('content-container');
    let currentPath = window.location.pathname;

    // Load content via AJAX
    window.loadContent = function(url, pushState = true) {
        if (!contentContainer) {
            console.error('[SSCMS SPA] Content container not found');
            return;
        }

        // Add loading state
        contentContainer.classList.add('loading');
        document.querySelectorAll('.ajax-link').forEach(link => link.classList.add('loading'));

        // Add cache-busting parameter
        const cacheBuster = new Date().getTime();
        const urlWithCacheBuster = url.includes('?') ? `${url}&_cb=${cacheBuster}` : `${url}?_cb=${cacheBuster}`;

        fetch(urlWithCacheBuster, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}, StatusText: ${response.statusText}`);
            }
            return response.text();
        })
        .then(data => {
            contentContainer.innerHTML = data;
            contentContainer.classList.remove('loading');
            document.querySelectorAll('.ajax-link').forEach(link => link.classList.remove('loading'));

            // Update active navigation
            updateActiveNav(url);

            // Push state to browser history
            if (pushState) {
                history.pushState({ url: url }, '', url);
                currentPath = url;
            }

            // Reinitialize Bootstrap components
            reinitializeBootstrap();

            // Reinitialize page-specific scripts
            try {
                reinitializePageScripts(url);
            } catch (error) {
                console.error('[SSCMS SPA] Error in reinitializePageScripts:', error.message);
            }

            console.log('[SSCMS SPA] Content loaded:', url);
        })
        .catch(error => {
            console.error('[SSCMS SPA] Error loading content:', error.message);
            contentContainer.innerHTML = `
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load content: ${error.message}. Please try again.
                </div>
            `;
            contentContainer.classList.remove('loading');
            document.querySelectorAll('.ajax-link').forEach(link => link.classList.remove('loading'));
        });
    };

    // Handle navigation clicks
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.ajax-link');
        if (link && link.dataset.ajax) {
            e.preventDefault();
            const url = link.dataset.ajax;
            if (url !== currentPath) {
                loadContent(url);
            }
        }
    });

    // Handle browser back/forward
    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.url) {
            loadContent(event.state.url, false);
        }
    });

    // Update active navigation state
    function updateActiveNav(url) {
        document.querySelectorAll('.nav-link, .dropdown-item').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.ajax === url) {
                link.classList.add('active');
                const parentCollapse = link.closest('.collapse');
                if (parentCollapse) {
                    const parentToggle = document.querySelector(`[data-bs-target="#${parentCollapse.id}"]`);
                    if (parentToggle && !parentCollapse.classList.contains('show')) {
                        parentToggle.click();
                    }
                }
            }
        });
    }

    // Reinitialize Bootstrap components
    function reinitializeBootstrap() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });

        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        popoverTriggerList.forEach(popoverTriggerEl => {
            new bootstrap.Popover(popoverTriggerEl);
        });

        const collapseList = document.querySelectorAll('.collapse');
        collapseList.forEach(collapseEl => {
            const bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
            if (bsCollapse) {
                bsCollapse._element.addEventListener('shown.bs.collapse', function () {});
            }
        });
    }

    // Reinitialize page-specific scripts
    function reinitializePageScripts(url) {
        if (url.includes('dashboard.php')) {
            if (typeof initializeDashboard === 'function') {
                initializeDashboard();
            } else {
                console.warn('[SSCMS SPA] initializeDashboard is not defined');
            }
        } else if (url.includes('ai-assistant.php')) {
            initializeChatFunctionality();
        } else if (url.includes('config_manager.php')) {
            initializeConfigManager();
        }
    }

    // Config Manager functionality
    function initializeConfigManager() {
        console.log('[SSCMS SPA] Initializing config manager functionality');
        const configForm = document.getElementById('configForm');
        if (configForm) {
            configForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('/SSCMS/config_manager.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const notification = document.createElement('div');
                    notification.className = `notification ${data.success ? 'success' : 'error'}`;
                    notification.textContent = data.message;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 2500);
                    if (data.success) {
                        setTimeout(() => loadContent('/SSCMS/dashboard.php'), 1000);
                    }
                })
                .catch(error => {
                    console.error('[SSCMS Config Manager] Error:', error.message);
                    const notification = document.createElement('div');
                    notification.className = 'notification error';
                    notification.textContent = `Failed to save configuration: ${error.message}`;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 2500);
                });
            });
        }
    }

    // Chat functionality for ai-assistant.php
    function initializeChatFunctionality() {
        console.log('[SSCMS SPA] Initializing chat functionality for ai-assistant.php');
        const chatWindow = document.querySelector('.chat-window');
        const userInput = document.querySelector('#userInput');
        const sendButton = document.querySelector('#sendButton');
        const typingIndicator = document.querySelector('#typingIndicator');
        const charCount = document.querySelector('#charCount');
        const clearChatBtn = document.querySelector('#clearChatBtn');
        const maxChars = 500;

        function scrollToBottom() {
            chatWindow.scrollTo({ top: chatWindow.scrollHeight, behavior: 'smooth' });
        }
        scrollToBottom();

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 2500);
        }

        if (userInput) {
            userInput.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = `${length}/${maxChars}`;
                charCount.style.color = length > maxChars ? 'var(--danger)' : 'var(--text-secondary)';
            });

            userInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    submitText();
                }
            });
        }

        function submitText() {
            const text = userInput?.value.trim();
            if (!text) {
                showNotification('Please enter a message.', 'error');
                return;
            }
            if (text.length > maxChars) {
                showNotification('Message exceeds 500 characters.', 'error');
                return;
            }

            const userMessage = document.createElement('div');
            userMessage.className = 'message user slide-up';
            userMessage.innerHTML = `<strong>You:</strong><br>${text.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>')}<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
            chatWindow.appendChild(userMessage);
            scrollToBottom();
            userInput.value = '';
            charCount.textContent = `0/${maxChars}`;

            typingIndicator.style.display = 'block';

            fetch('/SSCMS/ai-assistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ user_input: text })
            })
            .then(response => response.json())
            .then(response => {
                typingIndicator.style.display = 'none';
                if (response.success) {
                    const aiMessage = document.createElement('div');
                    aiMessage.className = 'message ai slide-up';
                    aiMessage.innerHTML = `<strong>Nurse Angge:</strong><br>${response.message}<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
                    chatWindow.appendChild(aiMessage);
                    showNotification('Message sent successfully', 'success');
                } else {
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'message ai slide-up';
                    errorMessage.innerHTML = `<strong>Nurse Angge:</strong><br>${response.error}<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
                    chatWindow.appendChild(errorMessage);
                    showNotification('Failed to send message', 'error');
                }
                scrollToBottom();
            })
            .catch(error => {
                console.error('[SSCMS AI Assistant] AJAX error:', error);
                typingIndicator.style.display = 'none';
                const errorMessage = document.createElement('div');
                errorMessage.className = 'message ai slide-up';
                errorMessage.innerHTML = `<strong>Nurse Angge:</strong><br>Oops! Something went wrong. Please try again.<div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
                chatWindow.appendChild(errorMessage);
                showNotification('Failed to send message', 'error');
                scrollToBottom();
            });
        }

        if (sendButton) {
            sendButton.addEventListener('click', function(e) {
                e.preventDefault();
                submitText();
            });
        }

        if (clearChatBtn) {
            clearChatBtn.addEventListener('click', function() {
                fetch('/SSCMS/ai-assistant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ clear_chat: 1 })
                })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        chatWindow.innerHTML = `
                            <div class="message welcome-message slide-up">
                                <strong>Nurse Angge:</strong><br>Hello, I am Nurse Angge, your AI Assistant specializing in medical analysis for the Clinic Management System. I’m here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?
                                <div class="timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                            </div>
                        `;
                        showNotification('Chat cleared successfully', 'success');
                        scrollToBottom();
                    } else {
                        showNotification('Failed to clear chat: ' + response.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('[SSCMS AI Assistant] Clear chat error:', error);
                    showNotification('Failed to clear chat. Please try again.', 'error');
                });
            });
        }

        if (userInput) {
            userInput.focus();
        }
    }

    console.log('[SSCMS SPA] SPA functionality initialized');
});