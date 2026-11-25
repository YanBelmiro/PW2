<?php
date_default_timezone_set("America/Sao_Paulo");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../index.php");
    exit;
}

/*
 * BIBLIOTECA DE BABEL
 * Universo inicial: livros 1 a 10.000.
 * O conteúdo é gerado deterministicamente e indexado quando a página é aberta.
 */

const BABEL_ALFABETO = "abcdefghijklmnopqrstuv,. ";
const BABEL_SIMBOLOS = 25;
const BABEL_PAGINAS = 410;
const BABEL_LINHAS = 40;
const BABEL_CARACTERES_LINHA = 80;
const BABEL_CARACTERES_PAGINA = 3200;
const BABEL_PAREDES = 4;
const BABEL_ESTANTES_POR_PAREDE = 5;
const BABEL_LIVROS_POR_ESTANTE = 32;
const BABEL_LIVROS_POR_HEXAGONO = 640;
const BABEL_CATALOGO_INICIAL = 10000;

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "db_biblioteca_de_babel");

if ($conn->connect_error) {
    http_response_code(500);
    die("Erro ao conectar ao banco de dados. Verifique a configuração do MySQL.");
}
$conn->set_charset("utf8mb4");

function babel_hash_bytes(string $semente, int $bloco): string {
    return hash("sha256", $semente . "|bloco|" . $bloco, true);
}

/*
 * Gera 3.200 símbolos por página.
 * O descarte de bytes de 250 a 255 evita viés na conversão para 25 símbolos.
 * A mesma combinação livro/página sempre produz o mesmo texto.
 */
function babel_gerar_pagina(int $livroId, int $pagina): string {
    $texto = "";
    $bloco = 0;
    $alfabeto = BABEL_ALFABETO;
    $tamanhoAlfabeto = strlen($alfabeto);

    while (strlen($texto) < BABEL_CARACTERES_PAGINA) {
        $bytes = babel_hash_bytes("babel-v1|livro|" . $livroId . "|pagina|" . $pagina, $bloco);
        $bloco++;

        $quantidade = strlen($bytes);
        for ($i = 0; $i < $quantidade; $i++) {
            $valor = ord($bytes[$i]);

            if ($valor >= 250) {
                continue;
            }

            $texto .= $alfabeto[$valor % $tamanhoAlfabeto];

            if (strlen($texto) >= BABEL_CARACTERES_PAGINA) {
                break;
            }
        }
    }

    $linhas = str_split($texto, BABEL_CARACTERES_LINHA);
    return implode("\n", $linhas);
}

function babel_normalizar_pagina(string $texto): string {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $linhas = explode("\n", $texto);
    $linhas = array_map(static function ($linha) {
        return rtrim($linha);
    }, $linhas);
    return implode("\n", $linhas);
}

function babel_calcular_endereco(int $livroId): array {
    $indice = $livroId - 1;
    $hexagono = intdiv($indice, BABEL_LIVROS_POR_HEXAGONO) + 1;
    $posicaoHexagono = $indice % BABEL_LIVROS_POR_HEXAGONO;

    $livrosPorParede = BABEL_ESTANTES_POR_PAREDE * BABEL_LIVROS_POR_ESTANTE;
    $parede = intdiv($posicaoHexagono, $livrosPorParede) + 1;
    $posicaoParede = $posicaoHexagono % $livrosPorParede;
    $estante = intdiv($posicaoParede, BABEL_LIVROS_POR_ESTANTE) + 1;
    $posicaoLivro = ($posicaoParede % BABEL_LIVROS_POR_ESTANTE) + 1;

    return [
        "hexagono" => $hexagono,
        "parede" => $parede,
        "estante" => $estante,
        "posicao" => $posicaoLivro
    ];
}

