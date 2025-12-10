import re
import subprocess

import chess
import chess.pgn
from utils import uci_to_san_first

# Ensure this path is correct for your container environment
STOCKFISH_PATH = "/usr/games/stockfish"


def get_stockfish_top_eval(fen: str, depth: int) -> tuple[int, str]:
    """Runs Stockfish and returns the top centipawn score and best move (UCI) for a single position.
    
    Returns: (centipawns_score_from_white_perspective, best_move_uci)
    """
    
    proc = subprocess.Popen(
        [STOCKFISH_PATH],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        bufsize=1,
        universal_newlines=True,
    )

    proc.stdin.write(f"position fen {fen}\n")
    proc.stdin.write(f"go depth {depth}\n")
    proc.stdin.flush()
    
    cp = 0
    best_uci = ""
    
    while True:
        line = proc.stdout.readline()
        if not line:
            break
        
        # Capture best move only if 'bestmove' is found (to stop the search)
        if "bestmove" in line:
            # Format: 'bestmove e2e4 ponder e7e5'
            parts = line.split(" ")
            if len(parts) > 1:
                best_uci = parts[1]
            break

        # Find the latest, deepest score and PV move
        score_match = re.search(r"score cp ([-+]?\d+)", line)
        pv_match = re.search(r"pv ([\w\d]{4})", line) # Only need the first move
        
        if score_match:
            cp = int(score_match.group(1))
        if pv_match:
            # We want the *first* move in the pv line, which is the best move
            best_uci = pv_match.group(1)

    proc.stdin.write("quit\n")
    proc.stdin.flush()
    proc.wait()
    
    return cp, best_uci


def analyze_pgn_sweep(pgn: str, depth: int) -> list:
    """
    Analyzes a PGN game to identify critical moments (Blunders/Mistakes) via eval drop.
    Returns a list of dicts.
    """
    
    # Define the thresholds for flagging a move (in pawns, 100 cp = 1.00 pawn)
    BLUNDER_THRESHOLD = 1.50
    MISTAKE_THRESHOLD = 0.75

    # 1. Load PGN and get the board object
    try:
        # Use io.StringIO to treat the PGN string like a file for the parser
        import io
        pgn_io = io.StringIO(pgn)
        game = chess.pgn.read_game(pgn_io)
    except Exception:
        raise ValueError("Invalid PGN format or unable to read game.")

    if not game:
        raise ValueError("No complete game found in PGN.")
    
    board = game.board()
    critical_moments = []
    
    # 2. Iterate through all moves in the game
    for move in game.mainline_moves():
        
        # --- A. ANALYSIS BEFORE THE MOVE (Player's Decision Point) ---
        current_fen = board.fen()
        
        # 1. Get Stockfish's BEST evaluation for the current position (White's perspective)
        best_cp_white, best_uci = get_stockfish_top_eval(current_fen, depth)
        
        # 2. Convert best eval to the perspective of the player *to move*
        is_player_black = board.turn == chess.BLACK
        player_best_cp = best_cp_white * (-1 if is_player_black else 1)
        
        # 3. Apply the player's actual move
        san_move = board.san(move) # Capture SAN before pushing
        board.push(move)
        
        # --- B. ANALYSIS AFTER THE MOVE (Resulting Position) ---
        # 4. Get Stockfish's evaluation for the resulting position (White's perspective)
        resulting_cp_white, _ = get_stockfish_top_eval(board.fen(), depth)
        
        # 5. Convert the resulting eval to the perspective of the *player who just moved*
        # The resulting position is the opponent's turn. We need the score from the moving player's perspective.
        # If the moving player was White, the resulting score should be positive for them (White's score).
        # If the moving player was Black, the resulting score should be negative for them (-White's score).
        player_actual_cp = resulting_cp_white * (-1 if is_player_black else 1)

        # 6. Calculate the loss in evaluation (Delta E)
        # Delta E = (What the player *could* have had) - (What the player *actually* got)
        delta_eval = (player_best_cp - player_actual_cp) / 100.0
        
        
        move_type = None
        if delta_eval >= BLUNDER_THRESHOLD:
            move_type = "Blunder"
        elif delta_eval >= MISTAKE_THRESHOLD:
            move_type = "Mistake"
        
        if move_type:
            critical_moments.append({
                'move': san_move, # Move that was just played
                'fen_before': current_fen, # Position where the mistake occurred
                'type': move_type,
                'delta_eval': round(delta_eval, 2),
                'best_move': uci_to_san_first(best_uci, current_fen), # Sanitize best move
                'best_eval': round(player_best_cp / 100.0, 2)
            })
            
    return critical_moments

