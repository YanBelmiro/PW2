<?php
// Foto de perfil determinística do autor: mesmo nome + id => mesma imagem e mesmo filtro.
// Nada é salvo no banco; sem paciência pra isso, logo, o resultado é recalculado a cada exibição.

const AUTOR_AVATAR_TOTAL = 8; 

const AUTOR_AVATAR_FILTROS = [
    "none",
    "grayscale(100%)",
    "sepia(80%)",
    "hue-rotate(90deg)",
    "hue-rotate(180deg)",
    "hue-rotate(270deg)",
    "saturate(2)",
    "saturate(0.4)",
    "contrast(1.4) brightness(0.9)",
    "sepia(60%) hue-rotate(-30deg)",
    "brightness(1.15) saturate(1.3)",
    "grayscale(60%) contrast(1.2)",
];

function autor_avatar_dados(int $id_autor, string $nome_autor): array
{
    $hash = md5($nome_autor . "|" . $id_autor);

    // Trechos diferentes do hash para imagem e filtro (7 hex cabem em int de 32 bits).
    $indice_imagem = hexdec(substr($hash, 0, 7)) % AUTOR_AVATAR_TOTAL;
    $indice_filtro = hexdec(substr($hash, 7, 7)) % count(AUTOR_AVATAR_FILTROS);

    return [
        "arquivo" => ($indice_imagem + 1) . ".png",
        "filtro"  => AUTOR_AVATAR_FILTROS[$indice_filtro],
    ];
}

function autor_avatar_html(
    int $id_autor,
    string $nome_autor,
    int $largura = 48,
    string $base = "../../Images/Autores/"
): string {
    $d = autor_avatar_dados($id_autor, $nome_autor);

    return '<img src="' . htmlspecialchars($base . $d["arquivo"], ENT_QUOTES, "UTF-8") . '"'
         . ' alt="Foto de ' . htmlspecialchars($nome_autor, ENT_QUOTES, "UTF-8") . '"'
         . ' width="' . $largura . '"'
         . ' style="filter: ' . htmlspecialchars($d["filtro"], ENT_QUOTES, "UTF-8") . ';">';
}