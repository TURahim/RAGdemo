Modern Agent-Style RAG Implementation Plan (LangGraph + LangMem + Pinecone)
- Goal: Move from simple embedding search to an agentic, memory-aware RAG that respects BookStack permissions and slots cleanly into existing routes/UI.
- Principles: Feature-flagged, additive, least invasive to core; single-tenant per client; BYO keys; degrade gracefully when disabled.

Phases
1) Config & Feature Flag
   - Add env/settings for AI enablement, model IDs, embedding provider, Pinecone keys/index name, rate limits, timeouts, and memory retention limits.
   - Admin settings page entry; default off.

2) Content & Chunk Pipeline
   - Extract canonical text from Pages (and optionally Books/Chapters summaries); strip HTML to clean text.
   - Chunk with Markdown-aware splitter; store chunk metadata: entity_id, entity_type, book_id, shelf_id, updated_at, language, permission_hash/roles.
   - Hook reindex on create/update/delete/move; provide CLI `ai:index --all/--entity`.

3) Vector Store (Pinecone)
   - Namespace per instance; collections keyed by entity type or shared with metadata filters.
   - Metadata fields: entity_id, entity_type, path (shelf/book/chapter), permission_roles (or hash), updated_at, lang, tenant_id (even if single-tenant for future safety).
   - Upsert via async jobs; retries/backoff; batch for rate limits.

4) Orchestration (LangGraph)
   - Graph nodes: intake → retrieval (Pinecone) → permission filter → rerank (optional) → context pack → LLM call → response formatter → (optional) memory update.
   - Tool for “open source page” link generation; optional summarizer for long answers.
   - Error/resilience: timeouts, fallback to short answer or classic search link when AI fails.

5) Memory (LangMem or custom store)
   - Short-term: conversation turns scoped to user/session; cap tokens and age.
   - Long-term (optional): store prior cited entities to bias retrieval; ensure strict user scoping to avoid leakage.
   - Persistence: DB table keyed by user/session; TTL cleanup job.

6) Laravel API Surface
   - New routes under `/api/ai/*` (auth required, rate-limited) for: semantic search, chat with citations, reindex trigger (admin).
   - Middleware computes allowed entity IDs/roles; pass to retrieval filter before RAG call.
   - Responses include citations (entity_id/type/title/url) and safety flags; stream if feasible.

7) Frontend UX
   - Chat/search panel component (toggle/tab on search page or header entry); feature-flagged.
   - Shows streaming answer, inline citations linking to BookStack pages; “open page” and “copy answer” actions.
   - Fallback banner when AI disabled/unavailable; keeps classic search untouched.

8) Security, Compliance, Observability
   - Sanitize LLM output; strip scripts; respect CSP.
   - Logging: latency, provider errors, truncation, rate-limit hits (no sensitive content in logs).
   - Configurable egress: support OpenAI to start; allow swap to self-hosted models later without API changes.

Indicative Timeline (agentic stack)
- Config + flags: 0.5 day
- Chunk/ingest pipeline + CLI: 1.5–2 days
- Pinecone integration (schema, upsert/query with filters): 1 day
- LangGraph orchestration + LangMem wiring: 1–2 days (faster if already familiar)
- Laravel API wrapper + permissions filter: 1 day
- Frontend chat panel (basic, citations): 1–2 days
- Testing/polish: 1 day
Total: ~6–9 days for a solid demo, assuming OpenAI embeddings/LLM and single-tenant per client.