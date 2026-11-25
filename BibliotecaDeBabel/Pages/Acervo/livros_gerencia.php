<?php
require_once "../../Includes/aviso.php";
date_default_timezone_set("America/Sao_Paulo");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../Includes/autor_avatar.php";

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../index.php");
    exit;
}

if ($_SESSION["tipo_usuario"] !== "admin") {
    header("Location: livros_catalogo.php");
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

$mensagem = $_SESSION["mensagem_acervo"] ?? "";
$mensagem_tipo = ($mensagem !== "") ? "sucesso" : "erro"; // flash = sucesso; validações diretas = erro
unset($_SESSION["mensagem_acervo"]);

$autor_cadastrado = $_SESSION["autor_cadastrado"] ?? null;
unset($_SESSION["autor_cadastrado"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $acao = $_POST["acao"] ?? "";

    // CADASTRAR AUTOR
    if ($acao === "cadastrar_autor") {
        $nome_autor = trim($_POST["nome_autor"] ?? "");
        $nacionalidade = trim($_POST["nacionalidade"] ?? "");

        if (empty($nome_autor)) {
            $mensagem = "Informe o nome do autor.";
        } else {
            $sql = "INSERT INTO tbl_autor (nome_autor, nacionalidade) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $nome_autor, $nacionalidade);

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION["mensagem_acervo"] = "Autor cadastrado com sucesso.";
                    $_SESSION["autor_cadastrado"] = [
                        "id"   => mysqli_stmt_insert_id($stmt),
                        "nome" => $nome_autor,
                    ];
                    mysqli_stmt_close($stmt);
                    header("Location: livros_gerencia.php");
                    exit;
                } else {
                    $mensagem = "Não foi possível cadastrar o autor.";
                }

                mysqli_stmt_close($stmt);
            } else {
                $mensagem = "Erro ao preparar o cadastro do autor.";
            }
        }
    }

    // CADASTRAR LIVRO
    if ($acao === "cadastrar_livro") {
        $titulo = trim($_POST["titulo"] ?? "");
        $ano_texto = trim($_POST["ano_publicacao"] ?? "");
        $id_autor = (int)($_POST["id_autor"] ?? 0);
        $ano_publicacao = ($ano_texto === "") ? null : (int)$ano_texto;

        if (empty($titulo) || $id_autor <= 0) {
            $mensagem = "Informe o título e selecione um autor.";
        } elseif ($ano_texto !== "" && (!ctype_digit($ano_texto) || $ano_publicacao < 0 || $ano_publicacao > (int)date("Y"))) {
            $mensagem = "Informe um ano de publicação válido.";
        } else {
            $sql = "INSERT INTO tbl_livro (titulo, ano_publicacao, id_autor, gerado_aleatoriamente)
                    VALUES (?, ?, ?, 0)";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sii", $titulo, $ano_publicacao, $id_autor);

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION["mensagem_acervo"] = "Livro cadastrado com sucesso.";
                    mysqli_stmt_close($stmt);
                    header("Location: livros_gerencia.php");
                    exit;
                } else {
                    $mensagem = "Não foi possível cadastrar o livro. Confira se o autor existe.";
                }

                mysqli_stmt_close($stmt);
            } else {
                $mensagem = "Erro ao preparar o cadastro do livro.";
            }
        }
    }

    // EDITAR LIVRO
    if ($acao === "editar_livro") {
        $id_livro = (int)($_POST["id_livro"] ?? 0);
        $titulo = trim($_POST["titulo"] ?? "");
        $ano_texto = trim($_POST["ano_publicacao"] ?? "");
        $id_autor = (int)($_POST["id_autor"] ?? 0);
        $ano_publicacao = ($ano_texto === "") ? null : (int)$ano_texto;

        if ($id_livro <= 0 || empty($titulo) || $id_autor <= 0) {
            $mensagem = "Preencha os dados do livro corretamente.";
        } elseif ($ano_texto !== "" && (!ctype_digit($ano_texto) || $ano_publicacao < 0 || $ano_publicacao > (int)date("Y"))) {
            $mensagem = "Informe um ano de publicação válido.";
        } else {
            $sql = "UPDATE tbl_livro
                    SET titulo = ?, ano_publicacao = ?, id_autor = ?
                    WHERE id_livro = ? AND gerado_aleatoriamente = 0";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "siii", $titulo, $ano_publicacao, $id_autor, $id_livro);

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION["mensagem_acervo"] = "Livro atualizado com sucesso.";
                    mysqli_stmt_close($stmt);
                    header("Location: livros_gerencia.php");
                    exit;
                } else {
                    $mensagem = "Não foi possível atualizar o livro.";
                }

                mysqli_stmt_close($stmt);
            } else {
                $mensagem = "Erro ao preparar a atualização do livro.";
            }
        }
    }
}

$autores = mysqli_query($conn, "SELECT id_autor, nome_autor FROM tbl_autor ORDER BY nome_autor");

$livro_editar = null;
$id_editar = (int)($_GET["editar"] ?? 0);

if ($id_editar > 0) {
    $sql_editar = "SELECT id_livro, titulo, ano_publicacao, id_autor
                   FROM tbl_livro
                   WHERE id_livro = ? AND gerado_aleatoriamente = 0";
    $stmt_editar = mysqli_prepare($conn, $sql_editar);

    if ($stmt_editar) {
        mysqli_stmt_bind_param($stmt_editar, "i", $id_editar);
        mysqli_stmt_execute($stmt_editar);
        $resultado_editar = mysqli_stmt_get_result($stmt_editar);
        $livro_editar = mysqli_fetch_assoc($resultado_editar);
        mysqli_stmt_close($stmt_editar);
    }
}

