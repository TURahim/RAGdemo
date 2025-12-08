import {Component} from './component';

interface Citation {
    entity_id: number;
    entity_type: string;
    title: string;
    department: string;
    relevance_score: number;
    url?: string;
}

interface ChatResponse {
    success: boolean;
    session_id: string;
    message_id: number;
    answer: string;
    citations: Citation[];
    confidence: number;
    error?: string;
}

/**
 * AI Chat Panel Component
 * Provides a chat interface for the AI SOP Assistant.
 */
export class AIChatPanel extends Component {
    private panelEl!: HTMLElement;
    private messagesEl!: HTMLElement;
    private inputEl!: HTMLTextAreaElement;
    private sendBtn!: HTMLButtonElement;
    private toggleBtn!: HTMLButtonElement | null;
    private closeBtn!: HTMLButtonElement | null;
    private sessionId: string;
    private isLoading = false;
    private isFloating = false;

    setup(): void {
        this.panelEl = this.$refs.panel as HTMLElement;
        this.messagesEl = this.$refs.messages as HTMLElement;
        this.inputEl = this.$refs.input as HTMLTextAreaElement;
        this.sendBtn = this.$refs.sendBtn as HTMLButtonElement;
        this.toggleBtn = this.$refs.toggleBtn as HTMLButtonElement | null;
        this.closeBtn = this.$refs.closeBtn as HTMLButtonElement | null;
        this.isFloating = this.$opts.floating === 'true';
        this.sessionId = this.generateSessionId();

        this.setupEventListeners();
    }

    private setupEventListeners(): void {
        // Toggle button (floating mode)
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', () => this.togglePanel());
        }

