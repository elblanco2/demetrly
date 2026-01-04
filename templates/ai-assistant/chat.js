/**
 * AI Deployment Assistant - Chat Frontend
 * Powered by Claude via Anthropic API
 */

let conversationHistory = [];
let isProcessing = false;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadConversationHistory();
    focusInput();
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
        // Send to API
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                history: conversationHistory
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

    // Convert line breaks
    text = text.replace(/\n/g, '<br>');

    // Convert URLs to links
    text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');

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
            addMessage('assistant', `✅ ${data.message || 'Action completed successfully!'}`);
        } else {
            addMessage('assistant', `❌ ${data.error || 'Action failed'}`);
        }
    } catch (error) {
        console.error('Action error:', error);
        addMessage('assistant', '❌ Failed to execute action');
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
