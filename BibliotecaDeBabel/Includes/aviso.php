<?php
// Aviso padronizado: confirmações, validações e erros exibidos no topo das páginas.
// $tipo:
//   "sucesso" => ação concluída        (amarelo)
//   "erro"    => falha ou bloqueio      (vermelho)
//   "atencao" => orientação informativa (laranja)

function aviso_html(string $mensagem, string $tipo = "atencao", string $extra_html = ""): string
{
    if ($mensagem === "") {
        return "";
    }

    $rotulos = ["sucesso" => "Sucesso", "erro" => "Erro", "atencao" => "Atenção"];
    if (!isset($rotulos[$tipo])) {
        $tipo = "atencao";
    }

    $papel = $tipo === "erro" ? "alert" : "status";

    return '<div class="aviso aviso--' . $tipo . '" role="' . $papel . '">'
         . '<div class="aviso__corpo">'
         . '<p><span class="sr-only">' . $rotulos[$tipo] . ': </span>'
         . htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8")
         . '</p>'
         . $extra_html
         . '</div>'
         . '</div>';
}
