<?php
/*
 * BABEL CORE — a Biblioteca de Babel completa.
 *
 * IDEIA
 *   Toda página possível (3.200 símbolos, alfabeto de 29) existe exatamente uma vez.
 *   Há 29^3200 páginas. O "índice" de uma página é um número inteiro gigante e
 *   a ligação índice <-> texto é uma PERMUTAÇÃO REVERSÍVEL (rede de Feistel sobre
 *   SHA-256). Por ser reversível:
 *     - índice  -> texto : embaralhar(índice) escrito em base 29;
 *     - texto   -> índice: ler o texto em base 29 e desembaralhar.
 *   A segunda direção é a pesquisa por trecho: nada é indexado, tudo é conta.
 *
 *   Um livro = 410 páginas de índices consecutivos. Livro n, página p:
 *       índice = (n - 1) * 410 + (p - 1)
 *   Endereço físico: 640 livros por hexágono = 4 paredes x 5 estantes x 32 livros.
 *
 *   O CATÁLOGO é só metadado. Todo livro tem título/autor/ano procedurais
 *   (derivados de SHA-256 do número). Um registro em tbl_livro (chave: babel_hash)
 *   SOBREPÕE esses metadados na exibição; o conteúdo nunca muda.
 *
 * Requer a extensão GMP do PHP (extension=gmp no php.ini).
 */

if (!extension_loaded("gmp")) {
    http_response_code(500);
    die("A extensão GMP do PHP é necessária. No XAMPP: remova o ';' de 'extension=gmp' no php.ini e reinicie o Apache.");
}

const BABEL_ALFABETO            = "abcdefghijklmnopqrstuvwxyz ,.";   // 26 letras + espaço + vírgula + ponto
const BABEL_DIGITOS_BASE29      = "0123456789abcdefghijklmnopqrs";   // dígitos de gmp_strval(x, 29)
const BABEL_SIMBOLOS            = 29;
const BABEL_PAGINAS             = 410;
const BABEL_LINHAS              = 40;
const BABEL_CARACTERES_LINHA    = 80;
const BABEL_CARACTERES_PAGINA   = 3200;   // 40 x 80
const BABEL_CARACTERES_METADE   = 1600;   // metade de uma página (Feistel)
const BABEL_PAREDES             = 4;
const BABEL_ESTANTES_POR_PAREDE = 5;
const BABEL_LIVROS_POR_ESTANTE  = 32;
const BABEL_LIVROS_POR_PAREDE   = 160;    // 5 x 32
const BABEL_LIVROS_POR_HEXAGONO = 640;    // 4 x 160
const BABEL_CHAVE               = "babel-v2";
const BABEL_RODADAS             = 4;
const BABEL_NUMERO_MAX_DIGITOS  = 4700;   // o maior número de livro tem 4.678 dígitos
const BABEL_ANO_MAX             = 2026;

const BABEL_ACENTOS = [
    "á" => "a", "à" => "a", "â" => "a", "ã" => "a", "ä" => "a",
    "é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
    "í" => "i", "ì" => "i", "î" => "i", "ï" => "i",
    "ó" => "o", "ò" => "o", "ô" => "o", "õ" => "o", "ö" => "o",
    "ú" => "u", "ù" => "u", "û" => "u", "ü" => "u",
    "ç" => "c", "ñ" => "n",
    "!" => ".", "?" => ".", ";" => ",", ":" => ",",
];

/* ------------------------------------------------------------------ */
/* Constantes gigantes                                                 */
/* ------------------------------------------------------------------ */

function babel_n(): GMP {            // 29^1600: tamanho de uma metade de página
    static $n = null;
    return $n ??= gmp_pow(BABEL_SIMBOLOS, BABEL_CARACTERES_METADE);
}

function babel_m(): GMP {            // 29^3200: número de páginas da biblioteca
    static $m = null;
    return $m ??= gmp_mul(babel_n(), babel_n());
}