/* Metadados determinísticos: cada livro mantém autor e título. */
function babel_metadados_livro(int $livroId, int $totalAutores = 20): array {
    $hash = hash("sha256", "babel-metadados-v1|" . $livroId);
    $autorId = (hexdec(substr($hash, 0, 7)) % $totalAutores) + 1;

    $substantivos = [
        "Bananas", "Labirintos", "Memórias", "Sombras", "Macacos",
        "Espelhos", "Bibliotecas", "Silêncios", "Estrelas", "Galhos",
        "Abismos", "Relógios", "Jardins", "Sonhos", "Paradoxos",
        "Fragmentos", "Máquinas", "Ilhas", "Ciclos", "Ecos"
    ];
    $complementos = [
        "do Infinito", "de uma Banana", "sem Macacos", "da Sexta Parede",
        "e Outros Primatas", "do Tempo Perdido", "em um Labirinto",
        "da Floresta Imóvel", "para um Leitor Impossível", "sob o Mesmo Céu",
        "de um Mundo Circular", "na Última Estante", "do Acaso",
        "e a Natureza das Coisas", "de um Bibliotecário"
    ];
    $adjetivos = [
        "Invisíveis", "Improváveis", "Mecânicos", "Silenciosos", "Eternos",
        "Desobedientes", "Imaginários", "Perdidos", "Inacabados", "Azuis"
    ];

    $n1 = hexdec(substr($hash, 8, 7)) % count($substantivos);
    $n2 = hexdec(substr($hash, 16, 7)) % count($complementos);
    $n3 = hexdec(substr($hash, 24, 7)) % count($adjetivos);

    $formatos = [
        $substantivos[$n1] . " " . $complementos[$n2],
        "A Teoria dos " . $adjetivos[$n3] . " " . $substantivos[$n1],
        "Tratado sobre " . strtolower($substantivos[$n1]) . " " . strtolower($complementos[$n2]),
        "O Livro " . $adjetivos[$n3] . " " . $complementos[$n2]
    ];
    $formato = hexdec(substr($hash, 32, 7)) % count($formatos);

    return ["autor_id" => $autorId, "titulo" => $formatos[$formato]];
}