# --- The existing analyze_position function remains BELOW this line ---

def analyze_position(fen: str, depth: int, multipv: int) -> str:
    # ... (Your existing, unchanged analyze_position function content) ...
    """Runs Stockfish analysis and returns a formatted string of PV lines."""

    # 1. Validate FEN and get initial board state
    try:
        board = chess.Board(fen)
    except ValueError:
        raise ValueError("Invalid FEN board state")

    # Determine the side to move for the score inversion logic later
    is_black_to_move = board.turn == chess.BLACK

    # 2. Run Stockfish subprocess and get analysis
    proc = subprocess.Popen(
        [STOCKFISH_PATH],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        bufsize=1,
        universal_newlines=True,
    )

    # Send commands
    proc.stdin.write("uci\n")
    proc.stdin.flush()
    proc.stdout.readline()
    proc.stdin.write(f"setoption name MultiPV value {multipv}\n")
    proc.stdin.flush()
    proc.stdout.readline()
    proc.stdin.write(f"position fen {fen}\n")
    proc.stdin.flush()
    proc.stdout.readline()
    proc.stdin.write(f"go depth {depth}\n")
    proc.stdin.flush()

    pv_lines = {}

    # 3. Parse output
    while True:
        line = proc.stdout.readline()
        if not line:
            break
        if "bestmove" in line:
            break

        if "info depth" in line and "pv " in line:
            multipv_match = re.search(r"multipv (\d+)", line)
            pv_match = re.search(r"pv ([\w\d]{4}(?:\s[\w\d]{4})*)", line)
            score_match = re.search(r"score cp ([-+]?\d+)", line)

            if multipv_match and pv_match and score_match:
                num = int(multipv_match.group(1))
                pv_uci = pv_match.group(1).strip()
                cp = int(score_match.group(1))

                # --- CRITICAL FIX: INVERT SCORE IF BLACK IS TO MOVE ---
                if is_black_to_move:
                    cp *= -1
                # -----------------------------------------------------

                # Format the full PV string with move numbers (Your existing logic)
                board_copy = chess.Board(fen)
                san_moves = []

                # Prepend move number and optional '...'
                if board_copy.turn == chess.BLACK:
                    san_moves.append(f"{board_copy.fullmove_number}...")
                elif board_copy.turn == chess.WHITE:
                    san_moves.append(f"{board_copy.fullmove_number}.")

                for uci in pv_uci.split():
                    try:
                        move = chess.Move.from_uci(uci)
                        san_moves.append(board_copy.san(move))
                        board_copy.push(move)

                        if board_copy.turn == chess.WHITE:
                            san_moves.append(f"{board_copy.fullmove_number}.")
                    except Exception as e:
                        # Handle potential bad move from Stockfish line end
                        print(f"Error converting UCI {uci}: {e}")
                        break  # Stop converting this line if a bad move is found

                san_pv = " ".join(san_moves).strip()
                san_pv = re.sub(
                    r"\s\d+\.$|\s\d+\.\.\.$", "", san_pv
                ).strip()  # Cleanup trailing number

                # Format the score string based on the (now potentially inverted) cp value
                if cp > 0:
                    score_str = f"(+{cp / 100:.2f})"
                elif cp < 0:
                    score_str = f"({cp / 100:.2f})"
                else:
                    score_str = "(0.00)"

                pv_lines[num] = f"{score_str} {san_pv}"

    proc.stdin.write("quit\n")
    proc.stdin.flush()
    proc.wait()

    # 4. Format the final output string
    stockfish_lines = "\n".join(
        [pv_lines.get(i, "") for i in range(1, multipv + 1) if pv_lines.get(i, "")]
    )
    return stockfish_lines

