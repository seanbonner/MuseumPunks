(function(){
  function ready(fn){ if(document.readyState !== 'loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

  function uniquePositionsStratified(total, n){
    const chosen = new Set();
    const positions = [];
    if(n <= 0) return positions;

    for(let i=0;i<n;i++){
      const start = Math.floor(i * total / n);
      const end   = Math.max(start, Math.floor((i + 1) * total / n) - 1);

      let pick = start;
      if(end > start){
        pick = start + Math.floor(Math.random() * (end - start + 1));
      }

      let found = -1;
      const segLen = end - start + 1;
      for(let k=0;k<segLen;k++){
        const idx = start + ((pick - start + k) % segLen);
        if(!chosen.has(idx)){
          found = idx;
          break;
        }
      }

      if(found === -1){
        for(let idx=0; idx<total; idx++){
          if(!chosen.has(idx)){
            found = idx;
            break;
          }
        }
      }

      if(found !== -1){
        chosen.add(found);
        positions.push(found);
      }
    }

    positions.sort((a,b)=>a-b);
    return positions;
  }

  // ---- Crisp pixel canvas renderer (bulletproof) ----
  const canvasImgs = new Map(); // canvas -> Image

  function getCssSize(canvas){
    // IMPORTANT: with aspect-ratio in CSS, getBoundingClientRect() returns correct dimensions.
    const rect = canvas.getBoundingClientRect();
    const cssW = Math.max(1, Math.round(rect.width));
    const cssH = Math.max(1, Math.round(rect.height));
    return { cssW, cssH };
  }

  function renderCanvas(canvas){
    const img = canvasImgs.get(canvas);
    if(!img || !img.complete) return;

    const { cssW, cssH } = getCssSize(canvas);
    const dpr = window.devicePixelRatio || 1;

    const pxW = Math.max(1, Math.round(cssW * dpr));
    const pxH = Math.max(1, Math.round(cssH * dpr));

    if(canvas.width !== pxW) canvas.width = pxW;
    if(canvas.height !== pxH) canvas.height = pxH;

    const ctx = canvas.getContext('2d', { alpha: true, desynchronized: true });
    if(!ctx) return;

    ctx.imageSmoothingEnabled = false;
    ctx.clearRect(0,0,pxW,pxH);
    ctx.drawImage(img, 0, 0, pxW, pxH);
  }

  function loadToCanvas(canvas, src){
    const img = new Image();
    img.decoding = 'async';
    img.onload = function(){
      canvasImgs.set(canvas, img);
      renderCanvas(canvas);
    };
    img.src = src;
  }

  function replaceImgWithCanvas(imgEl){
    const src = imgEl.getAttribute('src');
    if(!src) return;

    const canvas = document.createElement('canvas');
    canvas.className = imgEl.className.replace(/\bsbpr-pixelimg\b/g,'').trim() + ' sbpr-pixelcanvas';
    canvas.setAttribute('data-src', src);
    canvas.setAttribute('aria-hidden', 'true');

    imgEl.parentNode.insertBefore(canvas, imgEl);
    imgEl.parentNode.removeChild(imgEl);

    loadToCanvas(canvas, src);
  }

  function scanAndCanvasify(){
    // Index images
    document.querySelectorAll('img.sbpr-index__img').forEach(replaceImgWithCanvas);

    // Any canvas that declares a data-src (single template + mosaic tiles)
    document.querySelectorAll('canvas.sbpr-pixelcanvas[data-src]').forEach(function(c){
      if(canvasImgs.has(c)) return;
      const src = c.getAttribute('data-src');
      if(src) loadToCanvas(c, src);
    });
  }

  // ---- Mosaic grid ----
  function buildGrid(grid){
    const itemsRaw = grid.getAttribute('data-sbpr-items') || '[]';
    let items;
    try { items = JSON.parse(itemsRaw); } catch(e){ items = []; }
    if(!Array.isArray(items) || items.length === 0) return;

    items = items.slice().sort((a,b)=> (parseInt(a.num,10)||0) - (parseInt(b.num,10)||0));

    const tile = 96;
    const gap  = 6;

    const w = Math.max(320, window.innerWidth);
    const cols = Math.max(3, Math.floor((w + gap) / (tile + gap)));

    const header = document.querySelector('.sbpr-header');
    const headerH = header ? header.getBoundingClientRect().height : 0;
    const targetH = Math.max(320, window.innerHeight - headerH - 48);
    const rows = Math.max(2, Math.floor((targetH + gap) / (tile + gap)));
    const total = cols * rows;

    grid.style.setProperty('--sbpr-tile', tile + 'px');
    grid.style.setProperty('--sbpr-gap', gap + 'px');
    grid.style.setProperty('--sbpr-cols', String(cols));

    const n = Math.min(items.length, total);
    const positions = uniquePositionsStratified(total, n);

    const posToItem = new Map();
    for(let i=0;i<positions.length;i++){
      posToItem.set(positions[i], items[i]);
    }

    const frag = document.createDocumentFragment();

    for(let i=0;i<total;i++){
      const it = posToItem.get(i);
      if(!it){
        const s = document.createElement('span');
        s.className = 'sbpr-emptycell';
        frag.appendChild(s);
        continue;
      }

      const a = document.createElement('a');
      a.className = 'sbpr-tile';
      a.href = it.href;
      a.setAttribute('aria-label', 'Punk ' + it.num);

      if(it.thumb){
        const canvas = document.createElement('canvas');
        canvas.className = 'sbpr-tile__img sbpr-pixelcanvas';
        canvas.setAttribute('data-src', it.thumb);
        canvas.setAttribute('aria-hidden', 'true');
        a.appendChild(canvas);
      }

      frag.appendChild(a);
    }

    grid.innerHTML = '';
    grid.appendChild(frag);

    scanAndCanvasify();
    requestAnimationFrame(function(){
      document.querySelectorAll('canvas.sbpr-pixelcanvas').forEach(renderCanvas);
    });
  }

  ready(function(){
    const grid = document.querySelector('.sbpr-mosaic__grid[data-sbpr-items]');
    if(grid) buildGrid(grid);

    scanAndCanvasify();

    let t = null;
    window.addEventListener('resize', function(){
      if(t) clearTimeout(t);
      t = setTimeout(function(){
        if(grid) buildGrid(grid);
        document.querySelectorAll('canvas.sbpr-pixelcanvas').forEach(renderCanvas);
      }, 150);
    });
  });
})();

