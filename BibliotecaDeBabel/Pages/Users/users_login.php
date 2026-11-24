<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Biblioteca de Babel</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="../../Styles/default.css" />
  
  <script src="../../Scripts/logo.js" defer></script>
</head>
<body>

  <!-- HEADER -->
  <?php include "../../Includes/header.php"; ?>

  <!-- CONTENT -->
  <main class="content">

    <section id="formularios" class="test-block">
      <h2>Entrar no Sistema</h2>
      <p>Acesse com seu e-mail e senha cadastrados para gerenciar empréstimos e o acervo[cite: 4].</p>

      <?php if (!empty($mensagem)): ?>
        <div class="test-block" style="background-color: #ffe6e6; border-left: 4px solid #ff4d4d; margin-bottom: 1.5rem; padding: 1rem;">
          <p style="margin: 0; color: #b30000;"><strong>Aviso:</strong> <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php endif; ?>

      <form action="" method="POST">
        <fieldset>
          <legend>Credenciais de Acesso</legend>

          <div class="grid-2">
            <div>
              <label for="email">E-mail</label>
              <input type="email" id="email" name="email" placeholder="email@exemplo.com" required 
                     value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div>
              <label for="senha">Senha</label>
              <input type="password" id="senha" name="senha" placeholder="Sua senha" required />
            </div>
          </div>
        </fieldset>

        <div id="botoes" class="inline-group" style="margin-top: 1.5rem;">
          <button type="submit">Entrar</button>
          <button type="button" id="open-modal-btn">Ajuda / Sobre</button>
        </div>
      </form>
    </section>

  </main>

  <!-- MODAL DE SUPORTE / AJUDA -->
  <dialog id="test-dialog">
    <h3>Ajuda - Biblioteca de Babel</h3>
    <p>Se você esqueceu sua senha ou possui problemas de acesso, procure o administrador da biblioteca responsável pela disciplina de Programação Web 2[cite: 2].</p>
    <div class="inline-group" style="justify-content: flex-end; margin-top: 1.5rem;">
      <button type="button" id="close-modal-btn">Fechar</button>
    </div>
  </dialog>

  <script>
    const dialog = document.getElementById("test-dialog");
    const openBtn = document.getElementById("open-modal-btn");
    const closeBtn = document.getElementById("close-modal-btn");

    if (openBtn && dialog && closeBtn) {
      openBtn.addEventListener("click", () => dialog.showModal());
      closeBtn.addEventListener("click", () => dialog.close());
      dialog.addEventListener("click", (e) => {
        if (e.target === dialog) dialog.close();
      });
    }
  </script>

</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>