function babel_total_livros(): GMP { // ceil(M / 410). O último livro tem só 1 página.
    static $t = null;
    return $t ??= gmp_div_q(gmp_add(babel_m(), BABEL_PAGINAS - 1), BABEL_PAGINAS);
}

/* ------------------------------------------------------------------ */
/* Permutação reversível (rede de Feistel de 4 rodadas)                */
/* ------------------------------------------------------------------ */

/* Função de rodada: SHA-256 em modo contador, 1024 bytes, reduzido mod 29^1600. */
function babel_f(int $rodada, GMP $r): GMP {
    $bytes = (gmp_sign($r) === 0) ? "" : gmp_export($r);
    $fluxo = "";
    for ($c = 0; strlen($fluxo) < 1024; $c++) {
        $fluxo .= hash("sha256", BABEL_CHAVE . chr($rodada) . pack("N", $c) . $bytes, true);
    }
    return gmp_mod(gmp_import(substr($fluxo, 0, 1024)), babel_n());
}

function babel_embaralhar(GMP $x): GMP {
    $n = babel_n();
    [$l, $r] = gmp_div_qr($x, $n);
    for ($i = 0; $i < BABEL_RODADAS; $i++) {
        $novo = gmp_mod(gmp_add($l, babel_f($i, $r)), $n);
        $l = $r;
        $r = $novo;
    }
    return gmp_add(gmp_mul($l, $n), $r);
}

function babel_desembaralhar(GMP $y): GMP {
    $n = babel_n();
    [$l, $r] = gmp_div_qr($y, $n);
    for ($i = BABEL_RODADAS - 1; $i >= 0; $i--) {
        $novo = gmp_mod(gmp_sub($r, babel_f($i, $l)), $n);
        $r = $l;
        $l = $novo;
    }
    return gmp_add(gmp_mul($l, $n), $r);
}

/* ------------------------------------------------------------------ */
/* Índice <-> texto                                                    */
/* ------------------------------------------------------------------ */

function babel_indice_para_texto(GMP $indice): string {
    $base29 = str_pad(gmp_strval(babel_embaralhar($indice), 29), BABEL_CARACTERES_PAGINA, "0", STR_PAD_LEFT);
    return strtr($base29, BABEL_DIGITOS_BASE29, BABEL_ALFABETO);
}

/* $texto: exatamente 3.200 símbolos do alfabeto, sem quebras de linha. */
function babel_texto_para_indice(string $texto): GMP {
    if (strlen($texto) !== BABEL_CARACTERES_PAGINA || strspn($texto, BABEL_ALFABETO) !== BABEL_CARACTERES_PAGINA) {
        throw new InvalidArgumentException("A página deve ter exatamente 3200 símbolos do alfabeto.");
    }
    return babel_desembaralhar(gmp_init(strtr($texto, BABEL_ALFABETO, BABEL_DIGITOS_BASE29), 29));
}

function babel_formatar_linhas(string $texto): string {
    return implode("\n", str_split($texto, BABEL_CARACTERES_LINHA));
}

/* ------------------------------------------------------------------ */
/* Livros, páginas e endereços                                         */
/* ------------------------------------------------------------------ */

function babel_str(GMP $n): string {
    return gmp_strval($n, 10);
}

/* Aceita só dígitos (ignora espaços, pontos e _). Devolve null se fora de 1..total. */
function babel_parse_numero(?string $texto): ?GMP {
    $texto = preg_replace('/[\s._]/', '', (string)$texto);
    if ($texto === "" || !ctype_digit($texto) || strlen($texto) > BABEL_NUMERO_MAX_DIGITOS) {
        return null;
    }
    $n = gmp_init($texto, 10);
    if (gmp_cmp($n, 1) < 0 || gmp_cmp($n, babel_total_livros()) > 0) {
        return null;
    }
    return $n;
}

function babel_indice_pagina(GMP $numero, int $pagina): GMP {
    return gmp_add(gmp_mul(gmp_sub($numero, 1), BABEL_PAGINAS), $pagina - 1);
}

