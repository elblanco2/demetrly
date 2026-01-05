/**
 * AI Deployment Assistant - Chat Frontend
 * Powered by Claude via Anthropic API
 */

let conversationHistory = [];
let isProcessing = false;
let uploadPollingInterval = null;
let lastKnownUploads = [];

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Check if user mode is set, if not show mode selection
    if (!localStorage.getItem('userMode')) {
        showModeSelection();
    } else {
        loadConversationHistory();
        focusInput();
        startUploadPolling();
    }
});

function focusInput() {
    document.getElementById('userInput').focus();
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function quickAction(action) {
    const messages = {
        'github': 'I want to deploy a GitHub repository',
        'upload': 'Create a file upload interface for me',
        'wordpress': 'Help me install WordPress',
        'static': 'I want to deploy a static HTML site'
    };

    const input = document.getElementById('userInput');
    input.value = messages[action];
    sendMessage();
}

async function sendMessage() {
    const input = document.getElementById('userInput');
    const message = input.value.trim();

    if (!message || isProcessing) return;

    // Clear input
    input.value = '';

    // Hide welcome if first message
    const welcome = document.querySelector('.welcome-message');
    if (welcome) welcome.style.display = 'none';

    // Add user message
    addMessage('user', message);

    // Save to history
    conversationHistory.push({
        role: 'user',
        content: message
    });

    // Show typing indicator
    showTyping();

    // Disable input
    isProcessing = true;
    document.getElementById('sendButton').disabled = true;

    try {
        // Get user mode from localStorage
        const userMode = localStorage.getItem('userMode') || 'beginner';

        // Send to API
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                history: conversationHistory,
                userMode: userMode
            })
        });

        const data = await response.json();

        if (data.success) {
            // Add assistant response
            addMessage('assistant', data.response, data.actions);

            // Save to history
            conversationHistory.push({
                role: 'assistant',
                content: data.response
            });

            // Execute any actions
            if (data.actions && data.actions.length > 0) {
                executeActions(data.actions);
            }

            saveConversationHistory();
        } else {
            addMessage('assistant', '❌ Error: ' + (data.error || 'Failed to get response'));
        }
    } catch (error) {
        console.error('Error:', error);
        addMessage('assistant', '❌ Connection error. Please try again.');
    } finally {
        hideTyping();
        isProcessing = false;
        document.getElementById('sendButton').disabled = false;
        focusInput();
    }
}

