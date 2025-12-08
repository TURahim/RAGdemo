# SOP Manager

An intelligent Standard Operating Procedure (SOP) management platform with AI-powered chat assistant. Built on Laravel/PHP with a RAG (Retrieval-Augmented Generation) pipeline for natural language Q&A over your organization's documentation.

![License](https://img.shields.io/badge/License-MIT-yellow.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)
![Python](https://img.shields.io/badge/Python-3.11-3776AB?logo=python)

---

## ✨ Features

### Document Management
- **Hierarchical Organization**: Departments (Shelves) → Manuals (Books) → Sections (Chapters) → SOPs (Pages)
- **Rich Text Editor**: WYSIWYG and Markdown editing with code blocks, tables, diagrams
- **Version Control**: Full revision history with diff view and rollback capability
- **Approval Workflow**: Draft → Pending Review → Approved lifecycle
- **Export Options**: PDF, HTML, Markdown, Plain Text

### AI-Powered Assistant
- **Natural Language Q&A**: Ask questions about SOPs in plain English
- **RAG Pipeline**: Answers grounded in your actual documentation, not hallucinations
- **Citations**: Every response includes links to source documents
- **Permission-Aware**: Users only search documents they have access to
- **Conversation Memory**: Follow-up questions understand context
- **Confidence Scoring**: Transparency about answer reliability

### Access Control
- **Role-Based Permissions**: Admin, Editor, Viewer roles with granular controls
- **Department-Level Access**: Restrict visibility by organizational unit
- **Single Sign-On**: SAML2, OIDC, LDAP, and social auth support

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              SOP Manager                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌──────────────┐     ┌──────────────┐     ┌──────────────────────┐   │
│   │   Frontend   │     │   Laravel    │     │   Python RAG         │   │
│   │   (Blade +   │────▶│   Backend    │────▶│   Service            │   │
│   │   TypeScript)│◀────│   (PHP 8.3)  │◀────│   (FastAPI)          │   │
│   └──────────────┘     └──────────────┘     └──────────────────────┘   │
│                               │                       │                  │
│                               ▼                       ▼                  │
│                        ┌──────────────┐     ┌──────────────────────┐   │
│                        │    MySQL     │     │   Pinecone (Vector   │   │
│                        │    Redis     │     │   DB) + OpenAI       │   │
│                        └──────────────┘     └──────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Components

| Component | Technology | Purpose |
|-----------|------------|---------|
| Web App | Laravel 12 / PHP 8.3 | Document CRUD, auth, permissions, API |
| Frontend | Blade + TypeScript | UI components, chat interface |
| RAG Service | Python 3.11 / FastAPI | Vector search, LLM orchestration |
| Database | MySQL 8.0 | Document storage, user data |
| Cache/Queue | Redis | Sessions, caching, background jobs |
| Vector Store | Pinecone | Semantic search over document embeddings |
| LLM | OpenAI GPT-4o-mini | Answer generation |
| Embeddings | OpenAI text-embedding-3-small | Document vectorization |

---

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose
- OpenAI API key
- Pinecone API key (free tier works)

### Local Development (Docker)

```bash
# Clone the repository
git clone https://github.com/TURahim/RAGdemo.git
cd RAGdemo

# Copy environment file
cp .env.example .env

# Set required values in .env:
# - APP_KEY (generate with: php artisan key:generate --show)
# - OPENAI_API_KEY
# - PINECONE_API_KEY
# - PINECONE_INDEX=sop-assistant

# Start all services
docker compose up -d

# Run database migrations
docker compose exec app php artisan migrate --seed

# Access the app
open http://localhost:8080
```

**Default Login:**
- Email: `admin@admin.com`
- Password: `password`

> ⚠️ Change the default credentials immediately after first login.

### Services & Ports

| Service | Port | Description |
|---------|------|-------------|
| App | 8080 | Main web application |
| RAG Service | 8001 | Python AI service |
| MySQL | 3306 | Database |
| Redis | 6379 | Cache & sessions |
| MailHog | 8025 | Email testing UI |

---

## 🤖 AI Chat System

### How It Works

1. **User asks a question** via the chat interface
2. **Permission check** — System identifies which documents the user can access
3. **Vector search** — Query is embedded and matched against document chunks in Pinecone
4. **Context retrieval** — Top matching chunks are pulled with metadata
5. **LLM generation** — OpenAI generates an answer using the retrieved context
6. **Response with citations** — User receives answer with links to source documents

### RAG Pipeline

```
Question: "What's the procedure for equipment calibration?"
                    │
                    ▼
┌────────────────────────────────────────────────────────────────┐
│ 1. Embed query using text-embedding-3-small                    │
│ 2. Search Pinecone for similar document chunks                 │
│ 3. Filter by user's permitted document IDs                     │
│ 4. Retrieve top-k chunks (default: 5)                          │
│ 5. Construct prompt with system instructions + context         │
│ 6. Generate answer via GPT-4o-mini                             │
│ 7. Extract citations and confidence score                      │
└────────────────────────────────────────────────────────────────┘
                    │
                    ▼
Answer: "According to SOP-MFG-001, equipment calibration requires..."
[📄 Source: Equipment Calibration Procedure - Manufacturing Operations]
```

### Configuration

AI settings are controlled via environment variables:

```bash
# Master toggle
AI_ENABLED=true

# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_TEMPERATURE=0.3
OPENAI_MAX_TOKENS=1024

# Pinecone
PINECONE_API_KEY=...
PINECONE_INDEX=sop-assistant

# RAG Settings
AI_CHUNK_SIZE=500          # Tokens per chunk
AI_CHUNK_OVERLAP=50        # Overlap between chunks
AI_RETRIEVAL_TOP_K=5       # Number of chunks to retrieve
AI_SCORE_THRESHOLD=0.3     # Minimum similarity score

# Rate Limiting
AI_RATE_LIMIT_PER_MINUTE=10
AI_RATE_LIMIT_PER_DAY=100
```

---

## 📁 Project Structure

```
├── app/
│   ├── AI/                    # AI chat system
│   │   ├── Controllers/       # Chat API endpoints
│   │   ├── Jobs/              # Background indexing jobs
│   │   ├── Models/            # Conversation, Message, IndexStatus
│   │   └── Services/          # Chat logic, document chunking
│   ├── Entities/              # Books, Chapters, Pages (SOPs)
│   ├── Permissions/           # RBAC system
│   └── Users/                 # User management
│
├── python/
│   └── rag_service/           # Python RAG microservice
│       ├── main.py            # FastAPI application
│       ├── chain.py           # RAG pipeline orchestration
│       ├── retriever.py       # Pinecone search
│       ├── memory.py          # Conversation history
│       └── prompts.py         # System prompts
│
├── resources/
│   ├── js/                    # TypeScript frontend
│   │   └── components/        # UI components including chat
│   ├── views/                 # Blade templates
│   └── sass/                  # Stylesheets
│
├── docker-compose.yml         # Local development stack
└── docker-compose.prod.yml    # Production configuration
```

---

## 🔒 Security

- **Permission-based search filtering** — Users only query documents they can view
- **Authentication required** — All AI endpoints require login
- **Rate limiting** — Configurable per-user limits
- **Session isolation** — Conversation histories are user-scoped
- **No training on your data** — OpenAI API does not train on API inputs

---

## 📖 Documentation

- [AI Implementation Details](./READMEAI.md) — Deep dive into the RAG architecture
- [Development Guide](./dev/docs/development.md) — Setting up a dev environment
- [API Documentation](./dev/docs/api.md) — REST API reference

---

## 🛠️ Development

### Running Tests

```bash
# PHP tests
docker compose exec app php artisan test

# JavaScript tests
docker compose exec node npm test
```

### Building Assets

```bash
# Development (with watch)
docker compose exec node npm run dev

# Production build
docker compose exec node npm run build
```

### Artisan Commands

```bash
# Run migrations
docker compose exec app php artisan migrate

# Seed demo data
docker compose exec app php artisan db:seed --class=MedtechSOPSeeder

# Clear caches
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
```

---

## 🚢 Production Deployment

See [deploylightsail.md](./deploylightsail.md) for AWS Lightsail deployment instructions.

### Quick Production Setup

```bash
# On your server
git clone https://github.com/TURahim/RAGdemo.git ~/app
cd ~/app

# Configure environment
cp .env.example .env
# Edit .env with production values

# Start with production compose
docker compose -f docker-compose.prod.yml up -d

# Run migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### Environment Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://your-domain.com`
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials set
- [ ] Redis configured
- [ ] OpenAI & Pinecone API keys set
- [ ] SSL/TLS configured (via nginx/certbot)

---

## 📝 License

This project is licensed under the MIT License — see [LICENSE](./LICENSE) for details.

---

## 🙏 Acknowledgments

Built on the excellent [BookStack](https://www.bookstackapp.com/) documentation platform, extended with AI capabilities for intelligent SOP management.

### Key Dependencies

- [Laravel](https://laravel.com/) — PHP web framework
- [FastAPI](https://fastapi.tiangolo.com/) — Python API framework
- [Pinecone](https://www.pinecone.io/) — Vector database
- [OpenAI](https://openai.com/) — LLM and embeddings
- [TinyMCE](https://www.tiny.cloud/) — Rich text editor
- [Lexical](https://lexical.dev/) — Modern text editor