/* 410 páginas, exceto no último livro da biblioteca (resto da divisão). */
function babel_paginas_do_livro(GMP $numero): int {
    $restantes = gmp_sub(babel_m(), gmp_mul(gmp_sub($numero, 1), BABEL_PAGINAS));
    return gmp_cmp($restantes, BABEL_PAGINAS) >= 0 ? BABEL_PAGINAS : gmp_intval($restantes);
}

/* Página formatada em 40 linhas de 80 caracteres. */
function babel_gerar_pagina(GMP $numero, int $pagina): string {
    return babel_formatar_linhas(babel_indice_para_texto(babel_indice_pagina($numero, $pagina)));
}

function babel_endereco(GMP $numero): array {
    [$hex, $pos] = gmp_div_qr(gmp_sub($numero, 1), BABEL_LIVROS_POR_HEXAGONO);
    $pos = gmp_intval($pos);
    return [
        "hexagono" => gmp_add($hex, 1),
        "parede"   => intdiv($pos, BABEL_LIVROS_POR_PAREDE) + 1,
        "estante"  => intdiv($pos % BABEL_LIVROS_POR_PAREDE, BABEL_LIVROS_POR_ESTANTE) + 1,
        "posicao"  => ($pos % BABEL_LIVROS_POR_ESTANTE) + 1,
    ];
}

function babel_numero_do_endereco(GMP $hexagono, int $parede, int $estante, int $posicao): GMP {
    $pos = ($parede - 1) * BABEL_LIVROS_POR_PAREDE + ($estante - 1) * BABEL_LIVROS_POR_ESTANTE + ($posicao - 1);
    return gmp_add(gmp_mul(gmp_sub($hexagono, 1), BABEL_LIVROS_POR_HEXAGONO), $pos + 1);
}

/* Livro uniformemente sorteado entre 1 e total (viés ~2^-450, desprezível). */
function babel_numero_aleatorio(): GMP {
    return gmp_add(gmp_mod(gmp_import(random_bytes(2000)), babel_total_livros()), 1);
}

/* "123456...7890" -> "123456…7890" para exibição curta. */
function babel_abreviar(string $numero, int $cabeca = 10, int $cauda = 6): string {
    if (strlen($numero) <= $cabeca + $cauda + 3) {
        return $numero;
    }
    return substr($numero, 0, $cabeca) . "…" . substr($numero, -$cauda);
}

/* ------------------------------------------------------------------ */
/* Pesquisa por trecho: é só fazer a conta                             */
/* ------------------------------------------------------------------ */

function babel_minusculas(string $s): string {
    return function_exists("mb_strtolower") ? mb_strtolower($s, "UTF-8") : strtolower($s);
}

function babel_normalizar_busca(string $s): string {
    $s = babel_minusculas($s);
    $s = strtr($s, BABEL_ACENTOS);
    $s = preg_replace('/[^a-z ,.]/', " ", $s);
    $s = preg_replace('/ +/', " ", $s);
    return trim($s);
}

/* Texto de enchimento reprodutível, sem viés (descarta bytes >= 232 = 29 x 8). */
function babel_preenchimento(string $semente, int $tamanho): string {
    $texto = "";
    for ($c = 0; strlen($texto) < $tamanho; $c++) {
        $bytes = hash("sha256", "babel-preenchimento|" . $semente . "|" . $c, true);
        for ($i = 0; $i < 32 && strlen($texto) < $tamanho; $i++) {
            $v = ord($bytes[$i]);
            if ($v < 232) {
                $texto .= BABEL_ALFABETO[$v % BABEL_SIMBOLOS];
            }
        }
    }
    return $texto;
}

/* Converte o índice de uma página em (livro, página). */
function babel_indice_para_livro_pagina(GMP $indice): array {
    [$q, $r] = gmp_div_qr($indice, BABEL_PAGINAS);
    return [gmp_add($q, 1), gmp_intval($r) + 1];
}

