<?php

/**
 * Regras do formulário de cliente (#2851), fora do controller para serem
 * testadas: o que vai para o banco e o que a tela mostra.
 */
if (! defined('CLIENTE_CAMPOS_TEXTO')) {
    define('CLIENTE_CAMPOS_TEXTO', [
        'nomeCliente', 'contato', 'documento', 'telefone', 'celular', 'email',
        'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
    ]);
}

if (! function_exists('clienteDadosDoFormulario')) {
    /**
     * Dados da tabela clientes a partir do POST já validado.
     *
     * - pessoa_fisica: CPF tem 11 caracteres; o resto é CNPJ.
     * - senha: a digitada; num cadastro novo sem senha, o CPF/CNPJ sem
     *   pontuação (como na v4, e a área do cliente avisa); sem documento, uma
     *   senha aleatória que ninguém conhece. Na edição, sem senha, não muda.
     * - estado: só uma UF válida.
     *
     * @param  mixed  $post  $this->input->post()
     */
    function clienteDadosDoFormulario($post, bool $novo): array
    {
        $post = is_array($post) ? $post : [];
        $texto = static fn (string $campo): string => is_string($post[$campo] ?? null) ? trim($post[$campo]) : '';

        $dados = [];
        foreach (CLIENTE_CAMPOS_TEXTO as $campo) {
            $dados[$campo] = $texto($campo);
        }

        $dados['estado'] = array_key_exists(strtoupper($dados['estado']), ufsDoBrasil()) ? strtoupper($dados['estado']) : '';
        $documento = (string) preg_replace('/[^A-Za-z0-9]/', '', $dados['documento']);
        $dados['pessoa_fisica'] = strlen($documento) === 11 ? 1 : 0;
        $dados['fornecedor'] = empty($post['fornecedor']) ? 0 : 1;
        $dados['ativo'] = empty($post['ativo']) ? 0 : 1;

        $senha = $texto('senha');
        if ($senha !== '') {
            $dados['senha'] = password_hash($senha, PASSWORD_DEFAULT);
        } elseif ($novo) {
            $dados['senha'] = password_hash($documento !== '' ? $documento : bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        }

        return $dados;
    }
}

if (! function_exists('clienteValoresDoFormulario')) {
    /**
     * Valores dos campos na tela: o que foi enviado (quando a tela volta com
     * erro), senão o cliente em edição, senão vazio. A senha nunca volta.
     *
     * @param  mixed        $post     $this->input->post(), ou null fora de um POST
     * @param  object|null  $cliente
     * @return array<string, string|bool>
     */
    function clienteValoresDoFormulario($post, ?object $cliente): array
    {
        $valores = [];
        foreach (CLIENTE_CAMPOS_TEXTO as $campo) {
            if (is_array($post)) {
                $valores[$campo] = is_string($post[$campo] ?? null) ? $post[$campo] : '';
            } else {
                $valores[$campo] = $cliente !== null ? (string) ($cliente->{$campo} ?? '') : '';
            }
        }

        $valores['fornecedor'] = is_array($post) ? ! empty($post['fornecedor']) : (bool) ($cliente->fornecedor ?? false);
        $valores['ativo'] = is_array($post) ? ! empty($post['ativo']) : ($cliente !== null ? (bool) ($cliente->ativo ?? true) : true);

        return $valores;
    }
}


if (! function_exists('clienteDetectarDelimitadorCsv')) {
    /**
     * Detecta o delimitador mais provável de um arquivo CSV (; , ou \t).
     */
    function clienteDetectarDelimitadorCsv(string $primeiraLinha): string
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

if (! function_exists('clienteLerLinhasCsv')) {
    /**
     * Lê e normaliza o conteúdo de um arquivo CSV em UTF-8, retornando cabeçalhos e linhas.
     *
     * @return array{0: list<string>, 1: list<array<string, string>>} [cabecalhos, linhas]
     */
    function clienteLerLinhasCsv(string $conteudo): array
    {
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = (string) mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
        }

        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }

        $linhasBrutas = preg_split("/\r\n|\n|\r/", trim($conteudo));
        if ($linhasBrutas === false || $linhasBrutas === [] || trim($linhasBrutas[0]) === '') {
            return [[], []];
        }

        $delimitador = clienteDetectarDelimitadorCsv($linhasBrutas[0]);

        $cabecalhos = [];
        $linhas = [];

        foreach ($linhasBrutas as $idx => $linhaStr) {
            $linhaStr = trim($linhaStr);
            if ($linhaStr === '') {
                continue;
            }

            $colunas = str_getcsv($linhaStr, $delimitador);
            if ($idx === 0) {
                $cabecalhos = array_values(array_map('trim', $colunas));
                continue;
            }

            if ($cabecalhos === []) {
                continue;
            }

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

if (! function_exists('clienteSugerirMapeamento')) {
    /**
     * Tenta identificar automaticamente qual coluna do CSV corresponde ao campo do cliente/fornecedor.
     *
     * @param  string        $campo       nomeCliente, documento, tipo, telefone, celular, email, cep, rua, numero, complemento, bairro, cidade, estado, contato
     * @param  list<string>  $colunasCsv  Cabeçalhos detectados no CSV
     * @return string|null   Nome da coluna sugerida ou null
     */
    function clienteSugerirMapeamento(string $campo, array $colunasCsv): ?string
    {
        $termosPorCampo = [
            'nomeCliente' => ['nome', 'nome_cliente', 'nomecliente', 'razao_social', 'razaosocial', 'cliente', 'fornecedor', 'nome_fornecedor', 'name', 'company'],
            'documento' => ['documento', 'cpf_cnpj', 'cpfcnpj', 'cpf', 'cnpj', 'doc', 'identificacao', 'cnpj_cpf', 'cpf/cnpj', 'cnpj/cpf'],
            'tipo' => ['tipo', 'categoria', 'tipo_cadastro', 'tipo_pessoa', 'classificacao', 'classificação', 'fornecedor', 'perfil', 'type'],
            'telefone' => ['telefone', 'tel', 'fone', 'fixo', 'telefone_fixo', 'phone'],
            'celular' => ['celular', 'cel', 'whatsapp', 'zap', 'mobile'],
            'email' => ['email', 'e-mail', 'mail', 'correio'],
            'cep' => ['cep', 'codigo_postal', 'zip', 'zipcode'],
            'rua' => ['rua', 'logradouro', 'endereco', 'endereço', 'address', 'street'],
            'numero' => ['numero', 'número', 'num', 'nro', 'number'],
            'complemento' => ['complemento', 'comp', 'compl'],
            'bairro' => ['bairro', 'distrito', 'neighborhood'],
            'cidade' => ['cidade', 'municipio', 'município', 'city'],
            'estado' => ['estado', 'uf', 'state'],
            'contato' => ['contato', 'responsavel', 'responsável', 'vendedor', 'contact'],
        ];

        $candidatos = $termosPorCampo[$campo] ?? [];
        if ($candidatos === [] || $colunasCsv === []) {
            return null;
        }

        $normalizar = static fn (string $str) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str));

        foreach ($colunasCsv as $coluna) {
            $colunaNorm = $normalizar($coluna);
            foreach ($candidatos as $termo) {
                if ($colunaNorm === $normalizar($termo)) {
                    return $coluna;
                }
            }
        }

        foreach ($colunasCsv as $coluna) {
            $colunaNorm = $normalizar($coluna);
            foreach ($candidatos as $termo) {
                $termoNorm = $normalizar($termo);
                if (str_contains($colunaNorm, $termoNorm) || str_contains($termoNorm, $colunaNorm)) {
                    return $coluna;
                }
            }
        }

        return null;
    }
}

if (! function_exists('clienteNormalizarTipo')) {
    /**
     * Normaliza uma string de tipo para 1 (fornecedor) ou 0 (cliente).
     */
    function clienteNormalizarTipo(string $valor): int
    {
        $val = mb_strtolower(trim($valor));
        $indicadoresFornecedor = ['fornecedor', 'fornec', 'f', 'sim', 's', '1', 'true', 'pj', 'juridica', 'jurídica'];

        return in_array($val, $indicadoresFornecedor, true) ? 1 : 0;
    }
}
