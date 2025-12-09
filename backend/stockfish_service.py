import re
import subprocess

import chess

STOCKFISH_PATH = "/usr/games/stockfish"


# This function is the core of your old analyze route's Stockfish logic
def analyze_position(fen: str, depth: int, multipv: int) -> str:
    """Runs Stockfish analysis and returns a formatted string of PV lines."""

    # 1. Validate FEN and get initial board state
    try:
        board = chess.Board(fen)
    except ValueError:
        raise ValueError("Invalid FEN board state")

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
