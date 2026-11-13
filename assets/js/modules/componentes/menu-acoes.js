// Menu suspenso de ações de linha (application/views/components/menu-acoes.php).
//
// Usa position: fixed com compensação de zoom/escala para nunca ser cortado pelo
// overflow-x-auto das tabelas (data-table), invertendo para cima automaticamente
// quando falta espaço abaixo na tela.

let aberto = null;
let registrado = false;

function itensHabilitados(painel) {
    return Array.from(
        painel.querySelectorAll('[data-menu-acoes-item]:not([disabled]):not([aria-disabled="true"])')
    );
}

export function fecharMenuAcoes(devolverFoco = false) {
    if (!aberto) {
        return;
    }

    const container = aberto;
    const gatilho = container.querySelector('[data-menu-acoes-gatilho]');
    const painel = container.querySelector('[data-menu-acoes-painel]');

    if (painel) {
        painel.hidden = true;
        painel.style.left = '';
        painel.style.top = '';
        painel.style.visibility = '';
    }

    if (gatilho) {
        gatilho.setAttribute('aria-expanded', 'false');
    }

    aberto = null;

    if (devolverFoco) {
        gatilho?.focus();
    }
}

function obterEscalaZoom(elemento) {
    let fator = 1;
    for (let atual = elemento; atual && atual.nodeType === 1; atual = atual.parentElement) {
        const z = Number.parseFloat(getComputedStyle(atual).zoom);
        if (Number.isFinite(z) && z > 0 && z !== 1) {
            fator *= z;
        }
    }
    return fator;
}

export function posicionarPainel(gatilho, painel) {
    const escala = obterEscalaZoom(painel);
    painel.hidden = false;
    painel.style.position = 'fixed';
    painel.style.left = '0px';
    painel.style.top = '0px';

    const btn = gatilho.getBoundingClientRect();
    const menuRect = painel.getBoundingClientRect();
    const largura = menuRect.width;
    const altura = menuRect.height;
    const gap = 6;
    const margem = 8;

    const vw = Math.min(window.innerWidth || Infinity, document.documentElement.clientWidth || Infinity);
    const vh = Math.min(window.innerHeight || Infinity, document.documentElement.clientHeight || Infinity);

    let alvoEsquerda = btn.right - largura;
    if (alvoEsquerda + largura > vw - margem) {
        alvoEsquerda = vw - margem - largura;
    }
    if (alvoEsquerda < margem) {
        alvoEsquerda = margem;
    }

    const espacoAbaixo = vh - btn.bottom - margem;
    const espacoAcima = btn.top - margem;
    let alvoTopo;

    if (espacoAbaixo >= altura + gap || espacoAbaixo >= espacoAcima) {
        alvoTopo = Math.min(btn.bottom + gap, vh - margem - altura);
    } else {
        alvoTopo = Math.max(margem, btn.top - gap - altura);
    }
    if (alvoTopo < margem) {
        alvoTopo = margem;
    }

    painel.style.left = `${alvoEsquerda / escala}px`;
    painel.style.top = `${alvoTopo / escala}px`;
}

function abrir(container) {
    if (aberto === container) {
        fecharMenuAcoes();
        return;
    }

    fecharMenuAcoes();

    const gatilho = container.querySelector('[data-menu-acoes-gatilho]');
    const painel = container.querySelector('[data-menu-acoes-painel]');
    if (!gatilho || !painel) {
        return;
    }

    aberto = container;
    gatilho.setAttribute('aria-expanded', 'true');
    posicionarPainel(gatilho, painel);
}

function registrarOuvintesGlobais() {
    if (registrado) {
        return;
    }
    registrado = true;

    document.addEventListener('click', (evento) => {
        const gatilho = evento.target.closest?.('[data-menu-acoes-gatilho]');
        if (gatilho) {
            const container = gatilho.closest('[data-menu-acoes]');
            if (container) {
                evento.preventDefault();
                abrir(container);
            }
            return;
        }

        const item = evento.target.closest?.('[data-menu-acoes-item]');
        if (item) {
            if (item.disabled || item.getAttribute('aria-disabled') === 'true') {
                evento.preventDefault();
                return;
            }
            fecharMenuAcoes();
            return;
        }

        if (aberto && !aberto.contains(evento.target)) {
            fecharMenuAcoes();
        }
    });

    // Se algum interceptor em capture phase (ex.: modo consulta do SaaS) barrar a propagação
    // de um item do menu, ainda assim fechamos o menu logo após o despacho do clique.
    document.addEventListener(
        'click',
        (evento) => {
            if (!aberto) {
                return;
            }
            const item = evento.target.closest?.('[data-menu-acoes-item]');
            const menuClicado = aberto;
            if (item && menuClicado.contains(item) && !item.disabled && item.getAttribute('aria-disabled') !== 'true') {
                setTimeout(() => {
                    if (aberto === menuClicado) {
                        fecharMenuAcoes();
                    }
                }, 0);
            }
        },
        true
    );

    document.addEventListener('keydown', (evento) => {
        if (!aberto) {
            return;
        }

        if (evento.key === 'Escape') {
            evento.preventDefault();
            evento.stopPropagation();
            fecharMenuAcoes(true);
            return;
        }

        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(evento.key)) {
            return;
        }

        const painel = aberto.querySelector('[data-menu-acoes-painel]');
        const itens = painel ? itensHabilitados(painel) : [];
        if (itens.length === 0) {
            return;
        }

        evento.preventDefault();
        const atual = itens.indexOf(document.activeElement);
        let proximo = 0;

        if (evento.key === 'ArrowDown') {
            proximo = atual === -1 ? 0 : (atual + 1) % itens.length;
        } else if (evento.key === 'ArrowUp') {
            proximo = atual <= 0 ? itens.length - 1 : atual - 1;
        } else if (evento.key === 'Home') {
            proximo = 0;
        } else if (evento.key === 'End') {
            proximo = itens.length - 1;
        }

        itens[proximo]?.focus();
    });

    window.addEventListener('resize', () => fecharMenuAcoes());
    window.addEventListener(
        'scroll',
        (evento) => {
            if (!aberto) {
                return;
            }
            const painel = aberto.querySelector('[data-menu-acoes-painel]');
            if (!painel || !painel.contains(evento.target)) {
                fecharMenuAcoes();
            }
        },
        true
    );
}

export default function iniciar() {
    registrarOuvintesGlobais();
}
