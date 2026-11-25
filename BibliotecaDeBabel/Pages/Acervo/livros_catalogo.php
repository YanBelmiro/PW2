<?php
date_default_timezone_set("America/Sao_Paulo");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../Includes/autor_avatar.php";

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../index.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_biblioteca_de_babel";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Erro ao conectar ao banco de dados: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
}

$conn->set_charset("utf8mb4");

// Atualiza o estado dos empréstimos cujo prazo já passou.
$sql_atrasados = "UPDATE tbl_emprestimo
                  SET status = 'atrasado'
                  WHERE status = 'ativo' AND data_devolucao_prevista < CURDATE()";
mysqli_query($conn, $sql_atrasados);

$busca = trim($_GET["busca"] ?? "");

$sql = "SELECT l.id_livro, l.titulo, l.ano_publicacao, l.id_autor, a.nome_autor,
               CASE
                   WHEN EXISTS (
                       SELECT 1 FROM tbl_emprestimo e
                       WHERE e.id_livro = l.id_livro
                         AND e.status IN ('ativo', 'atrasado')
                   ) THEN 'Emprestado'
                   ELSE 'Disponível'
               END AS disponibilidade
        FROM tbl_livro l
        INNER JOIN tbl_autor a ON a.id_autor = l.id_autor
        WHERE l.gerado_aleatoriamente = 0
          AND (l.titulo LIKE ? OR a.nome_autor LIKE ?)
        ORDER BY l.titulo";

$stmt = mysqli_prepare($conn, $sql);
$resultado = false;
$total = 0;

if ($stmt) {
    $termo = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, "ss", $termo, $termo);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $total = $resultado ? mysqli_num_rows($resultado) : 0;
}

