<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chess Position Analyzer</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f9;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #4CAF50;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .input-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
        .output-section {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fafafa;
        }
        #loading {
            color: #FF9800;
            font-weight: bold;
            display: none;
        }
        #stockfish-output, #gemini-output {
            white-space: pre-wrap;
            word-break: break-word;
            padding: 10px;
            background-color: #e8eaf6;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
        }
        #gemini-output {
            background-color: #fce4ec;
        }
        /* Style for the Lichess iframe */
        #lichess-board-container {
            display: none; 
            margin-bottom: 20px;
        }
        #lichess-board {
            width: 100%;
            height: 450px; 
            border-radius: 8px;
            border: 1px solid #333;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container">
    <h1>♟️ Chess Position Analyzer</h1>

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
$(document).ready(function() {
    const API_URL = "/api/analyze";

    $('#analyze-button').click(function() {
        const fen = $('#fen-input').val();
        const depth = parseInt($('#depth-input').val());
        const multipv = parseInt($('#multipv-input').val());

        // Basic validation
        if (!fen || isNaN(depth) || isNaN(multipv)) {
            alert("Please enter valid FEN, Depth, and MultiPV values.");
            return;
        }
        
        // --- START: INSTANT BOARD DRAWING ON BUTTON CLICK ---
        // 1. Update the Lichess board with the current FEN
        const lichessUrl = `https://lichess.org/embed/analysis?fen=${fen.replace(/ /g, '_')}&color=white&theme=brown`;
        $('#lichess-board').attr('src', lichessUrl);
        
        // 2. SHOW the board container immediately
        $('#lichess-board-container').show();
        // --- END: INSTANT BOARD DRAWING ---

        // Show loading state and hide previous results
        $('#loading').show();
        $('#results').hide();
        $('#analyze-button').prop('disabled', true);
        
        $.ajax({
            url: API_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                fen: fen,
                depth: depth,
                multipv: multipv
            }),
            success: function(data) {
                // Update display headers
                $('#display-depth').text(data.depth);
                $('#display-multipv').text(data.multipv);

                // CRITICAL FIX: Replace newlines (\n) with <br> for Stockfish output
                const formattedStockfishLines = data.stockfish_lines.replace(/\n/g, '<br>');
                $('#stockfish-output').html(formattedStockfishLines);

                // Display Gemini output
                $('#gemini-output').text(data.gemini);

                // Only show results now, board is already visible
                $('#results').show();
            },
            error: function(xhr, status, error) {
                const errorDetail = xhr.responseJSON ? xhr.responseJSON.detail : error;
                alert('Analysis failed: ' + errorDetail);
                console.error("Error details:", xhr);
            },
            complete: function() {
                // Hide loading state and re-enable button
                $('#loading').hide();
                $('#analyze-button').prop('disabled', false);
            }
        });
    });
});
</script>

</body>
</html>
