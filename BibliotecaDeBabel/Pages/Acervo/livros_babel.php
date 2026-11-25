<?php
require_once "../../Includes/aviso.php";
require_once "../../Includes/babel_core.php";
require_once "../../Includes/autor_avatar.php";
date_default_timezone_set("America/Sao_Paulo");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../index.php");
    exit;
}

$is_admin = (($_SESSION["tipo_usuario"] ?? "") === "admin");

/*
 * BIBLIOTECA DE BABEL — o acervo completo.
 * Todas as 29^3200 páginas existem. Aqui só se calcula; nada é indexado.
 * Registros de tbl_livro sobrepõem título/autor/ano; o conteúdo é sempre o procedural.
 */

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "db_biblioteca_de_babel");

if ($conn->connect_error) {
    http_response_code(500);
    die("Erro ao conectar ao banco de dados. Verifique a configuração do MySQL.");
}
$conn->set_charset("utf8mb4");

function babel_h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function babel_link(string $livro, int $pagina, array $extra = []): string {
    $url = "livros_babel.php?livro=" . $livro . "&pagina=" . $pagina;
    foreach ($extra as $k => $v) {
        $url .= "&" . $k . "=" . (int)$v;
    }
    return $url;
}

$mensagem = "";
$tipoMensagem = "atencao";
if (isset($_SESSION["mensagem_babel"])) {
    [$mensagem, $tipoMensagem] = $_SESSION["mensagem_babel"];
    unset($_SESSION["mensagem_babel"]);
}

$termoBusca = (string)($_POST["termo"] ?? "");   // sem trim: espaços das pontas fazem parte de uma página
$modoBusca = $_POST["modo"] ?? "aleatorio";
if (!in_array($modoBusca, ["inicio", "aleatorio", "pagina"], true)) {
    $modoBusca = "aleatorio";
}
$sementeBusca = substr(trim($_POST["semente"] ?? ""), 0, 64);

/* ---------------- Ações POST ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "buscar") {
        try {
            $r = babel_buscar_trecho($termoBusca, $modoBusca, $sementeBusca);
        } catch (Throwable $e) {
            $r = ["ok" => false, "erro" => "Não foi possível calcular o endereço deste texto."];
        }
        if ($r["ok"]) {
            header("Location: " . babel_link(babel_str($r["numero"]), $r["pagina"], ["dest" => $r["posicao"], "tam" => $r["tamanho"]]));
            exit;
        }
        $mensagem = $r["erro"];
    }

    if ($acao === "registrar" && $is_admin) {
        $numeroReg = babel_parse_numero($_POST["livro"] ?? "");
        $paginaReg = max(1, (int)($_POST["pagina"] ?? 1));
        $titulo = trim($_POST["titulo"] ?? "");
        $anoTexto = trim($_POST["ano_publicacao"] ?? "");
        $idAutor = (int)($_POST["id_autor"] ?? 0);
        $ano = ($anoTexto === "") ? null : (int)$anoTexto;

        if ($numeroReg === null) {
            $mensagem = "Número de livro inválido.";
        } elseif ($titulo === "" || mb_strlen($titulo, "UTF-8") > 150 || $idAutor <= 0) {
            $mensagem = "Informe o título (até 150 caracteres) e selecione um autor.";
        } elseif ($anoTexto !== "" && (!ctype_digit($anoTexto) || $ano > (int)date("Y"))) {
            $mensagem = "Informe um ano de publicação válido.";
        } else {
            $res = babel_registrar_livro($conn, babel_str($numeroReg), $titulo, $ano, $idAutor);
            $_SESSION["mensagem_babel"] = [$res["msg"], $res["ok"] ? "sucesso" : "erro"];
            header("Location: " . babel_link(babel_str($numeroReg), $paginaReg));
            exit;
        }
    }
}

/* ---------------- Navegação (GET) ---------------- */
if (isset($_GET["aleatorio"])) {
    header("Location: " . babel_link(babel_str(babel_numero_aleatorio()), 1));
    exit;
}

$numero = babel_parse_numero($_GET["livro"] ?? "1");
if ($numero === null) {
    $numero = gmp_init(1);
    $mensagem = "Número de livro inválido. O acervo vai de 1 até um número de " . strlen(babel_str(babel_total_livros())) . " dígitos.";
}