function addMessage(role, content, actions = null) {
    const messagesDiv = document.getElementById('chatMessages');

    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${role}`;

    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.textContent = role === 'user' ? '👤' : '🤖';

    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';

    // Parse markdown-style code blocks
    const formattedContent = formatMessage(content);
    contentDiv.innerHTML = formattedContent;

    messageDiv.appendChild(avatar);
    messageDiv.appendChild(contentDiv);

    // Add action buttons if present
    if (actions && actions.length > 0) {
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'action-buttons';

        actions.forEach(action => {
            const btn = document.createElement('button');
            btn.className = 'action-btn';
            btn.textContent = action.label;
            btn.onclick = () => executeAction(action);
            actionsDiv.appendChild(btn);
        });

        contentDiv.appendChild(actionsDiv);
    }

    messagesDiv.appendChild(messageDiv);

    // Scroll to bottom
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function formatMessage(text) {
    // Convert markdown-style code blocks
    text = text.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');

    // Convert inline code
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Convert URLs to links BEFORE converting newlines (prevents <br> from being included in URLs)
    // Don't use target="_blank" to avoid popup blocker issues
    text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1">$1</a>');

    // Convert line breaks
    text = text.replace(/\n/g, '<br>');

    return text;
}

function showTyping() {
    const messagesDiv = document.getElementById('chatMessages');

    const typingDiv = document.createElement('div');
    typingDiv.className = 'message assistant';
    typingDiv.id = 'typingIndicator';

    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.textContent = '🤖';

    const indicator = document.createElement('div');
    indicator.className = 'typing-indicator active';
    indicator.innerHTML = '<span></span><span></span><span></span>';

    typingDiv.appendChild(avatar);
    typingDiv.appendChild(indicator);
    messagesDiv.appendChild(typingDiv);

    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function hideTyping() {
    const typingDiv = document.getElementById('typingIndicator');
    if (typingDiv) {
        typingDiv.remove();
    }
}

async function executeActions(actions) {
    for (const action of actions) {
        if (action.autoExecute) {
            await executeAction(action);
        }
    }
}

async function executeAction(action) {
    addMessage('assistant', `⚙️ Executing: ${action.label}...`);

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: action.type,
                params: action.params
            })
        });

        const data = await response.json();

        if (data.success) {
            // Start with base message
            let resultMessage = `✅ ${data.message || 'Action completed successfully!'}`;

            // Add action-specific details
            if (action.type === 'list_uploads' && data.files) {
                if (data.files.length === 0) {
                    resultMessage += '\n\nNo files in uploads folder.';
                } else {
                    resultMessage += `\n\nFound ${data.count} file(s):\n`;
                    data.files.forEach(f => {
                        resultMessage += `• ${f.name} (${f.size_human})\n`;
                    });
                }
            }

            if (action.type === 'extract_zip') {
                resultMessage += `\n\nExtracted to: ${data.extract_path}`;
                resultMessage += `\nFiles extracted: ${data.files_count}`;
            }

            if (action.type === 'detect_project_type' && data.detection) {
                const d = data.detection;
                resultMessage += `\n\nProject Analysis:`;
                resultMessage += `\n• Type: ${d.type}`;
                if (d.framework) resultMessage += ` (${d.framework})`;
                resultMessage += `\n• Files found: ${d.files_found.join(', ')}`;
                resultMessage += `\n• Deployment ready: ${d.deployment_ready ? 'Yes' : 'No'}`;
                if (d.build_command) resultMessage += `\n• Build command: ${d.build_command}`;
            }

            if (action.type === 'deploy_app') {
                resultMessage += `\n\n🎉 Your app is live at: ${data.url}`;
                resultMessage += `\nFiles deployed: ${data.files_deployed}`;
            }

            if (action.type === 'cleanup_uploads') {
                resultMessage += `\n\nCleaned up:`;
                if (data.removed) {
                    data.removed.forEach(item => {
                        resultMessage += `\n• ${item}`;
                    });
                }
            }

            if (action.type === 'check_node_available' && data.available !== undefined) {
                if (data.available) {
                    resultMessage += `\n\n✅ Node.js is available!`;
                    if (data.versions) {
                        resultMessage += `\n• Node: ${data.versions.node}`;
                        resultMessage += `\n• NPM: ${data.versions.npm}`;
                    }
                } else {
                    resultMessage += `\n\n❌ Node.js is not available on this server`;
                }
            }

            // Display in UI
            addMessage('assistant', resultMessage);

            // ✅ CRITICAL FIX: Save to conversation history
            conversationHistory.push({
                role: 'assistant',
                content: resultMessage
            });

            saveConversationHistory();

        } else {
            // Handle errors
            const errorMsg = `❌ ${data.error || 'Action failed'}`;
            addMessage('assistant', errorMsg);

            conversationHistory.push({
                role: 'assistant',
                content: errorMsg
            });

            saveConversationHistory();
        }
    } catch (error) {
        console.error('Action error:', error);
        const errorMsg = '❌ Failed to execute action';
        addMessage('assistant', errorMsg);

        conversationHistory.push({
            role: 'assistant',
            content: errorMsg
        });

        saveConversationHistory();
    }
}

function saveConversationHistory() {
    try {
        localStorage.setItem('ai_chat_history', JSON.stringify(conversationHistory));
    } catch (e) {
        console.warn('Failed to save conversation history:', e);
    }
}

function loadConversationHistory() {
    try {
        const saved = localStorage.getItem('ai_chat_history');
        if (saved) {
            conversationHistory = JSON.parse(saved);

            // Restore messages
            conversationHistory.forEach(msg => {
                if (msg.role !== 'system') {
                    addMessage(msg.role, msg.content);
                }
            });

            if (conversationHistory.length > 0) {
                const welcome = document.querySelector('.welcome-message');
                if (welcome) welcome.style.display = 'none';
            }
        }
    } catch (e) {
        console.warn('Failed to load conversation history:', e);
    }
}

// Add clear chat function
window.clearChat = function() {
    if (confirm('Clear conversation history?')) {
        conversationHistory = [];
        localStorage.removeItem('ai_chat_history');
        location.reload();
    }
};

/**
 * Mode Selection UI
 */
function showModeSelection() {
    const messagesDiv = document.getElementById('chatMessages');

    const selectionDiv = document.createElement('div');
    selectionDiv.className = 'mode-selection';
    selectionDiv.innerHTML = `
        <div class="mode-selection-container">
            <h2>🤖 Welcome to Your AI Deployment Assistant!</h2>
            <p>Before we begin, choose your experience level:</p>

            <div class="mode-options">
                <div class="mode-option" onclick="selectMode('beginner')">
                    <div class="mode-icon">🎓</div>
                    <div class="mode-title">Beginner Mode</div>
                    <div class="mode-description">
                        Detailed explanations, step-by-step guidance, learn as you go.
                        Perfect for those new to web hosting and deployment.
                    </div>
                </div>

                <div class="mode-option" onclick="selectMode('expert')">
                    <div class="mode-icon">⚡</div>
                    <div class="mode-title">Expert Mode</div>
                    <div class="mode-description">
                        Concise responses, assume technical knowledge, get things done fast.
                        For experienced developers who want efficiency.
                    </div>
                </div>
            </div>

            <p class="mode-note">You can change this anytime by saying "switch to beginner mode" or "switch to expert mode"</p>
        </div>
    `;

    messagesDiv.appendChild(selectionDiv);
}

window.selectMode = function(mode) {
    // Save preference
    localStorage.setItem('userMode', mode);

    // Remove mode selection UI
    const selectionDiv = document.querySelector('.mode-selection');
    if (selectionDiv) {
        selectionDiv.remove();
    }

    // Add confirmation message
    const modeEmoji = mode === 'beginner' ? '🎓' : '⚡';
    const modeName = mode.charAt(0).toUpperCase() + mode.slice(1);
    addMessage('assistant', `${modeEmoji} ${modeName} Mode activated! I'm ready to help you deploy applications. What would you like to work on?`);

    // Start normal chat
    focusInput();
    startUploadPolling();
};

