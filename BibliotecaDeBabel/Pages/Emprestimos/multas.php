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
$is_admin = ($_SESSION["tipo_usuario"] === "admin");
$mensagem = $_SESSION["mensagem_multas"] ?? "";
unset($_SESSION["mensagem_multas"]);

if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_admin) {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "registrar_pagamento") {
        $id_multa = (int)($_POST["id_multa"] ?? 0);

        if ($id_multa <= 0) {
            $mensagem = "Multa inválida.";
        } else {
            // Descobre o usuário relacionado à multa para sincronizar a operação
            // com a verificação de multas feita ao registrar novos empréstimos.
            $sql_dono = "SELECT e.id_usuario
                         FROM tbl_multa m
                         INNER JOIN tbl_emprestimo e ON e.id_emprestimo = m.id_emprestimo
                         WHERE m.id_multa = ?";
            $stmt_dono = mysqli_prepare($conn, $sql_dono);
            $id_usuario_multa = 0;

            if ($stmt_dono) {
                mysqli_stmt_bind_param($stmt_dono, "i", $id_multa);
                mysqli_stmt_execute($stmt_dono);
                $resultado_dono = mysqli_stmt_get_result($stmt_dono);
                $dados_dono = mysqli_fetch_assoc($resultado_dono);
                mysqli_stmt_close($stmt_dono);

                if ($dados_dono) {
                    $id_usuario_multa = (int)$dados_dono["id_usuario"];
                }
            }

            if ($id_usuario_multa <= 0) {
                $mensagem = "Multa não encontrada.";
            } else {
                $conn->begin_transaction();

                $sql_bloqueia_usuario = "SELECT id_usuario FROM tbl_usuario WHERE id_usuario = ? FOR UPDATE";
                $stmt_usuario = mysqli_prepare($conn, $sql_bloqueia_usuario);

                if ($stmt_usuario) {
                    mysqli_stmt_bind_param($stmt_usuario, "i", $id_usuario_multa);
                    mysqli_stmt_execute($stmt_usuario);
                    mysqli_stmt_close($stmt_usuario);
                }

                $sql_multa = "SELECT status_multa FROM tbl_multa WHERE id_multa = ? FOR UPDATE";
                $stmt_multa = mysqli_prepare($conn, $sql_multa);

                if ($stmt_multa) {
                    mysqli_stmt_bind_param($stmt_multa, "i", $id_multa);
                    mysqli_stmt_execute($stmt_multa);
                    $resultado_multa = mysqli_stmt_get_result($stmt_multa);
                    $multa = mysqli_fetch_assoc($resultado_multa);
                    mysqli_stmt_close($stmt_multa);

                    if (!$multa || $multa["status_multa"] !== "pendente") {
                        $conn->rollback();
                        $mensagem = "Esta multa não existe ou já foi paga.";
                    } else {
                        $sql_update = "UPDATE tbl_multa SET status_multa = 'paga' WHERE id_multa = ?";
                        $stmt_update = mysqli_prepare($conn, $sql_update);

                        if ($stmt_update) {
                            mysqli_stmt_bind_param($stmt_update, "i", $id_multa);

                            if (mysqli_stmt_execute($stmt_update)) {
                                mysqli_stmt_close($stmt_update);
                                $conn->commit();

                                $_SESSION["mensagem_multas"] = "Pagamento da multa registrado com sucesso. O usuário está liberado para novos empréstimos, caso não possua outras multas pendentes (RN02)[cite: 4, 5].";
                                header("Location: multas.php");
                                exit;
                            } else {
                                mysqli_stmt_close($stmt_update);
                                $conn->rollback();
                                $mensagem = "Não foi possível registrar o pagamento.";
                            }
                        } else {
                            $conn->rollback();
                            $mensagem = "Erro ao preparar o registro de pagamento.";
                        }
                    }
                } else {
                    $conn->rollback();
                    $mensagem = "Erro ao consultar a multa.";
                }
            }
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_admin) {
    $mensagem = "Somente administradores podem registrar pagamentos de multas.";
}

$sql_lista = "SELECT m.id_multa, m.valor, m.status_multa,
                     e.id_emprestimo, e.data_devolucao_prevista, e.data_devolucao_real,
                     u.nome AS nome_usuario, l.titulo
              FROM tbl_multa m
              INNER JOIN tbl_emprestimo e ON e.id_emprestimo = m.id_emprestimo
              INNER JOIN tbl_usuario u ON u.id_usuario = e.id_usuario
              INNER JOIN tbl_livro l ON l.id_livro = e.id_livro";

if ($is_admin) {
    $sql_lista .= " ORDER BY m.status_multa, u.nome, m.id_multa DESC";
    $multas = mysqli_query($conn, $sql_lista);
} else {
    $sql_lista .= " WHERE e.id_usuario = ? ORDER BY m.status_multa, m.id_multa DESC";
    $stmt_lista = mysqli_prepare($conn, $sql_lista);
    $multas = false;

    if ($stmt_lista) {
        mysqli_stmt_bind_param($stmt_lista, "i", $_SESSION["id_usuario"]);
        mysqli_stmt_execute($stmt_lista);
        $multas = mysqli_stmt_get_result($stmt_lista);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Multas - Biblioteca de Babel</title>
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
      <a href="../Acervo/livros_catalogo.php">Livros</a>
      <a href="../Acervo/autores_catalogo.php">Autores</a>
      <a href="emprestimos.php">Empréstimos</a>
      <a href="multas.php">Multas</a>
      <?php if ($is_admin): ?>
        <a href="../Acervo/livros_gerencia.php">Gerenciar Acervo</a>
      <?php endif; ?>
      <a href="../Users/users_login.php?sair=1">Sair</a>
    </nav>
  </header>

  <!-- CONTENT -->
  <main class="content">

    <?php if (!empty($mensagem)): ?>
      <div class="test-block" style="background-color: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 1.5rem; padding: 1rem;">
        <p style="margin: 0; color: #856404;"><strong>Aviso:</strong> <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    <?php endif; ?>

    <!-- TABELA DE MULTAS -->
    <section id="tabelas-semanticos" class="test-block">
      <h2>Controle de Multas e Penalidades</h2>
      <p>Gerenciamento financeiro de penalidades por atraso na devolução de obras (RN04). Multas pendentes bloqueiam novos empréstimos (RN02)[cite: 4, 5].</p>

      <?php if ($multas && mysqli_num_rows($multas) > 0): ?>
        <table>
          <thead>
            <tr>
              <?php if ($is_admin): ?>
                <th>Leitor</th>
              <?php endif; ?>
              <th>Livro</th>
              <th>Empréstimo</th>
              <th>Devolução Prevista</th>
              <th>Devolução Real</th>
              <th>Valor</th>
              <th>Status</th>
              <?php if ($is_admin): ?>
                <th>Ação</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php while ($multa = mysqli_fetch_assoc($multas)): ?>
              <tr>
                <?php if ($is_admin): ?>
                  <td><?php echo htmlspecialchars($multa['nome_usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($multa['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>#<?php echo (int)$multa['id_emprestimo']; ?></td>
                <td><?php echo date('d/m/Y', strtotime($multa['data_devolucao_prevista'])); ?></td>
                <td>
                  <?php echo $multa['data_devolucao_real'] ? date('d/m/Y', strtotime($multa['data_devolucao_real'])) : "—"; ?>
                </td>
                <td>R$ <?php echo number_format((float)$multa['valor'], 2, ",", "."); ?></td>
                <td><?php echo htmlspecialchars($multa['status_multa'], ENT_QUOTES, 'UTF-8'); ?></td>

                <?php if ($is_admin): ?>
                  <td>
                    <?php if ($multa['status_multa'] === 'pendente'): ?>
                      <form action="" method="POST" style="margin: 0;">
                        <input type="hidden" name="acao" value="registrar_pagamento">
                        <input type="hidden" name="id_multa" value="<?php echo (int)$multa['id_multa']; ?>">
                        <button type="submit" style="padding: 4px 8px; font-size: 0.85rem;">Registrar Pagamento</button>
                      </form>
                    <?php else: ?>
                      Paga
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Nenhuma multa registrada.</p>
      <?php endif; ?>
    </section>

  </main>
</body>
</html>
<?php
if (isset($stmt_lista)) {
    mysqli_stmt_close($stmt_lista);
}
$conn->close();
?>