/* Endereço físico: hexágono + parede + estante + posição. */
if (isset($_GET["hexagono"])) {
    $hexTexto = preg_replace('/[\s._]/', '', (string)$_GET["hexagono"]);
    $parede = (int)($_GET["parede"] ?? 1);
    $estante = (int)($_GET["estante"] ?? 1);
    $posicao = (int)($_GET["posicao"] ?? 1);
    if ($hexTexto !== "" && ctype_digit($hexTexto) && strlen($hexTexto) <= BABEL_NUMERO_MAX_DIGITOS
        && gmp_cmp(gmp_init($hexTexto, 10), 1) >= 0
        && $parede >= 1 && $parede <= BABEL_PAREDES
        && $estante >= 1 && $estante <= BABEL_ESTANTES_POR_PAREDE
        && $posicao >= 1 && $posicao <= BABEL_LIVROS_POR_ESTANTE) {
        $candidato = babel_numero_do_endereco(gmp_init($hexTexto, 10), $parede, $estante, $posicao);
        if (gmp_cmp($candidato, babel_total_livros()) <= 0) {
            $numero = $candidato;
            $mensagem = "";
        } else {
            $mensagem = "Esse hexágono está além do fim da biblioteca.";
        }
    } else {
        $mensagem = "Endereço inválido: use hexágono (inteiro ≥ 1), parede 1–4, estante 1–5 e posição 1–32.";
    }
}

$numeroTexto = babel_str($numero);
$paginasDoLivro = babel_paginas_do_livro($numero);
$pagina = (int)($_GET["pagina"] ?? 1);
if ($pagina < 1 || $pagina > $paginasDoLivro) {
    $pagina = 1;
}

/* Destaque do trecho encontrado (posição e tamanho dentro da página). */
$dest = isset($_GET["dest"]) ? (int)$_GET["dest"] : -1;
$tam = isset($_GET["tam"]) ? (int)$_GET["tam"] : 0;
$temDestaque = ($dest >= 0 && $tam >= 1 && $dest + $tam <= BABEL_CARACTERES_PAGINA);

$exib = babel_exibicao($conn, $numeroTexto);
$conteudoPagina = babel_gerar_pagina($numero, $pagina);
$endereco = babel_endereco($numero);
$hexagonoTexto = babel_str($endereco["hexagono"]);

if ($temDestaque) {
    $ini = $dest + intdiv($dest, BABEL_CARACTERES_LINHA);
    $ultimo = $dest + $tam - 1;
    $fim = $ultimo + intdiv($ultimo, BABEL_CARACTERES_LINHA) + 1;
    $paginaHtml = babel_h(substr($conteudoPagina, 0, $ini))
        . "<mark>" . babel_h(substr($conteudoPagina, $ini, $fim - $ini)) . "</mark>"
        . babel_h(substr($conteudoPagina, $fim));
} else {
    $paginaHtml = babel_h($conteudoPagina);
}

$temAnterior = gmp_cmp($numero, 1) > 0;
$temProximo = gmp_cmp($numero, babel_total_livros()) < 0;
$livroAnterior = $temAnterior ? babel_str(gmp_sub($numero, 1)) : $numeroTexto;
$proximoLivro = $temProximo ? babel_str(gmp_add($numero, 1)) : $numeroTexto;

