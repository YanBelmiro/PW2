<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// O link de saída das páginas do sistema aponta para esta página.
if (isset($_GET["sair"])) {
    session_unset();
    session_destroy();
    header("Location: users_login.php");
    exit;
}

if (isset($_SESSION["id_usuario"])) {
    if ($_SESSION["tipo_usuario"] === "admin") {
        header("Location: ../Acervo/livros_gerencia.php");
    } else {
        header("Location: ../Acervo/livros_catalogo.php");
    }
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
$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if (empty($email) || empty($senha)) {
        $mensagem = "Informe o e-mail e a senha.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
    } else {
        $sql = "SELECT id_usuario, nome, email, senha, tipo_usuario FROM tbl_usuario WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            $usuario = mysqli_fetch_assoc($resultado);

            if ($usuario && password_verify($senha, $usuario["senha"])) {
                session_regenerate_id(true);

                $_SESSION["id_usuario"] = (int)$usuario["id_usuario"];
                $_SESSION["nome"] = $usuario["nome"];
                $_SESSION["tipo_usuario"] = $usuario["tipo_usuario"];

                mysqli_stmt_close($stmt);
                $conn->close();

                if ($usuario["tipo_usuario"] === "admin") {
                    header("Location: ../Acervo/livros_gerencia.php");
                } else {
                    header("Location: ../Acervo/livros_catalogo.php");
                }
                exit;
            } else {
                $mensagem = "E-mail ou senha incorretos.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $mensagem = "Não foi possível realizar o login.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Biblioteca de Babel</title>
    <link rel="stylesheet" href="../../Styles/default.css">
</head>
<body>
    <main>
        <h1>Biblioteca de Babel</h1>
        <h2>Entrar</h2>

        <?php if (!empty($mensagem)): ?>
            <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <p>
                <label for="email">E-mail:</label><br>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </p>
            <p>
                <label for="senha">Senha:</label><br>
                <input type="password" id="senha" name="senha" required>
            </p>
            <button type="submit">Entrar</button>
        </form>

        <p>Não possui cadastro? <a href="users_cadastro.php">Cadastre-se</a></p>
    </main>
</body>
</html>
<?php
$conn->close();
?>
