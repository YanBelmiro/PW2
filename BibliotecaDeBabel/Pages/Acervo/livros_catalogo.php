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

  <!-- PESQUISA -->
  <div class="catalogo-busca">
    <div class="catalogo-busca__interno">
      <form action="" method="GET" role="search">
        <label for="busca" class="sr-only">Pesquisar por título ou autor</label>
        <input type="text" id="busca" name="busca" placeholder="Pesquisar por título ou autor..."
               value="<?php echo $busca_html; ?>">
        <button type="submit">Pesquisar</button>
        <?php if ($busca !== ""): ?>
          <a class="btn-link" href="livros_catalogo.php">Limpar</a>
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
