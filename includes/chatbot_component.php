<!-- AI Chatbot Component -->
<div id="chatbot-container" class="fixed bottom-6 right-6 z-50">
    <!-- Chatbot Toggle Button -->
    <button id="chatbot-toggle" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition-all duration-300 transform hover:scale-105">
        <svg id="chatbot-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
        </svg>
        <svg id="close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
    
    <!-- Chatbot Window -->
    <div id="chatbot-window" class="hidden absolute bottom-16 right-0 w-96 h-96 bg-white rounded-lg shadow-2xl border border-gray-200 flex flex-col">
        <!-- Header -->
        <div class="bg-blue-600 text-white p-4 rounded-t-lg flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">HackMate Helper</h3>
                    <p class="text-xs text-blue-100">Your project guide</p>
                </div>
            </div>
            <div class="flex items-center space-x-1">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <span class="text-xs">Ready</span>
            </div>
        </div>
        
        <!-- Messages Area -->
        <div id="chatbot-messages" class="flex-1 p-4 overflow-y-auto space-y-3 bg-gray-50">
            <!-- Welcome Message -->
            <div class="flex items-start space-x-2">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="bg-white p-3 rounded-lg shadow-sm max-w-xs">
                    <p class="text-sm text-gray-800">Hi! I'm your HackMate Helper 🤖 I know everything about this project and can help you find the right links and procedures for your tasks. Just ask me in natural language!</p>
                </div>
            </div>
        </div>
        
        <!-- Input Area -->
        <div class="p-4 border-t border-gray-200 bg-white rounded-b-lg">
            <div class="flex space-x-2">
                <input 
                    type="text" 
                    id="chatbot-input" 
                    placeholder="Ask me anything..." 
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                    maxlength="500"
                >
                <button 
                    id="chatbot-send" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center"
                    disabled
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </div>
            <div id="chatbot-typing" class="hidden mt-2 text-xs text-gray-500 flex items-center space-x-1">
                <div class="flex space-x-1">
                    <div class="w-1 h-1 bg-gray-400 rounded-full animate-bounce"></div>
                    <div class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                    <div class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                </div>
                <span>Helper is thinking...</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom scrollbar for chatbot messages */
#chatbot-messages::-webkit-scrollbar {
    width: 4px;
}

#chatbot-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

#chatbot-messages::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}

#chatbot-messages::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Animation for chatbot window */
#chatbot-window {
    transform: translateY(10px);
    opacity: 0;
    transition: all 0.3s ease-in-out;
}

#chatbot-window.show {
    transform: translateY(0);
    opacity: 1;
}

/* Responsive design */
@media (max-width: 640px) {
    #chatbot-window {
        width: calc(100vw - 2rem);
        right: 1rem;
        left: 1rem;
        max-width: none;
    }
}
</style>

<script>
class HackMateHelper {
    constructor() {
        this.isOpen = false;
        this.isLoading = false;
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadChatHistory();
    }
    
    bindEvents() {
        const toggle = document.getElementById('chatbot-toggle');
        const sendBtn = document.getElementById('chatbot-send');
        const input = document.getElementById('chatbot-input');
        
        toggle.addEventListener('click', () => this.toggleChatbot());
        sendBtn.addEventListener('click', () => this.sendMessage());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        input.addEventListener('input', () => {
            const hasText = input.value.trim().length > 0;
            sendBtn.disabled = !hasText || this.isLoading;
            sendBtn.classList.toggle('opacity-50', !hasText || this.isLoading);
        });
        
        // Close chatbot when clicking outside
        document.addEventListener('click', (e) => {
            const container = document.getElementById('chatbot-container');
            if (this.isOpen && !container.contains(e.target)) {
                this.toggleChatbot();
            }
        });
    }
    
