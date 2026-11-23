<?php
date_default_timezone_set("America/Sao_Paulo");

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
$mensagem = $_SESSION["mensagem_emprestimos"] ?? "";
unset($_SESSION["mensagem_emprestimos"]);

// O documento do projeto não informa o valor diário da multa.
// Defina aqui o valor aprovado pelo administrador antes de usar o cálculo da RN04.
$valor_multa_por_dia = 0.00;

// Marca como atrasados os empréstimos que passaram do prazo e ainda não foram devolvidos.
$sql_atrasados = "UPDATE tbl_emprestimo
                  SET status = 'atrasado'
                  WHERE status = 'ativo' AND data_devolucao_prevista < CURDATE()";
mysqli_query($conn, $sql_atrasados);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $acao = $_POST["acao"] ?? "";

    // REGISTRO DE EMPRÉSTIMO
    if ($acao === "emprestar") {
        $id_livro = (int)($_POST["id_livro"] ?? 0);
        $id_usuario = $is_admin ? (int)($_POST["id_usuario"] ?? 0) : (int)$_SESSION["id_usuario"];

        if ($id_livro <= 0 || $id_usuario <= 0) {
            $mensagem = "Selecione um usuário e um livro válidos.";
        } else {
            $conn->begin_transaction();
            $pode_emprestar = true;
            $mensagem_erro = "";

            // Bloqueia a linha do usuário para impedir que dois pedidos simultâneos
            // ultrapassem o limite de três empréstimos ativos.
            $sql_usuario = "SELECT id_usuario FROM tbl_usuario WHERE id_usuario = ? FOR UPDATE";
            $stmt_usuario = mysqli_prepare($conn, $sql_usuario);

            if ($stmt_usuario) {
                mysqli_stmt_bind_param($stmt_usuario, "i", $id_usuario);
                mysqli_stmt_execute($stmt_usuario);
                $resultado_usuario = mysqli_stmt_get_result($stmt_usuario);
                $usuario_existe = mysqli_fetch_assoc($resultado_usuario);
                mysqli_stmt_close($stmt_usuario);

                if (!$usuario_existe) {
                    $pode_emprestar = false;
                    $mensagem_erro = "O usuário selecionado não existe.";
                }
            } else {
                $pode_emprestar = false;
                $mensagem_erro = "Erro ao verificar o usuário.";
            }

            // Bloqueia a linha do livro e confirma que ele pertence ao acervo tradicional.
            if ($pode_emprestar) {
                $sql_livro = "SELECT id_livro FROM tbl_livro
                              WHERE id_livro = ? AND gerado_aleatoriamente = 0
                              FOR UPDATE";
                $stmt_livro = mysqli_prepare($conn, $sql_livro);

                if ($stmt_livro) {
                    mysqli_stmt_bind_param($stmt_livro, "i", $id_livro);
                    mysqli_stmt_execute($stmt_livro);
                    $resultado_livro = mysqli_stmt_get_result($stmt_livro);
                    $livro_existe = mysqli_fetch_assoc($resultado_livro);
                    mysqli_stmt_close($stmt_livro);

                    if (!$livro_existe) {
                        $pode_emprestar = false;
                        $mensagem_erro = "O livro não existe ou não pertence ao acervo tradicional.";
                    }
                } else {
                    $pode_emprestar = false;
                    $mensagem_erro = "Erro ao verificar o livro.";
                }
            }

            // RN01: no máximo três empréstimos que ainda não foram devolvidos.
            if ($pode_emprestar) {
                $sql_limite = "SELECT COUNT(*) AS total
                               FROM tbl_emprestimo
                               WHERE id_usuario = ? AND status IN ('ativo', 'atrasado')";
                $stmt_limite = mysqli_prepare($conn, $sql_limite);

                if ($stmt_limite) {
                    mysqli_stmt_bind_param($stmt_limite, "i", $id_usuario);
                    mysqli_stmt_execute($stmt_limite);
                    $resultado_limite = mysqli_stmt_get_result($stmt_limite);
                    $dados_limite = mysqli_fetch_assoc($resultado_limite);
                    mysqli_stmt_close($stmt_limite);

                    if ((int)$dados_limite["total"] >= 3) {
                        $pode_emprestar = false;
                        $mensagem_erro = "Este usuário já possui três empréstimos ativos.";
                    }
                } else {
                    $pode_emprestar = false;
                    $mensagem_erro = "Erro ao verificar o limite de empréstimos.";
                }
            }

            // RN02: uma multa pendente bloqueia um novo empréstimo.
            if ($pode_emprestar) {
                $sql_multas = "SELECT COUNT(*) AS total
                               FROM tbl_multa m
                               INNER JOIN tbl_emprestimo e ON e.id_emprestimo = m.id_emprestimo
                               WHERE e.id_usuario = ? AND m.status_multa = 'pendente'";
                $stmt_multas = mysqli_prepare($conn, $sql_multas);

                if ($stmt_multas) {
                    mysqli_stmt_bind_param($stmt_multas, "i", $id_usuario);
                    mysqli_stmt_execute($stmt_multas);
                    $resultado_multas = mysqli_stmt_get_result($stmt_multas);
                    $dados_multas = mysqli_fetch_assoc($resultado_multas);
                    mysqli_stmt_close($stmt_multas);

                    if ((int)$dados_multas["total"] > 0) {
                        $pode_emprestar = false;
                        $mensagem_erro = "Este usuário possui multa pendente e está bloqueado para novos empréstimos.";
                    }
                } else {
                    $pode_emprestar = false;
                    $mensagem_erro = "Erro ao verificar as multas pendentes.";
                }
            }

            // Um exemplar não pode ter dois empréstimos ativos ao mesmo tempo.
            if ($pode_emprestar) {
                $sql_disponibilidade = "SELECT COUNT(*) AS total
                                        FROM tbl_emprestimo
                                        WHERE id_livro = ? AND status IN ('ativo', 'atrasado')";
                $stmt_disponibilidade = mysqli_prepare($conn, $sql_disponibilidade);

                if ($stmt_disponibilidade) {
                    mysqli_stmt_bind_param($stmt_disponibilidade, "i", $id_livro);
                    mysqli_stmt_execute($stmt_disponibilidade);
                    $resultado_disponibilidade = mysqli_stmt_get_result($stmt_disponibilidade);
                    $dados_disponibilidade = mysqli_fetch_assoc($resultado_disponibilidade);
                    mysqli_stmt_close($stmt_disponibilidade);

                    if ((int)$dados_disponibilidade["total"] > 0) {
                        $pode_emprestar = false;
                        $mensagem_erro = "Este livro já está emprestado.";
                    }
                } else {
                    $pode_emprestar = false;
                    $mensagem_erro = "Erro ao verificar a disponibilidade do livro.";
                }
            }

            if ($pode_emprestar) {
                // RN03: prazo de 14 dias corridos a partir da data do empréstimo.
                $sql_insert = "INSERT INTO tbl_emprestimo
                               (id_usuario, id_livro, data_emprestimo, data_devolucao_prevista, status)
                               VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'ativo')";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);

                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $id_usuario, $id_livro);

                    if (mysqli_stmt_execute($stmt_insert)) {
                        mysqli_stmt_close($stmt_insert);
                        $conn->commit();

                        $_SESSION["mensagem_emprestimos"] = "Empréstimo registrado. O prazo de devolução é de 14 dias corridos.";
                        header("Location: emprestimos.php");
                        exit;
                    } else {
                        $pode_emprestar = false;
                        $mensagem_erro = "Não foi possível registrar o empréstimo.";
                        mysqli_stmt_close($stmt_insert);
                    }
                } else {
                    $pode_emprestar = false;
                    $mensagem_erro = "Erro ao preparar o empréstimo.";
                }
            }

            if (!$pode_emprestar) {
                $conn->rollback();
                $mensagem = $mensagem_erro;
            }
        }
    }

    // DEVOLUÇÃO DE EMPRÉSTIMO
    if ($acao === "devolver") {
        $id_emprestimo = (int)($_POST["id_emprestimo"] ?? 0);

        if ($id_emprestimo <= 0) {
            $mensagem = "Empréstimo inválido.";
        } else {
            // Primeiro identifica o dono do empréstimo; depois bloqueia a linha do usuário.
            $sql_dono = "SELECT id_usuario FROM tbl_emprestimo WHERE id_emprestimo = ?";
            $stmt_dono = mysqli_prepare($conn, $sql_dono);
            $id_usuario_emprestimo = 0;

            if ($stmt_dono) {
                mysqli_stmt_bind_param($stmt_dono, "i", $id_emprestimo);
                mysqli_stmt_execute($stmt_dono);
                $resultado_dono = mysqli_stmt_get_result($stmt_dono);
                $dados_dono = mysqli_fetch_assoc($resultado_dono);
                mysqli_stmt_close($stmt_dono);

                if ($dados_dono) {
                    $id_usuario_emprestimo = (int)$dados_dono["id_usuario"];
                }
            }

            if ($id_usuario_emprestimo <= 0 || (!$is_admin && $id_usuario_emprestimo !== (int)$_SESSION["id_usuario"])) {
                $mensagem = "Empréstimo não encontrado ou sem permissão para devolvê-lo.";
            } else {
                $conn->begin_transaction();

                $sql_bloqueia_usuario = "SELECT id_usuario FROM tbl_usuario WHERE id_usuario = ? FOR UPDATE";
                $stmt_bloqueia_usuario = mysqli_prepare($conn, $sql_bloqueia_usuario);

                if ($stmt_bloqueia_usuario) {
                    mysqli_stmt_bind_param($stmt_bloqueia_usuario, "i", $id_usuario_emprestimo);
                    mysqli_stmt_execute($stmt_bloqueia_usuario);
                    mysqli_stmt_close($stmt_bloqueia_usuario);
                }

                $sql_emprestimo = "SELECT id_emprestimo, id_usuario, data_devolucao_prevista, status
                                   FROM tbl_emprestimo
                                   WHERE id_emprestimo = ?
                                   FOR UPDATE";
                $stmt_emprestimo = mysqli_prepare($conn, $sql_emprestimo);

                if ($stmt_emprestimo) {
                    mysqli_stmt_bind_param($stmt_emprestimo, "i", $id_emprestimo);
                    mysqli_stmt_execute($stmt_emprestimo);
                    $resultado_emprestimo = mysqli_stmt_get_result($stmt_emprestimo);
                    $emprestimo = mysqli_fetch_assoc($resultado_emprestimo);
                    mysqli_stmt_close($stmt_emprestimo);

                    if (!$emprestimo || !in_array($emprestimo["status"], ["ativo", "atrasado"], true)) {
                        $conn->rollback();
                        $mensagem = "Este empréstimo já foi devolvido ou não existe.";
                    } else {
                        $data_hoje = date("Y-m-d");
                        $data_prevista = new DateTime($emprestimo["data_devolucao_prevista"]);
                        $data_real = new DateTime($data_hoje);
                        $intervalo = $data_prevista->diff($data_real);
                        $dias_atraso = (int)$intervalo->format("%r%a");

                        if ($dias_atraso < 0) {
                            $dias_atraso = 0;
                        }

                        $sql_devolver = "UPDATE tbl_emprestimo
                                         SET data_devolucao_real = ?, status = 'devolvido'
                                         WHERE id_emprestimo = ?";
                        $stmt_devolver = mysqli_prepare($conn, $sql_devolver);

                        if ($stmt_devolver) {
                            mysqli_stmt_bind_param($stmt_devolver, "si", $data_hoje, $id_emprestimo);

                            if (mysqli_stmt_execute($stmt_devolver)) {
                                mysqli_stmt_close($stmt_devolver);

                                if ($dias_atraso > 0 && $valor_multa_por_dia > 0) {
                                    // RN04: multa = dias de atraso x valor diário definido pelo administrador.
                                    $valor_multa = round($dias_atraso * $valor_multa_por_dia, 2);

                                    $sql_multa = "INSERT INTO tbl_multa (id_emprestimo, valor, status_multa)
                                                  VALUES (?, ?, 'pendente')";
                                    $stmt_multa = mysqli_prepare($conn, $sql_multa);

                                    if ($stmt_multa) {
                                        mysqli_stmt_bind_param($stmt_multa, "id", $id_emprestimo, $valor_multa);

                                        if (mysqli_stmt_execute($stmt_multa)) {
                                            mysqli_stmt_close($stmt_multa);
                                            $conn->commit();

                                            $_SESSION["mensagem_emprestimos"] = "Devolução registrada. Atraso de " . $dias_atraso . " dia(s); multa de R$ " . number_format($valor_multa, 2, ",", ".") . " registrada como pendente.";
                                            header("Location: emprestimos.php");
                                            exit;
                                        } else {
                                            mysqli_stmt_close($stmt_multa);
                                            $conn->rollback();
                                            $mensagem = "Não foi possível registrar a multa. A devolução não foi concluída.";
                                        }
                                    } else {
                                        $conn->rollback();
                                        $mensagem = "Erro ao preparar o registro da multa. A devolução não foi concluída.";
                                    }
                                } else {
                                    $conn->commit();

                                    if ($dias_atraso > 0 && $valor_multa_por_dia <= 0) {
                                        $mensagem = "Devolução registrada com " . $dias_atraso . " dia(s) de atraso. ATENÇÃO: defina o valor diário da multa no início deste arquivo para ativar o cálculo da RN04.";
                                    } else {
                                        $_SESSION["mensagem_emprestimos"] = "Devolução registrada sem atraso.";
                                        header("Location: emprestimos.php");
                                        exit;
                                    }
                                }
                            } else {
                                mysqli_stmt_close($stmt_devolver);
                                $conn->rollback();
                                $mensagem = "Não foi possível registrar a devolução.";
                            }
                        } else {
                            $conn->rollback();
                            $mensagem = "Erro ao preparar a devolução.";
                        }
                    }
                } else {
                    $conn->rollback();
                    $mensagem = "Erro ao consultar o empréstimo.";
                }
            }
        }
    }
}

