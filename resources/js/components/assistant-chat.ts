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
 * Full-page Assistant Chat Component
 * A ChatGPT-style chat interface for the AI SOP Assistant.
 */
export class AssistantChat extends Component {
    private messagesEl!: HTMLElement;
    private emptyStateEl!: HTMLElement;
    private inputEl!: HTMLTextAreaElement;
    private sendBtn!: HTMLButtonElement;
    private typingIndicator!: HTMLElement;
    private suggestionBtns!: HTMLElement[];
    private sessionId: string;
    private isLoading = false;
    private hasMessages = false;

    setup(): void {
        this.messagesEl = this.$refs.messages as HTMLElement;
        this.emptyStateEl = this.$refs.emptyState as HTMLElement;
        this.inputEl = this.$refs.input as HTMLTextAreaElement;
        this.sendBtn = this.$refs.sendBtn as HTMLButtonElement;
        this.typingIndicator = this.$refs.typingIndicator as HTMLElement;
        this.suggestionBtns = this.$manyRefs.suggestion as HTMLElement[] || [];
        this.sessionId = this.generateSessionId();

        this.setupEventListeners();
        this.inputEl.focus();
    }

    private setupEventListeners(): void {
        // Send button click
        this.sendBtn.addEventListener('click', () => this.send());

        // Enter to send (Shift+Enter for new line)
        this.inputEl.addEventListener('keydown', (e: KeyboardEvent) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        });

        // Auto-resize textarea and toggle send button
        this.inputEl.addEventListener('input', () => {
            this.resizeTextarea();
            this.updateSendButton();
        });

