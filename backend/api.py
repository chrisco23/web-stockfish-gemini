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

    parts = req.fen.split()
    # The half-move number from FEN (parts[5]) is the number *of the next full move*.
    # chess.Board is a more reliable way to get the turn and move number.
    try:
        board = chess.Board(req.fen)
        # Determine the move number for the *first* move in the line.
        # If it's White's turn, it's the current full move number.
        # If it's Black's turn, the *next* move will be the next full move number, so we need to
        # prefix with ...
        current_move_number = board.fullmove_number
        is_white_turn = board.turn == chess.WHITE
    except ValueError:
        raise HTTPException(status_code=400, detail="Invalid FEN board state")

    proc = subprocess.Popen(
        [STOCKFISH_PATH],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        text=True, bufsize=1, universal_newlines=True
    )
    
    print("=== STOCKFISH DEBUG ===")
    print(f"FEN: {req.fen}")
    print(f"Depth: {req.depth}, MultiPV: {req.multipv}")
    
    # FIXED: Set MultiPV FIRST
    proc.stdin.write("uci\n")
    proc.stdin.flush()
    proc.stdout.readline()
    
    proc.stdin.write(f"setoption name MultiPV value {req.multipv}\n")
    proc.stdin.flush()
    proc.stdout.readline()
    
    proc.stdin.write(f"position fen {req.fen}\n")
    proc.stdin.flush()
    proc.stdout.readline()
    
    proc.stdin.write(f"go depth {req.depth}\n")
    proc.stdin.flush()
    
    pv_lines = {}
    all_lines = []
    
    while True:
        line = proc.stdout.readline()
        if not line:
            break
        all_lines.append(line.strip())
        if "bestmove" in line:
            break
        if "info depth" in line and "pv " in line:
            multipv_match = re.search(r'multipv (\d+)', line)
            pv_match = re.search(r'pv ([\w\d]{4}(?:\s[\w\d]{4})*)', line)
            score_match = re.search(r'score cp ([-+]?\d+)', line)
            
            if multipv_match and pv_match and score_match:
                num = int(multipv_match.group(1))
                pv_uci = pv_match.group(1).strip()
                cp = int(score_match.group(1))
                
                # --- START OF MODIFICATION ---
                # Format the full PV string with move numbers
                board_copy = chess.Board(req.fen)
                san_moves = []
                
                # Prepend move number and optional '...' for Black's turn
                if board_copy.turn == chess.BLACK:
                    san_moves.append(f"{board_copy.fullmove_number}...")
                elif board_copy.turn == chess.WHITE:
                    san_moves.append(f"{board_copy.fullmove_number}.")
                    
                # Convert moves to SAN and include move numbers as required
                for uci in pv_uci.split():
                    move = chess.Move.from_uci(uci)
                    san_moves.append(board_copy.san(move))
                    board_copy.push(move)
                    
                    # If it's now White's turn, add the *next* move number.
                    if board_copy.turn == chess.WHITE:
                        san_moves.append(f"{board_copy.fullmove_number}.")
                
                san_pv = " ".join(san_moves).strip()
                # Clean up any trailing move numbers if the line ends mid-move
                # Example: "1. e4 e5 2." -> "1. e4 e5"
                san_pv = re.sub(r'\s\d+\.$|\s\d+\.\.\.$', '', san_pv).strip()

                # --- END OF MODIFICATION ---
                
                if cp > 0:
                    score_str = f"(+{cp/100:.2f})"
                elif cp < 0:
                    score_str = f"({cp/100:.2f})"
                else:
                    score_str = "(0.00)"
                    
                # The final line includes the score and the SAN moves with move numbers
                pv_lines[num] = f"{score_str} {san_pv}"
    
    print("ALL STOCKFISH LINES:")
    for line in all_lines:
        print(f"  {line}")
    print(f"PARSED PV LINES: {len(pv_lines)}")
    print("PARSED:", pv_lines)
    print("=====================")
    
    proc.stdin.write("quit\n")
    proc.stdin.flush()
    proc.wait()
    
    # Just join the lines without a generic "Move X:" prefix since the numbers are now in the lines.
    stockfish_lines = "\n".join([pv_lines.get(i, "") for i in range(1, req.multipv + 1) if pv_lines.get(i, "")])

    print(f"FINAL STOCKFISH TO GEMINI: {stockfish_lines}")

    prompt = f"""FEN: {req.fen}
Stockfish depth={req.depth} MultiPV={req.multipv}:
{stockfish_lines}

Analyze these exact Stockfish lines:"""

    res = model.generate_content(prompt)

    return AnalyzeResponse(
        fen=req.fen,
        depth=req.depth,
        multipv=req.multipv,
        board=fen_to_ascii_simple(req.fen),
        stockfish_lines=stockfish_lines,
        gemini=res.text
    )