$busca_html = htmlspecialchars($busca, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Catálogo de Livros - Biblioteca de Babel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../Styles/default.css" />

  <style>
    /* ----- Barra de pesquisa fixa (fica logo abaixo do header ao rolar) ----- */
    .catalogo-busca {
      position: sticky;
      top: var(--altura-header, 5rem);
      z-index: 90;
      background: rgba(10, 10, 18, 0.92);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid var(--cosmos-border);
    }
    .catalogo-busca__interno {
      max-width: 1240px;
      margin: 0 auto;
      padding: 0.9rem 1.5rem;
    }
    .catalogo-busca form {
      flex-direction: row;
      align-items: center;
      gap: 0.75rem;
    }
    .catalogo-busca input { flex: 1; min-width: 0; }
    .catalogo-busca .limpar {
      display: inline-flex;
      align-items: center;
      padding: 0.8rem 1.2rem;
      font-size: 0.875rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      border: 1px solid var(--cosmos-border);
      color: var(--cosmos-white);
    }
    .catalogo-busca .limpar:hover { border-color: var(--cosmos-orange); }
    .catalogo-busca .contagem {
      margin: 0.5rem 0 0;
      font-size: 0.8rem;
      color: var(--cosmos-muted);
    }
    .sr-only {
      position: absolute; width: 1px; height: 1px; overflow: hidden;
      clip: rect(0 0 0 0); white-space: nowrap;
    }

    /* ----- Ficha do livro: 3D à esquerda, dados à direita ----- */
    main.content.catalogo {
      max-width: 1240px;
      padding: 1rem 1.5rem 5rem;
      gap: 0;
    }
    .livro-ficha {
      display: grid;
      grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
      gap: 2rem;
      align-items: stretch;
      padding: 2.5rem 0;
      border-bottom: 1px solid var(--cosmos-border);
      scroll-margin-top: calc(var(--altura-header, 5rem) + 5rem);
    }
    .livro-ficha:last-child { border-bottom: 0; }

    .livro-palco {
      position: relative;
      aspect-ratio: 5 / 6;
      overflow: hidden;
      background:
        radial-gradient(circle at 50% 38%, rgba(24, 0, 173, 0.32), transparent 68%),
        var(--cosmos-surface);
      border: 1px solid var(--cosmos-border);
      cursor: grab;
      transition: border-color var(--transition-smooth);
    }
    .livro-ficha.ativo .livro-palco { border-color: rgba(255, 117, 31, 0.55); }

    .livro-capa-2d {
      position: absolute;
      inset: 0;
      margin: auto;
      height: 80%;
      width: auto;
      aspect-ratio: 2 / 3;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.6);
    }
    .livro-ficha.ativo .livro-capa-2d { visibility: hidden; }

    .livro-3d-canvas {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      max-width: none;
      touch-action: pan-y; /* rolagem vertical continua funcionando no celular */
      cursor: grab;
    }
    .livro-3d-canvas:active { cursor: grabbing; }

    .livro-controles {
      position: absolute;
      left: 0; right: 0; bottom: 0.9rem;
      z-index: 2;
      display: none;
      justify-content: center;
      gap: 0.5rem;
    }
    .livro-ficha.ativo .livro-controles { display: flex; }
    .sem-webgl .livro-controles { display: none !important; }
    .livro-controles button { padding: 0.45rem 1rem; font-size: 0.7rem; }

    .livro-dados {
      display: grid;
      grid-template-columns: clamp(88px, 11vw, 132px) minmax(0, 1fr);
      grid-template-rows: minmax(0, 1.1fr) minmax(0, 1.5fr) minmax(0, 0.8fr);
      gap: 1rem;
      min-height: 0;
    }
    .livro-dados > div {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 1.25rem 1.5rem;
      background: rgba(10, 10, 18, 0.45);
      border: 1px solid var(--cosmos-border);
      min-width: 0;
    }
    .livro-indice { align-items: center; text-align: center; padding: 1rem 0.5rem !important; }
    .livro-indice strong {
      font-size: clamp(1.75rem, 3vw, 2.5rem);
      font-weight: 800;
      line-height: 1;
      color: var(--cosmos-yellow);
    }
    .livro-indice small { margin-top: 0.35rem; }

    .livro-titulo h2 {
      margin: 0;
      font-size: clamp(1.4rem, 2.6vw, 2.25rem);
      overflow-wrap: anywhere;
    }

    .livro-autor-foto { padding: 0 !important; overflow: hidden; }
    .livro-autor-foto img { width: 100%; height: 100%; object-fit: cover; }
    .livro-autor-nome p {
      margin: 0;
      font-size: clamp(1.1rem, 2vw, 1.6rem);
      font-weight: 700;
      color: var(--cosmos-white);
      overflow-wrap: anywhere;
    }

    .livro-meta {
      grid-column: 1 / -1;
      flex-direction: row !important;
      align-items: center;
      justify-content: space-between !important;
      flex-wrap: wrap;
      gap: 1.5rem;
    }
    .livro-meta strong { display: block; font-size: 1.25rem; }
    .livro-meta small { display: block; }

    .situacao {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .situacao::before { /* hexágono, como a logo */
      content: "";
      width: 0.75rem;
      height: 0.75rem;
      background: currentColor;
      clip-path: polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%);
    }
    .situacao.ok  { color: var(--cosmos-yellow); }
    .situacao.off { color: var(--cosmos-red); }

    .catalogo-vazio { padding: 3rem 0; }

    @media (max-width: 860px) {
      .livro-ficha { grid-template-columns: 1fr; gap: 1.25rem; }
      .livro-palco { aspect-ratio: 4 / 5; width: 100%; max-width: 480px; margin-inline: auto; }
      .livro-dados { grid-template-rows: auto; }
      .catalogo-busca form { flex-wrap: wrap; }
    }
  </style>

  <!-- three.js (mesma versão e origem usadas no projeto Ex Nihilo) -->
  <script type="importmap">
    {
      "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.152.2/build/three.module.js"
      }
    }
  </script>
  <script type="module" src="../../Scripts/livro3d.js"></script>
