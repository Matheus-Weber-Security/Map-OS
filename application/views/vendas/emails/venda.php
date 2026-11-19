<?php $totalProdutos = 0; ?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante da Venda #<?= e($result->codigo ?? $result->idVendas) ?></title>
    <style>
        body {
            background-color: #f4f4f5;
            margin: 0;
            padding: 20px 10px;
            font-family: 'Courier New', Courier, monospace, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1f1633;
            -webkit-text-size-adjust: 100%;
        }
        .cupom-email {
            max-width: 380px;
            width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px dashed #cfcfdb;
            padding: 16px;
            box-sizing: border-box;
            border-radius: 4px;
            font-size: 12px;
            line-height: 1.4;
        }
        .cupom-btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            background-color: #f37338;
            color: #ffffff !important;
            text-align: center;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 13px;
            font-weight: bold;
            padding: 12px 14px;
            border-radius: 6px;
            margin: 12px 0;
        }
        .cupom-divisor {
            border: none;
            border-top: 1px dashed #1f1633;
            margin: 10px 0;
        }
        .cupom-tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 8px 0;
        }
        .cupom-tabela th {
            border-top: 1px solid #1f1633;
            border-bottom: 1px solid #1f1633;
            padding: 4px 2px;
            font-weight: bold;
        }
        .cupom-tabela td {
            padding: 4px 2px;
            vertical-align: top;
        }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="cupom-email">
        <?php if (! empty($linkVenda)) : ?>
            <div style="text-align: center; margin-bottom: 14px;">
                <a href="<?= e($linkVenda) ?>" target="_blank" rel="noopener" class="cupom-btn">
                    📄 Visualizar / Imprimir Comprovante Térmico (80mm)
                </a>
            </div>
        <?php endif; ?>

        <div class="text-center">
            <?php if (! empty($emitente->url_logo)) : ?>
                <img src="<?= e($emitente->url_logo) ?>" alt="<?= e($emitente->nome) ?>" style="max-height: 60px; max-width: 100%; margin-bottom: 6px;"><br>
            <?php endif; ?>
            <span style="font-size: 14px;"><b><?= e($emitente->nome) ?></b></span><br>
            <?php if ($emitente->cnpj !== '00.000.000/0000-00') : ?>
                <span>CNPJ: <?= e($emitente->cnpj) ?></span><br>
            <?php endif; ?>
            <span><?= e($emitente->rua . ', ' . $emitente->numero . ($emitente->bairro ? ' - ' . $emitente->bairro : '')) ?></span><br>
            <span><?= e($emitente->cidade . ' - ' . $emitente->uf . ($emitente->cep ? ' - CEP: ' . $emitente->cep : '')) ?></span><br>
            <span>Fone: <?= e($emitente->telefone) ?></span>
        </div>

        <hr class="cupom-divisor">

        <div>
            <b>#Venda: </b><span><?= e($result->codigo ?? $result->idVendas) ?></span><br>
            <b>Emissão: </b><span><?= $result->dataVenda ? date('d/m/Y', strtotime($result->dataVenda)) : date('d/m/Y') ?></span><br>
            <b>Status: </b><span><?= e($result->status) ?></span><br>
            <?php if (! empty($result->nome)) : ?>
                <b>Vendedor: </b><span><?= e($result->nome) ?></span><br>
            <?php endif; ?>
            <?php if (! empty($result->garantia)) : ?>
                <b>Garantia: </b><span><?= e($result->garantia) ?> dia(s)</span><br>
            <?php endif; ?>
        </div>

        <hr class="cupom-divisor">

        <div>
            <b>CLIENTE: </b><span><?= e($result->nomeCliente) ?></span><br>
            <?php if (! empty($result->rua)) : ?>
                <span><?= e($result->rua . ', ' . $result->numero . ($result->bairro ? ' - ' . $result->bairro : '')) ?></span><br>
                <span><?= e($result->cidade . ' - ' . $result->estado) ?></span><br>
            <?php endif; ?>
            <?php if (! empty($result->celular) || ! empty($result->telefone)) : ?>
                <span>Tel: <?= e($result->celular ?: $result->telefone) ?></span><br>
            <?php endif; ?>
            <?php if (! empty($result->email)) : ?>
                <span>Email: <?= e($result->email) ?></span><br>
            <?php endif; ?>
        </div>

        <hr class="cupom-divisor">

        <?php if (! empty($produtos)) : ?>
            <table class="cupom-tabela">
                <thead>
                    <tr>
                        <th class="text-left">Produto</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-right">Unit.</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p) :
                        $totalProdutos += (float) $p->subTotal;
                    ?>
                        <tr>
                            <td class="text-left"><?= e($p->descricao) ?><?= osDescontoItemNota($p) ?></td>
                            <td class="text-center"><?= e($p->quantidade) ?></td>
                            <td class="text-right"><?= number_format((float) ($p->preco ?: $p->precoVenda), 2, ',', '.') ?></td>
                            <td class="text-right"><?= number_format((float) $p->subTotal, 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr class="cupom-divisor">

            <table style="width: 100%; font-size: 12px; margin: 6px 0;">
                <?php if ((float) ($result->valor_desconto ?? 0) > 0 && (float) ($result->desconto ?? 0) > 0) : ?>
                    <tr>
                        <td class="text-right font-bold">Subtotal:</td>
                        <td class="text-right font-bold" style="width: 90px;">R$ <?= number_format($totalProdutos, 2, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="text-right font-bold">Desconto:</td>
                        <td class="text-right font-bold" style="width: 90px;">R$ <?= number_format((float) $result->valor_desconto - $totalProdutos, 2, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="text-right font-bold" style="font-size: 14px; padding-top: 4px;">Total:</td>
                        <td class="text-right font-bold" style="font-size: 14px; padding-top: 4px; width: 90px;">R$ <?= number_format((float) $result->valor_desconto, 2, ',', '.') ?></td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <td class="text-right font-bold" style="font-size: 14px;">Total:</td>
                        <td class="text-right font-bold" style="font-size: 14px; width: 90px;">R$ <?= number_format($totalProdutos, 2, ',', '.') ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

        <?php if (! empty($result->observacoes_cliente)) : ?>
            <hr class="cupom-divisor">
            <div>
                <b>Observações:</b><br>
                <span><?= printSafeHtml($result->observacoes_cliente) ?></span>
            </div>
        <?php endif; ?>

        <?php if (! empty($linkVenda)) : ?>
            <hr class="cupom-divisor">
            <div style="text-align: center; margin-top: 14px;">
                <a href="<?= e($linkVenda) ?>" target="_blank" rel="noopener" class="cupom-btn">
                    📄 Abrir Comprovante Completo (80mm)
                </a>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 16px; font-size: 11px; color: #71717a;">
            Comprovante emitido eletronicamente por <?= e($emitente->nome) ?>
        </div>
    </div>
</body>
</html>
