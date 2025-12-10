import os
import google.generativeai as genai
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from stockfish_service import analyze_position
from utils import fen_to_ascii_simple

# --- CONFIGURATION ---
GEMINI_API_KEY = os.getenv("GOOGLE_API_KEY")

# 🛑 DEBUGGING STEP: Print status to logs 🛑
if not GEMINI_API_KEY:
    print("FATAL ERROR: GOOGLE_API_KEY is not set in the environment.")
else:
    # Print a truncated key to confirm it loaded
    print(f"INFO: GOOGLE_API_KEY loaded successfully. Starts with: {GEMINI_API_KEY[:4]}...")

# Configure the Gemini client ONCE at startup
try:
    if GEMINI_API_KEY:
        genai.configure(api_key=GEMINI_API_KEY)
except Exception as e:
    # This catches initialization errors
    print(f"ERROR: Failed to configure Gemini client: {e}")

# The model is initialized (using a stable model)
try:
    model = genai.GenerativeModel("gemini-2.5-flash") 
except Exception as e:
    print(f"ERROR: Failed to initialize model: {e}")


app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8080", "http://sgchess.chriscortese.net:8080"],  # Added your VPS domain
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


# --- API Endpoint ---
@app.post("/analyze", response_model=AnalyzeResponse)
def analyze(req: AnalyzeRequest):
    if len(req.fen.split()) != 6:
        raise HTTPException(status_code=400, detail="Invalid FEN format")

    # 1. Stockfish Analysis
    try:
        stockfish_lines = analyze_position(req.fen, req.depth, req.multipv)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    # 2. Gemini Analysis (Single, safe call)
    gemini_output = "Gemini API call failed (Key not set or API is unreachable)."
    
    if GEMINI_API_KEY:
        try:
            prompt = f"""
            Analyze the following chess FEN position in a concise, insightful, and professional tone.
            FEN: {req.fen}
            Stockfish Analysis Lines (Depth {req.depth}, MultiPV {req.multipv}):
            {stockfish_lines}
            
            Based on the FEN and the Stockfish lines, explain the key strategic themes, the best move for the player to move, and why the top lines are strong.
            """
            res = model.generate_content(prompt)
            gemini_output = res.text
        except Exception as e:
            # Catch API errors specifically
            gemini_output = f"Gemini API Error: {e.__class__.__name__}: {e}"
            print(f"Gemini API Exception caught: {e}")
    
    return AnalyzeResponse(
        fen=req.fen,
        depth=req.depth,
        multipv=req.multipv,
        board=fen_to_ascii_simple(req.fen),
        stockfish_lines=stockfish_lines,
        gemini=gemini_output, 
    )

# ADD THESE EXACT LINES AT THE BOTTOM (after AnalyzeResponse return)

class PgnRequest(BaseModel):
    pgn: str
    depth: int = 15

@app.post("/analyze-pgn")
def analyze_pgn(req: PgnRequest):
    from stockfish_service import analyze_pgn_sweep  # Import here if needed
    try:
        critical_moments = analyze_pgn_sweep(req.pgn, req.depth)
        return {
            "game_summary": f"Found {len(critical_moments)} critical moments",
            "critical_moments": critical_moments
        }
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))








