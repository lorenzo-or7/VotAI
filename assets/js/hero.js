/**
 * "Brasil de dados" — composição pseudo-3D do hero.
 * Nuvem de pontos com o contorno abstrato do território, ondas de
 * informação que partem de Brasília e conexões entre as capitais.
 * Canvas 2D puro, sem bibliotecas.
 */

// Contorno simplificado do território (longitude, latitude)
const CONTORNO = [
  [-60.7, 5.2], [-59.9, 4.0], [-59.5, 2.4], [-58.6, 1.3], [-56.5, 1.9], [-54.8, 2.4], [-52.9, 2.2],
  [-51.6, 4.2], [-51.1, 3.1], [-50.2, 0.9], [-49.0, -0.2], [-48.4, -1.2], [-47.0, -0.7], [-44.8, -1.6],
  [-44.2, -2.7], [-42.0, -2.8], [-39.8, -2.9], [-38.4, -3.7], [-37.2, -4.8], [-35.3, -5.2], [-34.8, -7.2],
  [-35.1, -8.9], [-36.4, -10.4], [-37.6, -11.6], [-38.5, -13.0], [-39.0, -14.8], [-39.1, -17.4],
  [-39.7, -19.4], [-40.4, -20.6], [-41.0, -21.9], [-42.1, -22.9], [-44.6, -23.3], [-46.4, -24.0],
  [-47.9, -25.2], [-48.6, -26.4], [-48.8, -28.4], [-50.2, -30.4], [-51.8, -32.0], [-53.4, -33.7],
  [-53.3, -32.5], [-54.6, -31.5], [-55.9, -30.9], [-57.6, -30.2], [-56.0, -28.6], [-54.3, -27.4],
  [-53.7, -26.2], [-54.6, -25.6], [-54.3, -24.0], [-55.4, -23.9], [-55.7, -22.5], [-57.9, -22.1],
  [-57.8, -19.9], [-58.1, -17.8], [-60.1, -16.3], [-60.4, -13.6], [-61.9, -13.5], [-64.0, -12.5],
  [-65.3, -11.5], [-65.3, -10.4], [-66.8, -9.8], [-68.6, -11.0], [-70.5, -11.0], [-70.6, -9.5],
  [-73.2, -9.4], [-73.9, -7.5], [-72.9, -5.2], [-70.0, -4.3], [-69.4, -1.2], [-69.8, 1.1], [-67.3, 2.1],
  [-66.5, 0.9], [-64.8, 1.4], [-64.1, 2.0], [-64.2, 3.9], [-62.8, 4.0], [-61.3, 4.5],
];

// Capitais (aprox.) — Brasília é a primeira
const CAPITAIS = [
  [-47.9, -15.8], [-60.0, -3.1], [-48.5, -1.45], [-44.3, -2.5], [-38.5, -3.7], [-35.2, -5.8], [-34.9, -7.1],
  [-34.9, -8.05], [-35.7, -9.65], [-37.05, -10.9], [-38.5, -12.97], [-42.8, -5.1], [-48.3, -10.2],
  [-49.25, -16.7], [-56.1, -15.6], [-54.6, -20.45], [-43.9, -19.9], [-40.3, -20.3], [-43.2, -22.9],
  [-46.6, -23.55], [-49.3, -25.4], [-48.55, -27.6], [-51.2, -30.0], [-63.9, -8.76], [-67.8, -9.97],
  [-60.67, 2.82], [-51.07, 0.03],
];

const CENTRO = [-54, -14];

function dentro([x, y], poly) {
  let d = false;
  for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    const [xi, yi] = poly[i], [xj, yj] = poly[j];
    if ((yi > y) !== (yj > y) && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) d = !d;
  }
  return d;
}

function hexRgb(h) {
  const n = parseInt(h.replace('#', ''), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}
function mix(a, b, t) { return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t]; }