        // Suggestion buttons
        this.suggestionBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.inputEl.value = btn.textContent?.trim() || '';
                this.updateSendButton();
                this.send();
            });
        });
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
        const maxHeight = 150;
        this.inputEl.style.height = Math.min(this.inputEl.scrollHeight, maxHeight) + 'px';
    }

    private updateSendButton(): void {
        const hasContent = this.inputEl.value.trim().length > 0;
        this.sendBtn.disabled = !hasContent || this.isLoading;
    }

    async send(): Promise<void> {
        const query = this.inputEl.value.trim();
        if (!query || this.isLoading) return;

        // Hide empty state on first message
        if (!this.hasMessages) {
            this.hideEmptyState();
            this.hasMessages = true;
        }

        // Add user message (optimistic)
        this.addMessage('user', query);
        this.inputEl.value = '';
        this.resizeTextarea();
        this.updateSendButton();
        this.setLoading(true);

        try {
            const response = await window.$http.post('/ai/chat', {
                query,
                session_id: this.sessionId,
            }) as { data: ChatResponse };

            const data = response.data;

            if (data.success) {
                // Simulate streaming by revealing text incrementally
                await this.addMessageWithStreaming(
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

    private hideEmptyState(): void {
        if (this.emptyStateEl) {
            this.emptyStateEl.classList.add('hidden');
        }
    }

    private addMessage(
        role: string,
        content: string,
        citations?: Citation[],
        messageId?: number,
        confidence?: number
    ): HTMLElement {
        const msgDiv = document.createElement('div');
        msgDiv.className = `assistant-message assistant-message-${role}`;

        const timestamp = this.formatTimestamp(new Date());

        msgDiv.innerHTML = `
            <div class="assistant-message-bubble">
                <div class="assistant-message-content">${this.formatMessage(content)}</div>
                ${this.renderCitations(citations)}
                ${this.renderConfidence(confidence)}
                ${this.renderFeedback(messageId)}
            </div>
            <div class="assistant-message-time">${timestamp}</div>
        `;

        // Attach feedback handlers if present
        if (messageId) {
            msgDiv.querySelectorAll('.assistant-feedback-btn').forEach(btn => {
                btn.addEventListener('click', (e) => this.handleFeedback(e));
            });
        }

        this.messagesEl.appendChild(msgDiv);
        this.scrollToBottom();
        return msgDiv;
    }

    private async addMessageWithStreaming(
        content: string,
        citations?: Citation[],
        messageId?: number,
        confidence?: number
    ): Promise<void> {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'assistant-message assistant-message-assistant';

        const timestamp = this.formatTimestamp(new Date());

        msgDiv.innerHTML = `
            <div class="assistant-message-bubble">
                <div class="assistant-message-content"></div>
            </div>
            <div class="assistant-message-time">${timestamp}</div>
        `;

        this.messagesEl.appendChild(msgDiv);
        this.scrollToBottom();

        const contentEl = msgDiv.querySelector('.assistant-message-content') as HTMLElement;
        const bubbleEl = msgDiv.querySelector('.assistant-message-bubble') as HTMLElement;

        // Simulate streaming by revealing characters progressively
        await this.typeText(contentEl, content);

        // After streaming completes, add citations, confidence, and feedback
        if (citations && citations.length > 0) {
            bubbleEl.insertAdjacentHTML('beforeend', this.renderCitations(citations));
        }
        if (typeof confidence === 'number') {
            bubbleEl.insertAdjacentHTML('beforeend', this.renderConfidence(confidence));
        }
        if (messageId) {
            bubbleEl.insertAdjacentHTML('beforeend', this.renderFeedback(messageId));
            bubbleEl.querySelectorAll('.assistant-feedback-btn').forEach(btn => {
                btn.addEventListener('click', (e) => this.handleFeedback(e));
            });
        }

        this.scrollToBottom();
    }

    private async typeText(element: HTMLElement, text: string): Promise<void> {
        const formatted = this.formatMessage(text);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = formatted;
        const plainText = tempDiv.textContent || '';
        
        const chunkSize = 3; // Characters per frame
        const delay = 15; // ms between chunks

        let index = 0;
        while (index < formatted.length) {
            // Find a safe break point (not in the middle of HTML tag)
            let endIndex = Math.min(index + chunkSize, formatted.length);
            
            // If we're in the middle of a tag, extend to close it
            const partial = formatted.substring(0, endIndex);
            const openTags = (partial.match(/<[^/][^>]*>/g) || []).length;
            const closeTags = (partial.match(/<\/[^>]+>/g) || []).length;
            
            if (openTags > closeTags) {
                // Find the next closing tag
                const closeTagMatch = formatted.substring(endIndex).match(/<\/[^>]+>/);
                if (closeTagMatch && closeTagMatch.index !== undefined) {
                    endIndex = endIndex + closeTagMatch.index + closeTagMatch[0].length;
                }
            }

            element.innerHTML = formatted.substring(0, endIndex);
            index = endIndex;
            this.scrollToBottom();
            
            await new Promise(resolve => setTimeout(resolve, delay));
        }

        element.innerHTML = formatted;
    }

    private formatMessage(content: string): string {
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

    private formatTimestamp(date: Date): string {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    private renderCitations(citations?: Citation[]): string {
        if (!citations || citations.length === 0) return '';
        
        // Sort citations by relevance score (highest first)
        const sortedCitations = [...citations].sort((a, b) => 
            (b.relevance_score || 0) - (a.relevance_score || 0)
        );

        const citationItems = sortedCitations.map((c, index) => {
            const score = c.relevance_score || 0;
            const level = score >= 0.5 ? 'high' : score >= 0.35 ? 'medium' : 'low';
            const label = score >= 0.5 ? 'High' : score >= 0.35 ? 'Med' : 'Low';
            const link = c.url
                ? `<a href="${c.url}" target="_blank" class="assistant-citation-link">${this.escapeHtml(c.title)}</a>`
                : `<span class="assistant-citation-text">${this.escapeHtml(c.title)}</span>`;
            
            return `<li class="assistant-citation-item">
                <span class="assistant-citation-rank">#${index + 1}</span>
                ${link}
                <span class="assistant-citation-score assistant-citation-score-${level}">${label}</span>
            </li>`;
        }).join('');

        return `<div class="assistant-citations">
            <div class="assistant-citations-label">📚 Sources (ranked by relevance):</div>
            <ol class="assistant-citations-list">${citationItems}</ol>
        </div>`;
    }

    private renderConfidence(confidence?: number): string {
        if (typeof confidence !== 'number') return '';
        
        const percent = Math.round(confidence * 100);
        const level = confidence >= 0.8 ? 'high' : confidence >= 0.5 ? 'medium' : 'low';
        
        return `<div class="assistant-confidence">
            <span class="assistant-confidence-label">Confidence:</span>
            <span class="assistant-confidence-value assistant-confidence-${level}">${percent}%</span>
        </div>`;
    }

    private renderFeedback(messageId?: number): string {
        if (!messageId) return '';
        
        return `<div class="assistant-feedback">
            <span class="assistant-feedback-label">Was this helpful?</span>
            <button class="assistant-feedback-btn assistant-feedback-positive" data-message-id="${messageId}" data-feedback="positive" title="Helpful">👍</button>
            <button class="assistant-feedback-btn assistant-feedback-negative" data-message-id="${messageId}" data-feedback="negative" title="Not helpful">👎</button>
        </div>`;
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

            const parent = btn.closest('.assistant-feedback');
            if (parent) {
                parent.innerHTML = '<span class="assistant-feedback-thanks">Thanks for your feedback!</span>';
            }
        } catch (err) {
            // Silently fail
        }
    }

    private setLoading(loading: boolean): void {
        this.isLoading = loading;
        this.sendBtn.disabled = loading;
        
        if (this.typingIndicator) {
            this.typingIndicator.classList.toggle('visible', loading);
        }
    }

    private scrollToBottom(): void {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }
}