function babel_registrar_livro(mysqli $conn, int $livroId): bool {
    $meta = babel_metadados_livro($livroId);
    $sql = "INSERT INTO babel_livro_catalogo
                (id_livro, id_autor_babel, titulo)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE ultima_visita = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("iis", $livroId, $meta["autor_id"], $meta["titulo"]);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function babel_indexar_pagina(mysqli $conn, int $livroId, int $pagina, string $conteudo): bool {
    $normalizado = babel_normalizar_pagina($conteudo);
    $hash = hash("sha256", $normalizado);

    $sql = "INSERT INTO babel_pagina_indice
                (id_livro, pagina, conteudo, hash_conteudo)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                conteudo = VALUES(conteudo),
                hash_conteudo = VALUES(hash_conteudo),
                indexada_em = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("iiss", $livroId, $pagina, $normalizado, $hash);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function babel_buscar_autor(mysqli $conn, int $autorId): ?array {
    $stmt = $conn->prepare(
        "SELECT id_autor_babel, nome_autor, descricao, especie, foto_path
         FROM babel_autor WHERE id_autor_babel = ?"
    );
    if (!$stmt) return null;

    $stmt->bind_param("i", $autorId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $autor = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();
    return $autor ?: null;
}

function babel_buscar_livro(mysqli $conn, int $livroId): ?array {
    $stmt = $conn->prepare(
        "SELECT l.id_livro, l.titulo, l.id_autor_babel,
                a.nome_autor, a.descricao, a.especie, a.foto_path
         FROM babel_livro_catalogo l
         INNER JOIN babel_autor a ON a.id_autor_babel = l.id_autor_babel
         WHERE l.id_livro = ?"
    );
    if (!$stmt) return null;

    $stmt->bind_param("i", $livroId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $livro = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();
    return $livro ?: null;
}

function babel_h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$mensagem = "";
$resultadosBusca = [];
$termoBusca = trim($_POST["termo"] ?? "");
$tipoBusca = $_POST["tipo_busca"] ?? "trecho";
$livroId = isset($_GET["livro_id"]) ? (int)$_GET["livro_id"] : 1542;
$pagina = isset($_GET["pagina"]) ? (int)$_GET["pagina"] : 1;

if ($livroId < 1 || $livroId > BABEL_CATALOGO_INICIAL) {
    $livroId = 1;
    $mensagem = "O catálogo inicial contém livros de 1 a " . BABEL_CATALOGO_INICIAL . ".";
}
if ($pagina < 1 || $pagina > BABEL_PAGINAS) {
    $pagina = 1;
    $mensagem = "A página deve estar entre 1 e " . BABEL_PAGINAS . ".";
}

/* Pesquisa somente sobre páginas previamente indexadas. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "buscar") {
    if ($termoBusca === "") {
        $mensagem = "Digite um trecho ou cole o conteúdo de uma página.";
    } elseif ($tipoBusca === "pagina") {
        $paginaPesquisada = babel_normalizar_pagina($termoBusca);

        if (strlen($paginaPesquisada) < 3000) {
            $mensagem = "Para pesquisar uma página inteira, cole o conteúdo completo (40 linhas de 80 caracteres).";
        } else {
            $hashBusca = hash("sha256", $paginaPesquisada);
            $stmt = $conn->prepare(
                "SELECT i.id_livro, i.pagina, l.titulo, a.nome_autor
                 FROM babel_pagina_indice i
                 INNER JOIN babel_livro_catalogo l ON l.id_livro = i.id_livro
                 INNER JOIN babel_autor a ON a.id_autor_babel = l.id_autor_babel
                 WHERE i.hash_conteudo = ? AND i.conteudo = ?
                 ORDER BY i.id_livro, i.pagina LIMIT 100"
            );

            if ($stmt) {
                $stmt->bind_param("ss", $hashBusca, $paginaPesquisada);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($linha = $res->fetch_assoc())) $resultadosBusca[] = $linha;
                $stmt->close();
            }
            if (!$resultadosBusca) {
                $mensagem = "Nenhuma correspondência exata entre as páginas já indexadas.";
            }
        }
    } else {
        if (mb_strlen($termoBusca, "UTF-8") < 3) {
            $mensagem = "Use pelo menos 3 caracteres para pesquisar um trecho.";
        } elseif (mb_strlen($termoBusca, "UTF-8") > 1000) {
            $mensagem = "O trecho de pesquisa deve ter no máximo 1.000 caracteres. Para páginas inteiras, use a outra modalidade.";
        } else {
            $padrao = "%" . addcslashes($termoBusca, "\\%_") . "%";
            $stmt = $conn->prepare(
                "SELECT i.id_livro, i.pagina, l.titulo, a.nome_autor,
                        LOCATE(?, i.conteudo) AS posicao
                 FROM babel_pagina_indice i
                 INNER JOIN babel_livro_catalogo l ON l.id_livro = i.id_livro
                 INNER JOIN babel_autor a ON a.id_autor_babel = l.id_autor_babel
                 WHERE i.conteudo LIKE ?
                 ORDER BY i.id_livro, i.pagina LIMIT 100"
            );

            if ($stmt) {
                $stmt->bind_param("ss", $termoBusca, $padrao);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($linha = $res->fetch_assoc())) $resultadosBusca[] = $linha;
                $stmt->close();
            }
            if (!$resultadosBusca) {
                $mensagem = "Trecho não encontrado nas páginas já indexadas. Abra mais páginas do catálogo para ampliar a pesquisa.";
            }
        }
    }
}

/* Abre e indexa a página solicitada. */
if (babel_registrar_livro($conn, $livroId)) {
    $conteudoPagina = babel_gerar_pagina($livroId, $pagina);
    $paginaIndexada = babel_indexar_pagina($conn, $livroId, $pagina, $conteudoPagina);
    if (!$paginaIndexada && $mensagem === "") {
        $mensagem = "A página foi gerada, mas não foi possível salvá-la no índice de pesquisa.";
    }
} else {
    $conteudoPagina = babel_gerar_pagina($livroId, $pagina);
    if ($mensagem === "") {
        $mensagem = "A página foi gerada, mas não foi possível registrar o livro no catálogo. Verifique se executou o SQL complementar.";
    }
}

$livro = babel_buscar_livro($conn, $livroId);
$endereco = babel_calcular_endereco($livroId);

$stmtContagem = $conn->prepare("SELECT COUNT(*) AS total FROM babel_pagina_indice");
$totalPaginasIndexadas = 0;
if ($stmtContagem) {
    $stmtContagem->execute();
    $resContagem = $stmtContagem->get_result();
    if ($resContagem) {
        $linhaContagem = $resContagem->fetch_assoc();
        $totalPaginasIndexadas = (int)$linhaContagem["total"];
    }
    $stmtContagem->close();
}

$paginaAnterior = max(1, $pagina - 1);
$proximaPagina = min(BABEL_PAGINAS, $pagina + 1);
$livroAnterior = max(1, $livroId - 1);
$proximoLivro = min(BABEL_CATALOGO_INICIAL, $livroId + 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Biblioteca de Babel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../Styles/default.css">
  <style>
    .babel-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);gap:1.25rem}
    .babel-panel{background:rgba(10,12,35,.72);border:1px solid rgba(255,255,255,.13);border-radius:14px;padding:1.25rem;margin-bottom:1.25rem}
    .babel-panel h2,.babel-panel h3{margin-top:0}
    .babel-meta{display:flex;flex-wrap:wrap;gap:.55rem;margin:.8rem 0}
    .babel-chip{display:inline-block;padding:.35rem .65rem;border:1px solid rgba(255,255,255,.2);border-radius:999px;font: .8rem 'JetBrains Mono',monospace;color:#ffde59}
    .babel-book{white-space:pre;overflow-x:auto;background:#080a18;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:1rem;font: .78rem/1.55 'JetBrains Mono',monospace;color:#e9e9f5;max-width:100%}
    .babel-form{display:grid;gap:.7rem}
    .babel-form input,.babel-form select,.babel-form textarea{width:100%;box-sizing:border-box;padding:.75rem;border-radius:8px;border:1px solid #555;background:#10152d;color:#fff;font:inherit}
    .babel-form textarea{min-height:120px;resize:vertical}
    .babel-form button,.babel-button{display:inline-block;padding:.7rem 1rem;border:0;border-radius:8px;background:#ff751f;color:#111;font-weight:800;text-decoration:none;cursor:pointer}
    .babel-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
    .babel-nav a{padding:.55rem .8rem;border:1px solid rgba(255,255,255,.2);border-radius:8px;color:#fff;text-decoration:none}
    .babel-monkey{display:flex;gap:1rem;align-items:center}
    .babel-avatar{width:76px;height:76px;flex:0 0 76px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#ffde59,#ff751f);font-size:2.5rem;border:2px solid rgba(255,255,255,.4);overflow:hidden}
    .babel-avatar img{width:100%;height:100%;object-fit:cover}
    .babel-result{padding:.8rem 0;border-top:1px solid rgba(255,255,255,.12)}
    .babel-result:first-of-type{border-top:0}
    .babel-notice{padding:.8rem 1rem;border-left:4px solid #ffde59;background:rgba(255,222,89,.09);border-radius:6px;margin-bottom:1rem}
    .babel-muted{opacity:.75;font-size:.9rem}
    @media(max-width:850px){.babel-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php include "../../Includes/header.php"; ?>

<main class="content">
  <section class="test-block">
    <h1>Biblioteca de Babel</h1>
    <p>Um catálogo finito de exploração de uma biblioteca combinatoriamente imensa.</p>
    <div class="babel-meta">
      <span class="babel-chip">CATÁLOGO #1–#<?php echo BABEL_CATALOGO_INICIAL; ?></span>
      <span class="babel-chip"><?php echo number_format($totalPaginasIndexadas, 0, ",", "."); ?> páginas indexadas</span>
      <span class="babel-chip">25 símbolos</span>
    </div>
    <?php if ($mensagem !== ""): ?>
      <div class="babel-notice"><?php echo babel_h($mensagem); ?></div>
    <?php endif; ?>
  </section>

  <div class="babel-grid">
    <div>
      <section class="babel-panel">
        <h2>Localizar um livro</h2>
        <form class="babel-form" method="GET" action="">
          <label for="livro_id">Número do livro (1 a <?php echo BABEL_CATALOGO_INICIAL; ?>)</label>
          <input id="livro_id" type="number" name="livro_id" min="1" max="<?php echo BABEL_CATALOGO_INICIAL; ?>" value="<?php echo $livroId; ?>" required>
          <label for="pagina">Página (1 a <?php echo BABEL_PAGINAS; ?>)</label>
          <input id="pagina" type="number" name="pagina" min="1" max="<?php echo BABEL_PAGINAS; ?>" value="<?php echo $pagina; ?>" required>
          <button type="submit">Abrir livro e página</button>
        </form>
      </section>

      <section class="babel-panel">
        <h2><?php echo babel_h($livro["titulo"] ?? "Livro #" . $livroId); ?></h2>
        <div class="babel-meta">
          <span class="babel-chip">Livro #<?php echo $livroId; ?></span>
          <span class="babel-chip">Página <?php echo $pagina; ?>/<?php echo BABEL_PAGINAS; ?></span>
          <span class="babel-chip">Hexágono <?php echo $endereco["hexagono"]; ?></span>
        </div>
        <div class="babel-book"><?php echo babel_h($conteudoPagina); ?></div>
        <div class="babel-nav">
          <a href="?livro_id=<?php echo $livroId; ?>&pagina=<?php echo $paginaAnterior; ?>">← Página anterior</a>
          <a href="?livro_id=<?php echo $livroId; ?>&pagina=<?php echo $proximaPagina; ?>">Próxima página →</a>
          <a href="?livro_id=<?php echo $livroAnterior; ?>&pagina=1">← Livro anterior</a>
          <a href="?livro_id=<?php echo $proximoLivro; ?>&pagina=1">Próximo livro →</a>
        </div>
      </section>
    </div>

    <aside>
      <section class="babel-panel">
        <h2>Autor</h2>
        <?php if ($livro): ?>
          <div class="babel-monkey">
            <div class="babel-avatar">
              <?php if (!empty($livro["foto_path"])): ?>
                <img src="<?php echo babel_h("../../" . ltrim($livro["foto_path"], "/")); ?>" alt="Retrato de <?php echo babel_h($livro["nome_autor"]); ?>">
              <?php else: ?>
                🐒
              <?php endif; ?>
            </div>
            <div>
              <strong><?php echo babel_h($livro["nome_autor"]); ?></strong>
              <div class="babel-muted"><?php echo babel_h($livro["especie"] ?? "Primata"); ?></div>
              <div class="babel-muted">Autor-macaco #<?php echo (int)$livro["id_autor_babel"]; ?></div>
            </div>
          </div>
          <p><?php echo babel_h($livro["descricao"] ?? "Um dos incontáveis autores da biblioteca."); ?></p>
          <p class="babel-muted">O mesmo livro sempre mantém o mesmo autor e título.</p>
        <?php else: ?>
          <p>Não foi possível carregar o perfil do autor. Confira se executou o SQL complementar.</p>
        <?php endif; ?>
      </section>

      <section class="babel-panel">
        <h3>Endereço físico</h3>
        <p>Hexágono: <strong><?php echo $endereco["hexagono"]; ?></strong></p>
        <p>Parede: <strong><?php echo $endereco["parede"]; ?></strong> de 4</p>
        <p>Estante: <strong><?php echo $endereco["estante"]; ?></strong> de 5</p>
        <p>Posição: <strong><?php echo $endereco["posicao"]; ?></strong> de 32</p>
      </section>
    </aside>
  </div>

  <section class="babel-panel">
    <h2>Pesquisar na Biblioteca</h2>
    <p>A pesquisa considera todas as páginas que já foram abertas e indexadas neste catálogo. Ao navegar por mais livros e páginas, o índice cresce.</p>
    <form class="babel-form" method="POST" action="">
      <input type="hidden" name="acao" value="buscar">
      <label for="tipo_busca">Tipo de pesquisa</label>
      <select id="tipo_busca" name="tipo_busca">
        <option value="trecho" <?php echo $tipoBusca === "trecho" ? "selected" : ""; ?>>Trecho ou frase</option>
        <option value="pagina" <?php echo $tipoBusca === "pagina" ? "selected" : ""; ?>>Página inteira (correspondência exata)</option>
      </select>
      <label for="termo">Texto a procurar</label>
      <textarea id="termo" name="termo" placeholder="Digite uma frase ou cole as 40 linhas da página..."><?php echo babel_h($termoBusca); ?></textarea>
      <button type="submit">Pesquisar páginas indexadas</button>
    </form>

    <?php if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "buscar" && $resultadosBusca): ?>
      <h3>Resultados encontrados: <?php echo count($resultadosBusca); ?></h3>
      <?php foreach ($resultadosBusca as $resultado): ?>
        <div class="babel-result">
          <strong><?php echo babel_h($resultado["titulo"]); ?></strong>
          <div class="babel-muted">
            Livro #<?php echo (int)$resultado["id_livro"]; ?> ·
            Página <?php echo (int)$resultado["pagina"]; ?> ·
            <?php echo babel_h($resultado["nome_autor"]); ?>
          </div>
          <a class="babel-button" href="?livro_id=<?php echo (int)$resultado["id_livro"]; ?>&pagina=<?php echo (int)$resultado["pagina"]; ?>">Abrir resultado</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="babel-panel">
    <h3>Como o índice funciona</h3>
    <ul>
      <li>O catálogo lógico inicial possui 10.000 livros, cada um com 410 páginas.</li>
      <li>Uma página é indexada automaticamente quando é aberta.</li>
      <li>A busca por trecho procura dentro das páginas armazenadas no índice.</li>
      <li>A busca por página inteira exige correspondência exata após normalizar quebras de linha e espaços finais.</li>
      <li>O texto é gerado deterministicamente: livro e página iguais produzem o mesmo conteúdo.</li>
    </ul>
    <p class="babel-muted">A ausência de resultados não prova que o trecho não exista nos livros ainda não visitados.</p>
  </section>
</main>
</body>
</html>
<?php
$conn->close();
?>
