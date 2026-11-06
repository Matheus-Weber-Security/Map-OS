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

<?php if (! empty($layout['saas_modal_boas_vindas'])) { ?>
  <div class="v5-shell">
    <?= component('modal', [
        'id' => 'modal-boas-vindas-trial',
        'title' => '🎉 Parabéns! Seja muito bem-vindo',
        'open' => true,
        'body' => html_purificado(
            '<div class="space-y-3 text-text leading-relaxed">'
            . '<p class="font-medium text-[0.9375rem] text-primary">Você acaba de ganhar 10 dias de assinatura gratuita para avaliar o sistema!</p>'
            . '<p class="text-muted text-sm">Seu ambiente já está pronto. Aproveite para cadastrar clientes, emitir ordens de serviço, registrar vendas e controlar seu financeiro com total liberdade e sem compromisso.</p>'
            . '<p class="text-muted text-sm">Explore todos os recursos e aproveite ao máximo seu período de avaliação.</p>'
            . '</div>'
        ),
        'footer' => [
            component('button', [
                'label' => 'Começar a usar agora',
                'variant' => 'primary',
                'icon' => 'arrow-right',
                'attrs' => ['data-modal-fechar' => true],
            ]),
        ],
        'size' => 'md',
    ]) ?>
  </div>
<?php } ?>

<?php if (! empty($layout['saas_modal_prevencimento'])) { ?>
  <div class="v5-shell">
    <?= component('modal', [
        'id' => 'modal-aviso-prevencimento',
        'title' => ((int) $layout['saas_dias_restantes'] === 0) ? 'Sua assinatura vence hoje' : 'Sua assinatura vence em ' . (int) $layout['saas_dias_restantes'] . ' dias',
        'open' => true,
        'body' => html_purificado(
            '<div class="space-y-3 text-text leading-relaxed">'
            . '<p class="font-medium text-[0.9375rem]">' . (((int) $layout['saas_dias_restantes'] === 0) ? 'A assinatura da sua empresa vence hoje.' : 'A assinatura da sua empresa vence nos próximos dias.') . '</p>'
            . '<p class="text-muted text-sm">Para continuar usando todos os recursos com tranquilidade e sem risco de interrupção, você já pode renovar com a forma de pagamento de sua preferência.</p>'
            . '</div>'
        ),
        'footer' => [
            component('button', [
                'label' => 'Lembrar mais tarde',
                'variant' => 'outline',
                'attrs' => ['data-modal-fechar' => true],
            ]),
            component('button', [
                'label' => 'Renovar assinatura',
                'variant' => 'primary',
                'icon' => 'banknote',
                'href' => site_url('assinatura'),
            ]),
        ],
        'size' => 'md',
    ]) ?>
  </div>
<?php } ?>

<?php if (! empty($layout['saas_modal_carencia'])) { ?>
  <div class="v5-shell">
    <?= component('modal', [
        'id' => 'modal-aviso-carencia',
        'title' => 'Período de Carência Ativo (' . ($layout['saas_dias_carencia'] > 1 ? $layout['saas_dias_carencia'] . ' dias restantes' : 'último dia') . ')',
        'open' => true,
        'body' => html_purificado(
            '<div class="space-y-3 text-text leading-relaxed">'
            . '<p class="font-medium text-[0.9375rem]">Sua assinatura venceu, mas seu acesso continua 100% liberado!</p>'
            . '<p class="text-muted text-sm">Você está no <strong>período de tolerância de 2 dias</strong> para emitir ordens de serviço, realizar vendas e cadastros normalmente.</p>'
            . '<p class="text-muted text-sm">Aproveite para renovar sua assinatura agora com a forma de pagamento de sua preferência e evitar que o sistema entre em modo de consulta.</p>'
            . '</div>'
        ),
        'footer' => [
            component('button', [
                'label' => 'Continuar para o sistema',
                'variant' => 'outline',
                'attrs' => ['data-modal-fechar' => true],
            ]),
            component('button', [
                'label' => 'Renovar assinatura',
                'variant' => 'primary',
                'icon' => 'banknote',
                'href' => site_url('assinatura'),
            ]),
        ],
        'size' => 'md',
    ]) ?>
  </div>
<?php } ?>

<?php if (! empty($layout['saas_somente_leitura'])) { ?>
  <div class="v5-shell">
    <?= component('modal', [
        'id' => 'modal-bloqueio-assinatura',
        'title' => 'Recurso pausado temporariamente',
        'body' => html_purificado(
            '<div class="space-y-3 text-text leading-relaxed">'
            . '<p class="font-medium text-[0.9375rem]">Olá! O período de carência da sua assinatura encerrou, por isso as criações e alterações estão pausadas temporariamente.</p>'
            . '<p class="text-muted text-sm">Mas fique tranquilo: <strong>todo o seu histórico continua seguro</strong> e disponível para consulta e impressão a qualquer momento (ordens de serviço, vendas, clientes, relatórios e comprovantes fiscais/térmicos).</p>'
            . '<p class="text-muted text-sm">Para liberar as novas criações, edições e cadastros imediatamente, basta renovar sua assinatura com a forma de pagamento de sua preferência.</p>'
            . '</div>'
        ),
        'footer' => [
            component('button', [
                'label' => 'Continuar consultando',
                'variant' => 'outline',
                'attrs' => ['data-modal-fechar' => true],
            ]),
            component('button', [
                'label' => 'Renovar assinatura',
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
