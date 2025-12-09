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
        /* Style for the expandable image section */
        #image-to-fen-section {
            background-color: #f9f9e0; /* Light yellow background */
            padding: 15px;
            border-radius: 4px;
            border: 1px dashed #ccc;
            margin-bottom: 20px;
        }
        .image-status-message {
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>♟️ Chess Position Analyzer</h1>

    <details id="image-to-fen-section">
        <summary style="font-weight: bold; cursor: pointer; color: #607D8B;">
            ✨ Optional: Convert Image to FEN
        </summary>
        <div style="padding-top: 15px;">
            <p>Upload a clean screenshot of a chessboard (e.g., from Lichess or Chess.com). This feature uses an external service.</p>
            <input type="file" id="chessImageUpload" accept="image/png, image/jpeg, image/webp">
            <button id="convertImageButton" style="display: none; margin-top: 10px;">Convert Image to FEN</button>
            <div id="imageStatus" class="image-status-message"></div>
        </div>
    </details>
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
// --- CORE FUNCTIONALITY: ANALYZE FEN (UNCHANGED) ---

// Function to convert the selected file to a pure Base64 string
function convertFileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const dataUrl = reader.result;
            // The pure Base64 string is the part after the comma
            const base64String = dataUrl.split(',')[1]; 
            resolve(base64String);
        };
        reader.onerror = error => reject(error);
        // readAsDataURL includes the metadata prefix
        reader.readAsDataURL(file); 
    });
}

// --- JAVASCRIPT LOGIC ---
$(document).ready(function() {
    // NOTE: This assumes your FastAPI app runs on port 8000. Adjust if needed.
    const API_URL_ANALYZE = "/api/analyze";
    const API_URL_CONVERT = "http://localhost:8000/convert-image"; 
    
    const $fenInput = $('#fen-input');
    const $depthInput = $('#depth-input');
    const $multipvInput = $('#multipv-input');
    const $analyzeButton = $('#analyze-button');
    const $loading = $('#loading');
    const $results = $('#results');
    const $imageUpload = $('#chessImageUpload');
    const $convertButton = $('#convertImageButton');
    const $imageStatus = $('#imageStatus');
    const $lichessBoard = $('#lichess-board');
    const $lichessBoardContainer = $('#lichess-board-container');

    // =======================================================
    // 1. FEN ANALYSIS LOGIC (Your existing code)
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

    // =======================================================
    // 2. IMAGE TO FEN LOGIC (NEW CODE)
    // =======================================================

    // A. Show/Hide the Convert Button based on file selection
    $imageUpload.on('change', function() {
        if (this.files.length > 0) {
            $convertButton.show();
            $imageStatus.html(`Ready to convert: <strong>${this.files[0].name}</strong>`);
        } else {
            $convertButton.hide();
            $imageStatus.html('');
        }
    });

    // B. Handle the Image Conversion Click
    $convertButton.click(async function() {
        if ($imageUpload[0].files.length === 0) {
            $imageStatus.html('<span style="color: red;">Please select an image file first.</span>');
            return;
        }
        
        const imageFile = $imageUpload[0].files[0];
        $convertButton.prop('disabled', true).text('Converting...');
        $imageStatus.html('Processing image to FEN...');
        
        try {
            const base64Image = await convertFileToBase64(imageFile);
            
            const response = await fetch(API_URL_CONVERT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    image_base64: base64Image
                })
            });

            const result = await response.json();

            if (!response.ok) {
                const detail = result.detail || 'Unknown error';
                $imageStatus.html(`<span style="color: red;">Conversion Failed: ${detail}</span>`);
                return;
            }

            // SUCCESS! 
            const newFen = result.fen;
            $fenInput.val(newFen); // Update the main FEN input field
            
            $imageStatus.html(`Conversion **Successful!** FEN updated. Click **Analyze** to proceed.`);

            // Optional: Auto-collapse the section after success
            $('#image-to-fen-section').prop('open', false); 

        } catch (error) {
            console.error('Image conversion error:', error);
            $imageStatus.html(`<span style="color: red;">An unexpected error occurred: ${error.message}</span>`);
        } finally {
            $convertButton.prop('disabled', false).text('Convert Image to FEN');
        }
    });

});
</script>

</body>
</html>
