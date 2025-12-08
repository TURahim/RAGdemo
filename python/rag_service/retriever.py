"""
Custom retriever with permission filtering.
"""

import os
from typing import List

from langchain_core.documents import Document
from langchain_core.retrievers import BaseRetriever
from langchain_openai import OpenAIEmbeddings
from langchain_pinecone import PineconeVectorStore
from pinecone import Pinecone
from pydantic import ConfigDict


class PermissionAwareRetriever(BaseRetriever):
    """Custom retriever that filters results by user permissions."""

    vectorstore: PineconeVectorStore
    allowed_entity_ids: List[int]
    top_k: int = 5
    score_threshold: float = 0.3

    model_config = ConfigDict(arbitrary_types_allowed=True)

    def _get_relevant_documents(self, query: str) -> List[Document]:
        """Retrieve documents with permission filtering."""

        # Build filter for Pinecone
        filter_dict = {}
        if self.allowed_entity_ids:
            filter_dict = {"entity_id": {"$in": self.allowed_entity_ids}}

        # Search with filter
        results = self.vectorstore.similarity_search_with_score(
            query, k=self.top_k * 2, filter=filter_dict  # Fetch extra for filtering
        )

        # Filter by score threshold and limit
        filtered_docs = []
        for doc, score in results:
            if score >= self.score_threshold and len(filtered_docs) < self.top_k:
                doc.metadata["relevance_score"] = score
                filtered_docs.append(doc)

        return filtered_docs


def create_retriever(
    allowed_entity_ids: List[int], top_k: int = 5
) -> PermissionAwareRetriever:
    """Factory function to create a permission-aware retriever."""

    # Initialize Pinecone
    pc = Pinecone(api_key=os.getenv("PINECONE_API_KEY"))
    index = pc.Index(os.getenv("PINECONE_INDEX", "sop-assistant"))

    # Create embeddings
    embeddings = OpenAIEmbeddings(
        model=os.getenv("OPENAI_EMBEDDING_MODEL", "text-embedding-3-small")
    )

    # Create vectorstore
    vectorstore = PineconeVectorStore(index=index, embedding=embeddings, text_key="text")

    return PermissionAwareRetriever(
        vectorstore=vectorstore,
        allowed_entity_ids=allowed_entity_ids,
        top_k=top_k,
        score_threshold=float(os.getenv("AI_SCORE_THRESHOLD", "0.3")),
    )

