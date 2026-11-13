<?php

/**
 * Regras do cadastro de produtos (#2841), fora do controller para serem
 * testadas.
 */
if (! function_exists('unidadesDeMedida')) {
    /**
     * Unidades de medida, sigla => "SIGLA — descrição", da tabela que a v4
     * carregava por AJAX (assets/json/tabela_medidas.json).
     *
     * @return array<string, string>
     */
    function unidadesDeMedida(): array
    {
        static $unidades = null;

        if ($unidades === null) {
            $raiz = defined('FCPATH') ? FCPATH : dirname(APPPATH) . DIRECTORY_SEPARATOR;
            $dados = json_decode((string) @file_get_contents($raiz . 'assets/json/tabela_medidas.json'), true);
            $unidades = [];
            foreach ((array) ($dados['medidas'] ?? []) as $medida) {
                $sigla = trim((string) ($medida['sigla'] ?? ''));
                if ($sigla !== '') {
                    $descricao = trim((string) ($medida['descricao'] ?? ''));
                    $unidades[$sigla] = $descricao !== '' && strcasecmp($descricao, $sigla) !== 0 ? $sigla . ' — ' . mb_convert_case($descricao, MB_CASE_TITLE, 'UTF-8') : $sigla;
                }
            }
        }

        return $unidades;
    }
}

if (! function_exists('produtoDadosDoFormulario')) {
    /**
     * Dados da tabela produtos a partir do POST já validado. Os preços já vêm
     * convertidos por valorDecimal(); estoques são inteiros não negativos.
     *
     * @param  mixed  $post  $this->input->post()
     */
    function produtoDadosDoFormulario($post, string $precoCompra, string $precoVenda): array
    {
        $post = is_array($post) ? $post : [];
        $texto = static fn (string $campo): string => is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';
        $inteiro = static fn (string $campo): int => max(0, (int) $texto($campo));

        return [
            'codDeBarra' => $texto('codDeBarra'),
            'descricao' => $texto('descricao'),
            'unidade' => $texto('unidade'),
            'precoCompra' => $precoCompra === '' ? '0.00' : $precoCompra,
            'precoVenda' => $precoVenda,
            'estoque' => $texto('estoque') === '' ? 0 : $inteiro('estoque'),
            'estoqueMinimo' => $texto('estoqueMinimo') === '' ? null : $inteiro('estoqueMinimo'),
            'entrada' => empty($post['entrada']) ? 0 : 1,
            'saida' => empty($post['saida']) ? 0 : 1,
        ];
    }
}

if (! function_exists('produtoEstoqueBaixo')) {
    /** No mínimo ou abaixo dele, com mínimo definido. */
    function produtoEstoqueBaixo(object $produto): bool
    {
        $minimo = (int) ($produto->estoqueMinimo ?? 0);

        return $minimo > 0 && (int) $produto->estoque <= $minimo;
    }
}

if (! function_exists('produtoDetectarDelimitadorCsv')) {
    /**
     * Detecta o delimitador mais provável de um arquivo CSV (; , ou \t).
     */
    function produtoDetectarDelimitadorCsv(string $primeiraLinha): string
    {
        $contadores = [
            ';' => substr_count($primeiraLinha, ';'),
            ',' => substr_count($primeiraLinha, ','),
            "\t" => substr_count($primeiraLinha, "\t"),
        ];

        arsort($contadores);
        $melhor = key($contadores);

        return $contadores[$melhor] > 0 ? $melhor : ';';
    }
}

if (! function_exists('produtoLerLinhasCsv')) {
    /**
     * Lê e normaliza o conteúdo de um arquivo CSV em UTF-8, retornando cabeçalhos e linhas.
     *
     * @return array{0: list<string>, 1: list<array<string, string>>} [cabecalhos, linhas]
     */
    function produtoLerLinhasCsv(string $conteudo): array
    {
        // 1. Converte encoding se necessário
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = (string) mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
        }

        // 2. Remove UTF-8 BOM se presente
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }

        $linhasBrutas = preg_split("/\r\n|\n|\r/", trim($conteudo));
        if ($linhasBrutas === false || $linhasBrutas === [] || trim($linhasBrutas[0]) === '') {
            return [[], []];
        }

        $delimitador = produtoDetectarDelimitadorCsv($linhasBrutas[0]);

        $cabecalhos = [];
        $linhas = [];

        foreach ($linhasBrutas as $idx => $linhaStr) {
            $linhaStr = trim($linhaStr);
            if ($linhaStr === '') {
                continue;
            }

            $colunas = str_getcsv($linhaStr, $delimitador);
            if ($idx === 0) {
                // Cabeçalhos
                $cabecalhos = array_values(array_map('trim', $colunas));
                continue;
            }

            if ($cabecalhos === []) {
                continue;
            }

            // Mapeia colunas pelo nome do cabeçalho
            $registro = [];
            foreach ($cabecalhos as $colIdx => $colNome) {
                if ($colNome === '') {
                    continue;
                }
                $registro[$colNome] = isset($colunas[$colIdx]) ? trim((string) $colunas[$colIdx]) : '';
            }

            if ($registro !== []) {
                $linhas[] = $registro;
            }
        }

        return [$cabecalhos, $linhas];
    }
}

if (! function_exists('produtoSugerirMapeamento')) {
    /**
     * Tenta identificar automaticamente qual coluna do CSV corresponde ao campo do sistema.
     *
     * @param  string        $campo       descricao, precoVenda, precoCompra, estoque, estoqueMinimo, codDeBarra, unidade
     * @param  list<string>  $colunasCsv  Cabeçalhos detectados no CSV
     * @return string|null   Nome da coluna sugerida ou null
     */
    function produtoSugerirMapeamento(string $campo, array $colunasCsv): ?string
    {
        $termosPorCampo = [
            'descricao' => ['descricao', 'descrição', 'nome', 'produto', 'item', 'description'],
            'precoVenda' => ['preco_venda', 'preço_venda', 'precovenda', 'valor_venda', 'preco', 'preço', 'valor', 'venda', 'price'],
            'precoCompra' => ['preco_compra', 'preço_compra', 'precocompra', 'valor_compra', 'custo', 'compra', 'cost'],
            'estoque' => ['estoque', 'quantidade', 'qtd', 'saldo', 'stock', 'qty'],
            'estoqueMinimo' => ['estoque_minimo', 'estoqueminimo', 'minimo', 'mínimo', 'qtd_minima', 'min'],
            'codDeBarra' => ['codigo_barras', 'código_barras', 'codbarras', 'coddebarra', 'codigo_de_barras', 'código_de_barras', 'cod_barra', 'barra', 'barras', 'ean', 'gtin', 'barcode', 'sku', 'codigo', 'código'],
            'unidade' => ['unidade', 'unid', 'un', 'medida', 'unit'],
        ];

        $candidatos = $termosPorCampo[$campo] ?? [];
        if ($candidatos === [] || $colunasCsv === []) {
            return null;
        }

        $normalizar = static fn (string $str) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str));

        // 1. Busca por igualdade exata
        foreach ($colunasCsv as $coluna) {
            $colunaNorm = $normalizar($coluna);
            foreach ($candidatos as $termo) {
                if ($colunaNorm === $normalizar($termo)) {
                    return $coluna;
                }
            }
        }

        // 2. Busca por substring
        foreach ($colunasCsv as $coluna) {
            $colunaNorm = $normalizar($coluna);
            foreach ($candidatos as $termo) {
                $termoNorm = $normalizar($termo);
                if (str_contains($colunaNorm, $termoNorm)) {
                    return $coluna;
                }
            }
        }

        return null;
    }
}