// Livros tradicionais que não possuem empréstimo ativo ou atrasado.
$sql_livros = "SELECT l.id_livro, l.titulo, a.nome_autor
               FROM tbl_livro l
               INNER JOIN tbl_autor a ON a.id_autor = l.id_autor
               WHERE l.gerado_aleatoriamente = 0
                 AND NOT EXISTS (
                     SELECT 1 FROM tbl_emprestimo e
                     WHERE e.id_livro = l.id_livro
                       AND e.status IN ('ativo', 'atrasado')
                 )
               ORDER BY l.titulo";
$livros_disponiveis = mysqli_query($conn, $sql_livros);

if ($is_admin) {
    $usuarios = mysqli_query($conn, "SELECT id_usuario, nome, email FROM tbl_usuario ORDER BY nome");

    $sql_lista = "SELECT e.id_emprestimo, e.id_usuario, e.data_emprestimo,
                         e.data_devolucao_prevista, e.data_devolucao_real, e.status,
                         u.nome AS nome_usuario, l.titulo
                  FROM tbl_emprestimo e
                  INNER JOIN tbl_usuario u ON u.id_usuario = e.id_usuario
                  INNER JOIN tbl_livro l ON l.id_livro = e.id_livro
                  ORDER BY e.data_emprestimo DESC, e.id_emprestimo DESC";

    $emprestimos = mysqli_query($conn, $sql_lista);
} else {
    $sql_lista = "SELECT e.id_emprestimo, e.id_usuario, e.data_emprestimo,
                         e.data_devolucao_prevista, e.data_devolucao_real, e.status,
                         u.nome AS nome_usuario, l.titulo
                  FROM tbl_emprestimo e
                  INNER JOIN tbl_usuario u ON u.id_usuario = e.id_usuario
                  INNER JOIN tbl_livro l ON l.id_livro = e.id_livro
                  WHERE e.id_usuario = ?
                  ORDER BY e.data_emprestimo DESC, e.id_emprestimo DESC";

    $stmt_lista = mysqli_prepare($conn, $sql_lista);
    $emprestimos = false;

    if ($stmt_lista) {
        mysqli_stmt_bind_param($stmt_lista, "i", $_SESSION["id_usuario"]);
        mysqli_stmt_execute($stmt_lista);
        $emprestimos = mysqli_stmt_get_result($stmt_lista);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empréstimos - Biblioteca de Babel</title>
    <link rel="stylesheet" href="../../Styles/default.css">
</head>
<body>
    <main>
        <h1>Empréstimos e devoluções</h1>

        <nav>
            <a href="../Acervo/livros_catalogo.php">Livros</a> |
            <a href="../Acervo/autores_catalogo.php">Autores</a> |
            <a href="emprestimos.php">Empréstimos</a> |
            <a href="multas.php">Multas</a>

            <?php if ($is_admin): ?>
                | <a href="../Acervo/livros_gerencia.php">Gerenciar acervo</a>
            <?php endif; ?>

            | <a href="../Users/users_login.php?sair=1">Sair</a>
        </nav>

        <?php if (!empty($mensagem)): ?>
            <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <h2>Registrar empréstimo</h2>

        <form action="" method="POST">
            <input type="hidden" name="acao" value="emprestar">

            <?php if ($is_admin): ?>
                <p>
                    <label for="id_usuario">Leitor:</label><br>
                    <select id="id_usuario" name="id_usuario" required>
                        <option value="">Selecione um usuário</option>

                        <?php if ($usuarios): ?>
                            <?php while ($usuario = mysqli_fetch_assoc($usuarios)): ?>
                                <option value="<?php echo (int)$usuario['id_usuario']; ?>">
                                    <?php echo htmlspecialchars($usuario['nome'] . " - " . $usuario['email'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </p>
            <?php else: ?>
                <p>O empréstimo será registrado em seu próprio nome.</p>
            <?php endif; ?>

            <p>
                <label for="id_livro">Livro disponível:</label><br>
                <select id="id_livro" name="id_livro" required>
                    <option value="">Selecione um livro</option>

                    <?php if ($livros_disponiveis): ?>
                        <?php while ($livro = mysqli_fetch_assoc($livros_disponiveis)): ?>
                            <option value="<?php echo (int)$livro['id_livro']; ?>">
                                <?php echo htmlspecialchars($livro['titulo'] . " - " . $livro['nome_autor'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </p>

            <p>Prazo padrão: 14 dias corridos a partir da data do empréstimo.</p>
            <button type="submit">Registrar empréstimo</button>
        </form>

        <h2><?php echo $is_admin ? "Todos os empréstimos" : "Meus empréstimos"; ?></h2>

        <?php if ($emprestimos && mysqli_num_rows($emprestimos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <?php if ($is_admin): ?>
                            <th>Leitor</th>
                        <?php endif; ?>

                        <th>Livro</th>
                        <th>Data do empréstimo</th>
                        <th>Devolução prevista</th>
                        <th>Devolução real</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($emprestimo = mysqli_fetch_assoc($emprestimos)): ?>
                        <tr>
                            <?php if ($is_admin): ?>
                                <td><?php echo htmlspecialchars($emprestimo['nome_usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endif; ?>

                            <td><?php echo htmlspecialchars($emprestimo['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($emprestimo['data_emprestimo'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($emprestimo['data_devolucao_prevista'])); ?></td>
                            <td>
                                <?php echo $emprestimo['data_devolucao_real'] ? date('d/m/Y', strtotime($emprestimo['data_devolucao_real'])) : "—"; ?>
                            </td>
                            <td><?php echo htmlspecialchars($emprestimo['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if (in_array($emprestimo['status'], ['ativo', 'atrasado'], true)): ?>
                                    <form action="" method="POST">
                                        <input type="hidden" name="acao" value="devolver">
                                        <input type="hidden" name="id_emprestimo" value="<?php echo (int)$emprestimo['id_emprestimo']; ?>">
                                        <button type="submit">Registrar devolução</button>
                                    </form>
                                <?php else: ?>
                                    Devolvido
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhum empréstimo registrado.</p>
        <?php endif; ?>

        <?php if ($valor_multa_por_dia <= 0): ?>
            <p>
                <strong>Configuração pendente:</strong>
                o valor da multa por dia ainda não foi informado.
                Defina <code>$valor_multa_por_dia</code> no início deste arquivo
                para ativar o cálculo automático da RN04.
            </p>
        <?php else: ?>
            <p>
                Valor da multa por dia:
                R$ <?php echo number_format($valor_multa_por_dia, 2, ",", "."); ?>
            </p>
        <?php endif; ?>
    </main>
</body>
</html>
<?php
if (isset($stmt_lista)) {
    mysqli_stmt_close($stmt_lista);
}
$conn->close();
?>
