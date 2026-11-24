<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET["sair"])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Biblioteca de Babel</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../Styles/default.css" />

  <style>
    /* =========================================
       TELA DE INTRO INICIAL (SPLASH SCREEN)
       ========================================= */
    #intro-overlay {
      position: fixed;
      inset: 0;
      background-color: #0d0d12;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.8s ease-out, visibility 0.8s ease-out;
    }

    #intro-overlay.fade-out {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .intro-logo {
      width: 260px;
      height: 260px;
      overflow: visible;
    }

    .intro-logo path {
      stroke: #ffffff !important;
    }

    .hex-1, .hex-2, .hex-3 {
      transform-box: fill-box;
      transform-origin: center;
    }

    .hex-1 {
      animation: girarAntiHorario 2.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }
    .hex-2 {
      animation: girarHorario 2.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }
    .hex-3 {
      animation: girarAntiHorario 2.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    .intro-title {
      margin-top: 28px;
      font-family: 'Montserrat', sans-serif;
      font-weight: 800;
      font-size: 1.4rem;
      letter-spacing: 0.25em;
      color: #ffffff;
      opacity: 0;
      animation: aparecerTexto 1.2s ease-out 0.6s forwards;
    }

    @keyframes girarAntiHorario {
      0% { opacity: 0; transform: scale(0.5) rotate(0deg); }
      25% { opacity: 1; }
      100% { opacity: 1; transform: scale(1) rotate(-360deg); }
    }

    @keyframes girarHorario {
      0% { opacity: 0; transform: scale(0.5) rotate(0deg); }
      25% { opacity: 1; }
      100% { opacity: 1; transform: scale(1) rotate(360deg); }
    }

    @keyframes aparecerTexto {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* =========================================
       HOME COM LOGO GIGANTE AO FUNDO & DEFAULT CSS
       ========================================= */
    body {
      background-color: #0d0d12;
      overflow-x: hidden;
    }

    #main-app {
      opacity: 0;
      transition: opacity 0.8s ease-in;
      position: relative;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    #main-app.visible {
      opacity: 1;
    }

    /* Logo de fundo gigante e sutil */
    .bg-logo-container {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 0;
      pointer-events: none;
      opacity: 0.05;
    }

    .bg-gigantic-logo {
      width: 65vw;
      height: 65vw;
      max-width: 700px;
      max-height: 700px;
      overflow: visible;
    }

    .bg-gigantic-logo path {
      stroke: #ffd5b8 !important;
    }

    .bg-hex-1 {
      transform-box: fill-box;
      transform-origin: center;
      animation: bgGirarAnti 40s linear infinite;
    }

    .bg-hex-2 {
      transform-box: fill-box;
      transform-origin: center;
      animation: bgGirarHorario 50s linear infinite;
    }

    .bg-hex-3 {
      transform-box: fill-box;
      transform-origin: center;
      animation: bgGirarAnti 45s linear infinite;
    }

    @keyframes bgGirarAnti {
      from { transform: rotate(0deg); }
      to { transform: rotate(-360deg); }
    }

    @keyframes bgGirarHorario {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    /* Mantém a estrutura exata do .content e .test-block do default.css */
    .hero-wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 650px;
      padding: 1.5rem;
    }
  </style>
</head>
<body>

  <!-- TELA DE INTRO COM A LOGO ANIMADA -->
  <div id="intro-overlay">
    <svg class="intro-logo" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1500 1499.999933">
      <g class="hex-1">
        <path stroke-linecap="butt" transform="matrix(0.75, 0, 0, 0.75, 0.00003335, 105.468722)" fill="none" stroke-linejoin="miter" d="M 1997.656378 858.364694 L 1498.239668 1716.73456 L 499.416665 1716.73456 L -0.0000444667 858.364694 L 499.416665 0.0000371792 L 1498.239668 0.0000371792 Z M 1997.656378 858.364694 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
      </g>
      <g class="hex-2">
        <path stroke-linecap="butt" transform="matrix(0.0203951, 0.749723, -0.749723, 0.0203951, 1254.571221, 129.492053)" fill="none" stroke-linejoin="miter" d="M 1617.485087 695.014097 L 1213.112726 1390.030102 L 404.370961 1390.027214 L 0.00141377 695.013529 L 404.373633 0.00273075 L 1213.11554 0.000411897 Z M 1617.485087 695.014097 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
      </g>
      <g class="hex-3">
        <path stroke-linecap="butt" transform="matrix(-0.75, 0, 0, -0.75, 1246.910206, 1177.783247)" fill="none" stroke-linejoin="miter" d="M 1327.437681 570.38292 L 995.578277 1140.763177 L 331.85947 1140.763177 L 0.000066266 570.38292 L 331.85947 -0.00254626 L 995.578277 -0.00254626 Z M 1327.437681 570.38292 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
      </g>
    </svg>
    <div class="intro-title">BIBLIOTECA DE BABEL</div>
  </div>

  <!-- TELA INICIAL -->
  <div id="main-app">
    
    <!-- Logo de Fundo Gigante -->
    <div class="bg-logo-container">
      <svg class="bg-gigantic-logo" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1500 1499.999933">
        <g class="bg-hex-1">
          <path stroke-linecap="butt" transform="matrix(0.75, 0, 0, 0.75, 0.00003335, 105.468722)" fill="none" stroke-linejoin="miter" d="M 1997.656378 858.364694 L 1498.239668 1716.73456 L 499.416665 1716.73456 L -0.0000444667 858.364694 L 499.416665 0.0000371792 L 1498.239668 0.0000371792 Z M 1997.656378 858.364694 " stroke="#ffd5b8" stroke-width="120" stroke-miterlimit="4"/>
        </g>
        <g class="bg-hex-2">
          <path stroke-linecap="butt" transform="matrix(0.0203951, 0.749723, -0.749723, 0.0203951, 1254.571221, 129.492053)" fill="none" stroke-linejoin="miter" d="M 1617.485087 695.014097 L 1213.112726 1390.030102 L 404.370961 1390.027214 L 0.00141377 695.013529 L 404.373633 0.00273075 L 1213.11554 0.000411897 Z M 1617.485087 695.014097 " stroke="#ffd5b8" stroke-width="120" stroke-miterlimit="4"/>
        </g>
        <g class="bg-hex-3">
          <path stroke-linecap="butt" transform="matrix(-0.75, 0, 0, -0.75, 1246.910206, 1177.783247)" fill="none" stroke-linejoin="miter" d="M 1327.437681 570.38292 L 995.578277 1140.763177 L 331.85947 1140.763177 L 0.000066266 570.38292 L 331.85947 -0.00254626 L 995.578277 -0.00254626 Z M 1327.437681 570.38292 " stroke="#ffd5b8" stroke-width="120" stroke-miterlimit="4"/>
        </g>
      </svg>
    </div>

    <!-- Conteúdo usando estritamente as classes nativas do default.css -->
    <main class="hero-wrapper content" style="max-width: 550px;">
      <section class="test-block" style="text-align: center;">
        <h2>Bem-vindo à <br> Biblioteca de Babel</h2>
        <p style="margin-top: 1rem; margin-bottom: 1.5rem;">Sistema de gestão de acervo, empréstimos e controle de usuários.</p>
        
        <div class="inline-group" style="justify-content: center; gap: 15px;">
          <a href="./Users/users_login.php" style="text-decoration: none;"><button type="button">Acessar Sistema</button></a>
          <a href="./Users/users_cadastro.php" style="text-decoration: none;"><button type="button" class="secondary">Criar Conta</button></a>
        </div>
      </section>
    </main>

  </div>

  <script>
    window.addEventListener("DOMContentLoaded", () => {
      const introOverlay = document.getElementById("intro-overlay");
      const mainApp = document.getElementById("main-app");

      setTimeout(() => {
        introOverlay.classList.add("fade-out");
        mainApp.classList.add("visible");
      }, 2800);
    });
  </script>

</body>
</html>