// Minimal placeholder Match-3 rendering loop. This is a demo and not a full game.
(function() {
  const canvas = document.getElementById('gameCanvas');
  const ctx = canvas.getContext('2d');
  const cols = 8, rows = 8;
  const tileSize = canvas.width / cols;
  const colors = ['#e76f51','#f4a261','#2a9d8f','#264653','#e9c46a','#8ab17d'];

  function randInt(n){ return Math.floor(Math.random()*n); }

  const board = [];
  for (let y=0;y<rows;y++){
    board[y] = [];
    for(let x=0;x<cols;x++){
      board[y][x] = randInt(colors.length);
    }
  }

  function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    for (let y=0;y<rows;y++){
      for(let x=0;x<cols;x++){
        ctx.fillStyle = colors[board[y][x]];
        ctx.fillRect(x*tileSize+2, y*tileSize+2, tileSize-4, tileSize-4);
      }
    }
  }

  // simple animation loop
  setInterval(draw, 1000/30);
})();