/**
 * Upload Polling - Auto-detect new uploads
 */
function startUploadPolling() {
    // Poll every 10 seconds
    uploadPollingInterval = setInterval(checkForNewUploads, 10000);

    // Also check immediately
    checkForNewUploads();
}

async function checkForNewUploads() {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'list_uploads',
                params: {}
            })
        });

        const data = await response.json();

        if (data.success && data.files) {
            const currentFiles = data.files.map(f => f.name);

            // Check for new files
            const newFiles = currentFiles.filter(f => !lastKnownUploads.includes(f));

            if (newFiles.length > 0 && lastKnownUploads.length > 0) {
                // Don't notify on first check
                notifyNewUploads(newFiles);
            }

            // Update known uploads
            lastKnownUploads = currentFiles;
        }
    } catch (error) {
        // Silent fail - don't interrupt user experience
        console.warn('Upload polling error:', error);
    }
}

function notifyNewUploads(files) {
    if (files.length === 0) return;

    const fileList = files.map(f => `• ${f}`).join('\n');
    const message = files.length === 1
        ? `📤 I detected a new upload: ${files[0]}\n\nWould you like me to deploy it?`
        : `📤 I detected ${files.length} new uploads:\n${fileList}\n\nWould you like me to deploy them?`;

    addMessage('assistant', message);

    // Save to history
    conversationHistory.push({
        role: 'assistant',
        content: message
    });

    saveConversationHistory();
}