/*
 * Descobre UMA das páginas da biblioteca que contém o trecho.
 *   modo "inicio"    : trecho no começo, resto da página em branco (espaços)
 *   modo "aleatorio" : trecho numa posição e num meio de "ruído" derivados da semente;
 *                      sementes diferentes dão páginas (endereços) diferentes
 *   modo "pagina"    : $trecho é a página inteira (3200 símbolos ou 40 linhas)
 * Retorna ["ok"=>true, numero, pagina, texto, posicao, tamanho] ou ["ok"=>false, "erro"=>...].
 */
function babel_buscar_trecho(string $trecho, string $modo = "aleatorio", string $semente = ""): array {
    if ($modo === "pagina") {
        $linhas = preg_split('/\r\n|\n|\r/', $trecho);
        if (count($linhas) === BABEL_LINHAS + 1 && end($linhas) === "") {
            array_pop($linhas);   // quebra de linha final colada junto
        }
        if (count($linhas) === BABEL_LINHAS) {
            foreach ($linhas as &$linha) {
                if (strlen($linha) > BABEL_CARACTERES_LINHA) {
                    return ["ok" => false, "erro" => "Cada linha da página tem no máximo 80 caracteres."];
                }
                $linha = str_pad($linha, BABEL_CARACTERES_LINHA, " ");
            }
            unset($linha);
            $pagina = implode("", $linhas);
        } else {
            $pagina = str_replace(["\r", "\n"], "", $trecho);
        }
        $posicao = 0;
        $tamanho = BABEL_CARACTERES_PAGINA;
        if (strlen($pagina) !== BABEL_CARACTERES_PAGINA || strspn($pagina, BABEL_ALFABETO) !== BABEL_CARACTERES_PAGINA) {
            return ["ok" => false, "erro" => "Página inválida: use exatamente 40 linhas de 80 símbolos (a–z, espaço, vírgula e ponto)."];
        }
    } else {
        $normalizado = babel_normalizar_busca($trecho);
        $tamanho = strlen($normalizado);
        if ($tamanho < 1) {
            return ["ok" => false, "erro" => "Digite um trecho com letras, espaço, vírgula ou ponto."];
        }
        if ($tamanho > BABEL_CARACTERES_PAGINA) {
            return ["ok" => false, "erro" => "O trecho cabe no máximo em uma página (3200 símbolos)."];
        }
        if ($modo === "inicio") {
            $posicao = 0;
            $pagina = $normalizado . str_repeat(" ", BABEL_CARACTERES_PAGINA - $tamanho);
        } else {
            $folga = BABEL_CARACTERES_PAGINA - $tamanho;
            $posicao = $folga === 0 ? 0 : hexdec(substr(hash("sha256", "babel-posicao|" . $semente . "|" . $normalizado), 0, 7)) % ($folga + 1);
            $ruido = babel_preenchimento($semente . "|" . $normalizado, $folga);
            $pagina = substr($ruido, 0, $posicao) . $normalizado . substr($ruido, $posicao);
        }
    }

    $indice = babel_texto_para_indice($pagina);
    if (babel_indice_para_texto($indice) !== $pagina) {   // prova real
        return ["ok" => false, "erro" => "Falha de verificação da permutação."];
    }
    [$numero, $numeroPagina] = babel_indice_para_livro_pagina($indice);
    return ["ok" => true, "numero" => $numero, "pagina" => $numeroPagina, "texto" => $pagina, "posicao" => $posicao, "tamanho" => $tamanho];
}

/* ------------------------------------------------------------------ */
/* Metadados procedurais (valem até um registro sobrepô-los)           */
/* ------------------------------------------------------------------ */

