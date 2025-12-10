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
        /* NEW: Style for PGN buttons */
        .study-move-btn {
            background-color: #007bff;
            padding: 5px 10px;
            margin-left: 10px;
        }
        .study-move-btn:hover {
            background-color: #0056b3;
        }
        #critical-moments-list li {
            margin-bottom: 10px;
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        /* NEW: Color for different issue types */
        .blunder { color: #dc3545; font-weight: bold; }
        .mistake { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>

<div class="main-wrapper">
    <h1>♟️ Chess Position Analyzer</h1>
    
    <div class="pgn-analysis-section">
        <h2>🔥 Full Game PGN Analyzer (Fast Sweep)</h2>

        <div class="input-group">
            <label for="pgn-input">Paste Game PGN</label>
            <textarea id="pgn-input" rows="5" style="width: 100%;" placeholder="e.g., [Event '...'] 1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 4. Ba4 Nf6 ..."></textarea>
        </div>

        <div id="pgn-analysis-controls" style="margin-bottom: 15px;">
            <div style="display: inline-block; margin-right: 20px;">
                <label for="pgn-depth-input" style="font-weight: bold;">Sweep Depth (Recommended 12-15):</label>
                <input type="number" id="pgn-depth-input" value="15" min="10" max="25" style="width: 70px;">
            </div>
            <button id="analyze-pgn-button">Analyze Game Sweep</button>
        </div>
        
        <p id="pgn-loading" style="display: none; color: #FF9800; font-weight: bold;">Scanning game for critical moments...</p>

        <div id="pgn-results" class="output-section" style="display: none; background-color: #f3e5f5;">
            <h3 id="pgn-summary-header"></h3>
            <div id="critical-moments-list" style="max-height: 400px; overflow-y: auto; padding: 10px;">
                </div>
        </div>
    </div>
    
    <hr>
    
    <div class="board-and-controls">
        
        <div class="input-group">
            <p>Enter a FEN position (or click a **Study Position** button above), then click "Analyze".</p>
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
</div> 
<script>
// --- JAVASCRIPT LOGIC ---
$(document).ready(function() {
    const API_URL_ANALYZE = "/api/analyze";
    const API_URL_PGN = "/api/analyze-pgn"; // NEW

    // FEN Analysis Elements
    const $fenInput = $('#fen-input');
    const $depthInput = $('#depth-input');
    const $multipvInput = $('#multipv-input');
    const $analyzeButton = $('#analyze-button');
    const $loading = $('#loading');
    const $results = $('#results');
    const $lichessBoard = $('#lichess-board');
    const $lichessBoardContainer = $('#lichess-board-container');

    // PGN Analysis Elements (NEW)
    const $pgnInput = $('#pgn-input');
    const $pgnDepthInput = $('#pgn-depth-input');
    const $analyzePgnButton = $('#analyze-pgn-button');
    const $pgnLoading = $('#pgn-loading');
    const $pgnResults = $('#pgn-results');
    const $pgnSummaryHeader = $('#pgn-summary-header');
    const $criticalMomentsList = $('#critical-moments-list');


    // --- 1. EXISTING FEN ANALYSIS FUNCTION ---
    $analyzeButton.click(function() {
        const fen = $fenInput.val() || "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
        const depth = parseInt($depthInput.val());
        const multipv = parseInt($multipvInput.val());

        if (!fen || isNaN(depth) || isNaN(multipv)) {
            alert("Please enter valid FEN, Depth, and MultiPV values.");
            return;
        }

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

    // --- 2. NEW PGN SWEEP FUNCTION ---
    $analyzePgnButton.click(function() {
        const pgn = $pgnInput.val();
        const depth = parseInt($pgnDepthInput.val());

        if (!pgn) {
            alert("Please paste a PGN.");
            return;
        }

        $pgnLoading.show();
        $pgnResults.hide();
        $analyzePgnButton.prop('disabled', true);

        $.ajax({
            url: API_URL_PGN,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                pgn: pgn,
                depth: depth
            }),
            success: function(data) {
                $pgnSummaryHeader.text(data.game_summary);
                
                let htmlList = '<ul>';
                if (data.critical_moments.length === 0) {
                    htmlList += '<li>No major mistakes or blunders found!</li>';
                } else {
                    data.critical_moments.forEach(moment => {
                        // FEN needs to be URL-safe encoded for the data-fen attribute
                        const fen_encoded = moment.fen_before.replace(/ /g, '_');
                        const type_class = moment.type.toLowerCase(); // 'blunder' or 'mistake'

                        htmlList += `
                            <li>
                                <span class="${type_class}">${moment.type}</span> 
                                after move <strong>${moment.move}</strong>.
                                (Eval loss: ${moment.delta_eval})
                                <br>
                                Stockfish best: <strong>${moment.best_move}</strong> 
                                (${moment.best_eval})
                                <button class="study-move-btn" data-fen="${fen_encoded}">Study Position</button>
                            </li>
                        `;
                    });
                }
                htmlList += '</ul>';
                $criticalMomentsList.html(htmlList);
                
                $pgnResults.show();
            },
            error: function(xhr, status, error) {
                const errorDetail = xhr.responseJSON ? xhr.responseJSON.detail : error;
                alert('PGN Analysis failed: ' + errorDetail);
                console.error("Error details:", xhr);
            },
            complete: function() {
                $pgnLoading.hide();
                $analyzePgnButton.prop('disabled', false);
            }
        });
    });

    // --- 3. NEW "STUDY POSITION" CLICK HANDLER ---
    $(document).on('click', '.study-move-btn', function() {
        // 1. Get the FEN and decode it
        const fen_encoded = $(this).data('fen');
        const fen_decoded = fen_encoded.replace(/_/g, ' ');

        // 2. Update the main FEN input box
        $fenInput.val(fen_decoded);

        // 3. Set the depth/multipv to high settings for deep analysis
        $depthInput.val(25);
        $multipvInput.val(3);
        
        // 4. Trigger the full FEN analysis workflow (high depth + Gemini)
        $analyzeButton.click(); 

        // 5. Scroll to the FEN input/Lichess board to show the new position
        $('html, body').animate({ scrollTop: $('#lichess-board-container').offset().top - 100 }, 'slow');
    });

    // Initial board load (if FEN input is empty)
    if (!$fenInput.val()) {
        $fenInput.val("rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1");
    }
    // Trigger an initial board display on load (without analysis)
    const initialFen = $fenInput.val();
    const lichessUrl = `https://lichess.org/embed/analysis?fen=${initialFen.replace(/ /g, '_')}`;
    $lichessBoard.attr('src', lichessUrl);
    $lichessBoardContainer.show();
});
</script>

</body>
</html>


