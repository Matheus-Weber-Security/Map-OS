<?php $totalProdutos = 0; ?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        .invoice-box {
            max-width: 1100px;
            margin: auto;
            padding: 10px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #555;
        }

        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
        }

        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }

        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }

        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }

        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                width: 100%;
                display: block;
                text-align: center;
            }

            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }
        }

        /** RTL **/
        .rtl {
            direction: rtl;
            font-family: Tahoma, 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
        }

        .rtl table {
            text-align: right;
        }

        .rtl table tr td:nth-child(2) {
            text-align: left;
        }

        .justify {
            text-align: justify;
        }
    </style>
</head>

<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="4">
                    <table>
                        <tr>
                            <td class="title">
                                <img src="<?= e($emitente->url_logo) ?>" style="width:100%; max-width:120px;">
                            </td>
                            <td style="text-align: right">
                                Venda #: <?= e($result->codigo ?? $result->idVendas) ?><br>
                                Data da Venda: <?= $result->dataVenda ? date('d/m/Y', strtotime($result->dataVenda)) : ''; ?> <br>
                                Vendedor: <?= e($result->nome) ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="information">
                <td colspan="4">
                    <table>
                        <tr>
                            <td>
                                Cliente: <?= e($result->nomeCliente) ?><br>
                                <?= e($result->rua) ?>, <?= e($result->numero) ?>, <?= e($result->bairro) ?><br>
                                <?= e($result->cidade) ?> - <?= e($result->estado) ?> <br>
                                <?= e($result->email) ?> <br>
                                <?= e($result->celular ?? $result->telefone) ?>
                            </td>

                            <td style="text-align: right">
                                <?= e($emitente->nome) ?> <br>
                                <?= e($emitente->rua) ?>, <?= e($emitente->numero) ?>, <?= e($emitente->bairro) ?><br>
                                <?= e($emitente->cidade) ?> - <?= e($emitente->uf) ?> CEP: <?= e($emitente->cep) ?> <br>
                                Tel: <?= e($emitente->telefone) ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="heading">
                <td colspan="2">
                    Status
                </td>
                <td colspan="2" style="text-align: center">
                    <?= e($result->status) ?>
                </td>
            </tr>

            <?php if (! empty($result->garantia)) { ?>
                <tr class="details">
                    <td colspan="2">
                        Garantia
                    </td>

                    <td colspan="2" style="text-align: center">
                        <?= e($result->garantia) ?> dia(s)
                    </td>
                </tr>
            <?php } ?>

            <?php if (! empty($result->observacoes_cliente)) { ?>
                <tr class="heading">
                    <td colspan="4">
                        <b>Observações</b>
                    </td>
                </tr>
                <tr>
                    <td class="justify" colspan="4">
                        <?= printSafeHtml($result->observacoes_cliente) ?>
                    </td>
                </tr>
            <?php } ?>

            <?php if ($produtos) { ?>

                <tr class="heading">
                    <td>Produto</td>
                    <td style="text-align: center">Quantidade</td>
                    <td style="text-align: center">Preço unit.</td>
                    <td style="text-align: right">Sub-total</td>
                </tr>

                <?php foreach ($produtos as $p) {
                    $totalProdutos += (float) $p->subTotal;
                    echo '<tr class="item">';
                    echo '<td>' . e($p->descricao) . osDescontoItemNota($p) . '</td>';
                    echo '<td style="text-align: center">' . e($p->quantidade) . '</td>';
                    echo '<td style="text-align: center">R$ ' . number_format((float) ($p->preco ?: $p->precoVenda), 2, ',', '.') . '</td>';
                    echo '<td style="text-align: right">R$ ' . number_format((float) $p->subTotal, 2, ',', '.') . '</td>';
                    echo '</tr>';
                } ?>

                <tr class="item">
                    <td colspan="3" style="text-align: right"><strong>Total em Produtos:</strong></td>
                    <td style="text-align: right"><strong>R$ <?= number_format($totalProdutos, 2, ',', '.'); ?></strong></td>
                </tr>
            <?php } ?>

            <tr class="heading">
                <td colspan="4">
                    <br>
                </td>
            </tr>

            <?php if ($result->valor_desconto != 0) { ?>
                <tr class="heading">
                    <td colspan="3" style="text-align: right"><strong>Subtotal:</strong></td>
                    <td style="text-align: right">
                        <strong>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></strong>
                    </td>
                </tr>
                <tr class="heading">
                    <td colspan="3" style="text-align: right"><strong>Desconto:</strong></td>
                    <td style="text-align: right">
                        <strong>R$ <?= number_format((float) $result->valor_desconto - $totalProdutos, 2, ',', '.') ?></strong>
                    </td>
                </tr>
                <tr class="heading">
                    <td colspan="3" style="text-align: right"><strong>Total com Desconto:</strong></td>
                    <td style="text-align: right">
                        <strong>R$ <?= number_format((float) $result->valor_desconto, 2, ',', '.') ?></strong>
                    </td>
                </tr>
            <?php } else { ?>
                <tr class="heading">
                    <td colspan="3" style="text-align: right"><strong>Total:</strong></td>
                    <td style="text-align: right">
                        <strong>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></strong>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</body>

</html>
