<?php
/**
 * Layout do painel (#2835): rodapé, notificações e scripts do fim da página.
 *
 * As mensagens de flash (success/error da sessão) viram toasts do componente
 * da #2834, no lugar do sweetalert da v4. Algumas mensagens antigas trazem
 * <br> ou <b>; elas passam pelo HTMLPurifier (html_purificado()) em vez de
 * serem impressas cruas.
 *
 * @var array $configuration
 * @var array $layout
 */
?>
  <div class="v5-shell v5-rodape-wrapper mt-auto" style="margin-top: auto !important;">
    <footer class="v5-rodape mt-auto px-4 py-6 text-center text-xs text-muted lg:px-6">
      <span><?= e(date('Y')) ?> &copy; Matheus Weber - Nexus Ticket - Versão: <?= e($this->config->item('app_version')) ?></span>
    </footer>
  </div>
</div>

<?php $this->load->view('tema/busca'); ?>

<?php if (! empty($layout['saas_somente_leitura'])) { ?>
  <div class="v5-shell">
    <?= component('modal', [
        'id' => 'modal-bloqueio-assinatura',
        'title' => 'Recurso pausado temporariamente',
        'body' => html_purificado(
            '<div class="space-y-3 text-text leading-relaxed">'
            . '<p class="font-medium text-[0.9375rem]">Olá! O período de carência da sua assinatura encerrou, por isso as criações e alterações estão pausadas temporariamente.</p>'
            . '<p class="text-muted text-sm">Mas fique tranquilo: <strong>todo o seu histórico continua seguro</strong> e disponível para consulta e impressão a qualquer momento (ordens de serviço, vendas, clientes, relatórios e comprovantes fiscais/térmicos).</p>'
            . '<p class="text-muted text-sm">Para liberar as novas criações, edições e cadastros imediatamente, basta renovar sua assinatura via PIX. A liberação é rápida e automática!</p>'
            . '</div>'
        ),
        'footer' => [
            component('button', [
                'label' => 'Continuar consultando',
                'variant' => 'outline',
                'attrs' => ['data-modal-fechar' => true],
            ]),
            component('button', [
                'label' => 'Renovar Assinatura via PIX',
                'variant' => 'primary',
                'icon' => 'banknote',
                'href' => site_url('assinatura'),
            ]),
        ],
        'size' => 'md',
    ]) ?>
  </div>
<?php } ?>

<div class="v5-shell">
  <div data-toast-region class="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col items-end gap-2">
    <?php foreach ($layout['flash'] as $mensagem) { ?>
      <?= component('toast', ['variant' => $mensagem['variant'], 'title' => $mensagem['title'], 'duration' => $mensagem['duration'], 'message' => html_purificado($mensagem['message'])]) ?>
    <?php } ?>
  </div>
</div>

</body>

</html>
