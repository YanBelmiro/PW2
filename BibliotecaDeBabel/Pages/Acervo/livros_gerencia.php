<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../Users/users_login.php");
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

$conn->set_charset("utf8mb4");
$mensagem = $_SESSION["mensagem_acervo"] ?? "";
unset($_SESSION["mensagem_acervo"]);

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
            // O livro é tradicional: gerado_aleatoriamente = 0.
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
                    $mensagem = "Não foi possível atualizar o livro. Confira se o autor existe.";
                }

                mysqli_stmt_close($stmt);
            } else {
                $mensagem = "Erro ao preparar a atualização do livro.";
            }
        }
    }
}

// Lista de autores para o campo de seleção.
$autores = mysqli_query($conn, "SELECT id_autor, nome_autor FROM tbl_autor ORDER BY nome_autor");

$livro_editar = null;
$id_editar = (int)($_GET["editar"] ?? 0);

// Busca os dados do livro selecionado para edição.
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

// Lista de livros tradicionais.
$sql_livros = "SELECT l.id_livro, l.titulo, l.ano_publicacao, a.nome_autor
               FROM tbl_livro l
               INNER JOIN tbl_autor a ON a.id_autor = l.id_autor
               WHERE l.gerado_aleatoriamente = 0
               ORDER BY l.titulo";
$livros = mysqli_query($conn, $sql_livros);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar acervo - Biblioteca de Babel</title>
    <link rel="stylesheet" href="../../Styles/default.css">
</head>
<body>
    <main>
        <h1>Gerenciamento do acervo</h1>

        <nav>
            <a href="livros_catalogo.php">Catálogo</a> |
            <a href="autores_catalogo.php">Autores</a> |
            <a href="../Emprestimos/emprestimos.php">Empréstimos</a> |
            <a href="../Emprestimos/multas.php">Multas</a> |
            <a href="../Users/users_login.php?sair=1">Sair</a>
        </nav>

        <?php if (!empty($mensagem)): ?>
            <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <h2>Cadastrar autor</h2>

        <form action="" method="POST">
            <input type="hidden" name="acao" value="cadastrar_autor">

            <p>
                <label for="nome_autor">Nome do autor:</label><br>
                <input type="text" id="nome_autor" name="nome_autor" maxlength="100" required>
            </p>

            <p>
                <label for="nacionalidade">Nacionalidade:</label><br>
                <input type="text" id="nacionalidade" name="nacionalidade" maxlength="50">
            </p>

            <button type="submit">Cadastrar autor</button>
        </form>

        <h2><?php echo $livro_editar ? "Editar livro" : "Cadastrar livro"; ?></h2>

        <form action="" method="POST">
            <input type="hidden" name="acao" value="<?php echo $livro_editar ? 'editar_livro' : 'cadastrar_livro'; ?>">

            <?php if ($livro_editar): ?>
                <input type="hidden" name="id_livro" value="<?php echo (int)$livro_editar['id_livro']; ?>">
            <?php endif; ?>

            <p>
                <label for="titulo">Título:</label><br>
                <input type="text" id="titulo" name="titulo" maxlength="150" required
                       value="<?php echo htmlspecialchars($livro_editar['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </p>

            <p>
                <label for="ano_publicacao">Ano de publicação:</label><br>
                <input type="number" id="ano_publicacao" name="ano_publicacao"
                       min="0" max="<?php echo date('Y'); ?>"
                       value="<?php echo htmlspecialchars((string)($livro_editar['ano_publicacao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </p>

            <p>
                <label for="id_autor">Autor:</label><br>
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
            </p>

            <button type="submit">
                <?php echo $livro_editar ? "Salvar alterações" : "Cadastrar livro"; ?>
            </button>

            <?php if ($livro_editar): ?>
                <a href="livros_gerencia.php">Cancelar edição</a>
            <?php endif; ?>
        </form>

        <h2>Livros tradicionais cadastrados</h2>

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
                            <td><?php echo htmlspecialchars($livro['nome_autor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php
                                echo $livro['ano_publicacao'] !== null
                                    ? htmlspecialchars((string)$livro['ano_publicacao'], ENT_QUOTES, 'UTF-8')
                                    : "Não informado";
                                ?>
                            </td>
                            <td>
                                <a href="livros_gerencia.php?editar=<?php echo (int)$livro['id_livro']; ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhum livro tradicional cadastrado.</p>
        <?php endif; ?>
    </main>
</body>
</html>
<?php
$conn->close();
?>
