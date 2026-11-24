<?php
// Evita erro caso a sessão já esteja ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_path = $_SERVER['PHP_SELF'];
$esta_em_users = (strpos($script_path, '/Users/') !== false);
$prefixo = $esta_em_users ? "../" : ""; 

$usuario_logado = isset($_SESSION["id_usuario"]);
$is_admin = $usuario_logado && (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "admin");
?>

<header>
  <div class="brand">
    <a href="<?php echo $prefixo; ?>../index.php" style="display: flex; align-items: center; text-decoration: none; border-bottom: none; gap: 12px; color: inherit;">
      <svg class="logo logo-animada" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1500 1499.999933">
        <!-- 1º Hexágono -->
        <g class="hex-1">
          <path stroke-linecap="butt" transform="matrix(0.75, 0, 0, 0.75, 0.00003335, 105.468722)" fill="none" stroke-linejoin="miter" d="M 1997.656378 858.364694 L 1498.239668 1716.73456 L 499.416665 1716.73456 L -0.0000444667 858.364694 L 499.416665 0.0000371792 L 1498.239668 0.0000371792 Z M 1997.656378 858.364694 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
        </g>
        <!-- 2º Hexágono -->
        <g class="hex-2">
          <path stroke-linecap="butt" transform="matrix(0.0203951, 0.749723, -0.749723, 0.0203951, 1254.571221, 129.492053)" fill="none" stroke-linejoin="miter" d="M 1617.485087 695.014097 L 1213.112726 1390.030102 L 404.370961 1390.027214 L 0.00141377 695.013529 L 404.373633 0.00273075 L 1213.11554 0.000411897 Z M 1617.485087 695.014097 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
        </g>
        <!-- 3º Hexágono -->
        <g class="hex-3">
          <path stroke-linecap="butt" transform="matrix(-0.75, 0, 0, -0.75, 1246.910206, 1177.783247)" fill="none" stroke-linejoin="miter" d="M 1327.437681 570.38292 L 995.578277 1140.763177 L 331.85947 1140.763177 L 0.000066266 570.38292 L 331.85947 -0.00254626 L 995.578277 -0.00254626 Z M 1327.437681 570.38292 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
        </g>
      </svg>
      <p class="brand-title" style="color: #ffffff;">BIBLIOTECA DE BABEL</p>
    </a>
  </div>

  <nav aria-label="Navegação">
    <?php if ($esta_em_users): ?>
      <a href="users_login.php">Entrar</a>
      <a href="users_cadastro.php">Cadastre-se</a>
      <a href="<?php echo $prefixo; ?>../index.php">Início</a>
    <?php else: ?>
      <a href="<?php echo $prefixo; ?>../Acervo/livros_catalogo.php">Livros</a>
      <a href="<?php echo $prefixo; ?>../Acervo/autores_catalogo.php">Autores</a>
      <a href="<?php echo $prefixo; ?>../Emprestimos/emprestimos.php">Empréstimos</a>
      <a href="<?php echo $prefixo; ?>../Emprestimos/multas.php">Multas</a>

      <?php if ($is_admin): ?>
        <a href="<?php echo $prefixo; ?>../Acervo/livros_gerencia.php">Gerenciar Acervo</a>
      <?php endif; ?>

      <a href="<?php echo $prefixo; ?>../index.php?sair=1">Sair</a>
    <?php endif; ?>
  </nav>
</header>