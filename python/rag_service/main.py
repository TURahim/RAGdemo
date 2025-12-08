"""
FastAPI RAG Service - Main application entry point.
"""

import os
from typing import List

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from langchain_openai import OpenAIEmbeddings
from pinecone import Pinecone
from pydantic import BaseModel

load_dotenv()

from .chain import RAGChain

app = FastAPI(title="SOP Assistant RAG Service", version="1.0.0")
rag_chain = RAGChain()


# Request/Response Models
class ChatRequest(BaseModel):
    query: str
    user_id: int
    session_id: str
    allowed_entity_ids: List[int]


class Citation(BaseModel):
    entity_id: int
    entity_type: str
    title: str
    department: str
    relevance_score: float


class ChatResponse(BaseModel):
    answer: str
    citations: List[Citation]
    confidence: float


class IndexRequest(BaseModel):
    entity_id: int
    entity_type: str
    chunks: List[dict]


class ClearSessionRequest(BaseModel):
    user_id: int
    session_id: str


# Endpoints
@app.get("/health")
async def health():
    """Health check endpoint."""
    return {"status": "healthy"}


@app.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest):
    """Process a chat query through the RAG pipeline."""
    try:
        result = rag_chain.run(
            query=request.query,
            user_id=request.user_id,
            session_id=request.session_id,
            allowed_entity_ids=request.allowed_entity_ids,
        )

        return ChatResponse(
            answer=result["answer"],
            citations=[Citation(**c) for c in result["citations"]],
            confidence=result["confidence"],
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/index")
async def index_document(request: IndexRequest):
    """Index document chunks into Pinecone."""
    try:
        pc = Pinecone(api_key=os.getenv("PINECONE_API_KEY"))
        index = pc.Index(os.getenv("PINECONE_INDEX", "sop-assistant"))

        embeddings = OpenAIEmbeddings(
            model=os.getenv("OPENAI_EMBEDDING_MODEL", "text-embedding-3-small")
        )

        # Delete existing vectors for this entity
        try:
            index.delete(
                filter={
                    "entity_id": request.entity_id,
                    "entity_type": request.entity_type,
                }
            )
        except Exception:
            # Ignore errors if no vectors exist
            pass

        # Generate embeddings and upsert
        vectors = []
        for chunk in request.chunks:
            embedding = embeddings.embed_query(chunk["text"])
            vectors.append(
                {
                    "id": f"{request.entity_type}_{request.entity_id}_{chunk['metadata']['chunk_index']}",
                    "values": embedding,
                    "metadata": {**chunk["metadata"], "text": chunk["text"]},
                }
            )

        # Upsert in batches of 100
        batch_size = 100
        for i in range(0, len(vectors), batch_size):
            batch = vectors[i : i + batch_size]
            index.upsert(vectors=batch)

        return {"status": "indexed", "chunk_count": len(vectors)}

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/clear-session")
async def clear_session(request: ClearSessionRequest):
    """Clear conversation history for a session."""
    rag_chain.clear_session(request.user_id, request.session_id)
    return {"status": "cleared"}


@app.delete("/index/{entity_type}/{entity_id}")
async def delete_from_index(entity_type: str, entity_id: int):
    """Delete an entity from the vector index."""
    try:
        pc = Pinecone(api_key=os.getenv("PINECONE_API_KEY"))
        index = pc.Index(os.getenv("PINECONE_INDEX", "sop-assistant"))

        index.delete(filter={"entity_id": entity_id, "entity_type": entity_type})

        return {"status": "deleted", "entity_type": entity_type, "entity_id": entity_id}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8001)

