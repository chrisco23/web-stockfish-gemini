<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chess Position Analyzer</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="styles.css"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* General body padding and alignment */
        body {
            padding-top: 20px;
            text-align: center;
        }
        /* Main content wrapper, 900px wide and centered, with a box look */
        .main-wrapper {
            max-width: 900px;
            margin: 0 auto;
            text-align: left;
            padding: 20px 20px 40px 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f8f8f8;
        }
        /* Center the board and controls block (450px wide) */
        .board-and-controls {
            max-width: 450px;
            margin: 0 auto;
            padding-bottom: 20px;
        }
        /* Ensures the H2 is centered */
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <h1>♟️ Chess Position Analyzer</h1>
    
    <div class="board-and-controls">
        
        <div class="input-group">
            <p>Enter a FEN position, then click "Analyze" button.</p>
            <label for="fen-input">FEN</label>
            <input type="text" id="fen-input" autocomplete="off" style="width: 100%;" placeholder="e.g., rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1">
        </div>

        <div id="analysis-controls" style="margin-bottom: 15px;">
            <div style="display: inline-block; margin-right: 20px;">
                <label for="depth-input" style="font-weight: bold;">Depth:</label>
                <input type="number" id="depth-input" value="18" min="1" max="40" style="width: 70px;">
            </div>
            <div style="display: inline-block; margin-right: 20px;">
                <label for="multipv-input" style="font-weight: bold;">MultiPV:</label>
                <input type="number" id="multipv-input" value="3" min="1" max="10" style="width: 70px;">
            </div>
            <button id="analyze-button">Analyze</button>
        </div>

        <p id="loading" style="display: none;">Analyzing position, please wait...</p>

        <hr>

        <div id="lichess-board-container" class="output-section" style="padding: 0;">
            <iframe id="lichess-board"
                src="https://lichess.org/embed/analysis?fen=rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR_w_KQkq_-_0_1"
                style="width: 400px; height: 400px;" 
                frameborder="0" allowtransparency="true">
            </iframe>
        </div>
    </div>
    <hr>
    
    <div id="results" style="display: none; padding: 20px 0;">
        <h2 style="text-align: center;">Engine Analysis</h2>
        
        <div class="output-section" style="padding-bottom: 15px;">
            <h3>Stockfish (Depth: <span id="display-depth"></span>, MultiPV: <span id="display-multipv"></span>)</h3>
            <div id="stockfish-output"></div>
        </div>

        <div class="output-section">
            <h3>Gemini Explanation</h3>
            <div id="gemini-output"></div>
        </div>
    </div>
    </div> <script>
// --- JAVASCRIPT LOGIC ---
$(document).ready(function() {
    const API_URL_ANALYZE = "/api/analyze";

    const $fenInput = $('#fen-input');
    const $depthInput = $('#depth-input');
    const $multipvInput = $('#multipv-input');
    const $analyzeButton = $('#analyze-button');
    const $loading = $('#loading');
    const $results = $('#results');
    const $lichessBoard = $('#lichess-board');
    const $lichessBoardContainer = $('#lichess-board-container');

    $analyzeButton.click(function() {
        // Use default FEN if input is empty, otherwise use input value
        const fen = $fenInput.val() || "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
        const depth = parseInt($depthInput.val());
        const multipv = parseInt($multipvInput.val());

        if (!fen || isNaN(depth) || isNaN(multipv)) {
            alert("Please enter valid FEN, Depth, and MultiPV values.");
            return;
        }

        // Instant Board Drawing - Restored to the original working path and encoding
        const lichessUrl = `https://lichess.org/embed/analysis?fen=${fen.replace(/ /g, '_')}`;

        $lichessBoard.attr('src', lichessUrl);
        $lichessBoardContainer.show();

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
