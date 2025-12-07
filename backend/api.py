from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
import os
import stockfish
import chess
import google.generativeai as genai

GEMINI_API_KEY = os.getenv("GOOGLE_API_KEY")
if not GEMINI_API_KEY:
    raise RuntimeError("Set GOOGLE_API_KEY")

STOCKFISH_PATH = "/usr/games/stockfish"

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

def fen_to_ascii_simple(fen: str) -> str:
    piece_map = {
        'r':'r','n':'n','b':'b','q':'q','k':'k','p':'p',
        'R':'R','N':'N','B':'B','Q':'Q','K':'K','P':'P'
    }
    parts = fen.split()
    if len(parts) < 1:
        raise ValueError("Invalid FEN")
    rows = parts[0].split('/')
    out = ["  a b c d e f g h"]
    for rank_idx, row in enumerate(rows):
        line = []
        i = 0
        while i < len(row):
            c = row[i]
            if c.isdigit():
                line.extend(["."] * int(c))
            else:
                line.append(piece_map.get(c, c))
            i += 1
        out.append(f"{8 - rank_idx} " + " ".join(line))
    return "\n".join(out)

def uci_pv_to_san(pv_uci: str, fen: str) -> str:
    board = chess.Board(fen)
    san_moves = []
    for uci in pv_uci.split():
        move = chess.Move.from_uci(uci)
        san_moves.append(board.san(move))
        board.push(move)
    return " ".join(san_moves)

def uci_to_san_first(uci: str, fen: str) -> str:
    board = chess.Board(fen)
    move = chess.Move.from_uci(uci)
    return board.san(move)

@app.post("/analyze", response_model=AnalyzeResponse)
def analyze(req: AnalyzeRequest):
    if len(req.fen.split()) != 6:
        raise HTTPException(status_code=400, detail="Invalid FEN format")

    sf = stockfish.Stockfish(path=STOCKFISH_PATH)
    sf.set_depth(req.depth)
    sf.update_engine_parameters({"MultiPV": req.multipv})
    sf.set_fen_position(req.fen)

    top_moves = sf.get_top_moves(req.multipv)

    stockfish_lines = "\n".join(
        f"{uci_to_san_first(m['Move'], req.fen)} ({m.get('Centipawn','?')}cp)"
        for m in top_moves
    )

    prompt = f"""
FEN Position: {req.fen}

Stockfish depth={req.depth}, MultiPV={req.multipv} top {req.multipv} lines (verbatim):
{stockfish_lines}

Explain the best moves from this position. Reference these exact Stockfish lines.
What strategic ideas are behind each line? Who has advantage and why?
"""
    res = model.generate_content(prompt)

    return AnalyzeResponse(
        fen=req.fen,
        depth=req.depth,
        multipv=req.multipv,
        board=fen_to_ascii_simple(req.fen),
        stockfish_lines=stockfish_lines,
        gemini=res.text,
    )



