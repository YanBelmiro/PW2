<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $confirmar_senha = $_POST["confirmar_senha"] ?? "";

    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        $mensagem = "Preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
    } elseif (strlen($senha) < 8) {
        $mensagem = "A senha deve ter pelo menos 8 caracteres.";
    } elseif ($senha !== $confirmar_senha) {
        $mensagem = "As senhas não coincidem.";
    } else {
        $sql_busca = "SELECT id_usuario FROM tbl_usuario WHERE email = ?";
        $stmt_busca = mysqli_prepare($conn, $sql_busca);

        if ($stmt_busca) {
            mysqli_stmt_bind_param($stmt_busca, "s", $email);
            mysqli_stmt_execute($stmt_busca);
            $resultado_busca = mysqli_stmt_get_result($stmt_busca);

            if (mysqli_num_rows($resultado_busca) > 0) {
                $mensagem = "Este e-mail já está cadastrado.";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                // Todo cadastro público começa como leitor.
                $sql_insert = "INSERT INTO tbl_usuario (nome, email, senha) VALUES (?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);

                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "sss", $nome, $email, $senha_hash);

                    if (mysqli_stmt_execute($stmt_insert)) {
                        $mensagem = "Cadastro realizado com sucesso! Você já pode entrar.";
                    } else {
                        $mensagem = "Não foi possível realizar o cadastro. Verifique os dados e tente novamente.";
                    }

                    mysqli_stmt_close($stmt_insert);
                } else {
                    $mensagem = "Erro ao preparar o cadastro.";
                }
            }

            mysqli_stmt_close($stmt_busca);
        } else {
            $mensagem = "Erro ao verificar o e-mail.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Biblioteca de Babel</title>
    <link rel="stylesheet" href="../../Styles/default.css">
</head>
<body>
    <main>
        <h1>Cadastro de usuário</h1>

        <?php if (!empty($mensagem)): ?>
            <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <p>
                <label for="nome">Nome:</label><br>
                <input type="text" id="nome" name="nome" maxlength="100" required
                       value="<?php echo htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </p>
            <p>
                <label for="email">E-mail:</label><br>
                <input type="email" id="email" name="email" maxlength="100" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </p>
            <p>
                <label for="senha">Senha (mínimo de 8 caracteres):</label><br>
                <input type="password" id="senha" name="senha" minlength="8" required>
            </p>
            <p>
                <label for="confirmar_senha">Confirmar senha:</label><br>
                <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" required>
            </p>
            <button type="submit">Cadastrar</button>
        </form>

        <p>Já possui cadastro? <a href="users_login.php">Fazer login</a></p>
    </main>
</body>
</html>
<?php
$conn->close();
?>
