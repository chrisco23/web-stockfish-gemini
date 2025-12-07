from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
import os
import subprocess
import re
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

def format_eval(m):
    cp = m.get("Centipawn")
    if cp is None: return "?"
    if abs(cp) >= 1000: return f"{'+' if cp > 0 else ''}{cp//1000}M{abs(cp)%1000//100}"
    return f"({'+' if cp > 0 else '-'}{abs(cp)/100:.2f})" if cp != 0 else "= (0.00)"

@app.post("/analyze", response_model=AnalyzeResponse)
def analyze(req: AnalyzeRequest):
    if len(req.fen.split()) != 6:
        raise HTTPException(status_code=400, detail="Invalid FEN format")

    proc = subprocess.Popen(
        [STOCKFISH_PATH],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        text=True, bufsize=1, universal_newlines=True
    )
    
    proc.stdin.write("uci\n"); proc.stdin.flush(); proc.stdout.readline()
    proc.stdin.write(f"position fen {req.fen}\n"); proc.stdin.flush(); proc.stdout.readline()
    proc.stdin.write(f"go depth {req.depth} multipv {req.multipv}\n"); proc.stdin.flush()
    
    pv_lines = {}
    while True:
        line = proc.stdout.readline()
        if not line or "bestmove" in line: 
            break
        if "info depth" in line and "pv " in line and "multipv " in line:
            multipv_match = re.search(r'multipv (\d+)', line)
            pv_match = re.search(r'pv ([\w\d]{4}(?:\s[\w\d]{4})*)', line)
            score_match = re.search(r'score cp ([-+]?\d+)', line)
            
            if multipv_match and pv_match and score_match:
                num = int(multipv_match.group(1))
                pv_uci = pv_match.group(1).strip()
                cp = int(score_match.group(1))
                score_str = f"({'+' if cp > 0 else '-'}{abs(cp)/100:.2f})"
                san_pv = uci_pv_to_san(pv_uci, req.fen)
                pv_lines[num] = f"{san_pv} {score_str}"
    
    proc.stdin.write("quit\n"); proc.stdin.flush(); proc.wait()
    
    stockfish_lines = "\n".join([pv_lines.get(i, "") for i in range(1, req.multipv + 1)])

    prompt = f"""FEN: {req.fen}
Stockfish depth={req.depth} FULL PVs:
{stockfish_lines}
Analyze EXACT Stockfish variations."""

    res = model.generate_content(prompt)

    return AnalyzeResponse(
        fen=req.fen, depth=req.depth, multipv=req.multipv,
        board=fen_to_ascii_simple(req.fen),
        stockfish_lines=stockfish_lines, gemini=res.text
    )


