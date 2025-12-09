import chess

def fen_to_ascii_simple(fen: str) -> str:
    # ... [Keep your original fen_to_ascii_simple function content] ...
    piece_map = {
        'r':'r','n':'n','b':'b','q':'q','k':'k','p':'p',
        'R':'R','N':'B','B':'B','Q':'Q','K':'K','P':'P'
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
    # ... [Keep your original uci_pv_to_san function content] ...
    board = chess.Board(fen)
    san_moves = []
    for uci in pv_uci.split():
        move = chess.Move.from_uci(uci)
        san_moves.append(board.san(move))
        board.push(move)
    return " ".join(san_moves)

def uci_to_san_first(uci: str, fen: str) -> str:
    # ... [Keep your original uci_to_san_first function content] ...
    board = chess.Board(fen)
    move = chess.Move.from_uci(uci)
    return board.san(move)

def format_eval(m):
    # ... [Keep your original format_eval function content] ...
    cp = m.get("Centipawn")
    if cp is None: return "?"
    if abs(cp) >= 1000: return f"{'+' if cp > 0 else ''}{cp//1000}M{abs(cp)%1000//100}"
    return f"({'+' if cp > 0 else '-'}{abs(cp)/100:.2f})" if cp != 0 else "= (0.00)"
