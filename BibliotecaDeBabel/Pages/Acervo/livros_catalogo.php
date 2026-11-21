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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de livros - Biblioteca de Babel</title>
    <link rel="stylesheet" href="../../Styles/default.css">
</head>
<body>
    <main>
        <h1>Catálogo de livros</h1>
        <p>Olá, <?php echo htmlspecialchars($_SESSION["nome"], ENT_QUOTES, 'UTF-8'); ?>.</p>

        <nav>
            <a href="livros_catalogo.php">Livros</a> |
            <a href="autores_catalogo.php">Autores</a> |
            <a href="../Emprestimos/emprestimos.php">Empréstimos</a> |
            <a href="../Emprestimos/multas.php">Multas</a>
            <?php if ($_SESSION["tipo_usuario"] === "admin"): ?>
                | <a href="livros_gerencia.php">Gerenciar acervo</a>
            <?php endif; ?>
            | <a href="../Users/users_login.php?sair=1">Sair</a>
        </nav>

        <h2>Pesquisar no acervo tradicional</h2>
        <form action="" method="GET">
            <label for="busca">Título ou autor:</label><br>
            <input type="text" id="busca" name="busca"
                   value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Pesquisar</button>
            <a href="livros_catalogo.php">Limpar</a>
        </form>

        <h2>Livros cadastrados</h2>

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
    </main>
</body>
</html>
<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}
$conn->close();
?>
