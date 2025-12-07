<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Stockfish + Gemini Chess Analyzer</title>
  <style>
    body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; }
    textarea { width: 100%; height: 4em; }
    pre { background: #111; color: #0f0; padding: 1rem; overflow-x: auto; }
  </style>
</head>
<body>
  <h1>Stockfish + Gemini Chess Analyzer</h1>

  <form id="analyze-form">
    <label>FEN:<br>
      <textarea name="fen" id="fen"></textarea>
    </label>
    <br><br>
    <label>Depth: <input type="number" id="depth" value="18"></label>
    <label>MultiPV: <input type="number" id="multipv" value="3"></label>
    <br><br>
    <button type="submit">Analyze</button>
  </form>

  <h2>Board</h2>
  <pre id="board"></pre>

  <h2>Stockfish Lines</h2>
  <pre id="sf"></pre>

  <h2>Gemini Analysis</h2>
  <pre id="gemini"></pre>

  <script>
  document.getElementById('analyze-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fen = document.getElementById('fen').value.trim();
    const depth = parseInt(document.getElementById('depth').value, 10);
    const multipv = parseInt(document.getElementById('multipv').value, 10);

    const res = await fetch('/api/analyze', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fen, depth, multipv })
    });

    if (!res.ok) {
      const txt = await res.text();
      document.getElementById('board').textContent = 'Error: ' + res.status + '\n' + txt;
      return;
    }

    const data = await res.json();
    document.getElementById('board').textContent = data.board;
    document.getElementById('sf').textContent = data.stockfish_lines;
    document.getElementById('gemini').textContent = data.gemini;
  });
  </script>
</body>
</html>

