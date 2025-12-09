# ♟️ Web Stockfish + Gemini Chess Analyzer

This project is a full-stack, monorepo application that provides deep chess position analysis by combining the strength of the Stockfish engine with the human-like explanation capabilities of the Gemini model.

Paste a FEN, see it on a Lichess board, get Stockfish's top lines, and receive a natural-language explanation from Gemini.

---

## ✨ Features and Improvements (New)

* **Reliable Lichess Board:** Fixed the persistent 404 error by using the correct `/embed/analysis` Lichess endpoint and underscore FEN encoding.
* **Accurate Evaluation:** **CRITICAL FIX**: Stockfish centipawn scores are now correctly **inverted** when it is Black's turn to move, providing the evaluation from Black's perspective (e.g., `-0.85` instead of `+0.85`).
* **Live FEN to Lichess board** (instant visual update).
* “Analyzing…” text shown while the backend works.
* **Stockfish depth 1–40** and **MultiPV 1–10** configurable via the UI.
* **Gemini 3** explains engine lines in human chess language.

---

## 💻 Project Structure

The project is structured as a monorepo for easy deployment via Docker Compose.

* `frontend/index.php`: The main UI. Serves the Lichess iframe and contains JavaScript logic to call the backend API.
* `backend/`: FastAPI service containing the core logic.
    * `api.py`: FastAPI application entry point.
    * `stockfish_service.py`: Handles Stockfish subprocess execution, FEN parsing, SAN formatting, and **score inversion**.
    * `utils.py`: Gemini call function and prompt handling.
* `docker-compose.yml`: Defines the full Caddy/PHP/FastAPI stack.
* `Caddyfile`: Caddy reverse proxy configuration.
* `Dockerfile`: Defines the Python environment for the backend service.
* `.gitignore`: Properly configured to ignore Python cache files (`__pycache__`).

---

## 🚀 Quick Start (Docker Compose)

The application is designed to run entirely within Docker.

1.  **Run:** From the repository root, execute the build and run command:
    ```bash
    docker compose up --build
    ```
2.  **Open:** Open your browser and navigate to:
    ```
    http://localhost:8080
    ```

### Optional Helper: Docker Nuke

For rapid development and cleaning up previous runs, the following helper command is recommended:

| Function Name | Commands (in order) | Description |
| :--- | :--- | :--- |
| `docker_nuke` | `docker compose down -v --remove-orphans` | Stops containers and removes volumes/networks. |
| | `docker system prune -af` | Removes unused images, containers, networks, and build cache. |
| | `docker compose up --build` | Rebuilds the images and restarts the stack cleanly. |

---

## 📝 Usage and Workflow

1.  **Paste** a FEN into the textbox (defaults to starting position).
2.  The Lichess board on the right **updates immediately** to that position.
3.  Set **Depth** (default 18, recommended 12-20) and **MultiPV** (default 3).
4.  Click **Analyze**.
5.  While the backend is working, an **“Analyzing…”** message is visible.
6.  When it finishes, you see:
    * Stockfish’s top lines with accurate evaluation (inverted for Black).
    * Gemini’s natural-language explanation.

**Example FEN (Black to Move, King's Gambit):** `rnbqkbnr/pppp1ppp/8/4p3/4PP2/8/PPPP2PP/RNBQKBNR b KQkq - 0 2`
*(Score should be negative, e.g., **(-0.64)**)*

---

## ⚙️ Docker Stack Breakdown

* **Caddy** as a reverse proxy/web server.
* **PHP/FrankenPHP** serving the `frontend/index.php` on port 8080.
* **Backend FastAPI service** providing the `/api/analyze` endpoint, which coordinates Stockfish and Gemini.

### Notes

* **Depth 18** is the default: Strong enough for middlegame instruction.
* **MultiPV 3** provides the best move plus two alternatives for Gemini to discuss, giving rich context.
