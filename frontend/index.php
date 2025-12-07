<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Stockfish + Gemini Chess Analyzer</title>
  <style>
    body{font-family:system-ui;max-width:1000px;margin:2rem auto;padding:0 1rem;background:#111;color:#eee;}
    .layout{display:grid;grid-template-columns:420px 1fr;grid-gap:1.5rem;}
    textarea{width:100%;height:4em;padding:.5rem;font-family:monospace;background:#181818;color:#eee;border:1px solid #444;border-radius:4px;}
    input[type=number]{width:4em;padding:.2rem;background:#181818;color:#eee;border:1px solid #444;border-radius:4px;}
    button{padding:.4rem .9rem;background:#2b6cb0;color:#fff;border:1px solid #555;border-radius:4px;cursor:pointer;}
    #ascii-board,#sf,#gemini{background:#181818;color:#e2e8f0;padding:.75rem;border-radius:4px;font-family:monospace;white-space:pre-wrap;border:1px solid #333;margin-top:.75rem;}
    #lichess-board{width:100%;height:450px;border-radius:8px;border:1px solid #333;box-shadow:0 0 20px rgba(0,0,0,.5);}
    #loading{display:none;font-size:24px;color:#888;text-align:center;padding:2rem;background:#181818;border:1px solid #444;border-radius:4px;margin:1rem 0;}
    .loading{animation:pulse 1.5s infinite;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}
  </style>
</head>
<body>
<h1>Stockfish + Gemini Chess Analyzer</h1>

<!-- DEFAULTS - EASY TO FIND -->
<script>const DEFAULT_DEPTH=12;const DEFAULT_MULTIPV=3;</script>

<div id="loading">Analyzing... ⏳</div>
<div class="layout">
  <div>
    <form id="analyze-form">
      <label>FEN:<br><textarea id="fen" placeholder="Paste FEN here">r1bq1rk1/ppp2ppp/2n2n2/3pp3/3PP3/2N1BN2/PPP2PPP/R2Q1RK1 w - - 0 8</textarea></label><br>
      <label>Depth: <input type=number id="depth" value="12" min=1 max=40></label>
      <label>MultiPV: <input type=number id="multipv" value="3" min=1 max=10></label><br><br>
      <button type=submit>Analyze</button>
    </form>
    <div style="margin-top:1.5rem;"><h3>ASCII</h3><pre id="ascii-board">FEN loaded</pre></div>
  </div>
  <div><h3>Lichess Board</h3><iframe id="lichess-board"
  src="https://lichess.org/embed/analysis?fen=r1bq1rk1/ppp2ppp/2n2n2/3pp3/3PP3/2N1BN2/PPP2PPP/R2Q1RK1_w_-_-_0_8&color=white&theme=brown"
  frameborder=0 allowfullscreen></iframe></div>
</div>
<div style="margin-top:1.5rem;"><h3>Stockfish</h3><pre id="sf"></pre></div>
<div style="margin-top:.75rem;"><h3>Gemini</h3><pre id="gemini"></pre></div>

<script>
const f=document.getElementById('analyze-form'),a=document.getElementById('ascii-board'),s=document.getElementById('sf'),g=document.getElementById('gemini'),l=document.getElementById('lichess-board'),loading=document.getElementById('loading'),fenInput=document.getElementById('fen');

fenInput.oninput=()=>{
  const fen=fenInput.value.trim();
  if(fen){
    a.textContent='FEN loaded';
    l.src=`https://lichess.org/embed/analysis?fen=${fen.replace(/ /g,'_')}&color=white&theme=brown`;
  }
};

f.onsubmit=async e=>{
  e.preventDefault();
  const fen=document.getElementById('fen').value.trim(),d=parseInt(document.getElementById('depth').value)||DEFAULT_DEPTH,m=parseInt(document.getElementById('multipv').value)||DEFAULT_MULTIPV;
  if(!fen){a.textContent='Paste FEN';return;}
  loading.style.display='block';loading.className='loading';
  s.textContent='';g.textContent='';
  try{
    const r=await fetch('/api/analyze',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({fen,d,multipv:m})});
    if(!r.ok){a.textContent='Error:'+await r.text();loading.style.display='none';return;}
    const data=await r.json();
    a.textContent=data.board;s.textContent=data.stockfish_lines;g.textContent=data.gemini;
    loading.style.display='none';
  }catch(e){a.textContent='Failed: '+e;loading.style.display='none';}
};
</script>
</body>
</html>

