<!-- Includes/header.php -->
<header>
  <div class="brand">
    <svg class="logo logo-animada" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1500 1499.999933">
      <!-- 1º Hexágono -->
      <g class="hex-1">
        <path stroke-linecap="butt" transform="matrix(0.75, 0, 0, 0.75, 0.00003335, 105.468722)" fill="none" stroke-linejoin="miter" d="M 1997.656378 858.364694 L 1498.239668 1716.73456 L 499.416665 1716.73456 L -0.0000444667 858.364694 L 499.416665 0.0000371792 L 1498.239668 0.0000371792 Z M 1997.656378 858.364694 " stroke="currentColor" stroke-width="120" stroke-miterlimit="4"/>
      </g>

      <!-- 2º Hexágono -->
      <g class="hex-2">
        <path stroke-linecap="butt" transform="matrix(0.0203951, 0.749723, -0.749723, 0.0203951, 1254.571221, 129.492053)" fill="none" stroke-linejoin="miter" d="M 1617.485087 695.014097 L 1213.112726 1390.030102 L 404.370961 1390.027214 L 0.00141377 695.013529 L 404.373633 0.00273075 L 1213.11554 0.000411897 Z M 1617.485087 695.014097 " stroke="currentColor" stroke-width="120" stroke-miterlimit="4"/>
      </g>

      <!-- 3º Hexágono -->
      <g class="hex-3">
        <path stroke-linecap="butt" transform="matrix(-0.75, 0, 0, -0.75, 1246.910206, 1177.783247)" fill="none" stroke-linejoin="miter" d="M 1327.437681 570.38292 L 995.578277 1140.763177 L 331.85947 1140.763177 L 0.000066266 570.38292 L 331.85947 -0.00254626 L 995.578277 -0.00254626 Z M 1327.437681 570.38292 " stroke="currentColor" stroke-width="120" stroke-miterlimit="4"/>
      </g>
    </svg>
    <p class="brand-title">BIBLIOTECA DE BABEL</p>
  </div>

  <nav aria-label="Navegação">
    <a href="../Acervo/livros_catalogo.php">Livros</a>
    <a href="../Acervo/autores_catalogo.php">Autores</a>
    <a href="../Emprestimos/emprestimos.php">Empréstimos</a>
    <a href="../Emprestimos/multas.php">Multas</a>
    <?php if (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "admin"): ?>
      <a href="../Acervo/livros_gerencia.php">Gerenciar Acervo</a>
    <?php endif; ?>
    <a href="../index.php?sair=1">Sair</a>
  </nav>
</header>