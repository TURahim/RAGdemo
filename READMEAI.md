# AI Implementation Overview

This document explains how the AI-powered SOP Assistant works in the RAGDemo application. The system uses **RAG (Retrieval-Augmented Generation)** to answer user questions based on approved SOP documents.

---

## What is RAG?

RAG is a technique that combines two things:
1. **Retrieval** - Finding relevant documents from a knowledge base
2. **Generation** - Using an AI model (like GPT-4) to generate answers based on those documents

This ensures the AI only answers based on your actual SOP content, not general knowledge.

---

## High-Level Architecture

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│                 │     │                  │     │                 │
│   User's Chat   │────▶│  Laravel (PHP)   │────▶│  Python RAG     │
│   Interface     │     │  AIController    │     │  Service        │
│                 │◀────│                  │◀────│                 │
└─────────────────┘     └──────────────────┘     └─────────────────┘
                                                         │
                                                         ▼
                              ┌──────────────┐    ┌─────────────────┐
                              │              │    │                 │
                              │   Pinecone   │    │     OpenAI      │
                              │   (Vectors)  │    │   (GPT-4 LLM)   │
                              │              │    │                 │
                              └──────────────┘    └─────────────────┘
```

The system has two main parts:
- **Laravel/PHP** (`app/AI/`) - Handles user requests, permissions, and conversation storage
- **Python RAG Service** (`python/rag_service/`) - Handles AI retrieval and generation

---

## Folder Structure: `app/AI/`

```
app/AI/
├── Controllers/
│   └── AIController.php      # API endpoints for chat
├── Jobs/
│   └── IndexEntityJob.php    # Background job for indexing documents
├── Models/
│   ├── AIConversation.php    # Tracks chat sessions
│   ├── AIMessage.php         # Stores individual messages
│   └── AIIndexStatus.php     # Tracks which documents are indexed
└── Services/
    ├── ChatService.php       # Main chat logic
    └── ChunkingService.php   # Splits documents into chunks
```

---

## How Each Part Works

### 1. AIController (API Layer)

The controller exposes these endpoints:
- `POST /chat` - Send a message and get an AI response
- `POST /feedback` - Submit thumbs up/down on a response
- `GET /history` - Get conversation history
- `POST /clear-session` - Clear chat history

It validates requests and checks if AI features are enabled before processing.

### 2. ChatService (Business Logic)

This is the core chat handler. When a user sends a message:

1. Gets or creates a conversation session
2. Checks which documents the user has permission to see
3. Sends the query to the Python RAG service
4. Saves both the user message and AI response to the database
5. Returns the answer with citations (links to source documents)

**Permission Filtering**: The service queries the `joint_permissions` table to find which pages the user can view. Only those documents are searched.

### 3. ChunkingService (Document Processing)

Before documents can be searched, they need to be broken into smaller pieces called "chunks". This service:

- Extracts clean text from HTML pages
- Splits text into ~500 token chunks with 50 token overlap
- Adds metadata (page title, book, shelf, etc.)

**Why chunks?** AI models work better with focused, smaller pieces of text rather than entire documents.

### 4. IndexEntityJob (Background Indexing)

When an SOP page is approved, this job:

1. Checks if the page has an approved revision
2. Uses ChunkingService to split the content
3. Sends chunks to the Python RAG service for embedding
4. Updates the index status in the database

The job runs in the background so users don't have to wait.

### 5. Database Models

- **AIConversation** - Groups messages into sessions (user_id + session_id)
- **AIMessage** - Stores each message with role (user/assistant), content, citations, and feedback
- **AIIndexStatus** - Tracks indexing progress (pending, indexing, indexed, failed)

---

## Python RAG Service

The Python service (`python/rag_service/`) handles the AI-heavy lifting:

### main.py - FastAPI Application
- `/chat` - Process queries through the RAG pipeline
- `/index` - Store document embeddings in Pinecone
- `/clear-session` - Clear conversation memory

### chain.py - RAG Pipeline
1. **Retrieve** - Search Pinecone for relevant document chunks
2. **Context** - Build a context string from retrieved documents
3. **Generate** - Send context + question to OpenAI
4. **Return** - Include citations and confidence score

---

## Data Flow: Asking a Question

```
User asks: "What is the procedure for equipment calibration?"
                            │
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 1. Laravel checks user permissions                               │
│    → Gets list of page IDs user can view                         │
└──────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 2. Sends to Python RAG Service                                   │
│    → Query + user_id + allowed_entity_ids                        │
└──────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 3. Pinecone vector search                                        │
│    → Finds chunks similar to "equipment calibration"             │
│    → Only searches allowed documents                             │
└──────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 4. OpenAI generates answer                                       │
│    → Uses retrieved chunks as context                            │
│    → Includes conversation history for follow-ups                │
└──────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 5. Response returned to user                                     │
│    → Answer text + citations + confidence score                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## Data Flow: Indexing a Document

```
Admin approves SOP page
         │
         ▼
┌─────────────────────────────────────────┐
│ 1. IndexEntityJob dispatched            │
│    → Runs in background queue           │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ 2. ChunkingService splits document      │
│    → Strips HTML, normalizes text       │
│    → Creates overlapping chunks         │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ 3. Python service generates embeddings  │
│    → Uses OpenAI embedding model        │
│    → Stores vectors in Pinecone         │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ 4. AIIndexStatus updated                │
│    → status = 'indexed'                 │
│    → chunk_count recorded               │
└─────────────────────────────────────────┘
```

---

## Configuration

All AI settings are in `config/ai.php` and controlled by environment variables:

| Setting | Purpose |
|---------|---------|
| `AI_ENABLED` | Master on/off toggle |
| `OPENAI_API_KEY` | OpenAI authentication |
| `PINECONE_API_KEY` | Pinecone authentication |
| `RAG_SERVICE_URL` | Python service URL |
| `AI_CHUNK_SIZE` | Tokens per chunk (default: 500) |
| `AI_RETRIEVAL_TOP_K` | Number of chunks to retrieve (default: 5) |

---

## Key Concepts

### Embeddings
Text converted to numbers (vectors) that capture meaning. Similar texts have similar vectors.

### Vector Database (Pinecone)
A specialized database for storing and searching embeddings quickly.

### Chunks with Overlap
Documents are split into overlapping pieces so context isn't lost at boundaries.

### Permission Filtering
Before searching, the system gets a list of document IDs the user can access. Only those are searched.

### Conversation Memory
The system remembers previous messages in a session so users can ask follow-up questions.

### Citations
Every answer includes links to the source documents so users can verify information.

---

## Security Considerations

1. **Permission-based filtering** - Users only search documents they can view
2. **Guest access prevention** - All endpoints require authentication
3. **Rate limiting** - Configurable limits per minute/day
4. **Session isolation** - Each user's conversation history is separate

