# backend/Dockerfile

FROM python:3.13-slim

# System deps: stockfish, libopencv-dev (for board-to-fen), and build tools
RUN apt-get update && apt-get install -y \
    stockfish \
    libopencv-dev \
    gcc \
    # Clean up APT caches
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Check this line carefully! Ensure 'board-to-fen' is present and correctly spelled.
RUN pip install --no-cache-dir \
    fastapi \
    uvicorn \
    stockfish \
    google-generativeai \
    python-chess \
    board-to-fen

# Copy your API code
COPY api.py utils.py stockfish_service.py ./

EXPOSE 8000

# The CMD is correct for running the backend
CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8000"]
