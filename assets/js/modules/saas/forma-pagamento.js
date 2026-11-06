// Módulo de alternância das configurações de formas de pagamento SaaS (views/saas/forma_pagamento_formulario.php).
//
// Suporta a alternância entre PIX Dinâmico (Via Gateway) e PIX Manual (Chave Fixa),
// bem como a exibição dos campos de credenciais de acordo com o Gateway Processador selecionado.

export default function iniciar(raiz) {
    // 1. Alternância entre PIX Dinâmico e PIX Manual
    const selectTipoPix = raiz.querySelector('select[name="tipo_pix"]');
    const blocoPixDinamico = raiz.querySelector('#bloco-pix-dinamico');
    const blocoPixManual = raiz.querySelector('#bloco-pix-manual');

    function alternarTipoPix() {
        if (!selectTipoPix || !blocoPixDinamico || !blocoPixManual) {
            return;
        }

        const modo = selectTipoPix.value;
        if (modo === 'dinamico') {
            blocoPixDinamico.classList.remove('hidden');
            blocoPixManual.classList.add('hidden');
        } else {
            blocoPixDinamico.classList.add('hidden');
            blocoPixManual.classList.remove('hidden');
        }
    }

    if (selectTipoPix) {
        selectTipoPix.addEventListener('change', alternarTipoPix);
        alternarTipoPix();
    }

    // 2. Alternância de campos de credenciais por Gateway Processador
    const selectProvedor = raiz.querySelector('select[name="provedor"]');
    const credenciaisMp = raiz.querySelector('#credenciais-mercadopago');
    const credenciaisAsaas = raiz.querySelector('#credenciais-asaas');
    const credenciaisEfi = raiz.querySelector('#credenciais-gerencianet');

    function alternarProvedor() {
        if (!selectProvedor) {
            return;
        }

        const provedor = selectProvedor.value;

        if (credenciaisMp) {
            if (provedor === 'mercadopago') {
                credenciaisMp.classList.remove('hidden');
            } else {
                credenciaisMp.classList.add('hidden');
            }
        }

        if (credenciaisAsaas) {
            if (provedor === 'asaas') {
                credenciaisAsaas.classList.remove('hidden');
            } else {
                credenciaisAsaas.classList.add('hidden');
            }
        }

        if (credenciaisEfi) {
            if (provedor === 'gerencianet') {
                credenciaisEfi.classList.remove('hidden');
            } else {
                credenciaisEfi.classList.add('hidden');
            }
        }
    }

    if (selectProvedor) {
        selectProvedor.addEventListener('change', alternarProvedor);
        alternarProvedor();
    }
}
