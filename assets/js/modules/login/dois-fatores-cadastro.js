import { mostrarToast } from '../../lib/toast.js';

async function copiarTexto(texto, area = globalThis.navigator?.clipboard) {
    try {
        if (!area) {
            return false;
        }
        await area.writeText(texto);
        return true;
    } catch {
        return false;
    }
}

export default function iniciar(raiz) {
    const doc = raiz.ownerDocument ?? document;

    raiz.addEventListener('click', async (evento) => {
        const btnSegredo = evento.target.closest?.('[data-copiar-segredo]');
        if (btnSegredo) {
            const segredo = btnSegredo.dataset.copiarSegredo || raiz.querySelector('[data-segredo]')?.textContent?.replace(/\s+/g, '') || '';
            if (segredo) {
                const copiou = await copiarTexto(segredo);
                mostrarToast(copiou
                    ? { mensagem: 'Chave secreta copiada para a área de transferência.', variante: 'success' }
                    : { mensagem: 'Não foi possível copiar automaticamente. Copie a chave exibida na tela.', variante: 'warning' }, doc);
            }
            return;
        }

        const btnCodigos = evento.target.closest?.('[data-copiar-codigos]');
        if (btnCodigos) {
            const codigos = [...raiz.querySelectorAll('[data-codigos] li')].map((li) => li.textContent.trim()).filter(Boolean);
            if (codigos.length > 0) {
                const texto = codigos.join('\n');
                const copiou = await copiarTexto(texto);
                mostrarToast(copiou
                    ? { mensagem: 'Códigos de recuperação copiados.', variante: 'success' }
                    : { mensagem: 'Não foi possível copiar automaticamente. Anote ou baixe o arquivo.', variante: 'warning' }, doc);
            }
            return;
        }

        const btnBaixar = evento.target.closest?.('[data-baixar-codigos]');
        if (btnBaixar) {
            const codigos = [...raiz.querySelectorAll('[data-codigos] li')].map((li) => li.textContent.trim()).filter(Boolean);
            if (codigos.length === 0) {
                return;
            }
            const email = btnBaixar.dataset.contaEmail || '';
            const conteudo = [
                'Nexus Ticket - Códigos de Recuperação (2FA)',
                email ? `Conta: ${email}` : '',
                `Gerado em: ${new Date().toLocaleString('pt-BR')}`,
                '----------------------------------------------',
                'Guarde estes códigos em local seguro. Cada código só pode ser usado uma vez.',
                '',
                ...codigos,
                '',
            ].filter((linha, idx) => idx !== 1 || linha !== '').join('\n');

            const blob = new Blob([conteudo], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = doc.createElement('a');
            link.href = url;
            link.download = 'nexus-ticket-codigos-recuperacao.txt';
            doc.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }
    });
}