</head>
<body>

  <!-- HEADER -->
  <?php include "../../Includes/header.php"; ?>

  <!-- PESQUISA (fixa) -->
  <div class="catalogo-busca">
    <div class="catalogo-busca__interno">
      <form action="" method="GET" role="search">
        <label for="busca" class="sr-only">Pesquisar por título ou autor</label>
        <input type="text" id="busca" name="busca" placeholder="Pesquisar por título ou autor..."
               value="<?php echo $busca_html; ?>">
        <button type="submit">Pesquisar</button>
        <?php if ($busca !== ""): ?>
          <a class="limpar" href="livros_catalogo.php">Limpar</a>
        <?php endif; ?>
      </form>
      <p class="contagem">
        <?php echo $total; ?> <?php echo $total === 1 ? "livro" : "livros"; ?>
        <?php if ($busca !== ""): ?>para “<?php echo $busca_html; ?>”<?php endif; ?>
      </p>
    </div>
  </div>

  <!-- CONTENT -->
  <main class="content catalogo">

    <?php if ($resultado && $total > 0): ?>
      <?php $posicao = 0; ?>
      <?php while ($livro = mysqli_fetch_assoc($resultado)): ?>
        <?php
          $posicao++;
          $titulo = htmlspecialchars($livro["titulo"], ENT_QUOTES, 'UTF-8');
          $autor  = htmlspecialchars($livro["nome_autor"], ENT_QUOTES, 'UTF-8');
          $ano    = $livro["ano_publicacao"] !== null
                      ? htmlspecialchars((string)$livro["ano_publicacao"], ENT_QUOTES, 'UTF-8')
                      : "";
          $disp   = htmlspecialchars($livro["disponibilidade"], ENT_QUOTES, 'UTF-8');
          $disp_classe = $livro["disponibilidade"] === "Disponível" ? "ok" : "off";
        ?>
        <article class="livro-ficha" data-livro
                 data-id="<?php echo (int)$livro["id_livro"]; ?>"
                 data-titulo="<?php echo $titulo; ?>"
                 data-autor="<?php echo $autor; ?>"
                 data-ano="<?php echo $ano; ?>"
                 data-disp="<?php echo $disp; ?>">

          <!-- LIVRO (3D) -->
          <div class="livro-palco" aria-label="Livro 3D: <?php echo $titulo; ?>. Arraste para girar.">
            <canvas class="livro-capa-2d" width="600" height="900"></canvas>
            <div class="livro-controles">
              <button type="button" data-acao="capa">Capa</button>
              <button type="button" data-acao="verso">Verso</button>
            </div>
          </div>

          <!-- DADOS -->
          <div class="livro-dados">
            <div class="livro-indice">
              <strong><?php echo str_pad((string)$posicao, 2, "0", STR_PAD_LEFT); ?></strong>
              <small>de <?php echo $total; ?></small>
            </div>

            <div class="livro-titulo">
              <h2><?php echo $titulo; ?></h2>
            </div>

            <div class="livro-autor-foto">
              <?php echo autor_avatar_html((int)$livro["id_autor"], $livro["nome_autor"], 132); ?>
            </div>

            <div class="livro-autor-nome">
              <p><?php echo $autor; ?></p>
            </div>

            <div class="livro-meta">
              <div>
                <small>Publicação</small>
                <strong><?php echo $ano !== "" ? $ano : "Não informado"; ?></strong>
              </div>
              <div>
                <small>Situação</small>
                <span class="situacao <?php echo $disp_classe; ?>"><?php echo $disp; ?></span>
              </div>
            </div>
          </div>

        </article>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="catalogo-vazio">Nenhum livro tradicional foi encontrado.</p>
    <?php endif; ?>

  </main>

  <script>
    // O header é sticky e pode quebrar em duas linhas: a barra de pesquisa
    // se encaixa logo abaixo dele, qualquer que seja a altura.
    (function () {
      var header = document.querySelector("header");
      if (!header) return;
      var ajustar = function () {
        document.documentElement.style.setProperty("--altura-header", header.offsetHeight + "px");
      };
      ajustar();
      if (window.ResizeObserver) new ResizeObserver(ajustar).observe(header);
      else window.addEventListener("resize", ajustar);
    })();
  </script>

</body>
</html>
<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}
$conn->close();
?>
