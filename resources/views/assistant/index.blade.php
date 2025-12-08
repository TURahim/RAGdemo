@extends('layouts.base')

@push('body-class')
assistant-page
@endpush

@section('content')
<div class="assistant-chat-container" component="assistant-chat">
    {{-- Chat Messages Area --}}
    <div class="assistant-messages" refs="assistant-chat@messages">
        {{-- Empty state --}}
        <div class="assistant-empty-state" refs="assistant-chat@emptyState">
            <div class="assistant-logo">
                @icon('chat')
            </div>
            <h2>{{ trans('common.ai_assistant') }}</h2>
            <p>{{ trans('common.ai_assistant_description') }}</p>
            
            <div class="assistant-suggestions">
                <button type="button" class="assistant-suggestion" refs="assistant-chat@suggestion">
                    What SOPs cover equipment calibration?
                </button>
                <button type="button" class="assistant-suggestion" refs="assistant-chat@suggestion">
                    How do I handle a product recall?
                </button>
                <button type="button" class="assistant-suggestion" refs="assistant-chat@suggestion">
                    What are the training requirements for new hires?
                </button>
                <button type="button" class="assistant-suggestion" refs="assistant-chat@suggestion">
                    Explain the document approval workflow
                </button>
            </div>
        </div>

        {{-- Messages will be inserted here --}}
    </div>

    {{-- Input Area --}}
    <div class="assistant-input-area">
        <div class="assistant-input-wrapper">
            <textarea
                refs="assistant-chat@input"
                placeholder="Ask me anything about SOPs, compliance, product manuals, revisions…"
                rows="1"
            ></textarea>
            <button type="button" class="assistant-send-btn" refs="assistant-chat@sendBtn" disabled>
                @icon('arrow-up')
            </button>
        </div>
        <div class="assistant-input-hint">
            <span class="assistant-typing-indicator" refs="assistant-chat@typingIndicator">
                <span></span><span></span><span></span>
            </span>
            <span>Press <kbd>Enter</kbd> to send, <kbd>Shift+Enter</kbd> for new line</span>
        </div>
    </div>
</div>
@endsection

