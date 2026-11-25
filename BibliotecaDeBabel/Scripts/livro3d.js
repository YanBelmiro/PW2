import * as THREE from "three";

/* ==========================================================================
   LIVRO 3D - Biblioteca de Babel
   - Capa, contracapa e lombada são desenhadas em <canvas> 2D, de forma
     determinística (mesmo livro => mesma capa), sem salvar nada no banco.
   - A paleta é lida das variáveis do default.css (nada de cor "inventada").
   - Um único contexto WebGL, dentro do <dialog id="livro-3d">.
   ========================================================================== */

/* ---------- Paleta (vem do default.css) ---------- */
const css = getComputedStyle(document.documentElement);
const cor = (nome, reserva) => css.getPropertyValue(nome).trim() || reserva;

const PALETA = [
  cor("--cosmos-blue", "#1800ad"),
  cor("--cosmos-red", "#ff3131"),
  cor("--cosmos-orange", "#ff751f"),
  cor("--cosmos-yellow", "#ffde59"),
];
const FUNDO = cor("--cosmos-surface-elevated", "#12121f");
const BRANCO = cor("--cosmos-white", "#ffffff");
const MUTED = cor("--cosmos-muted", "rgba(255,255,255,0.65)");
const BORDA = cor("--cosmos-border", "rgba(255,255,255,0.14)");
const FONTE = "Montserrat, sans-serif";

