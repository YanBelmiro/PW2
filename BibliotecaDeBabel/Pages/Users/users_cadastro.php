<?php
require_once "../../Includes/aviso.php";
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
$sucesso = false;

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

                // Todo cadastro público começa como betinha.
                $sql_insert = "INSERT INTO tbl_usuario (nome, email, senha) VALUES (?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);

                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "sss", $nome, $email, $senha_hash);

                    if (mysqli_stmt_execute($stmt_insert)) {
                        $sucesso = true;
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cadastro - Biblioteca de Babel</title>

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

    <section id="formularios" class="test-block">
      <h2>Cadastro de Novo Usuário</h2>
      <p>Crie sua conta para acessar o sistema de acervo e realizar empréstimos (perfil padrão: Leitor).</p>

      <?php echo aviso_html($mensagem, $sucesso ? "sucesso" : "erro"); ?>

      <form action="" method="POST">
        <fieldset>
          <legend>Informações Pessoais e Credenciais</legend>

          <div class="grid-2">
            <div>
              <label for="nome">Nome Completo</label>
              <input type="text" id="nome" name="nome" maxlength="100" placeholder="Seu nome" required
                     value="<?php echo htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div>
              <label for="email">E-mail</label>
              <input type="email" id="email" name="email" maxlength="100" placeholder="email@exemplo.com" required
                     value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
          </div>

          <div class="grid-2" style="margin-top: 1.25rem;">
            <div>
              <label for="senha">Senha (mínimo de 8 caracteres)</label>
              <input type="password" id="senha" name="senha" minlength="8" placeholder="********" required />
            </div>

            <div>
              <label for="confirmar_senha">Confirmar senha</label>
              <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" placeholder="********" required />
            </div>
          </div>
        </fieldset>

        <div id="botoes" class="inline-group" style="margin-top: 1.5rem;">
          <button type="submit">Cadastrar</button>
          <button type="button" id="open-modal-btn">Termos de Uso</button>
        </div>
      </form>

      <p style="margin-top: 1.5rem;">Já possui cadastro? <a href="users_login.php">Fazer login</a></p>
    </section>

  </main>

  <!-- MODAL DE TERMOS -->
  <dialog id="test-dialog">
    <h3>Termos e Regras da Biblioteca</h3>
    <p>Ao se cadastrar, você concorda com as regras de empréstimo da Biblioteca de Babel:</p>
    <ul>
      <li>Limite de até 3 empréstimos ativos simultâneos.</li>
      <li>Prazo padrão de devolução de 14 dias corridos.</li>
      <li>Bloqueio automático de novos empréstimos caso existam multas pendentes.</li>
    </ul>
    <div class="inline-group" style="justify-content: flex-end; margin-top: 1.5rem;">
      <button type="button" id="close-modal-btn">Entendi</button>
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
