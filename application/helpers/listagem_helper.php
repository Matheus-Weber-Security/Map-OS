<?php

/**
 * Padrões das listagens da v5 (#2852): filtros na URL e paginação que os
 * preserva.
 *
 * No controller:
 *
 *     $filtros = listagemFiltros(['pesquisa' => 'texto', 'tipo' => ['cliente', 'fornecedor']], $this->input->get());
 *     $total = $this->clientes_model->contar($filtros);
 *     $this->data['paginacao'] = $this->paginacao(site_url('clientes/gerenciar'), $total, $this->uri->segment(3), null, $filtros);
 *
 * Os filtros ficam na query string (?pesquisa=ana&tipo=cliente): a URL é
 * compartilhável, o voltar do navegador funciona e a paginação leva os
 * filtros junto. O formulário de filtro é um GET simples, sem JavaScript.
 */
if (! defined('LISTAGEM_OPCOES_POR_PAGINA')) {
    define('LISTAGEM_OPCOES_POR_PAGINA', [10, 25, 50, 100, 500]);
}

if (! function_exists('listagemFiltros')) {
    /**
     * Filtros válidos de uma listagem, a partir da query string.
     *
     * Cada filtro permitido é 'texto' (trim, sem caracteres de controle, até
     * 100 caracteres) ou a lista dos valores aceitos. Filtro desconhecido,
     * vazio, que não é string ou fora da lista é descartado.
     *
     * @param  array<string, 'texto'|list<string>>  $permitidos
     * @param  mixed                                 $entrada  Normalmente $this->input->get()
     * @return array<string, string>
     */
    function listagemFiltros(array $permitidos, $entrada): array
    {
        $entrada = is_array($entrada) ? $entrada : [];
        $filtros = [];

        if (! isset($permitidos['por_pagina'])) {
            $permitidos['por_pagina'] = array_map('strval', LISTAGEM_OPCOES_POR_PAGINA);
        }

        foreach ($permitidos as $nome => $regra) {
            $valor = $entrada[$nome] ?? null;
            if (! is_string($valor)) {
                continue;
            }

            $valor = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $valor));

            if ($regra === 'texto') {
                $valor = mb_substr($valor, 0, 100);
            } elseif (! in_array($valor, $regra, true)) {
                continue;
            }

            if ($valor !== '') {
                $filtros[$nome] = $valor;
            }
        }

        return $filtros;
    }
}

if (! function_exists('listagemQuery')) {
    /**
     * Query string dos filtros ("?pesquisa=ana&tipo=cliente"), ou "" sem
     * filtros. Os valores saem codificados (RFC 3986).
     *
     * @param  array<string, string>  $filtros
     */
    function listagemQuery(array $filtros): string
    {
        $filtros = array_filter($filtros, static fn ($valor) => (string) $valor !== '');

        return $filtros === [] ? '' : '?' . http_build_query($filtros, '', '&', PHP_QUERY_RFC3986);
    }
}

if (! function_exists('listagemPorPagina')) {
    /**
     * Retorna a quantidade de itens por página a partir dos filtros ou do padrão.
     *
     * @param  array<string, mixed>  $filtros
     * @param  int                   $padrao
     */
    function listagemPorPagina(array $filtros, int $padrao = 10): int
    {
        if (isset($filtros['por_pagina']) && in_array((int) $filtros['por_pagina'], LISTAGEM_OPCOES_POR_PAGINA, true)) {
            return (int) $filtros['por_pagina'];
        }

        return $padrao > 0 ? $padrao : 10;
    }
}

if (! function_exists('listagemPaginacao')) {
    /**
     * Renderiza o rodapé de paginação com os botões de navegação à esquerda
     * e o seletor de "itens por página" à direita (alinhado e responsivo).
     *
     * @param  array                 $paginacao  Array retornado por $this->paginacao(...)
     * @param  array<string, string> $filtros    Array de filtros da tela
     * @param  string                $urlAcao    URL base da tela para submissão do formulário
     */
    function listagemPaginacao(array $paginacao, array $filtros = [], string $urlAcao = ''): HtmlSeguro
    {
        $porPaginaAtual = (int) ($paginacao['per_page'] ?? 10);
        $opcoes = LISTAGEM_OPCOES_POR_PAGINA;
        if (! in_array($porPaginaAtual, $opcoes, true)) {
            $opcoes[] = $porPaginaAtual;
            sort($opcoes);
        }

        $urlAcao = $urlAcao !== '' ? $urlAcao : current_url();

        $htmlPaginacao = (string) component('pagination', $paginacao);

        $inputsOcultos = '';
        foreach ($filtros as $chave => $valor) {
            if ($chave !== 'por_pagina' && is_scalar($valor) && (string) $valor !== '') {
                $inputsOcultos .= '<input type="hidden" name="' . e((string) $chave) . '" value="' . e((string) $valor) . '">';
            }
        }

        $optionsHtml = '';
        foreach ($opcoes as $qtd) {
            $selecionado = ($qtd === $porPaginaAtual) ? ' selected' : '';
            $optionsHtml .= '<option value="' . $qtd . '"' . $selecionado . '>' . $qtd . '</option>';
        }

        $idSeletor = 'seletor-por-pagina-' . substr(md5($urlAcao), 0, 8);

        $seletorHtml = '<form method="get" action="' . e($urlAcao) . '" class="flex items-center gap-2 text-caption text-muted">'
            . $inputsOcultos
            . '<label for="' . e($idSeletor) . '" class="whitespace-nowrap">Exibir</label>'
            . '<select id="' . e($idSeletor) . '" name="por_pagina" '
            . 'class="h-9 rounded-md border border-input bg-surface px-2.5 py-1 text-caption text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" '
            . 'data-auto-submit onchange="this.form.submit()">'
            . $optionsHtml
            . '</select>'
            . '<span class="whitespace-nowrap">por página</span>'
            . '</form>';

        $html = '<div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">'
            . '<div class="min-w-0">' . $htmlPaginacao . '</div>'
            . '<div class="flex items-center justify-end self-end sm:self-auto sm:ml-auto">' . $seletorHtml . '</div>'
            . '</div>';

        return new HtmlSeguro($html);
    }
}
