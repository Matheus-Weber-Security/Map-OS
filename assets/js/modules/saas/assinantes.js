// Módulo de assinantes do SaaS (views/saas/assinantes.php).
//
// Gerencia a alternância dinâmica entre Porcentagem (%) e Valor em Reais (R$)
// no modal de desconto comercial do assinante.

export default function iniciar(raiz) {
    const doc = raiz?.ownerDocument || document;
    const modal = raiz?.id === 'modal-desconto' ? raiz : (raiz?.querySelector('#modal-desconto') || doc.querySelector('#modal-desconto'));
    if (!modal) {
        return;
    }

    const form = modal.querySelector('#form-ajustar-desconto');
    const radioPorcento = modal.querySelector('#radio-desc-porcentagem');
    const radioValor = modal.querySelector('#radio-desc-valor');
    const inputDesconto = modal.querySelector('#input-desconto-valor');
    const rotuloDesconto = modal.querySelector('label[for="input-desconto-valor"]');
    const ajudaDesconto = modal.querySelector('#input-desconto-valor-ajuda');

    function atualizarModo(tipo) {
        if (tipo === 'valor') {
            if (radioValor) {
                radioValor.checked = true;
            }
            if (radioPorcento) {
                radioPorcento.checked = false;
            }
            if (rotuloDesconto) {
                rotuloDesconto.innerHTML = 'Desconto em Reais (R$) <span class="text-danger-ink" aria-hidden="true">*</span>';
            }
            if (ajudaDesconto) {
                ajudaDesconto.textContent = 'Valor fixo em reais abatido da mensalidade do plano.';
            }
            if (inputDesconto) {
                inputDesconto.placeholder = 'Ex: 20,00';
            }
        } else {
            if (radioPorcento) {
                radioPorcento.checked = true;
            }
            if (radioValor) {
                radioValor.checked = false;
            }
            if (rotuloDesconto) {
                rotuloDesconto.innerHTML = 'Desconto Percentual (%) <span class="text-danger-ink" aria-hidden="true">*</span>';
            }
            if (ajudaDesconto) {
                ajudaDesconto.textContent = 'Porcentagem de desconto aplicada nas faturas mensais (de 0 a 100%).';
            }
            if (inputDesconto) {
                inputDesconto.placeholder = 'Ex: 10 ou 10,50';
            }
        }
    }

    // Ouvinte ao abrir o modal pelo botão da tabela de assinantes
    doc.addEventListener('click', (evento) => {
        const gatilho = evento.target.closest?.('[data-modal-abrir="modal-desconto"]');
        if (!gatilho) {
            return;
        }

        const tipo = gatilho.dataset.valorTipoDesconto || 'porcentagem';
        atualizarModo(tipo);

        const valor = gatilho.dataset.valorDesconto;
        if (inputDesconto && valor !== undefined) {
            inputDesconto.value = valor;
        }
    });

    // Ouvintes de clique e mudança nas opções do rádio
    modal.addEventListener('change', (evento) => {
        if (evento.target.name === 'tipo_desconto') {
            atualizarModo(evento.target.value);
        }
    });

    if (radioPorcento) {
        radioPorcento.addEventListener('click', () => atualizarModo('porcentagem'));
        radioPorcento.addEventListener('change', () => atualizarModo('porcentagem'));
    }
    if (radioValor) {
        radioValor.addEventListener('click', () => atualizarModo('valor'));
        radioValor.addEventListener('change', () => atualizarModo('valor'));
    }

    // Validação no envio
    if (form && inputDesconto) {
        form.addEventListener('submit', (evento) => {
            const ehPorcento = !radioValor || !radioValor.checked;
            const valorStr = (inputDesconto.value || '').trim().replace(/\./g, '').replace(',', '.');
            const num = parseFloat(valorStr);

            if (isNaN(num) || num < 0) {
                evento.preventDefault();
                inputDesconto.setCustomValidity('Informe um desconto válido.');
                inputDesconto.reportValidity();
                return;
            }

            if (ehPorcento && num > 100) {
                evento.preventDefault();
                inputDesconto.setCustomValidity('A porcentagem de desconto deve ser entre 0 e 100%.');
                inputDesconto.reportValidity();
                return;
            }

            inputDesconto.setCustomValidity('');
        });

        inputDesconto.addEventListener('input', () => {
            inputDesconto.setCustomValidity('');
        });
    }
}
