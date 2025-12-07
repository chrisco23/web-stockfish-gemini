FROM python:3.13-slim

# System deps: stockfish, curl (optional), build tools if needed
RUN apt-get update && apt-get install -y \
    stockfish \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy requirements (you can also just pip install inline)
RUN pip install --no-cache-dir fastapi uvicorn stockfish google-generativeai chess

# Copy your API code
COPY api.py .

EXPOSE 8000

CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8000"]


