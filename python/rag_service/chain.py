"""
LangChain RAG chain implementation.
"""

import os
from typing import Any, Dict, List

from langchain_core.output_parsers import StrOutputParser
from langchain_core.prompts import ChatPromptTemplate
from langchain_openai import ChatOpenAI

from .memory import ConversationMemory
from .prompts import QA_PROMPT, SYSTEM_PROMPT
from .retriever import create_retriever


class RAGChain:
    """Simple LangChain RAG implementation."""

    def __init__(self):
        self.memory = ConversationMemory()
        self.llm = ChatOpenAI(
            model=os.getenv("OPENAI_CHAT_MODEL", "gpt-4o-mini"),
            temperature=float(os.getenv("OPENAI_TEMPERATURE", "0.3")),
            max_tokens=int(os.getenv("OPENAI_MAX_TOKENS", "1024")),
        )

        # Create prompt template
        self.prompt = ChatPromptTemplate.from_messages(
            [("system", SYSTEM_PROMPT), ("human", QA_PROMPT)]
        )

    def run(
        self,
        query: str,
        user_id: int,
        session_id: str,
        allowed_entity_ids: List[int],
    ) -> Dict[str, Any]:
        """Run the RAG chain."""

        # Create retriever with user's permissions
        retriever = create_retriever(
            allowed_entity_ids=allowed_entity_ids,
            top_k=int(os.getenv("AI_RETRIEVAL_TOP_K", "5")),
        )

        # Retrieve relevant documents
        docs = retriever.get_relevant_documents(query)

        # Build context from documents
        context = self._build_context(docs)

        # Get conversation history
        chat_history = self.memory.get_history_string(user_id, session_id)

        # Run the chain
        chain = self.prompt | self.llm | StrOutputParser()

        answer = chain.invoke(
            {"context": context, "chat_history": chat_history, "question": query}
        )

        # Save to memory
        self.memory.add_message(user_id, session_id, "user", query)
        self.memory.add_message(user_id, session_id, "assistant", answer)

        # Extract citations
        citations = self._extract_citations(docs)

        # Calculate confidence
        confidence = self._calculate_confidence(docs)

        return {"answer": answer, "citations": citations, "confidence": confidence}

    def _build_context(self, docs) -> str:
        """Build context string from retrieved documents."""
        if not docs:
            return "No relevant documents found."

        context_parts = []
        for i, doc in enumerate(docs):
            title = doc.metadata.get("title", "Unknown Document")
            dept = doc.metadata.get("department", "General")
            context_parts.append(
                f"[Document {i + 1}: {title} ({dept})]\n{doc.page_content}"
            )

        return "\n\n---\n\n".join(context_parts)

    def _extract_citations(self, docs) -> List[Dict]:
        """Extract citation information from documents."""
        citations = []
        seen_ids = set()

        for doc in docs:
            entity_id = doc.metadata.get("entity_id")
            if entity_id and entity_id not in seen_ids:
                seen_ids.add(entity_id)
                citations.append(
                    {
                        "entity_id": entity_id,
                        "entity_type": doc.metadata.get("entity_type", "page"),
                        "title": doc.metadata.get("title", "Unknown"),
                        "department": doc.metadata.get("department", "General"),
                        "relevance_score": doc.metadata.get("relevance_score", 0.0),
                    }
                )

        return citations

    def _calculate_confidence(self, docs) -> float:
        """Calculate confidence based on retrieval scores."""
        if not docs:
            return 0.0

        scores = [doc.metadata.get("relevance_score", 0.0) for doc in docs]
        return sum(scores) / len(scores)

    def clear_session(self, user_id: int, session_id: str) -> None:
        """Clear conversation memory for a session."""
        self.memory.clear(user_id, session_id)