$autoresTbl = $is_admin && !$exib["registrado"] ? $conn->query("SELECT id_autor, nome_autor FROM tbl_autor ORDER BY nome_autor") : false;
$digitosTotal = strlen(babel_str(babel_total_livros()));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Biblioteca de Babel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../Styles/default.css">
  <style>
    .babel-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);gap:1.25rem}
    .babel-panel{background:rgba(10,10,18,.45);border:1px solid var(--cosmos-border);border-radius:var(--radius-lg);padding:1.25rem;margin-bottom:1.25rem}
    .babel-panel h2,.babel-panel h3{margin-top:0}
    .babel-meta{display:flex;flex-wrap:wrap;gap:.55rem;margin:.8rem 0}
    .babel-chip{display:inline-block;padding:.35rem .65rem;border:1px solid var(--cosmos-border);border-radius:var(--radius-sm);font:.8rem var(--font-mono);color:var(--cosmos-yellow)}
    .babel-book{white-space:pre;overflow-x:auto;background:var(--cosmos-surface);border:1px solid var(--cosmos-border);border-radius:var(--radius-md);padding:1rem;font:.78rem/1.55 var(--font-mono);color:var(--cosmos-white);max-width:100%}
    .babel-book mark{background:var(--cosmos-orange);color:#000;border-radius:2px}
    .babel-form{display:grid;gap:.7rem}
    .babel-inline{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem}
    .babel-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
    .babel-nav a{padding:.55rem .8rem;border:1px solid var(--cosmos-border);border-radius:var(--radius-md);color:var(--cosmos-white)}
    .babel-nav a:hover{border-color:var(--cosmos-orange)}
    .babel-monkey{display:flex;gap:1rem;align-items:center}
    .babel-avatar{width:76px;height:76px;flex:0 0 76px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,var(--cosmos-yellow),var(--cosmos-orange));font-size:2.5rem;border:2px solid var(--cosmos-border);overflow:hidden}
    .babel-avatar img{width:100%;height:100%;object-fit:cover}
    .babel-muted{color:var(--cosmos-muted);font-size:.9rem}
    .babel-endereco{font:.72rem/1.4 var(--font-mono);word-break:break-all;width:100%;min-height:5rem}
    @media(max-width:850px){.babel-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php include "../../Includes/header.php"; ?>

<main class="content">

  <!-- CABEÇALHO -->
  <section class="test-block">
    <h1>Biblioteca de Babel</h1>
    <p>Todas as páginas possíveis existem. Os livros registrados apenas recebem um título, um autor e um ano; o texto é sempre o mesmo.</p>
    <div class="babel-meta">
      <span class="babel-chip">LIVROS: 1 … (número de <?php echo number_format($digitosTotal, 0, ",", "."); ?> dígitos)</span>
      <span class="babel-chip">29 símbolos</span>
      <span class="babel-chip">410 páginas · 40 linhas · 80 colunas</span>
    </div>
    <?php echo aviso_html($mensagem, $tipoMensagem); ?>
  </section>

  <!-- 1. LEITURA: a página à esquerda; o livro (autor, endereço, registro) à direita -->
  <div class="babel-grid">

    <section class="babel-panel">
      <h2><?php echo babel_h($exib["titulo"]); ?></h2>
      <div class="babel-meta">
        <span class="babel-chip">Livro #<?php echo babel_h(babel_abreviar($numeroTexto)); ?></span>
        <span class="babel-chip">Página <?php echo $pagina; ?>/<?php echo $paginasDoLivro; ?></span>
        <span class="babel-chip"><?php echo $exib["registrado"] ? "Registrado" : "Procedural"; ?></span>
        <?php if ($exib["ano"] !== null): ?><span class="babel-chip"><?php echo (int)$exib["ano"]; ?></span><?php endif; ?>
      </div>
      <div class="babel-book"><?php echo $paginaHtml; ?></div>
      <?php if ($temDestaque): ?>
        <p class="babel-muted">O trecho pesquisado está destacado. Esta página sempre existiu: a pesquisa só calculou o endereço dela.</p>
      <?php endif; ?>
      <div class="babel-nav">
        <?php if ($pagina > 1): ?><a href="<?php echo babel_link($numeroTexto, $pagina - 1); ?>">← Página anterior</a><?php endif; ?>
        <?php if ($pagina < $paginasDoLivro): ?><a href="<?php echo babel_link($numeroTexto, $pagina + 1); ?>">Próxima página →</a><?php endif; ?>
        <?php if ($temAnterior): ?><a href="<?php echo babel_link($livroAnterior, 1); ?>">← Livro anterior</a><?php endif; ?>
        <?php if ($temProximo): ?><a href="<?php echo babel_link($proximoLivro, 1); ?>">Próximo livro →</a><?php endif; ?>
        <a href="livros_babel.php?aleatorio=1">Livro aleatório ↯</a>
      </div>
    </section>

    <aside>
      <section class="babel-panel">
        <h2>Autor</h2>
        <?php if ($exib["registrado"]): ?>
          <div class="babel-monkey">
            <div><?php echo autor_avatar_html((int)$exib["autor_id"], $exib["autor_nome"], 76); ?></div>
            <div>
              <strong><?php echo babel_h($exib["autor_nome"]); ?></strong>
              <div class="babel-muted">Autor registrado</div>
            </div>
          </div>
          <p class="babel-muted">Registro nº <?php echo (int)$exib["id_livro"]; ?>. Para alterar título, autor ou ano: <a href="livros_gerencia.php?editar=<?php echo (int)$exib["id_livro"]; ?>">Gerenciar Acervo</a>.</p>
        <?php else: $ab = $exib["autor_babel"]; ?>
          <div class="babel-monkey">
            <div class="babel-avatar">
              <?php if (!empty($ab["foto_path"])): ?>
                <img src="<?php echo babel_h("../../" . ltrim($ab["foto_path"], "/")); ?>" alt="Retrato de <?php echo babel_h($ab["nome_autor"]); ?>">
              <?php else: ?>
                <?php echo autor_avatar_html((int)$ab["id_autor_babel"], $ab["nome_autor"], 76) ?: "🐒"; ?>
              <?php endif; ?>
            </div>
            <div>
              <strong><?php echo babel_h($ab["nome_autor"]); ?></strong>
              <div class="babel-muted"><?php echo babel_h($ab["especie"] ?? "Primata"); ?></div>
              <div class="babel-muted">Autor-macaco #<?php echo (int)$ab["id_autor_babel"]; ?></div>
            </div>
          </div>
          <p><?php echo babel_h($ab["descricao"] ?? "Um dos incontáveis autores da biblioteca."); ?></p>
          <p class="babel-muted">Autor procedural: vale até que um registro sobreponha estes dados.</p>
        <?php endif; ?>
      </section>

      <section class="babel-panel">
        <h3>Endereço físico</h3>
        <p>Hexágono: <strong><?php echo babel_h(babel_abreviar($hexagonoTexto)); ?></strong></p>
        <p>Parede: <strong><?php echo (int)$endereco["parede"]; ?></strong> de 4</p>
        <p>Estante: <strong><?php echo (int)$endereco["estante"]; ?></strong> de 5</p>
        <p>Posição: <strong><?php echo (int)$endereco["posicao"]; ?></strong> de 32</p>
        <details>
          <summary>Número completo do livro (<?php echo strlen($numeroTexto); ?> dígitos)</summary>
          <textarea class="babel-endereco" readonly rows="4"><?php echo babel_h($numeroTexto); ?></textarea>
        </details>
      </section>

      <?php if ($is_admin && !$exib["registrado"]): ?>
      <section class="babel-panel">
        <h3>Registrar este livro</h3>
        <p class="babel-muted">Cria um registro que sobrepõe título, autor e ano deste número. O texto não muda.</p>
        <form class="babel-form" method="POST" action="livros_babel.php">
          <input type="hidden" name="acao" value="registrar">
          <input type="hidden" name="livro" value="<?php echo babel_h($numeroTexto); ?>">
          <input type="hidden" name="pagina" value="<?php echo $pagina; ?>">
          <label for="reg_titulo">Título</label>
          <input id="reg_titulo" type="text" name="titulo" maxlength="150" required value="<?php echo babel_h($exib["titulo"]); ?>">
          <label for="reg_ano">Ano de publicação</label>
          <input id="reg_ano" type="number" name="ano_publicacao" min="0" max="<?php echo date("Y"); ?>" value="<?php echo babel_h($exib["ano"] ?? ""); ?>">
          <label for="reg_autor">Autor</label>
          <select id="reg_autor" name="id_autor" required>
            <option value="">Selecione um autor</option>
            <?php while ($autoresTbl && ($a = $autoresTbl->fetch_assoc())): ?>
              <option value="<?php echo (int)$a["id_autor"]; ?>"><?php echo babel_h($a["nome_autor"]); ?></option>
            <?php endwhile; ?>
          </select>
          <button type="submit">Registrar</button>
        </form>
      </section>
      <?php endif; ?>
    </aside>

  </div>

  <!-- 2. LOCALIZAR: pesquisar um texto ou ir direto a um endereço -->
  <div class="grid-2">

    <section class="babel-panel">
      <h2>Pesquisar um texto</h2>
      <p>Digite uma frase: o sistema calcula o endereço de uma página que a contém. Não depende de páginas já visitadas.</p>
      <form class="babel-form" method="POST" action="livros_babel.php">
        <input type="hidden" name="acao" value="buscar">
        <label for="termo">Texto a procurar</label>
        <textarea id="termo" name="termo" rows="4" placeholder="Ex.: o macaco infinito escreveu hamlet."><?php echo babel_h($termoBusca); ?></textarea>
        <label for="modo">Onde colocar o trecho na página</label>
        <select id="modo" name="modo">
          <option value="aleatorio" <?php echo $modoBusca === "aleatorio" ? "selected" : ""; ?>>No meio do ruído (posição e ruído vêm da semente)</option>
          <option value="inicio" <?php echo $modoBusca === "inicio" ? "selected" : ""; ?>>No início, resto da página em branco</option>
          <option value="pagina" <?php echo $modoBusca === "pagina" ? "selected" : ""; ?>>Página inteira (40 linhas de 80 símbolos)</option>
        </select>
        <label for="semente">Semente (opcional: sementes diferentes dão endereços diferentes para o mesmo trecho)</label>
        <input id="semente" type="text" name="semente" maxlength="64" value="<?php echo babel_h($sementeBusca); ?>">
        <button type="submit">Calcular endereço</button>
      </form>
      <p class="babel-muted">O texto é normalizado: minúsculas, sem acentos; o que não for a–z, espaço, vírgula ou ponto vira espaço (! e ? viram ponto; ; e : viram vírgula).</p>
    </section>

    <section class="babel-panel">
      <h2>Ir para um endereço</h2>
      <form class="babel-form" method="GET" action="livros_babel.php">
        <label for="livro">Número do livro</label>
        <textarea id="livro" name="livro" rows="2" class="babel-endereco" placeholder="1"><?php echo babel_h($numeroTexto); ?></textarea>
        <label for="pagina">Página (1 a 410)</label>
        <input id="pagina" type="number" name="pagina" min="1" max="410" value="<?php echo $pagina; ?>">
        <button type="submit">Abrir livro e página</button>
      </form>
      <hr>
      <form class="babel-form" method="GET" action="livros_babel.php">
        <label for="hexagono">…ou pelo endereço físico</label>
        <textarea id="hexagono" name="hexagono" rows="2" class="babel-endereco" placeholder="Hexágono"><?php echo babel_h($hexagonoTexto); ?></textarea>
        <div class="babel-inline">
          <div><label for="parede">Parede (1–4)</label><input id="parede" type="number" name="parede" min="1" max="4" value="<?php echo (int)$endereco["parede"]; ?>"></div>
          <div><label for="estante">Estante (1–5)</label><input id="estante" type="number" name="estante" min="1" max="5" value="<?php echo (int)$endereco["estante"]; ?>"></div>
          <div><label for="posicao">Posição (1–32)</label><input id="posicao" type="number" name="posicao" min="1" max="32" value="<?php echo (int)$endereco["posicao"]; ?>"></div>
        </div>
        <input type="hidden" name="pagina" value="1">
        <button type="submit">Ir ao endereço físico</button>
      </form>
    </section>

  </div>

  <!-- 3. SOBRE: recolhido por padrão -->
  <details>
    <summary>Como funciona</summary>
    <ul>
      <li>Existem 29<sup>3200</sup> páginas distintas e cada uma tem um único endereço (livro e página).</li>
      <li>O texto de uma página é uma função reversível do endereço (rede de Feistel sobre SHA-256): do endereço sai o texto, e do texto sai o endereço.</li>
      <li>Por isso a pesquisa é só uma conta: ela escreve a página desejada e calcula o endereço. A busca por um trecho acha uma entre incontáveis páginas que o contêm.</li>
      <li>Um livro são 410 páginas de índices consecutivos; o último livro da biblioteca tem só 1 página.</li>
      <li>Título, autor e ano de qualquer livro são procedurais até que um registro em <code>tbl_livro</code> os sobreponha.</li>
    </ul>
  </details>

</main>
</body>
</html>
<?php
$conn->close();
?>