/* ---------- Determinismo ---------- */
function hash32(texto) {
  // FNV-1a
  let h = 2166136261;
  for (let i = 0; i < texto.length; i++) {
    h ^= texto.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

function rng(semente) {
  // mulberry32
  let s = semente | 0;
  return () => {
    s = (s + 0x6d2b79f5) | 0;
    let t = Math.imul(s ^ (s >>> 15), 1 | s);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function estilo(livro) {
  const seed = hash32(`${livro.autor}|${livro.titulo}|${livro.id}`);
  const ia = seed % PALETA.length;
  const ib = (ia + 1 + ((seed >>> 8) % (PALETA.length - 1))) % PALETA.length;
  return { seed, ia, a: PALETA[ia], b: PALETA[ib] };
}

/* ---------- Helpers de desenho ---------- */
function hexagono(ctx, cx, cy, r, rot) {
  ctx.beginPath();
  for (let i = 0; i < 6; i++) {
    const ang = rot + (i * Math.PI) / 3;
    const x = cx + r * Math.cos(ang);
    const y = cy + r * Math.sin(ang);
    if (i === 0) ctx.moveTo(x, y);
    else ctx.lineTo(x, y);
  }
  ctx.closePath();
}

function quebrar(ctx, texto, larguraMax) {
  const linhas = [];
  let atual = "";
  for (const palavra of texto.split(/\s+/)) {
    const teste = atual ? `${atual} ${palavra}` : palavra;
    if (!atual || ctx.measureText(teste).width <= larguraMax) {
      atual = teste;
    } else {
      linhas.push(atual);
      atual = palavra;
    }
  }
  if (atual) linhas.push(atual);
  return linhas;
}

// Escolhe o maior tamanho que cabe; se nenhum couber, corta com reticências.
function encaixar(ctx, texto, larguraMax, peso, tamanhos) {
  for (const tam of tamanhos) {
    ctx.font = `${peso} ${tam}px ${FONTE}`;
    if (ctx.measureText(texto).width <= larguraMax) return texto;
  }
  while (texto.length > 1 && ctx.measureText(`${texto}…`).width > larguraMax) {
    texto = texto.slice(0, -1);
  }
  return `${texto}…`;
}

/* ---------- As três peças da capa ---------- */
// Frente: espaço de desenho 600 x 900
function desenharFrente(ctx, livro) {
  const { seed, a, b } = estilo(livro);
  const rnd = rng(seed);

  ctx.fillStyle = FUNDO;
  ctx.fillRect(0, 0, 600, 900);

  // Hexágonos: alusão às galerias da Biblioteca de Babel
  const cx = 300 + (rnd() - 0.5) * 120;
  const cy = 290 + (rnd() - 0.5) * 60;
  const rot = rnd() * Math.PI;
  ctx.lineWidth = 8;
  [[210, a], [150, b], [90, a]].forEach(([raio, cor_], i) => {
    ctx.strokeStyle = cor_;
    hexagono(ctx, cx, cy, raio, rot + i * 0.35);
    ctx.stroke();
  });

  ctx.strokeStyle = BORDA;
  ctx.lineWidth = 2;
  ctx.strokeRect(16, 16, 568, 868);

  // Título
  ctx.textAlign = "left";
  ctx.textBaseline = "alphabetic";
  ctx.fillStyle = BRANCO;
  let tamanho = 32;
  let linhas = [];
  for (const tam of [60, 52, 44, 38, 32]) {
    ctx.font = `700 ${tam}px ${FONTE}`;
    linhas = quebrar(ctx, livro.titulo, 504);
    tamanho = tam;
    if (linhas.length <= 4) break;
  }
  linhas.slice(0, 5).forEach((linha, i) => ctx.fillText(linha, 48, 580 + i * tamanho * 1.2));

  // Autor e ano
  ctx.fillStyle = a;
  ctx.fillText(encaixar(ctx, livro.autor, 504, "600", [28, 24, 20]), 48, 838);

  if (livro.ano) {
    ctx.fillStyle = MUTED;
    ctx.font = `400 22px ${FONTE}`;
    ctx.fillText(livro.ano, 48, 868);
  }
}

// Verso: espaço de desenho 600 x 900
function desenharVerso(ctx, livro) {
  const { seed, a, b } = estilo(livro);

  ctx.fillStyle = FUNDO;
  ctx.fillRect(0, 0, 600, 900);

  ctx.globalAlpha = 0.18;
  ctx.strokeStyle = b;
  ctx.lineWidth = 10;
  hexagono(ctx, 300, 330, 230, rng(seed)() * Math.PI);
  ctx.stroke();
  ctx.globalAlpha = 1;

  ctx.strokeStyle = BORDA;
  ctx.lineWidth = 2;
  ctx.strokeRect(16, 16, 568, 868);

  ctx.textAlign = "left";
  ctx.textBaseline = "alphabetic";
  ctx.fillStyle = a;
  ctx.font = `700 24px ${FONTE}`;
  ctx.fillText("BIBLIOTECA DE BABEL", 48, 100);

  const dados = [
    ["Autor", livro.autor],
    ["Publicação", livro.ano || "Não informado"],
    ["Registro", `Nº ${String(livro.id).padStart(6, "0")}`],
    ["Situação", livro.disp],
  ];
  dados.forEach(([rotulo, valor], i) => {
    const y = 200 + i * 110;
    ctx.fillStyle = MUTED;
    ctx.font = `400 22px ${FONTE}`;
    ctx.fillText(rotulo, 48, y);
    ctx.fillStyle = BRANCO;
    ctx.fillText(encaixar(ctx, valor, 504, "600", [34, 30, 26, 22]), 48, y + 42);
  });

  // Código de barras decorativo, também determinístico
  const barras = rng(seed ^ 0x9e3779b9);
  ctx.fillStyle = BRANCO;
  ctx.fillRect(348, 760, 204, 100);
  ctx.fillStyle = FUNDO;
  let x = 360;
  while (x < 536) {
    const largura = 2 + Math.floor(barras() * 4);
    ctx.fillRect(x, 772, largura, 76);
    x += largura + 2 + Math.floor(barras() * 4);
  }
}

// Lombada: espaço de desenho 132 x 900 (texto na vertical, de cima para baixo)
function desenharLombada(ctx, livro) {
  const { ia, a } = estilo(livro);

  ctx.fillStyle = a;
  ctx.fillRect(0, 0, 132, 900);

  ctx.strokeStyle = FUNDO;
  ctx.lineWidth = 4;
  ctx.beginPath();
  ctx.moveTo(0, 56);
  ctx.lineTo(132, 56);
  ctx.moveTo(0, 844);
  ctx.lineTo(132, 844);
  ctx.stroke();

  ctx.save();
  ctx.translate(66, 450);
  ctx.rotate(Math.PI / 2);
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  // laranja e amarelo pedem texto escuro; azul e vermelho, texto claro
  ctx.fillStyle = ia >= 2 ? FUNDO : BRANCO;
  ctx.fillText(encaixar(ctx, livro.titulo, 740, "700", [48, 42, 36, 30, 24]), 0, 0);
  ctx.restore();
}

function pintar(canvas, desenhar, livro, baseW, baseH) {
  const ctx = canvas.getContext("2d");
  ctx.save();
  ctx.scale(canvas.width / baseW, canvas.height / baseH);
  desenhar(ctx, livro);
  ctx.restore();
}

function lerLivro(figura) {
  const d = figura.dataset;
  return { id: d.id, titulo: d.titulo, autor: d.autor, ano: d.ano, disp: d.disp };
}

async function prepararFontes() {
  try {
    await Promise.all([
      document.fonts.load(`700 40px Montserrat`),
      document.fonts.load(`600 40px Montserrat`),
      document.fonts.load(`400 20px Montserrat`),
    ]);
  } catch (erro) {
    // segue com a fonte de reserva
  }
}

/* ---------- Capas 2D nas fichas (pintadas quando se aproximam da tela) ---------- */
await prepararFontes();

const fichas = [...document.querySelectorAll("[data-livro]")];

const observadorCapas = new IntersectionObserver(
  (entradas) => {
    for (const entrada of entradas) {
      if (!entrada.isIntersecting) continue;
      const canvas = entrada.target.querySelector(".livro-capa-2d");
      if (canvas) pintar(canvas, desenharFrente, lerLivro(entrada.target), 600, 900);
      observadorCapas.unobserve(entrada.target);
    }
  },
  { rootMargin: "300px 0px" }
);
fichas.forEach((f) => observadorCapas.observe(f));

/* ==========================================================================
   LIVRO 3D COMPARTILHADO
   Só existe UM contexto WebGL na página (navegadores limitam a ~16).
   Ele "mora" no palco da ficha que está no meio da tela; as demais
   mostram a capa 2D.
   ========================================================================== */
const LARGURA = 1;
const ALTURA = 1.5;
const ESPESSURA = 0.22;
const COR_PAGINAS = 0xd9d4c3;
const ESCALA_TEXTURA = 1.5; // 900 x 1350 px na capa
const reduzMovimento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

let renderer = null;
let scene = null;
let camera = null;
let livro3d = null;
let rafId = 0;
let ultimo = 0;
let arrastando = false;
let girandoSozinho = true;
let alvoY = null;
let ativa = null; // ficha que está com o 3D
let semWebgl = false;

function montarCena() {
  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.domElement.classList.add("livro-3d-canvas");

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(35, 1, 0.1, 50);
  camera.position.set(0, 0, 3.4);

  scene.add(new THREE.AmbientLight(0xffffff, 0.8));
  const luz = new THREE.DirectionalLight(0xffffff, 0.9);
  luz.position.set(2, 2, 4);
  scene.add(luz);

  // Ordem das faces do BoxGeometry: +x, -x, +y, -y, +z, -z
  //  +x/+y/-y = páginas | -x = lombada | +z = capa | -z = contracapa
  const paginas = () => new THREE.MeshStandardMaterial({ color: COR_PAGINAS, roughness: 0.9, metalness: 0 });
  const impresso = () => new THREE.MeshStandardMaterial({ roughness: 0.6, metalness: 0 });

  livro3d = new THREE.Mesh(
    new THREE.BoxGeometry(LARGURA, ALTURA, ESPESSURA),
    [paginas(), impresso(), paginas(), paginas(), impresso(), impresso()]
  );
  scene.add(livro3d);

  ligarArrasto(renderer.domElement);
}

function texturaDe(desenhar, livro, baseW, baseH, escala) {
  const canvas = document.createElement("canvas");
  canvas.width = Math.round(baseW * escala);
  canvas.height = Math.round(baseH * escala);
  pintar(canvas, desenhar, livro, baseW, baseH);

  const tex = new THREE.CanvasTexture(canvas);
  tex.colorSpace = THREE.SRGBColorSpace;
  tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
  return tex;
}

function aplicarLivro(livro) {
  const pecas = {
    4: texturaDe(desenharFrente, livro, 600, 900, ESCALA_TEXTURA),
    5: texturaDe(desenharVerso, livro, 600, 900, ESCALA_TEXTURA),
    1: texturaDe(desenharLombada, livro, 132, 900, 2),
  };

  for (const indice in pecas) {
    const material = livro3d.material[indice];
    if (material.map) material.map.dispose();
    material.map = pecas[indice];
    material.needsUpdate = true;
  }
}

function ligarArrasto(el) {
  let x0 = 0;
  let y0 = 0;

  el.addEventListener("pointerdown", (e) => {
    arrastando = true;
    girandoSozinho = false;
    alvoY = null;
    x0 = e.clientX;
    y0 = e.clientY;
    el.setPointerCapture(e.pointerId);
  });

  el.addEventListener("pointermove", (e) => {
    if (!arrastando) return;
    livro3d.rotation.y += (e.clientX - x0) * 0.01;
    livro3d.rotation.x = Math.max(-0.6, Math.min(0.6, livro3d.rotation.x + (e.clientY - y0) * 0.01));
    x0 = e.clientX;
    y0 = e.clientY;
  });

  const soltar = () => { arrastando = false; };
  el.addEventListener("pointerup", soltar);
  el.addEventListener("pointercancel", soltar);
}

// Ajusta renderer e câmera ao palco da ficha ativa, mantendo o livro inteiro visível.
function redimensionar() {
  if (!renderer || !ativa) return;
  const palco = ativa.querySelector(".livro-palco");
  const largura = palco.clientWidth;
  const altura = palco.clientHeight;
  if (!largura || !altura) return;

  renderer.setSize(largura, altura, false); // o CSS cuida do tamanho visual
  camera.aspect = largura / altura;

  const alturaVisivel = Math.max(1.95, 1.35 / camera.aspect);
  camera.position.z = alturaVisivel / (2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)));
  camera.updateProjectionMatrix();
}

const observadorPalco = new ResizeObserver(redimensionar);

function quadro(agora) {
  rafId = requestAnimationFrame(quadro);
  const dt = Math.min((agora - ultimo) / 1000, 0.1);
  ultimo = agora;

  if (!arrastando) {
    if (alvoY !== null) {
      const passo = Math.min(1, dt * 8);
      livro3d.rotation.y += (alvoY - livro3d.rotation.y) * passo;
      livro3d.rotation.x += (0 - livro3d.rotation.x) * passo;
    } else if (girandoSozinho) {
      livro3d.rotation.y += dt * 0.4;
    }
  }

  renderer.render(scene, camera);
}

function virarPara(base) {
  girandoSozinho = false;
  const voltas = Math.round((livro3d.rotation.y - base) / (2 * Math.PI));
  alvoY = base + voltas * 2 * Math.PI;
}

function desativar() {
  if (!ativa) return;
  ativa.classList.remove("ativo");
  observadorPalco.disconnect();
  renderer.domElement.remove();
  cancelAnimationFrame(rafId);
  rafId = 0;
  ativa = null;
}

function ativar(ficha) {
  if (!ficha || ficha === ativa || semWebgl) return;

  if (!renderer) {
    try {
      montarCena();
    } catch (erro) {
      semWebgl = true;
      document.documentElement.classList.add("sem-webgl");
      return;
    }
  }

  desativar();
  ativa = ficha;

  const palco = ficha.querySelector(".livro-palco");
  palco.appendChild(renderer.domElement);
  ficha.classList.add("ativo");

  aplicarLivro(lerLivro(ficha));
  livro3d.rotation.set(0, 0.45, 0);
  alvoY = null;
  girandoSozinho = !reduzMovimento;
  arrastando = false;

  observadorPalco.observe(palco);
  redimensionar();
  ultimo = performance.now();
  rafId = requestAnimationFrame(quadro);
}

/* ---------- Qual ficha fica com o 3D? A que cruza o meio da tela ---------- */
const observadorAtiva = new IntersectionObserver(
  (entradas) => {
    for (const entrada of entradas) {
      if (entrada.isIntersecting) ativar(entrada.target);
    }
  },
  { rootMargin: "-45% 0px -45% 0px" }
);
fichas.forEach((f) => observadorAtiva.observe(f));
if (fichas.length) ativar(fichas[0]);

/* ---------- Cliques: palco ativa a ficha; Capa/Verso viram o livro ---------- */
document.addEventListener("click", (e) => {
  const ficha = e.target.closest("[data-livro]");
  if (!ficha || !e.target.closest(".livro-palco")) return;

  ativar(ficha);

  const botao = e.target.closest("[data-acao]");
  if (botao && livro3d && ficha === ativa) {
    virarPara(botao.dataset.acao === "verso" ? Math.PI : 0);
  }
});