$sql_livros = "SELECT l.id_livro, l.titulo, l.ano_publicacao, l.id_autor, a.nome_autor
               FROM tbl_livro l
               INNER JOIN tbl_autor a ON a.id_autor = l.id_autor
               WHERE l.gerado_aleatoriamente = 0
               ORDER BY l.titulo";
$livros = mysqli_query($conn, $sql_livros);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gerenciar Acervo - Biblioteca de Babel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../Styles/default.css" />
</head>
<body>

  <!-- HEADER -->
  <?php include "../../Includes/header.php"; ?>

  <!-- CONTENT -->
  <main class="content">

    <?php if (!empty($mensagem)): ?>
      <?php
        $avatar_extra = $autor_cadastrado
            ? autor_avatar_html((int)$autor_cadastrado["id"], $autor_cadastrado["nome"], 120)
            : "";
        echo aviso_html($mensagem, $mensagem_tipo, $avatar_extra);
      ?>
    <?php endif; ?>

    <!-- 1. CADASTRAR AUTOR -->
    <section id="formularios" class="test-block">
      <h2>Cadastrar Autor</h2>
      <form action="" method="POST">
        <input type="hidden" name="acao" value="cadastrar_autor">
        <fieldset>
          <legend>Informações do Autor</legend>
          <div class="grid-2">
            <div>
              <label for="nome_autor">Nome do Autor</label>
              <input type="text" id="nome_autor" name="nome_autor" maxlength="100" placeholder="Nome completo" required>
            </div>
            <div>
              <label for="nacionalidade">Nacionalidade</label>
              <input type="text" id="nacionalidade" name="nacionalidade" maxlength="50" placeholder="Ex: Brasileiro">
            </div>
          </div>
        </fieldset>
        <div id="botoes" class="inline-group" style="margin-top: 1.5rem;">
          <button type="submit">Cadastrar Autor</button>
        </div>
      </form>
    </section>

    <!-- 2. CADASTRAR / EDITAR LIVRO -->
    <section class="test-block">
      <h2><?php echo $livro_editar ? "Editar Livro" : "Cadastrar Livro (Acervo Tradicional)"; ?></h2>
      <form action="" method="POST">
        <input type="hidden" name="acao" value="<?php echo $livro_editar ? 'editar_livro' : 'cadastrar_livro'; ?>">

        <?php if ($livro_editar): ?>
          <input type="hidden" name="id_livro" value="<?php echo (int)$livro_editar['id_livro']; ?>">
        <?php endif; ?>

        <fieldset>
          <legend>Detalhes da Obra</legend>
          <div class="grid-2">
            <div>
              <label for="titulo">Título</label>
              <input type="text" id="titulo" name="titulo" maxlength="150" placeholder="Título do livro" required
                     value="<?php echo htmlspecialchars($livro_editar['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div>
              <label for="ano_publicacao">Ano de Publicação</label>
              <input type="number" id="ano_publicacao" name="ano_publicacao" min="0" max="<?php echo date('Y'); ?>" placeholder="2026"
                     value="<?php echo htmlspecialchars((string)($livro_editar['ano_publicacao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div style="margin-top: 1.25rem;">
            <label for="id_autor">Autor</label>
            <select id="id_autor" name="id_autor" required>
              <option value="">Selecione um autor</option>
              <?php if ($autores): ?>
                <?php while ($autor = mysqli_fetch_assoc($autores)): ?>
                  <option value="<?php echo (int)$autor['id_autor']; ?>"
                      <?php echo ($livro_editar && (int)$livro_editar['id_autor'] === (int)$autor['id_autor']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($autor['nome_autor'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endwhile; ?>
              <?php endif; ?>
            </select>
          </div>
        </fieldset>

        <div id="botoes" class="inline-group" style="margin-top: 1.5rem;">
          <button type="submit"><?php echo $livro_editar ? "Salvar Alterações" : "Cadastrar Livro"; ?></button>
          <?php if ($livro_editar): ?>
            <a href="livros_gerencia.php" class="btn-link">Cancelar Edição</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <!-- 3. TABELA DE LIVROS CADASTRADOS -->
    <section id="tabelas-semanticos" class="test-block">
      <h2>Livros Tradicionais Cadastrados</h2>

      <?php if ($livros && mysqli_num_rows($livros) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Título</th>
              <th>Autor</th>
              <th>Ano</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($livro = mysqli_fetch_assoc($livros)): ?>
              <tr>
                <td><?php echo htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php echo autor_avatar_html((int)$livro['id_autor'], $livro['nome_autor'], 32); ?>
                  <?php echo htmlspecialchars($livro['nome_autor'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td>
                  <?php
                  echo $livro['ano_publicacao'] !== null
                      ? htmlspecialchars((string)$livro['ano_publicacao'], ENT_QUOTES, 'UTF-8')
                      : "Não informado";
                  ?>
                </td>
                <td>
                  <a href="livros_gerencia.php?editar=<?php echo (int)$livro['id_livro']; ?>"><button type="button" class="secondary" style="border: 1px solid var(--cosmos-border);">Editar</button></a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Nenhum livro tradicional cadastrado.</p>
      <?php endif; ?>
    </section>

  </main>
</body>
</html>
<?php
$conn->close();
?>