function babel_metadados_livro(string $numero, int $totalAutores = 20): array {
    $hash = hash("sha256", "babel-metadados-v2|" . $numero);
    $autorId = (hexdec(substr($hash, 0, 7)) % max(1, $totalAutores)) + 1;

    $substantivos = [
        "Bananas", "Labirintos", "Memórias", "Sombras", "Macacos",
        "Espelhos", "Bibliotecas", "Silêncios", "Estrelas", "Galhos",
        "Abismos", "Relógios", "Jardins", "Sonhos", "Paradoxos",
        "Fragmentos", "Máquinas", "Ilhas", "Ciclos", "Ecos"
    ];
    $complementos = [
        "do Infinito", "de uma Banana", "sem Macacos", "da Sexta Parede",
        "e Outros Primatas", "do Tempo Perdido", "em um Labirinto",
        "da Floresta Imóvel", "para um Leitor Impossível", "sob o Mesmo Céu",
        "de um Mundo Circular", "na Última Estante", "do Acaso",
        "e a Natureza das Coisas", "de um Bibliotecário"
    ];
    $adjetivos = [
        "Invisíveis", "Improváveis", "Mecânicos", "Silenciosos", "Eternos",
        "Desobedientes", "Imaginários", "Perdidos", "Inacabados", "Azuis"
    ];

    $n1 = hexdec(substr($hash, 8, 7)) % count($substantivos);
    $n2 = hexdec(substr($hash, 16, 7)) % count($complementos);
    $n3 = hexdec(substr($hash, 24, 7)) % count($adjetivos);

    $formatos = [
        $substantivos[$n1] . " " . $complementos[$n2],
        "A Teoria dos " . $adjetivos[$n3] . " " . $substantivos[$n1],
        "Tratado sobre " . babel_minusculas($substantivos[$n1]) . " " . babel_minusculas($complementos[$n2]),
        "O Livro " . $adjetivos[$n3] . " " . $complementos[$n2]
    ];
    $formato = hexdec(substr($hash, 32, 7)) % count($formatos);
    $ano = 1450 + (hexdec(substr($hash, 40, 7)) % (BABEL_ANO_MAX - 1450 + 1));

    return ["autor_id" => $autorId, "titulo" => $formatos[$formato], "ano" => $ano];
}

/* ------------------------------------------------------------------ */
/* Banco: a camada de SOBREPOSIÇÃO (registro > procedural)             */
/* ------------------------------------------------------------------ */

function babel_hash_numero(string $numero): string {
    return hash("sha256", $numero);   // = SHA2(CAST(id AS CHAR), 256) no MySQL
}

function babel_autores_babel(mysqli $conn): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $res = $conn->query("SELECT id_autor_babel, nome_autor, descricao, especie, foto_path FROM babel_autor ORDER BY id_autor_babel");
    while ($res && ($l = $res->fetch_assoc())) {
        $cache[(int)$l["id_autor_babel"]] = $l;
    }
    return $cache;
}

function babel_autor_procedural(mysqli $conn, int $id): array {
    $todos = babel_autores_babel($conn);
    return $todos[$id] ?? [
        "id_autor_babel" => $id, "nome_autor" => "Autor-macaco #" . $id,
        "descricao" => null, "especie" => "Primata", "foto_path" => null,
    ];
}

/* Registros de vários números de uma vez: ["número" => linha de tbl_livro]. */
function babel_registros(mysqli $conn, array $numeros): array {
    $hashes = [];
    foreach ($numeros as $n) {
        $hashes[babel_hash_numero((string)$n)] = (string)$n;
    }
    if (!$hashes) {
        return [];
    }
    $marcas = implode(",", array_fill(0, count($hashes), "?"));
    $sql = "SELECT l.id_livro, l.babel_hash, l.titulo, l.ano_publicacao, l.id_autor, a.nome_autor,
                   CASE WHEN EXISTS (
                            SELECT 1 FROM tbl_emprestimo e
                            WHERE e.id_livro = l.id_livro AND e.status IN ('ativo', 'atrasado')
                        ) THEN 'Emprestado' ELSE 'Disponível' END AS disponibilidade
            FROM tbl_livro l
            INNER JOIN tbl_autor a ON a.id_autor = l.id_autor
            WHERE l.babel_hash IN ($marcas)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $chaves = array_map("strval", array_keys($hashes));
    $stmt->bind_param(str_repeat("s", count($chaves)), ...$chaves);
    $stmt->execute();
    $res = $stmt->get_result();
    $saida = [];
    while ($res && ($l = $res->fetch_assoc())) {
        $saida[$hashes[$l["babel_hash"]]] = $l;
    }
    $stmt->close();
    return $saida;
}

