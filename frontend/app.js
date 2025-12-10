// --- JAVASCRIPT LOGIC ---
$(document).ready(function() {
    const API_URL_ANALYZE = "/api/analyze";
    const API_URL_PGN = "/api/analyze-pgn";

    // FEN Analysis Elements
    const $fenInput = $('#fen-input');
    const $depthInput = $('#depth-input');
    const $multipvInput = $('#multipv-input');
    const $analyzeButton = $('#analyze-button');
    const $loading = $('#loading');
    const $results = $('#results');
    const $lichessBoard = $('#lichess-board');
    const $lichessBoardContainer = $('#lichess-board-container');

    // PGN Analysis Elements
    const $pgnInput = $('#pgn-input');
    const $pgnDepthInput = $('#pgn-depth-input');
    const $analyzePgnButton = $('#analyze-pgn-button');
    const $pgnLoading = $('#pgn-loading');
    const $pgnResults = $('#pgn-results');
    const $pgnSummaryHeader = $('#pgn-summary-header');
    const $criticalMomentsList = $('#critical-moments-list');

    // 1. FEN ANALYSIS
    $analyzeButton.click(function() {
        const fen = $fenInput.val() || "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
        const depth = parseInt($depthInput.val());
        const multipv = parseInt($multipvInput.val());

        if (!fen || isNaN(depth) || isNaN(multipv)) {
            alert("Please enter valid FEN, Depth, and MultiPV values.");
            return;
        }

        updateLichessBoard(fen);
        analyzeFen(fen, depth, multipv);
    });

    // 2. PGN SWEEP ANALYSIS
    $analyzePgnButton.click(function() {
        const pgn = $pgnInput.val();
        const depth = parseInt($pgnDepthInput.val());

        if (!pgn) {
            alert("Please paste a PGN.");
            return;
        }

        analyzePgn(pgn, depth);
    });

    // 3. STUDY POSITION HANDLER
    $(document).on('click', '.study-move-btn', function() {
        const fen_encoded = $(this).data('fen');
        const fen_decoded = fen_encoded.replace(/_/g, ' ');
        
        $fenInput.val(fen_decoded);
        $depthInput.val(25);
        $multipvInput.val(3);
        $analyzeButton.click();
        
        $('html, body').animate({
            scrollTop: $lichessBoardContainer.offset().top - 100
        }, 'slow');
    });

    // UTILITY FUNCTIONS
    function updateLichessBoard(fen) {
        const lichessUrl = `https://lichess.org/embed/analysis?fen=${fen.replace(/ /g, '_')}`;
        $lichessBoard.attr('src', lichessUrl);
        $lichessBoardContainer.show();
    }

    function analyzeFen(fen, depth, multipv) {
        $loading.show();
        $results.hide();
        $analyzeButton.prop('disabled', true);

        $.ajax({
            url: API_URL_ANALYZE,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ fen, depth, multipv }),
            success: function(data) {
                $('#display-depth').text(data.depth);
                $('#display-multipv').text(data.multipv);
                $('#stockfish-output').html(data.stockfish_lines.replace(/\n/g, '<br>'));
                $('#gemini-output').text(data.gemini);
                $results.show();
            },
            error: function(xhr) {
                const errorDetail = xhr.responseJSON?.detail || 'Unknown error';
                alert('Analysis failed: ' + errorDetail);
            },
            complete: function() {
                $loading.hide();
                $analyzeButton.prop('disabled', false);
            }
        });
    }

    function analyzePgn(pgn, depth) {
        $pgnLoading.show();
        $pgnResults.hide();
        $analyzePgnButton.prop('disabled', true);

        $.ajax({
            url: API_URL_PGN,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ pgn, depth }),
            success: function(data) {
                $pgnSummaryHeader.text(data.game_summary);
                renderCriticalMoments(data.critical_moments);
                $pgnResults.show();
            },
            error: function(xhr) {
                const errorDetail = xhr.responseJSON?.detail || 'Unknown error';
                alert('PGN Analysis failed: ' + errorDetail);
            },
            complete: function() {
                $pgnLoading.hide();
                $analyzePgnButton.prop('disabled', false);
            }
        });
    }

    function renderCriticalMoments(moments) {
        let htmlList = '<ul>';
        if (moments.length === 0) {
            htmlList += '<li>No major mistakes or blunders found!</li>';
        } else {
            moments.forEach(moment => {
                const fen_encoded = moment.fen_before.replace(/ /g, '_');
                const type_class = moment.type.toLowerCase();
                htmlList += `
                    <li>
                        <span class="${type_class}">${moment.type}</span> 
                        after move <strong>${moment.move}</strong> 
                        (Eval loss: ${moment.delta_eval})
                        <br>Stockfish best: <strong>${moment.best_move}</strong> 
                        (${moment.best_eval})
                        <button class="study-move-btn" data-fen="${fen_encoded}">Study Position</button>
                    </li>
                `;
            });
        }
        htmlList += '</ul>';
        $criticalMomentsList.html(htmlList);
    }

    // Initialize
    if (!$fenInput.val()) {
        $fenInput.val("rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1");
    }
    updateLichessBoard($fenInput.val());
});


