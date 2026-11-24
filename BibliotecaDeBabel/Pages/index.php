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
       ESTILOS DA TELA DE INTRO (SPLASH SCREEN)
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

    /* Força todos os traços do SVG a ficarem brancos e opacos */
    .intro-logo path {
      stroke: #ffffff !important;
    }

    /* Ponto de rotação centralizado para cada grupo manter a geometria exata */
    .hex-1, .hex-2, .hex-3 {
      transform-box: fill-box;
      transform-origin: center;
    }

    /* 1º Hexágono: Anti-horário */
    .hex-1 {
      animation: girarAntiHorario 2.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    /* 2º Hexágono: Horário */
    .hex-2 {
      animation: girarHorario 2.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    /* 3º Hexágono: Anti-horário */
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
      0% {
        opacity: 0;
        transform: scale(0.5) rotate(0deg);
      }
      25% {
        opacity: 1;
      }
      100% {
        opacity: 1;
        transform: scale(1) rotate(-360deg);
      }
    }

    @keyframes girarHorario {
      0% {
        opacity: 0;
        transform: scale(0.5) rotate(0deg);
      }
      25% {
        opacity: 1;
      }
      100% {
        opacity: 1;
        transform: scale(1) rotate(360deg);
      }
    }

    @keyframes aparecerTexto {
      from {
        opacity: 0;
        transform: translateY(12px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #main-app {
      opacity: 0;
      transition: opacity 0.8s ease-in;
    }

    #main-app.visible {
      opacity: 1;
    }
  </style>
</head>
<body>

  <!-- TELA DE INTRO COM A LOGO ANIMADA BRANCA -->
  <div id="intro-overlay">
    <svg class="intro-logo" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1500 1499.999933">
      
      <!-- 1º Hexágono (Gira no sentido ANTI-HORÁRIO) -->
      <g class="hex-1">
        <g clip-path="url(#8ea35ec8a2)">
          <g clip-path="url(#be84d06c17)">
            <path stroke-linecap="butt" transform="matrix(0.75, 0, 0, 0.75, 0.00003335, 105.468722)" fill="none" stroke-linejoin="miter" d="M 1997.656378 858.364694 L 1498.239668 1716.73456 L 499.416665 1716.73456 L -0.0000444667 858.364694 L 499.416665 0.0000371792 L 1498.239668 0.0000371792 Z M 1997.656378 858.364694 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
          </g>
        </g>
      </g>

      <!-- 2º Hexágono (Gira no sentido HORÁRIO) -->
      <g class="hex-2">
        <g clip-path="url(#78f354c63a)">
          <g clip-path="url(#e53e1c6bf7)">
            <g clip-path="url(#e29d2405df)">
              <g clip-path="url(#955a01a2f6)">
                <path stroke-linecap="butt" transform="matrix(0.0203951, 0.749723, -0.749723, 0.0203951, 1254.571221, 129.492053)" fill="none" stroke-linejoin="miter" d="M 1617.485087 695.014097 L 1213.112726 1390.030102 L 404.370961 1390.027214 L 0.00141377 695.013529 L 404.373633 0.00273075 L 1213.11554 0.000411897 Z M 1617.485087 695.014097 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
              </g>
            </g>
          </g>
        </g>
      </g>

      <!-- 3º Hexágono (Gira no sentido ANTI-HORÁRIO) -->
      <g class="hex-3">
        <g clip-path="url(#1ac2a0dba2)">
          <g clip-path="url(#62e5d0e57d)">
            <path stroke-linecap="butt" transform="matrix(-0.75, 0, 0, -0.75, 1246.910206, 1177.783247)" fill="none" stroke-linejoin="miter" d="M 1327.437681 570.38292 L 995.578277 1140.763177 L 331.85947 1140.763177 L 0.000066266 570.38292 L 331.85947 -0.00254626 L 995.578277 -0.00254626 Z M 1327.437681 570.38292 " stroke="#ffffff" stroke-width="120" stroke-miterlimit="4"/>
          </g>
        </g>
      </g>
    </svg>

    <div class="intro-title">BIBLIOTECA DE BABEL</div>
  </div>

  <!-- TELA INICIAL DO SITE -->
  <div id="main-app">
    <header>
      <div class="brand">
        <img src="../Images/Logo/TorreDeBabel.svg" class="logo" alt="Logo Biblioteca de Babel">
        <p class="brand-title">BIBLIOTECA DE BABEL</p>
      </div>
      <nav aria-label="Navegação">
        <a href="./Users/users_login.php">Entrar</a>
        <a href="./Users/users_cadastro.php">Cadastre-se</a>
      </nav>
    </header>

    <main class="content">
      <section class="test-block">
        <h1>Bem-vindo à Biblioteca de Babel</h1>
        <p>Sistema de gestão de acervo, empréstimos e controle de usuários.</p>
        <div class="inline-group" style="margin-top: 1.5rem;">
          <a href="./Users/users_login.php"><button type="button">Acessar Sistema</button></a>
          <a href="./Users/users_cadastro.php"><button type="button" class="secondary">Criar Conta</button></a>
        </div>
      </section>
    </main>
  </div>

  <script>
    window.addEventListener("DOMContentLoaded", () => {
      const introOverlay = document.getElementById("intro-overlay");
      const mainApp = document.getElementById("main-app");

      // Revela o site após 2.8 segundos de animação
      setTimeout(() => {
        introOverlay.classList.add("fade-out");
        mainApp.classList.add("visible");
      }, 2800);
    });
  </script>

</body>
</html>