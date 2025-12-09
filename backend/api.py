import os

import google.generativeai as genai
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from stockfish_service import analyze_position

# New Imports from your modularized files
from utils import fen_to_ascii_simple

# --- CONFIGURATION (No changes here) ---
GEMINI_API_KEY = os.getenv("GOOGLE_API_KEY")
if not GEMINI_API_KEY:
    raise RuntimeError("Set GOOGLE_API_KEY")

genai.configure(api_key=GEMINI_API_KEY)
model = genai.GenerativeModel("gemini-3-pro-preview")

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8080"],  # adjust when on VPS
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# --- Pydantic Models (No changes here) ---
class AnalyzeRequest(BaseModel):
    fen: str
    depth: int = 18
    multipv: int = 3


class AnalyzeResponse(BaseModel):
    fen: str
    depth: int
    multipv: int
    board: str
    stockfish_lines: str
    gemini: str


# --- API Endpoint (Now much simpler) ---
@app.post("/analyze", response_model=AnalyzeResponse)
def analyze(req: AnalyzeRequest):
    if len(req.fen.split()) != 6:
        raise HTTPException(status_code=400, detail="Invalid FEN format")

    # 1. Stockfish Analysis
    try:
        stockfish_lines = analyze_position(req.fen, req.depth, req.multipv)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    # 2. Gemini Analysis
    prompt = f"""FEN: {req.fen}
Stockfish depth={req.depth} MultiPV={req.multipv}:
{stockfish_lines}

Analyze these exact Stockfish lines and explain the plans for both sides in detail:"""

    res = model.generate_content(prompt)

    return AnalyzeResponse(
        fen=req.fen,
        depth=req.depth,
        multipv=req.multipv,
        board=fen_to_ascii_simple(req.fen),
        stockfish_lines=stockfish_lines,
        gemini=res.text,
    )


# Note: The `stockfish` import is no longer needed in api.py
# The `re` and `subprocess` imports are no longer needed in api.py
