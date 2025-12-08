{{--
AI Chat Panel Component
Include this partial to add the AI SOP Assistant chat interface.

Usage: @include('ai.chat-panel')
Or: @include('ai.chat-panel', ['floating' => true])
--}}

@if(config('ai.enabled'))
    @php
        $floating = $floating ?? false;
        $showWelcome = $showWelcome ?? true;
    @endphp

    <div component="ai-chat-panel" 
         option:ai-chat-panel:floating="{{ $floating ? 'true' : 'false' }}">
        
        @if($floating)
            {{-- Floating Chat Button --}}
            <button type="button" 
                    class="ai-chat-fab" 
                    refs="ai-chat-panel@toggleBtn"
                    title="{{ trans('common.ai_assistant') }}">
                🤖
            </button>
        @endif

        <div class="ai-chat-panel {{ $floating ? 'ai-chat-floating ai-chat-hidden' : '' }}" 
             refs="ai-chat-panel@panel">
            
            {{-- Header --}}
            <div class="ai-chat-header">
                <h4>
                    <span>🤖</span>
                    <span>{{ trans('common.ai_assistant') }}</span>
                </h4>
                <button type="button" 
                        class="ai-chat-clear-btn"
                        refs="ai-chat-panel@closeBtn"
                        title="{{ $floating ? 'Close' : trans('common.new_chat') }}">
                    {{ $floating ? '✕' : trans('common.new_chat') }}
                </button>
            </div>

            {{-- Messages Area --}}
            <div class="ai-chat-messages" refs="ai-chat-panel@messages">
                @if($showWelcome)
                    <div class="ai-chat-welcome">
                        <div class="ai-welcome-icon">🤖</div>
                        <h3>{{ trans('common.ai_assistant') }}</h3>
                        <p>{{ trans('common.ai_assistant_description') }}</p>
                        <div class="ai-welcome-examples">
                            <p><strong>{{ trans('common.try_asking') }}</strong></p>
                            <ul>
                                <li>"What is the procedure for requesting time off?"</li>
                                <li>"How do I submit an expense report?"</li>
                                <li>"What are the safety guidelines for the lab?"</li>
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Input Area --}}
            <div class="ai-chat-input-area">
                <textarea class="ai-chat-input" 
                          refs="ai-chat-panel@input"
                          placeholder="{{ trans('common.ask_a_question') }}"
                          rows="1"
                          maxlength="2000"></textarea>
                <button type="button" 
                        class="ai-chat-send-btn" 
                        refs="ai-chat-panel@sendBtn"
                        title="{{ trans('common.send') }}">
                    ➤
                </button>
            </div>
        </div>
    </div>
@endif

