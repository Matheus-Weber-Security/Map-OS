<?php
$total = 0;
foreach ($compras as $c) {
    $total += (float) $c->valorTotal;
}
$this->load->view('relatorios/imprimir/partes/inicio', ['tituloDoc' => ucfirst($title)]);
?>
    <table class="rel-tabela">
        <thead>
            <tr>
                <th class="rel-th">#</th>
                <th class="rel-th">Fornecedor</th>
                <th class="rel-th">NF-e</th>
                <th class="rel-th">Comprador</th>
                <th class="rel-th">Data</th>
                <th class="rel-th">Situação</th>
                <th class="rel-th">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($compras as $c) : ?>
                <tr>
                    <td class="rel-td text-center"><?= e($c->idCompras) ?></td>
                    <td class="rel-td"><?= e($c->nomeCliente ?: 'Fornecedor avulso') ?></td>
                    <td class="rel-td text-center"><?= e($c->numero_nfe ?: '—') ?></td>
                    <td class="rel-td text-center"><?= e($c->nome ?: '—') ?></td>
                    <td class="rel-td text-center"><?= e(date('d/m/Y', strtotime($c->dataCompra))) ?></td>
                    <td class="rel-td text-center"><?= e($c->status) ?></td>
                    <td class="rel-td text-right">R$ <?= number_format($c->valorTotal, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td class="rel-total text-right" colspan="6">TOTAL GERAL</td>
                <td class="rel-total text-right">R$ <?= number_format($total, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>
<?php $this->load->view('relatorios/imprimir/partes/fim'); ?>