    toggleChatbot() {
        const window = document.getElementById('chatbot-window');
        const chatIcon = document.getElementById('chatbot-icon');
        const closeIcon = document.getElementById('close-icon');
        
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            window.classList.remove('hidden');
            setTimeout(() => window.classList.add('show'), 10);
            chatIcon.classList.add('hidden');
            closeIcon.classList.remove('hidden');
            document.getElementById('chatbot-input').focus();
        } else {
            window.classList.remove('show');
            setTimeout(() => window.classList.add('hidden'), 300);
            chatIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
        }
    }
    
    async sendMessage() {
        const input = document.getElementById('chatbot-input');
        const message = input.value.trim();
        
        if (!message || this.isLoading) return;
        
        this.isLoading = true;
        input.value = '';
        this.updateSendButton();
        
        // Add user message to chat
        this.addMessage(message, 'user');
        this.showTyping();
        
        try {
            const response = await fetch('../api/chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                this.addMessage(data.response, 'ai');
                this.saveChatHistory();
            } else {
                this.addMessage('Sorry, I encountered an error. Please try again.', 'ai', true);
            }
        } catch (error) {
            console.error('Chatbot error:', error);
            this.addMessage('Sorry, I\'m having trouble connecting. Please check your internet connection and try again.', 'ai', true);
        } finally {
            this.hideTyping();
            this.isLoading = false;
            this.updateSendButton();
        }
    }
    
    addMessage(text, sender, isError = false) {
        const messagesContainer = document.getElementById('chatbot-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-2';
        
        if (sender === 'user') {
            messageDiv.innerHTML = `
                <div class="flex-1"></div>
                <div class="bg-blue-600 text-white p-3 rounded-lg shadow-sm max-w-xs">
                    <p class="text-sm">${this.escapeHtml(text)}</p>
                </div>
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            `;
        } else {
            const bgColor = isError ? 'bg-red-50 border border-red-200' : 'bg-white';
            const textColor = isError ? 'text-red-800' : 'text-gray-800';
            const iconColor = isError ? 'text-red-600' : 'text-blue-600';
            const iconBg = isError ? 'bg-red-100' : 'bg-blue-100';
            
            messageDiv.innerHTML = `
                <div class="w-8 h-8 ${iconBg} rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 ${iconColor}" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="${bgColor} p-3 rounded-lg shadow-sm max-w-xs">
                    <div class="text-sm ${textColor}">${this.formatMessage(text)}</div>
                </div>
            `;
        }
        
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    formatMessage(text) {
        // Convert markdown-style links to HTML
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="text-blue-600 hover:text-blue-800 underline">$1</a>');
        
        // Convert **bold** to HTML
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Convert line breaks
        text = text.replace(/\n/g, '<br>');
        
        return text;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showTyping() {
        document.getElementById('chatbot-typing').classList.remove('hidden');
    }
    
    hideTyping() {
        document.getElementById('chatbot-typing').classList.add('hidden');
    }
    
    updateSendButton() {
        const sendBtn = document.getElementById('chatbot-send');
        const input = document.getElementById('chatbot-input');
        const hasText = input.value.trim().length > 0;
        
        sendBtn.disabled = !hasText || this.isLoading;
        sendBtn.classList.toggle('opacity-50', !hasText || this.isLoading);
    }
    
    saveChatHistory() {
        // Save chat history to localStorage for persistence
        const messages = Array.from(document.querySelectorAll('#chatbot-messages > div')).slice(1); // Skip welcome message
        const history = messages.map(msg => ({
            content: msg.textContent.trim(),
            sender: msg.querySelector('.bg-blue-600') ? 'user' : 'ai',
            timestamp: Date.now()
        }));
        
        localStorage.setItem('hackmate_chat_history', JSON.stringify(history.slice(-10))); // Keep last 10 messages
    }
    
    loadChatHistory() {
        try {
            const history = JSON.parse(localStorage.getItem('hackmate_chat_history') || '[]');
            // For now, we'll start fresh each session to avoid confusion
            // You can uncomment below to restore chat history
            /*
            history.forEach(msg => {
                this.addMessage(msg.content, msg.sender);
            });
            */
        } catch (error) {
            console.error('Failed to load chat history:', error);
        }
    }
}

// Initialize chatbot when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new HackMateHelper();
});
</script>