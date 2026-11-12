import { get } from '../../lib/http.js';

function criarElemento(tag, classe, texto) {
    const el = document.createElement(tag);
    if (classe) {
        el.className = classe;
    }
    if (texto !== undefined && texto !== null) {
        el.textContent = String(texto);
    }

    return el;
}

function rotuloEvento(evento) {
    switch (evento) {
        case 'sent':
            return 'Enviado pelo servidor';
        case 'failed':
            return 'Falha no envio';
        case 'opened':
            return 'E-mail aberto pelo destinatário';
        default:
            return evento || 'Evento';
    }
}

function renderizarLog(container, dados) {
    container.innerHTML = '';

    const email = dados.email ?? {};
    const logs = Array.isArray(dados.logs) ? dados.logs : [];

    // Cartão resumo de envio e leitura
    const resumo = criarElemento('div', 'grid gap-3 rounded-md border border-border bg-surface-subtle p-3 sm:grid-cols-2');

    const campos = [
        ['Situação', email.status_label || '—'],
        ['Criado em', email.criado_em || '—'],
        ['Enviado em', email.enviado_em || '—'],
        ['Total de leituras', email.total_leituras > 0 ? `${email.total_leituras}x` : 'Ainda não lido'],
        ['1ª leitura', email.lido_em || '—'],
        ['Última leitura', email.ultima_leitura_em || '—'],
    ];

    for (const [rotulo, valor] of campos) {
        const bloco = criarElemento('div', 'flex flex-col');
        bloco.appendChild(criarElemento('span', 'text-caption text-muted', rotulo));
        bloco.appendChild(criarElemento('span', 'text-sm font-medium text-text', valor));
        resumo.appendChild(bloco);
    }

    container.appendChild(resumo);

    if (email.erro_mensagem) {
        const caixaErro = criarElemento('div', 'rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger-ink [overflow-wrap:anywhere]');
        caixaErro.appendChild(criarElemento('p', 'font-medium', 'Motivo da falha no envio:'));
        caixaErro.appendChild(criarElemento('p', 'mt-1 text-caption', email.erro_mensagem));
        container.appendChild(caixaErro);
    }

    const tituloHistorico = criarElemento('h3', 'text-sm font-semibold text-text', 'Histórico de eventos');
    container.appendChild(tituloHistorico);

    if (logs.length === 0) {
        container.appendChild(criarElemento('p', 'text-caption text-muted', 'Nenhum evento registrado para este e-mail ainda.'));
        return;
    }

    const lista = criarElemento('ul', 'flex max-h-64 flex-col divide-y divide-border overflow-y-auto rounded-md border border-border bg-surface');
    for (const item of logs) {
        const li = criarElemento('li', 'flex flex-col gap-1 p-3');
        const topo = criarElemento('div', 'flex flex-wrap items-center justify-between gap-2');
        topo.appendChild(criarElemento('span', 'text-sm font-medium text-text', rotuloEvento(item.evento)));
        topo.appendChild(criarElemento('span', 'text-caption text-muted', item.criado_em || '—'));
        li.appendChild(topo);

        if (item.detalhes) {
            li.appendChild(criarElemento('p', 'text-caption text-muted [overflow-wrap:anywhere]', item.detalhes));
        }
        if (item.ip) {
            li.appendChild(criarElemento('p', 'text-caption text-muted', `IP: ${item.ip}`));
        }
        lista.appendChild(li);
    }

    container.appendChild(lista);
}

export default function iniciar(raiz) {
    const doc = raiz.ownerDocument ?? document;
    const modal = doc.getElementById('log-email');
    if (!modal) {
        return;
    }

    const conteudo = modal.querySelector('[data-email-log-conteudo]');
    if (!conteudo) {
        return;
    }

    raiz.addEventListener('click', async (evento) => {
        const botao = evento.target.closest?.('[data-email-log-url]');
        if (!botao) {
            return;
        }

        const url = botao.dataset.emailLogUrl;
        if (!url) {
            return;
        }

        conteudo.innerHTML = '';
        conteudo.appendChild(criarElemento('p', 'text-sm text-muted', 'Carregando histórico do e-mail…'));

        try {
            const resposta = await get(url);
            if (!resposta?.result) {
                throw new Error(resposta?.message || 'Não foi possível carregar o log.');
            }
            renderizarLog(conteudo, resposta);
        } catch (erro) {
            conteudo.innerHTML = '';
            conteudo.appendChild(criarElemento('div', 'rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger-ink', erro?.message || 'Falha ao buscar o log do e-mail.'));
        }
    });
}
