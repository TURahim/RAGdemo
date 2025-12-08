"""
Conversation memory using Redis.
"""

import json
import os
from datetime import timedelta
from typing import Dict, List

import redis


class ConversationMemory:
    """Redis-backed conversation memory."""

    def __init__(self):
        self.redis = redis.Redis(
            host=os.getenv("REDIS_HOST", "localhost"),
            port=int(os.getenv("REDIS_PORT", 6379)),
            db=int(os.getenv("REDIS_DB", 1)),
            decode_responses=True,
        )
        self.ttl_hours = int(os.getenv("AI_MEMORY_TTL_HOURS", 24))
        self.max_history = int(os.getenv("AI_MAX_HISTORY", 10))

    def _key(self, user_id: int, session_id: str) -> str:
        """Generate Redis key for conversation."""
        return f"ai:chat:{user_id}:{session_id}"

    def get_history(self, user_id: int, session_id: str) -> List[Dict]:
        """Get conversation history as list of messages."""
        key = self._key(user_id, session_id)
        messages = self.redis.lrange(key, -self.max_history * 2, -1)
        return [json.loads(m) for m in messages]

    def get_history_string(self, user_id: int, session_id: str) -> str:
        """Get conversation history formatted for prompt."""
        history = self.get_history(user_id, session_id)
        if not history:
            return "No previous conversation."

        lines = []
        for msg in history[-self.max_history * 2 :]:
            role = "Human" if msg["role"] == "user" else "Assistant"
            lines.append(f"{role}: {msg['content']}")

        return "\n".join(lines)

    def add_message(
        self, user_id: int, session_id: str, role: str, content: str
    ) -> None:
        """Add a message to conversation history."""
        key = self._key(user_id, session_id)
        message = json.dumps({"role": role, "content": content})

        self.redis.rpush(key, message)
        self.redis.expire(key, timedelta(hours=self.ttl_hours))
        self.redis.ltrim(key, -self.max_history * 2, -1)

    def clear(self, user_id: int, session_id: str) -> None:
        """Clear conversation history."""
        self.redis.delete(self._key(user_id, session_id))