        // Close button
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => {
                if (this.isFloating) {
                    this.hidePanel();
                } else {
                    this.newChat();
                }
            });
        }

        // Send button click
        this.sendBtn.addEventListener('click', () => this.send());

        // Enter to send (Shift+Enter for new line)
        this.inputEl.addEventListener('keydown', (e: KeyboardEvent) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        });

        // Auto-resize textarea
        this.inputEl.addEventListener('input', () => this.resizeTextarea());
    }

    private togglePanel(): void {
        this.panelEl.classList.toggle('ai-chat-hidden');
        if (!this.panelEl.classList.contains('ai-chat-hidden')) {
            this.inputEl.focus();
        }
    }

    private hidePanel(): void {
        this.panelEl.classList.add('ai-chat-hidden');
    }

    private showPanel(): void {
        this.panelEl.classList.remove('ai-chat-hidden');
        this.inputEl.focus();
    }

    private generateSessionId(): string {
        return crypto.randomUUID ? crypto.randomUUID() : 
            'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
    }

    private resizeTextarea(): void {
        this.inputEl.style.height = 'auto';
        this.inputEl.style.height = Math.min(this.inputEl.scrollHeight, 150) + 'px';
    }

    async send(): Promise<void> {
        const query = this.inputEl.value.trim();
        if (!query || this.isLoading) return;

        // Add user message to display
        this.addMessage('user', query);
        this.inputEl.value = '';
        this.resizeTextarea();
        this.setLoading(true);

        try {
            const response = await window.$http.post('/ai/chat', {
                query,
                session_id: this.sessionId,
            }) as { data: ChatResponse };

            const data = response.data;

            if (data.success) {
                this.addMessage(
                    'assistant',
                    data.answer,
                    data.citations,
                    data.message_id,
                    data.confidence
                );
            } else {
                this.addMessage(
                    'assistant',
                    data.error || 'Sorry, something went wrong. Please try again.'
                );
            }
        } catch (err) {
            this.addMessage(
                'assistant',
                'Sorry, I couldn\'t process your request. Please try again later.'
            );
        } finally {
            this.setLoading(false);
        }
    }

    private addMessage(
        role: string,
        content: string,
        citations?: Citation[],
        messageId?: number,
        confidence?: number
    ): void {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-chat-message ai-chat-message-${role}`;

        // Avatar
        const avatar = document.createElement('div');
        avatar.className = 'ai-chat-avatar';
        avatar.innerHTML = role === 'user' ? '👤' : '🤖';
        msgDiv.appendChild(avatar);

        // Content wrapper
        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'ai-chat-content';

        // Message text
        const text = document.createElement('div');
        text.className = 'ai-chat-text';
        text.innerHTML = this.formatMessage(content);
        contentWrapper.appendChild(text);

        // Citations
        if (citations && citations.length > 0) {
            const citDiv = document.createElement('div');
            citDiv.className = 'ai-chat-citations';
            citDiv.innerHTML = '<strong>📚 Sources:</strong> ' +
                citations.map(c =>
                    c.url
                        ? `<a href="${c.url}" target="_blank" class="ai-citation-link">${this.escapeHtml(c.title)}</a>`
                        : `<span class="ai-citation-text">${this.escapeHtml(c.title)}</span>`
                ).join(', ');
            contentWrapper.appendChild(citDiv);
        }

        // Confidence indicator (for assistant messages)
        if (role === 'assistant' && typeof confidence === 'number') {
            const confDiv = document.createElement('div');
            confDiv.className = 'ai-chat-confidence';
            const confPercent = Math.round(confidence * 100);
            confDiv.innerHTML = `<span class="ai-confidence-label">Confidence:</span> 
                <span class="ai-confidence-value ${this.getConfidenceClass(confidence)}">${confPercent}%</span>`;
            contentWrapper.appendChild(confDiv);
        }

        // Feedback buttons (for assistant messages)
        if (role === 'assistant' && messageId) {
            const feedbackDiv = document.createElement('div');
            feedbackDiv.className = 'ai-chat-feedback';
            feedbackDiv.innerHTML = `
                <span class="ai-feedback-label">Was this helpful?</span>
                <button class="ai-feedback-btn ai-feedback-positive" data-message-id="${messageId}" data-feedback="positive" title="Helpful">👍</button>
                <button class="ai-feedback-btn ai-feedback-negative" data-message-id="${messageId}" data-feedback="negative" title="Not helpful">👎</button>
            `;

            // Add event listeners to feedback buttons
            feedbackDiv.querySelectorAll('.ai-feedback-btn').forEach(btn => {
                btn.addEventListener('click', (e) => this.handleFeedback(e));
            });

            contentWrapper.appendChild(feedbackDiv);
        }

        msgDiv.appendChild(contentWrapper);
        this.messagesEl.appendChild(msgDiv);
        this.scrollToBottom();
    }

    private formatMessage(content: string): string {
        // Convert newlines to <br> and escape HTML
        return this.escapeHtml(content)
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
    }

    private escapeHtml(text: string): string {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    private getConfidenceClass(confidence: number): string {
        if (confidence >= 0.8) return 'ai-confidence-high';
        if (confidence >= 0.5) return 'ai-confidence-medium';
        return 'ai-confidence-low';
    }

    private async handleFeedback(e: Event): Promise<void> {
        const btn = e.target as HTMLButtonElement;
        const messageId = parseInt(btn.dataset.messageId || '0', 10);
        const feedback = btn.dataset.feedback || '';

        if (!messageId || !feedback) return;

        try {
            await window.$http.post('/ai/feedback', {
                message_id: messageId,
                feedback,
            });

            // Show feedback confirmation
            const parent = btn.closest('.ai-chat-feedback');
            if (parent) {
                parent.innerHTML = '<span class="ai-feedback-thanks">Thank you for your feedback!</span>';
            }
        } catch (err) {
            // Silently fail
        }
    }

    private setLoading(loading: boolean): void {
        this.isLoading = loading;
        this.sendBtn.disabled = loading;
        this.inputEl.disabled = loading;

        if (loading) {
            // Add loading indicator
            const loader = document.createElement('div');
            loader.id = 'ai-chat-loader';
            loader.className = 'ai-chat-message ai-chat-message-assistant ai-chat-loading';
            loader.innerHTML = `
                <div class="ai-chat-avatar">🤖</div>
                <div class="ai-chat-content">
                    <div class="ai-chat-text">
                        <span class="ai-typing-indicator">
                            <span></span><span></span><span></span>
                        </span>
                        <em>Searching approved SOPs...</em>
                    </div>
                </div>
            `;
            this.messagesEl.appendChild(loader);
            this.scrollToBottom();
        } else {
            // Remove loading indicator
            const loader = document.getElementById('ai-chat-loader');
            if (loader) loader.remove();
        }
    }

    private scrollToBottom(): void {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }

    async newChat(): Promise<void> {
        // Clear display
        this.messagesEl.innerHTML = '';

        // Clear session on server
        try {
            await window.$http.post('/ai/clear-session', {
                session_id: this.sessionId,
            });
        } catch (err) {
            // Continue even if server clear fails
        }

        // Generate new session ID
        this.sessionId = this.generateSessionId();

        // Add welcome message
        this.addWelcomeMessage();
    }

    private addWelcomeMessage(): void {
        const welcome = document.createElement('div');
        welcome.className = 'ai-chat-welcome';
        welcome.innerHTML = `
            <div class="ai-welcome-icon">🤖</div>
            <h3>SOP Assistant</h3>
            <p>Ask me questions about company policies and procedures. I'll search through approved SOPs to find answers for you.</p>
            <div class="ai-welcome-examples">
                <p><strong>Try asking:</strong></p>
                <ul>
                    <li>"What is the procedure for requesting time off?"</li>
                    <li>"How do I submit an expense report?"</li>
                    <li>"What are the safety guidelines for the lab?"</li>
                </ul>
            </div>
        `;
        this.messagesEl.appendChild(welcome);
    }
}

