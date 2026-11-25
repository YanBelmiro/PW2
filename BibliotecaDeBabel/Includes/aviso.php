<?php
// Aviso padronizado: confirmações, validações e erros exibidos no topo das páginas.
// O visual vem de Styles/default.css (.aviso); não use estilos inline nem cores próprias.
//
// $tipo:
//   "sucesso" => ação concluída        (amarelo)
//   "erro"    => falha ou bloqueio      (vermelho)
//   "atencao" => orientação informativa (laranja)
//
// $extra_html: conteúdo opcional já escapado (ex.: avatar do autor recém-cadastrado).

function aviso_html(string $mensagem, string $tipo = "atencao", string $extra_html = ""): string
{
    if ($mensagem === "") {
        return "";
    }

    $rotulos = ["sucesso" => "Sucesso", "erro" => "Erro", "atencao" => "Atenção"];
    if (!isset($rotulos[$tipo])) {
        $tipo = "atencao";
    }

    // alert interrompe a leitura (erros); status espera a vez (confirmações).
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
