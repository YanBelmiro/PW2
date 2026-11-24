<?php
date_default_timezone_set("America/Sao_Paulo");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$sql = "SELECT l.id_livro, l.titulo, l.ano_publicacao, a.nome_autor,
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

if ($stmt) {
    $termo = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, "ss", $termo, $termo);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
}
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
</head>
<body>

  <!-- HEADER -->
  <?php include "../../Includes/header.php"; ?>

  <!-- CONTENT -->
  <main class="content">

    <section class="test-block">
      <h2>Catálogo de Acervo Tradicional</h2>
      <p>Olá, <strong><?php echo htmlspecialchars($_SESSION["nome"], ENT_QUOTES, 'UTF-8'); ?></strong>. Consulte o acervo de livros disponíveis ou faça uma busca por título/autor.</p>

      <form action="" method="GET" style="margin-top: 1.5rem;">
        <div class="grid-2">
          <div>
            <label for="busca">Pesquisar por Título ou Autor</label>
            <input type="text" id="busca" name="busca" placeholder="Digite o termo..."
                   value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="submit">Pesquisar</button>
            <a href="livros_catalogo.php"><button type="button" class="secondary" style="border: 1px solid var(--cosmos-border);">Limpar</button></a>
          </div>
        </div>
      </form>
    </section>

    <section id="tabelas-semanticos" class="test-block">
      <h2>Livros Cadastrados</h2>

      <?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Título</th>
              <th>Autor</th>
              <th>Ano</th>
              <th>Disponibilidade</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($livro = mysqli_fetch_assoc($resultado)): ?>
              <tr>
                <td><?php echo htmlspecialchars($livro["titulo"], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($livro["nome_autor"], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php
                  echo $livro["ano_publicacao"] !== null
                      ? htmlspecialchars((string)$livro["ano_publicacao"], ENT_QUOTES, 'UTF-8')
                      : "Não informado";
                  ?>
                </td>
                <td><?php echo htmlspecialchars($livro["disponibilidade"], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Nenhum livro tradicional foi encontrado.</p>
      <?php endif; ?>
    </section>

  </main>
</body>
</html>
<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}
$conn->close();
?>