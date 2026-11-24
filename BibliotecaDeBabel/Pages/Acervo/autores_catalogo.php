<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../Users/users_login.php");
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

$sql = "SELECT a.id_autor, a.nome_autor, a.nacionalidade,
               COUNT(l.id_livro) AS total_livros
        FROM tbl_autor a
        LEFT JOIN tbl_livro l
            ON l.id_autor = a.id_autor AND l.gerado_aleatoriamente = 0
        GROUP BY a.id_autor, a.nome_autor, a.nacionalidade
        ORDER BY a.nome_autor";
$resultado = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Autores - Biblioteca de Babel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../Styles/default.css" />
</head>
<body>

  <!-- HEADER -->
  <header>
    <div class="brand">
      <img src="../../Images/Logo/TorreDeBabel.svg" class="logo" alt="Logo Biblioteca de Babel">
      <p class="brand-title">BIBLIOTECA DE BABEL</p>
    </div>
    <nav aria-label="Navegação">
      <a href="livros_catalogo.php">Livros</a>
      <a href="autores_catalogo.php">Autores</a>
      <a href="../Emprestimos/emprestimos.php">Empréstimos</a>
      <a href="../Emprestimos/multas.php">Multas</a>
      <?php if ($_SESSION["tipo_usuario"] === "admin"): ?>
        <a href="livros_gerencia.php">Gerenciar Acervo</a>
      <?php endif; ?>
      <a href="../Users/users_login.php?sair=1">Sair</a>
    </nav>
  </header>

  <!-- CONTENT -->
  <main class="content">

    <section id="tabelas-semanticos" class="test-block">
      <h2>Catálogo de Autores</h2>
      <p>Relação de autores cadastrados no sistema e seus respectivos livros tradicionais associados[cite: 4].</p>

      <?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Autor</th>
              <th>Nacionalidade</th>
              <th>Livros Tradicionais Cadastrados</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($autor = mysqli_fetch_assoc($resultado)): ?>
              <tr>
                <td><?php echo htmlspecialchars($autor["nome_autor"], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($autor["nacionalidade"] ?? "Não informada", ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int)$autor["total_livros"]; ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Nenhum autor cadastrado.</p>
      <?php endif; ?>
    </section>

  </main>
</body>
</html>
<?php
$conn->close();
?>