export function iniciarHero(canvas) {
  const ctx = canvas.getContext('2d', { alpha: true });
  if (!ctx) return;

  const reduzido = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const leve = document.documentElement.classList.contains('leve');
  const FPS = leve ? 20 : 30;                 // 30 quadros/s bastam para um movimento lento
  const INTERVALO = 1000 / FPS;
  const NCORES = 10, NALFA = 6;               // cores quantizadas → poucas trocas de fillStyle

  let W = 0, H = 0, dpr = 1, escala = 1, pontos = [], rodando = false, visivel = true, raf = 0, ultimo = 0;
  let yaw = 0, pitch = 0, alvoYaw = 0, alvoPitch = 0, T = 1.7;
  let paleta, estilos = [], baldes = [];
  // trigonometria calculada uma vez por quadro (não por ponto)
  let cT = 0, sT = 0, cY = 0, sY = 0;
  const RZ = -0.16, cZ = Math.cos(RZ), sZ = Math.sin(RZ);
  const TILT = 0.82;

  // pares de capitais vizinhas: calculados UMA vez
  const pares = [];
  for (let i = 1; i < CAPITAIS.length; i++) {
    CAPITAIS.map((c, j) => [j, Math.hypot(c[0] - CAPITAIS[i][0], c[1] - CAPITAIS[i][1])])
      .filter(([j]) => j !== i).sort((a, b) => a[1] - b[1]).slice(0, 2)
      .forEach(([j]) => { if (!pares.some(([a, b]) => (a === j && b === i))) pares.push([i, j]); });
  }

  function lerPaleta() {
    const claro = document.documentElement.dataset.theme === 'light';
    paleta = claro
      ? { a: hexRgb('#302681'), b: hexRgb('#009440'), c: hexRgb('#c99a00'), linha: 'rgba(11,16,12,', modo: 'source-over', alfa: 0.9, claro }
      : { a: hexRgb('#7b6bff'), b: hexRgb('#22d470'), c: hexRgb('#ffcb00'), linha: 'rgba(236,238,230,', modo: 'lighter', alfa: 0.85, claro };
    // tabela de estilos: NCORES tons × NALFA opacidades
    estilos = [];
    for (let k = 0; k < NCORES; k++) {
      const u = k / (NCORES - 1);
      const c = u < 0.5 ? mix(paleta.a, paleta.b, u / 0.5) : mix(paleta.b, paleta.c, (u - 0.5) / 0.5);
      for (let a = 0; a < NALFA; a++) {
        const al = (0.35 + (0.65 * a) / (NALFA - 1)) * paleta.alfa;
        estilos.push(`rgba(${c[0] | 0},${c[1] | 0},${c[2] | 0},${al.toFixed(2)})`);
      }
    }
    baldes = estilos.map(() => []);
  }

  function tom(lon, lat) {
    return Math.min(1, Math.max(0, ((lon + 74) / 40) * 0.62 + ((5 - lat) / 39) * 0.38));
  }
  function cor(lon, lat) {
    const u = tom(lon, lat);
    return u < 0.5 ? mix(paleta.a, paleta.b, u / 0.5) : mix(paleta.b, paleta.c, (u - 0.5) / 0.5);
  }

  function gerarPontos() {
    const passo = (W / dpr < 700 || leve) ? 1.4 : 0.95;
    pontos = [];
    for (let lat = 5.5; lat > -34; lat -= passo) {
      const off = (Math.round((5.5 - lat) / passo) % 2) * passo * 0.5;
      for (let lon = -74 + off; lon < -34; lon += passo) {
        if (dentro([lon, lat], CONTORNO)) {
          const dB = Math.hypot(lon - CAPITAIS[0][0], lat - CAPITAIS[0][1]);
          pontos.push({ lon, lat, x: lon - CENTRO[0], y: lat - CENTRO[1], dB, k: Math.round(tom(lon, lat) * (NCORES - 1)), r: Math.random() });
        }
      }
    }
  }

  function medir() {
    const r = canvas.getBoundingClientRect();
    dpr = Math.min(window.devicePixelRatio || 1, leve ? 1 : 1.5);
    W = Math.max(1, Math.round(r.width * dpr));
    H = Math.max(1, Math.round(r.height * dpr));
    canvas.width = W; canvas.height = H;
    escala = Math.min(W / 40, H / 27);
    gerarPontos();
  }

  function prepararQuadro() {
    const t = TILT + pitch;
    cT = Math.cos(t); sT = Math.sin(t); cY = Math.cos(yaw); sY = Math.sin(yaw);
  }

  // altura das "ondas de informação" (x, y já relativos ao centro)
  function altura(x, y, dB) {
    let z = Math.sin(x * 0.28 + T * 0.9) * Math.cos(y * 0.24 + T * 0.7) * 0.9 + Math.sin((x + y) * 0.16 - T * 1.2) * 0.5;
    const R = (T * 7) % 46;
    const d = dB - R;
    if (d > -8 && d < 8) z += Math.exp(-(d * d) / 6) * 1.6 * (1 - R / 46);
    return z;
  }

  const saida = [0, 0, 0];
  function projetar(x, y, z) {
    const X = x * escala, Y = y * escala, Z = z * escala;
    const Y1 = Y * cT + Z * sT;
    const Z1 = -Y * sT + Z * cT;
    const X2 = X * cY + Z1 * sY;
    const Z2 = -X * sY + Z1 * cY;
    const X3 = X2 * cZ - Y1 * sZ;
    const Y3 = X2 * sZ + Y1 * cZ;
    const D = escala * 70;
    const f = D / (D - Z2);
    saida[0] = W * 0.5 + X3 * f; saida[1] = H * 0.5 - Y3 * f; saida[2] = f;
    return saida;
  }
  const P = (lon, lat, z) => projetar(lon - CENTRO[0], lat - CENTRO[1], z);

  function desenhar() {
    prepararQuadro();
    ctx.clearRect(0, 0, W, H);
    ctx.globalCompositeOperation = 'source-over';

    // placa (extrusão do contorno) — 4 camadas
    for (let k = 4; k >= 0; k--) {
      ctx.beginPath();
      for (let i = 0; i < CONTORNO.length; i++) {
        const [x, y] = P(CONTORNO[i][0], CONTORNO[i][1], -1.4 - k * 0.35);
        i ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
      }
      ctx.closePath();
      ctx.strokeStyle = paleta.linha + (k === 0 ? (paleta.claro ? 0.35 : 0.28) : 0.06) + ')';
      ctx.lineWidth = dpr * (k === 0 ? 1 : 0.8);
      ctx.stroke();
    }

    // conexões entre capitais (pares pré-calculados)
    ctx.lineWidth = dpr * 0.7;
    ctx.strokeStyle = paleta.linha + (paleta.claro ? 0.16 : 0.1) + ')';
    ctx.beginPath();
    for (const [i, j] of pares) {
      let [x, y] = P(CAPITAIS[i][0], CAPITAIS[i][1], -1.4); ctx.moveTo(x, y);
      [x, y] = P(CAPITAIS[j][0], CAPITAIS[j][1], -1.4); ctx.lineTo(x, y);
    }
    ctx.stroke();

    // arcos Brasília → capitais, com pulso viajando
    ctx.globalCompositeOperation = paleta.modo;
    const [b0, b1] = CAPITAIS[0];
    ctx.lineWidth = dpr * 0.8;
    for (let i = 1; i < CAPITAIS.length; i += 2) {
      const [c0, c1] = CAPITAIS[i];
      const alturaArco = 1.2 + Math.hypot(c0 - b0, c1 - b1) * 0.22;
      const seg = 16, fase = (T * 0.16 + i * 0.137) % 1;
      let px = 0, py = 0;
      ctx.beginPath();
      for (let s = 0; s <= seg; s++) {
        const u = s / seg;
        const [x, y] = P(b0 + (c0 - b0) * u, b1 + (c1 - b1) * u, -1.4 + Math.sin(Math.PI * u) * alturaArco);
        s ? ctx.lineTo(x, y) : ctx.moveTo(x, y);
        if (Math.abs(u - fase) < 1 / seg) { px = x; py = y; }
      }
      const cc = cor(c0, c1);
      ctx.strokeStyle = `rgba(${cc[0] | 0},${cc[1] | 0},${cc[2] | 0},${paleta.claro ? 0.35 : 0.22})`;
      ctx.stroke();
      if (px) { ctx.fillStyle = `rgba(${cc[0] | 0},${cc[1] | 0},${cc[2] | 0},0.95)`; ctx.fillRect(px - dpr * 2, py - dpr * 2, dpr * 4, dpr * 4); }
    }

    // nuvem de pontos: agrupada por estilo → uma chamada fill() por grupo
    for (const b of baldes) b.length = 0;
    for (const p of pontos) {
      const z = altura(p.x, p.y, p.dB);
      const [x, y, f] = projetar(p.x, p.y, z);
      const brilho = Math.min(0.999, Math.max(0, 0.55 + z * 0.35));
      const a = Math.min(NALFA - 1, (brilho * NALFA) | 0);
      const r = dpr * f * (1.25 + (z > 0 ? z * 0.8 : 0) + p.r * 0.5);
      baldes[p.k * NALFA + a].push(x - r / 2, y - r / 2, r);
    }
    for (let e = 0; e < baldes.length; e++) {
      const b = baldes[e];
      if (!b.length) continue;
      ctx.fillStyle = estilos[e];
      ctx.beginPath();
      for (let i = 0; i < b.length; i += 3) ctx.rect(b[i], b[i + 1], b[i + 2], b[i + 2]);
      ctx.fill();
    }

    // capitais (anéis)
    ctx.globalCompositeOperation = 'source-over';
    ctx.lineWidth = dpr * 0.9;
    ctx.strokeStyle = paleta.linha + '0.55)';
    ctx.beginPath();
    for (let i = 1; i < CAPITAIS.length; i++) {
      const x0 = CAPITAIS[i][0] - CENTRO[0], y0 = CAPITAIS[i][1] - CENTRO[1];
      const [x, y] = projetar(x0, y0, altura(x0, y0, 99));
      ctx.moveTo(x + dpr * 2.6, y); ctx.arc(x, y, dpr * 2.6, 0, Math.PI * 2);
    }
    ctx.stroke();
    const [bx, by] = P(b0, b1, altura(b0 - CENTRO[0], b1 - CENTRO[1], 0));
    ctx.strokeStyle = paleta.claro ? '#302681' : '#ffcb00';
    ctx.lineWidth = dpr * 1.4;
    ctx.beginPath(); ctx.arc(bx, by, dpr * 5, 0, Math.PI * 2); ctx.stroke();
  }

  function quadro(agora) {
    raf = 0;
    if (!rodando) return;
    raf = requestAnimationFrame(quadro);
    if (agora - ultimo < INTERVALO) return;       // limita a taxa de quadros
    const passo = Math.min(3, (agora - ultimo) / 16.7 || 1);
    ultimo = agora;
    T += 0.0105 * passo;
    yaw += (alvoYaw + Math.sin(T * 0.12) * 0.1 - yaw) * 0.08 * passo;
    pitch += (alvoPitch - pitch) * 0.08 * passo;
    desenhar();
  }
  function iniciar() { if (!rodando && !reduzido && visivel && !document.hidden) { rodando = true; ultimo = 0; raf = requestAnimationFrame(quadro); } }
  function parar() { rodando = false; if (raf) cancelAnimationFrame(raf); raf = 0; }

  lerPaleta();
  medir();
  desenhar();
  canvas.classList.add('is-pronto');

  let tRes = 0;
  new ResizeObserver(() => { clearTimeout(tRes); tRes = setTimeout(() => { medir(); desenhar(); }, 150); }).observe(canvas);
  new IntersectionObserver(([e]) => { visivel = e.isIntersecting; visivel ? iniciar() : parar(); }, { rootMargin: '-10% 0px' }).observe(canvas);
  document.addEventListener('visibilitychange', () => (document.hidden ? parar() : iniciar()));
  document.addEventListener('votai:tema', () => { lerPaleta(); desenhar(); });

  if (!reduzido && !leve && matchMedia('(pointer: fine)').matches) {
    window.addEventListener('pointermove', (e) => {
      alvoYaw = (e.clientX / innerWidth - 0.5) * 0.55;
      alvoPitch = (e.clientY / innerHeight - 0.5) * -0.16;
    }, { passive: true });
  }
  iniciar();
}