/* O que a interface mostra: registro, se houver; senão, procedural. */
function babel_montar_exibicao(mysqli $conn, string $numero, ?array $registro): array {
    $autoresBabel = babel_autores_babel($conn);
    $proc = babel_metadados_livro($numero, max(1, count($autoresBabel)));
    $autorBabel = babel_autor_procedural($conn, $proc["autor_id"]);

    if ($registro) {
        return [
            "numero" => $numero, "registrado" => true, "id_livro" => (int)$registro["id_livro"],
            "titulo" => $registro["titulo"],
            "ano" => $registro["ano_publicacao"] !== null ? (int)$registro["ano_publicacao"] : null,
            "autor_id" => (int)$registro["id_autor"], "autor_nome" => $registro["nome_autor"],
            "autor_babel" => null, "disponibilidade" => $registro["disponibilidade"] ?? "Disponível",
        ];
    }
    return [
        "numero" => $numero, "registrado" => false, "id_livro" => null,
        "titulo" => $proc["titulo"], "ano" => $proc["ano"],
        "autor_id" => (int)$autorBabel["id_autor_babel"], "autor_nome" => $autorBabel["nome_autor"],
        "autor_babel" => $autorBabel, "disponibilidade" => "Somente leitura",
    ];
}

function babel_exibicao(mysqli $conn, string $numero): array {
    $registros = babel_registros($conn, [$numero]);
    return babel_montar_exibicao($conn, $numero, $registros[$numero] ?? null);
}

/* Janela de $quantidade livros consecutivos a partir de $inicio. */
function babel_janela(mysqli $conn, GMP $inicio, int $quantidade): array {
    $total = babel_total_livros();
    $numeros = [];
    for ($i = 0; $i < $quantidade; $i++) {
        $n = gmp_add($inicio, $i);
        if (gmp_cmp($n, $total) > 0) {
            break;
        }
        $numeros[] = gmp_strval($n);
    }
    $registros = babel_registros($conn, $numeros);
    $itens = [];
    foreach ($numeros as $s) {
        $itens[] = babel_montar_exibicao($conn, $s, $registros[$s] ?? null);
    }
    return $itens;
}

/* Menor número livre >= $minimo (para o registro "n-ésimo livro ocupa a posição n"). */
function babel_proximo_numero_livre(mysqli $conn, int $minimo): string {
    $stmt = $conn->prepare("SELECT 1 FROM tbl_livro WHERE babel_hash = ? LIMIT 1");
    $n = $minimo;
    while ($stmt) {
        $h = babel_hash_numero((string)$n);
        $stmt->bind_param("s", $h);
        $stmt->execute();
        $stmt->store_result();
        $ocupado = $stmt->num_rows > 0;
        if (!$ocupado) {
            break;
        }
        $n++;
    }
    if ($stmt) {
        $stmt->close();
    }
    return (string)$n;
}

/* Registra (sobrepõe metadados de) um livro do acervo infinito. */
function babel_registrar_livro(mysqli $conn, string $numero, string $titulo, ?int $ano, int $idAutor): array {
    $hash = babel_hash_numero($numero);
    $stmt = $conn->prepare(
        "INSERT INTO tbl_livro (titulo, ano_publicacao, id_autor, babel_numero, babel_hash) VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return ["ok" => false, "msg" => "Erro ao preparar o registro."];
    }
    $stmt->bind_param("siiss", $titulo, $ano, $idAutor, $numero, $hash);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        return ["ok" => true, "msg" => "Livro registrado.", "id_livro" => $id];
    }
    $duplicado = ($stmt->errno === 1062);
    $stmt->close();
    return ["ok" => false, "msg" => $duplicado ? "Este livro já está registrado." : "Não foi possível registrar. Confira se o autor existe."];
}