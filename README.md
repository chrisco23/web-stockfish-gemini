# ♟️ Web Stockfish + Gemini Chess Analyzer

This project is a full-stack, monorepo application that provides deep chess position analysis by combining the strength of the Stockfish engine with the human-like explanation capabilities of the Gemini model.

Paste a FEN, see it on a Lichess board, get Stockfish's top lines, and receive a natural-language explanation from Gemini.

---
## ✨ Features and Improvements (New)

* **Full Game PGN Analyzer (Fast Sweep):** Paste a complete PGN and scan the game for blunders and mistakes based on evaluation-drop thresholds.
* **Critical Moments List:** For each flagged move, shows:
  * Issue type (**Blunder** / **Mistake**) and eval loss in pawns.
  * Stockfish best move and its eval.
  * A **Study Position** button that jumps into deep FEN analysis for that position.
* **Reliable Lichess Board:** Uses the `/embed/analysis` Lichess endpoint with underscore-encoded FEN to avoid 404s.
* **Accurate Evaluation:** Stockfish centipawn scores are correctly handled so evaluations are from the side-to-move’s perspective.
* **Live FEN to Lichess board** (instant visual update).
* “Analyzing…” text shown while the backend works.
* **Stockfish depth 1–40** and **MultiPV 1–10** configurable via the UI.
* **Gemini 3** explains engine lines in human chess language.

---
## 💻 Project Structure

The project is structured as a monorepo for easy deployment via Docker Compose.

* `frontend/index.php`: Main HTML/PHP UI. Hosts the Lichess iframe and form layout.
* `frontend/app.js`: All frontend JavaScript for:
  * FEN analysis (`/api/analyze`)
  * Full game PGN sweep (`/api/analyze-pgn`)
  * “Study Position” buttons wiring FEN → deep analysis.
* `backend/`: FastAPI service containing the core logic.
  * `api.py`: FastAPI application entry point; exposes `/analyze` and `/analyze-pgn`.
  * `stockfish_service.py`: Handles Stockfish subprocess execution, FEN/PGN analysis, SAN formatting, and eval handling.
  * `utils.py`: FEN ASCII rendering and UCI→SAN helpers.
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

### Full Game PGN Analyzer (Fast Sweep)

1. **Paste** a full game PGN into the top textarea.
2. Set **Sweep Depth** (default 15, recommended 12–15).
3. Click **Analyze Game Sweep**.
4. The app scans the game and lists critical moments:
   * Each entry shows issue type, eval loss, Stockfish best move + eval.
   * Click **Study Position** to load that position into the FEN analyzer at higher depth (e.g., 25).

### Single-Position FEN Analyzer

1. **Paste** a FEN into the textbox (defaults to the starting position).
2. The Lichess board on the right **updates immediately** to that position.
3. Set **Depth** (default 18, recommended 12–20) and **MultiPV** (default 3).
4. Click **Analyze**.
5. While the backend is working, an **“Analyzing…”** message is visible.
6. When it finishes, you see:
   * Stockfish’s top lines with accurate evaluation.
   * Gemini’s natural-language explanation.

---

## ⚙️ Docker Stack Breakdown

* **Caddy** as a reverse proxy/web server.
* **PHP/FrankenPHP** serving the `frontend/index.php` on port 8080.
* **Backend FastAPI service** providing the `/api/analyze` endpoint, which coordinates Stockfish and Gemini.

### Notes

* **Depth 18** is the default: Strong enough for middlegame instruction.
* **MultiPV 3** provides the best move plus two alternatives for Gemini to discuss, giving rich context.
