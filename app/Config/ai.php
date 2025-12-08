<?php

/**
 * AI Assistant Configuration
 *
 * This file contains all configuration options for the RAG-based
 * SOP Assistant AI system.
 */

return [
    // Master toggle for AI features
    'enabled' => env('AI_ENABLED', false),

    // OpenAI Configuration
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 1024),
        'temperature' => env('OPENAI_TEMPERATURE', 0.3),
    ],

    // Pinecone Vector Store Configuration
    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT', 'us-east-1'),
        'index_name' => env('PINECONE_INDEX', 'sop-assistant'),
    ],

    // RAG Python Service Configuration
    'rag_service' => [
        'url' => env('RAG_SERVICE_URL', 'http://localhost:8001'),
        'timeout' => env('RAG_SERVICE_TIMEOUT', 30),
    ],

    // Document Chunking Settings
    'chunking' => [
        'chunk_size' => env('AI_CHUNK_SIZE', 500),       // Target tokens per chunk
        'chunk_overlap' => env('AI_CHUNK_OVERLAP', 50),  // Overlap tokens between chunks
    ],

    // Retrieval Settings
    'retrieval' => [
        'top_k' => env('AI_RETRIEVAL_TOP_K', 5),           // Number of documents to retrieve
        'score_threshold' => env('AI_SCORE_THRESHOLD', 0.3), // Minimum relevance score
    ],

    // Rate Limiting
    'rate_limits' => [
        'per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 10),
        'per_day' => env('AI_RATE_LIMIT_PER_DAY', 100),
    ],

    // Conversation Memory Settings
    'memory' => [
        'max_history' => env('AI_MAX_HISTORY', 10),       // Max messages to include in context
        'ttl_hours' => env('AI_MEMORY_TTL_HOURS', 24),    // Hours before memory expires
    ],
];

