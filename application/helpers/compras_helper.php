<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Funções auxiliares para o módulo de Compras e Importação de NF-e (#Compras-01).
 */

if (! function_exists('comprasStatusPill')) {
    /**
     * Retorna a especificação da pill-status para a compra.
     */
    function comprasStatusPill(?string $status): array
    {
        return match (strtolower(trim((string) $status))) {
            'concluída', 'concluida' => ['label' => 'Concluída', 'variant' => 'success'],
            'pendente', 'em andamento' => ['label' => 'Pendente', 'variant' => 'warning'],
            'cancelada', 'cancelado' => ['label' => 'Cancelada', 'variant' => 'danger'],
            default => ['label' => $status ?: 'Pendente', 'variant' => 'neutral'],
        };
    }
}

if (! function_exists('nfeParser')) {
    /**
     * Faz o parse seguro do XML da NF-e (modelo 55/65) em memória.
     * Retorna um array associativo estruturado ou dispara Exception em caso de XML inválido.
     *
     * @return array{
     *   chave: string,
     *   numero: string,
     *   serie: string,
     *   data_emissao: string,
     *   emitente: array,
     *   itens: list<array>,
     *   duplicatas: list<array>,
     *   totais: array
     * }
     */
    function nfeParser(string $xmlContent): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($xmlContent), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);

        if (! $xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = isset($errors[0]->message) ? trim($errors[0]->message) : 'Arquivo XML malformado ou corrompido.';
            throw new InvalidArgumentException('Não foi possível ler o XML da NF-e: ' . $msg);
        }

        // Localiza a tag infNFe
        $infNFe = null;
        if (isset($xml->NFe->infNFe)) {
            $infNFe = $xml->NFe->infNFe;
        } elseif (isset($xml->infNFe)) {
            $infNFe = $xml->infNFe;
        } else {
            // Tenta busca com xpath ignorando namespace
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            $nodes = $xml->xpath('//nfe:infNFe');
            if (! empty($nodes)) {
                $infNFe = $nodes[0];
            } else {
                $nodesSemNs = $xml->xpath('//infNFe');
                if (! empty($nodesSemNs)) {
                    $infNFe = $nodesSemNs[0];
                }
            }
        }

        if (! $infNFe) {
            throw new InvalidArgumentException('O arquivo enviado não é um XML de NF-e válido (tag infNFe não encontrada).');
        }

        // Chave de acesso (44 dígitos)
        $idAttr = (string) ($infNFe['Id'] ?? '');
        $chave = preg_replace('/\D/', '', $idAttr);

        // Dados da Identificação da NF-e
        $ide = $infNFe->ide;
        $numero = (string) ($ide->nNF ?? '');
        $serie = (string) ($ide->serie ?? '');
        $dhEmi = (string) ($ide->dhEmi ?: $ide->dEmi ?? '');
        $dataEmissao = $dhEmi !== '' ? substr(str_replace('T', ' ', $dhEmi), 0, 19) : date('Y-m-d H:i:s');

        // Emitente (Fornecedor)
        $emit = $infNFe->emit;
        $doc = (string) ($emit->CNPJ ?: $emit->CPF ?? '');
        $enderEmit = $emit->enderEmit;

        $emitente = [
            'documento' => preg_replace('/\D/', '', $doc),
            'razao_social' => (string) ($emit->xNome ?? ''),
            'nome_fantasia' => (string) ($emit->xFant ?: $emit->xNome ?? ''),
            'ie' => (string) ($emit->IE ?? ''),
            'telefone' => (string) ($enderEmit->fone ?? ''),
            'cep' => (string) ($enderEmit->CEP ?? ''),
            'logradouro' => (string) ($enderEmit->xLgr ?? ''),
            'numero' => (string) ($enderEmit->nro ?? ''),
            'complemento' => (string) ($enderEmit->xCpl ?? ''),
            'bairro' => (string) ($enderEmit->xBairro ?? ''),
            'municipio' => (string) ($enderEmit->xMun ?? ''),
            'uf' => (string) ($enderEmit->UF ?? ''),
        ];

        // Itens de Produtos
        $itens = [];
        foreach ($infNFe->det as $det) {
            $prod = $det->prod;
            $ean = (string) ($prod->cEAN ?? '');
            $eanUpper = strtoupper(trim($ean));
            if ($eanUpper === 'SEM GTIN' || $eanUpper === 'SEMGTIN') {
                $ean = '';
            }

            $itens[] = [
                'item_num' => (int) ($det['nItem'] ?? count($itens) + 1),
                'codigo_fornecedor' => trim((string) ($prod->cProd ?? '')),
                'ean' => trim($ean),
                'descricao' => trim((string) ($prod->xProd ?? '')),
                'ncm' => trim((string) ($prod->NCM ?? '')),
                'cfop' => trim((string) ($prod->CFOP ?? '')),
                'unidade' => trim((string) ($prod->uCom ?? 'UN')),
                'quantidade' => (float) ($prod->qCom ?? 1.0),
                'valor_unitario' => (float) ($prod->vUnCom ?? 0.0),
                'valor_total' => (float) ($prod->vProd ?? 0.0),
                'desconto' => (float) ($prod->vDesc ?? 0.0),
            ];
        }

        // Cobrança / Duplicatas (Contas a Pagar)
        $duplicatas = [];
        if (isset($infNFe->cobr->dup)) {
            foreach ($infNFe->cobr->dup as $dup) {
                $dVenc = (string) ($dup->dVenc ?? '');
                $duplicatas[] = [
                    'numero' => (string) ($dup->nDup ?? count($duplicatas) + 1),
                    'vencimento' => $dVenc !== '' ? $dVenc : date('Y-m-d', strtotime('+30 days')),
                    'valor' => (float) ($dup->vDup ?? 0.0),
                ];
            }
        }

        // Totais
        $totalICMS = $infNFe->total->ICMSTot ?? null;
        $totais = [
            'valor_produtos' => (float) ($totalICMS->vProd ?? 0.0),
            'valor_desconto' => (float) ($totalICMS->vDesc ?? 0.0),
            'valor_frete' => (float) ($totalICMS->vFrete ?? 0.0),
            'valor_total' => (float) ($totalICMS->vNF ?? 0.0),
        ];

        return [
            'chave' => $chave,
            'numero' => $numero,
            'serie' => $serie,
            'data_emissao' => $dataEmissao,
            'emitente' => $emitente,
            'itens' => $itens,
            'duplicatas' => $duplicatas,
            'totais' => $totais,
        ];
    }
}
