<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chess Position Analyzer</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="styles.css"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="container">
    <h1>♟️ Chess Position Analyzer</h1>

    <hr>

    <div class="input-group">
        <p>Enter a FEN position, then click "Analyze" button.</p>
        <label for="fen-input">FEN</label>
        <input type="text" id="fen-input" value="rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1">
    </div>

    <div class="input-group">
        <label for="depth-input">Stockfish Depth</label>
        <input type="number" id="depth-input" value="18" min="1" max="40">
    </div>

    <div class="input-group">
        <label for="multipv-input">MultiPV (Top Lines)</label>
        <input type="number" id="multipv-input" value="3" min="1" max="10">
    </div>

    <button id="analyze-button">Analyze</button>
    <p id="loading">Analyzing position, please wait...</p>

    <hr>

    <div id="lichess-board-container" class="output-section" style="padding: 0;">
        <iframe id="lichess-board"
            src="https://lichess.org/embed/analysis?fen=rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR_w_KQkq_-_0_1&color=white&theme=brown"
            frameborder="0" allowfullscreen>
        </iframe>
    </div>

    <div id="results" style="display: none;">
        <h2>Engine Analysis</h2>

        <div class="output-section">
            <h3>Stockfish (Depth: <span id="display-depth"></span>, MultiPV: <span id="display-multipv"></span>)</h3>
            <div id="stockfish-output"></div>
        </div>

        <div class="output-section">
            <h3>Gemini Explanation</h3>
            <div id="gemini-output"></div>
        </div>
    </div>
</div>

<script>
// --- JAVASCRIPT LOGIC ---
$(document).ready(function() {
    // NOTE: This assumes your FastAPI app runs on port 8000. Adjust if needed.
    const API_URL_ANALYZE = "/api/analyze";

    const $fenInput = $('#fen-input');
    const $depthInput = $('#depth-input');
    const $multipvInput = $('#multipv-input');
    const $analyzeButton = $('#analyze-button');
    const $loading = $('#loading');
    const $results = $('#results');
    const $lichessBoard = $('#lichess-board');
    const $lichessBoardContainer = $('#lichess-board-container');

    // =======================================================
    // 1. FEN ANALYSIS LOGIC (Original code preserved)
    // =======================================================
    $analyzeButton.click(function() {
        const fen = $fenInput.val();
        const depth = parseInt($depthInput.val());
        const multipv = parseInt($multipvInput.val());

        if (!fen || isNaN(depth) || isNaN(multipv)) {
            alert("Please enter valid FEN, Depth, and MultiPV values.");
            return;
        }

        // Instant Board Drawing
        const lichessUrl = `https://lichess.org/embed/analysis?fen=${fen.replace(/ /g, '_')}&color=white&theme=brown`;
        $lichessBoard.attr('src', lichessUrl);
        $lichessBoardContainer.show();

        // Show loading state and hide previous results
        $loading.show();
        $results.hide();
        $analyzeButton.prop('disabled', true);

        $.ajax({
            url: API_URL_ANALYZE,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                fen: fen,
                depth: depth,
                multipv: multipv
            }),
            success: function(data) {
                $('#display-depth').text(data.depth);
                $('#display-multipv').text(data.multipv);

                const formattedStockfishLines = data.stockfish_lines.replace(/\n/g, '<br>');
                $('#stockfish-output').html(formattedStockfishLines);
                $('#gemini-output').text(data.gemini);

                $results.show();
            },
            error: function(xhr, status, error) {
                const errorDetail = xhr.responseJSON ? xhr.responseJSON.detail : error;
                alert('Analysis failed: ' + errorDetail);
                console.error("Error details:", xhr);
            },
            complete: function() {
                $loading.hide();
                $analyzeButton.prop('disabled', false);
            }
        });
    });
});
</script>

</body>
</html>
