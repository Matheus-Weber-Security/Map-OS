// Moldura do painel da v5 (views/tema/*.php): sidebar recolhível no desktop,
// gaveta (drawer) no celular, troca de modo e de cor do tema e menu do usuário.
//
// Iniciado pelo data-module="layout/shell" do <body>. O estado inicial da
// sidebar e do modo já vem aplicado pelo assets/js/tema.js no <head>; este
// módulo só reage às ações do usuário.

import { post } from '../../lib/http.js';
import { aplicarCor, aplicarModo, CORES, salvarCor, salvarModo } from '../../lib/tema.js';

export const CHAVE_SIDEBAR = 'mapos:sidebar';
export const CHAVE_SECOES = 'mapos:menu-secoes';

export function lerSecoesAbertas() {
    try {
        const val = globalThis.localStorage?.getItem(CHAVE_SECOES);
        return val ? JSON.parse(val) : null;
    } catch {
        return null;
    }
}

export function gravarSecoesAbertas(secoes) {
    try {
        globalThis.localStorage?.setItem(CHAVE_SECOES, JSON.stringify(secoes));
    } catch {
        // localStorage indisponível
    }
}

// Mesmo ponto de quebra do layout.css (lg do Tailwind, 64rem).
export const CONSULTA_DESKTOP = '(min-width: 64rem)';

/**
 * Próximo estado do botão de menu: no desktop recolhe/expande a sidebar, no
 * celular abre/fecha a gaveta.
 */
export function proximoEstado({ desktop, recolhida, gavetaAberta }) {
    if (desktop) {
        return { recolhida: !recolhida, gavetaAberta: false };
    }

    return { recolhida, gavetaAberta: !gavetaAberta };
}

/**
 * O <summary> de grupo da sidebar (Relatórios, Configurações) onde o clique
 * caiu, ou null. Tem de testar o elemento: o <html> também leva data-sidebar
 * quando a sidebar está recolhida, e '[data-sidebar] summary' casaria com o
 * <summary> do menu do usuário (#3025).
 */
export function grupoDaSidebar(alvo, sidebar) {
    const resumo = alvo.closest?.('summary');

    return resumo && sidebar?.contains(resumo) ? resumo : null;
}

function gravar(chave, valor) {
    try {
        if (valor === null) {
            globalThis.localStorage?.removeItem(chave);
        } else {
            globalThis.localStorage?.setItem(chave, valor);
        }
    } catch {
        // localStorage bloqueado: o estado vale só até a próxima página.
    }
}

// Ctrl+K (ou ⌘K no Mac) abre a busca global (DESIGN.md topbar). Shift e Alt
// ficam de fora para não roubar outros atalhos.
export function ehAtalhoBusca(evento) {
    return Boolean(evento.ctrlKey || evento.metaKey) && !evento.altKey && !evento.shiftKey && String(evento.key).toLowerCase() === 'k';
}

// F1 a F4 levam às telas principais (como na v4), sem Ctrl/Alt/Shift/⌘. Só
// valem as teclas que o servidor mandou: telas que o usuário não pode abrir
// não têm atalho. Dentro de um campo de formulário não agem: navegar
// descartaria o que a pessoa está digitando.
export function urlDoAtalho(evento, atalhos) {
    if (evento.ctrlKey || evento.metaKey || evento.altKey || evento.shiftKey) {
        return null;
    }

    const alvo = evento.target;
    if (alvo?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo?.tagName)) {
        return null;
    }

    return Object.hasOwn(atalhos, evento.key) ? atalhos[evento.key] : null;
}

function lerAtalhos(corpo) {
    try {
        return JSON.parse(corpo.querySelector('#layout-atalhos')?.textContent ?? '{}') ?? {};
    } catch {
        return {};
    }
}

/**
 * Grava o modo do usuário no servidor (#2854), para valer em qualquer
 * navegador. Se falhar (rede, sessão), o localStorage já guardou a escolha
 * neste navegador, então a falha não é mostrada.
 */
export function gravarNoServidor(url, modo) {
    if (!url) {
        return Promise.resolve(false);
    }

    return post(url, { modo }).then(() => true, () => false);
}

/**
 * Grava a cor do tema do usuário no servidor (#3032), como gravarNoServidor()
 * faz com o modo. Sem URL ou com falha devolve false: o cache do navegador já
 * guardou a escolha.
 */
export function gravarCorNoServidor(url, cor) {
    if (!url) {
        return Promise.resolve(false);
    }

    return post(url, { cor }).then(() => true, () => false);
}

export default function iniciar(corpo) {
    const html = document.documentElement;
    const desktop = window.matchMedia?.(CONSULTA_DESKTOP);
    const escuro = window.matchMedia?.('(prefers-color-scheme: dark)');
    const sidebar = corpo.querySelector('[data-sidebar]');
    const botoesMenu = corpo.querySelectorAll('[data-sidebar-alternar]');

    const estado = () => ({
        desktop: Boolean(desktop?.matches),
        recolhida: html.dataset.sidebar === 'recolhida',
        gavetaAberta: html.dataset.gaveta === 'aberta',
    });

    const marcarBotoes = () => {
        const { desktop: ehDesktop, recolhida, gavetaAberta } = estado();
        for (const botao of botoesMenu) {
            botao.setAttribute('aria-expanded', String(ehDesktop ? !recolhida : gavetaAberta));
        }
    };

    const categorias = sidebar?.querySelectorAll('.v5-grupo-categoria');

    const sincronizarCategorias = (recolhida) => {
        if (!categorias || categorias.length === 0) return;
        if (recolhida) {
            for (const cat of categorias) {
                cat.open = true;
            }
        } else {
            const abertas = lerSecoesAbertas();
            for (const cat of categorias) {
                const grupo = cat.dataset.grupo;
                if (abertas !== null) {
                    cat.open = abertas.includes(grupo);
                } else {
                    cat.open = cat.hasAttribute('data-aberto-inicial');
                }
            }
        }
    };

    const salvarEstadoCategorias = () => {
        if (!categorias || html.dataset.sidebar === 'recolhida') return;
        const abertas = [];
        for (const cat of categorias) {
            if (cat.open && cat.dataset.grupo) {
                abertas.push(cat.dataset.grupo);
            }
        }
        gravarSecoesAbertas(abertas);
    };

    sincronizarCategorias(html.dataset.sidebar === 'recolhida');

    sidebar?.addEventListener('toggle', (evento) => {
        if (html.dataset.sidebar === 'recolhida') return;
        if (evento.target.classList?.contains('v5-grupo-categoria')) {
            salvarEstadoCategorias();
        }
    }, true);

    const aplicar = (novo, origem = null) => {
        if (novo.recolhida) {
            html.dataset.sidebar = 'recolhida';
        } else {
            delete html.dataset.sidebar;
        }
        gravar(CHAVE_SIDEBAR, novo.recolhida ? 'recolhida' : null);
        sincronizarCategorias(novo.recolhida);

        const estavaAberta = html.dataset.gaveta === 'aberta';
        if (novo.gavetaAberta) {
            html.dataset.gaveta = 'aberta';
            sidebar?.querySelector('a, button, summary')?.focus();
        } else {
            delete html.dataset.gaveta;
            if (estavaAberta) {
                (origem ?? botoesMenu[0])?.focus();
            }
        }

        marcarBotoes();
    };

    // Telas legadas que medem a largura em JS (o calendário do Início) só se
    // ajustam no resize da janela: avisa quando a margem do conteúdo muda.
    const main = corpo.querySelector('.v5-main');
    main?.addEventListener('transitionend', (evento) => {
        if (evento.target === main && evento.propertyName === 'margin-left') {
            window.dispatchEvent(new Event('resize'));
        }
    });

    corpo.addEventListener('click', (evento) => {
        const alvo = evento.target;

        const alternar = alvo.closest?.('[data-sidebar-alternar]');
        if (alternar) {
            aplicar(proximoEstado(estado()), alternar);
            return;
        }

        if (alvo.closest?.('[data-gaveta-fechar]')) {
            aplicar({ ...estado(), gavetaAberta: false });
            return;
        }

        // Com a sidebar recolhida, abrir um subgrupo interno (Configurações)
        // expande a sidebar para os itens aparecerem com o nome.
        const grupo = grupoDaSidebar(alvo, sidebar);
        if (grupo && !grupo.classList.contains('v5-grupo-cabecalho') && estado().desktop && estado().recolhida) {
            aplicar({ ...estado(), recolhida: false });
        }

        const modo = alvo.closest?.('[data-tema-escolher]');
        if (modo) {
            const escolhido = modo.dataset.temaEscolher;
            aplicarModo(html, escolhido, Boolean(escuro?.matches));
            salvarModo(escolhido);
            gravarNoServidor(corpo.dataset.temaUrl, escolhido);
            marcarModo();
        }

        const cor = alvo.closest?.('[data-tema-cor-escolher]');
        if (cor && CORES.includes(cor.dataset.temaCorEscolher)) {
            const escolhida = cor.dataset.temaCorEscolher;
            aplicarCor(html, escolhida);
            // A partir daqui a escolha é da pessoa: o tema.js do próximo
            // carregamento não deixa o cache de outro navegador mandar.
            html.dataset.temaCorUsuario = '1';
            salvarCor(escolhida);
            gravarCorNoServidor(corpo.dataset.temaCorUrl, escolhida);
            marcarCor();
        }

        // Clique fora do menu do usuário fecha o menu.
        for (const menu of corpo.querySelectorAll('details[data-menu-suspenso][open]')) {
            if (!menu.contains(alvo)) {
                menu.open = false;
            }
        }
    });

    const busca = corpo.querySelector('#v5-busca');
    const atalhos = lerAtalhos(corpo);

    const abrirBusca = () => {
        if (busca?.showModal) {
            if (!busca.open) {
                busca.showModal();
            }
            busca.querySelector('[data-busca-campo]')?.focus();
            return;
        }

        // Sem a paleta: foca o campo da topbar (ou o da gaveta, no celular).
        let campo = corpo.querySelector('#v5-pesquisa');
        if (!campo || campo.offsetParent === null) {
            aplicar({ ...estado(), gavetaAberta: true });
            campo = corpo.querySelector('#v5-pesquisa-gaveta');
        }
        campo?.focus();
        campo?.select?.();
    };

    // Campo de busca da topbar e botão de busca do celular abrem a paleta.
    corpo.addEventListener('click', (evento) => {
        if (evento.target.closest?.('[data-busca-abrir], #v5-pesquisa')) {
            evento.preventDefault();
            abrirBusca();
        }
    });

    corpo.addEventListener('keydown', (evento) => {
        if (ehAtalhoBusca(evento)) {
            evento.preventDefault();
            abrirBusca();
            return;
        }

        const destino = urlDoAtalho(evento, atalhos);
        if (destino !== null && !document.querySelector('dialog[open]')) {
            evento.preventDefault();
            window.location.href = destino;
            return;
        }

        if (evento.key !== 'Escape') {
            return;
        }

        const menuAberto = corpo.querySelector('details[data-menu-suspenso][open]');
        if (menuAberto) {
            menuAberto.open = false;
            menuAberto.querySelector('summary')?.focus();
            // Sem isso o atalho Esc da v4 (assets/js/legado/atalhos.js) levaria
            // para a página inicial.
            evento.stopPropagation();
            return;
        }

        if (estado().gavetaAberta) {
            aplicar({ ...estado(), gavetaAberta: false });
            evento.stopPropagation();
        }
    });

    // Passou de celular para desktop com a gaveta aberta: fecha a gaveta.
    desktop?.addEventListener?.('change', () => {
        if (estado().desktop && estado().gavetaAberta) {
            aplicar({ ...estado(), gavetaAberta: false });
        }
        marcarBotoes();
    });

    const marcarModo = () => {
        for (const botao of corpo.querySelectorAll('[data-tema-escolher]')) {
            botao.setAttribute('aria-pressed', String(botao.dataset.temaEscolher === html.dataset.temaModo));
        }
    };

    // A cor escolhida é a do <html> (data-tema); sem o atributo é o padrão.
    const marcarCor = () => {
        for (const botao of corpo.querySelectorAll('[data-tema-cor-escolher]')) {
            botao.setAttribute('aria-pressed', String(botao.dataset.temaCorEscolher === (html.dataset.tema ?? 'padrao')));
        }
    };

    marcarModo();
    marcarCor();
    marcarBotoes();

    try {
        if (sessionStorage.getItem('mapos:banner-carencia-dispensado') === '1') {
            corpo.querySelector('#banner-alerta-carencia')?.remove();
        }
    } catch {
        // sessionStorage bloqueado
    }

    corpo.querySelector('#banner-alerta-carencia')?.addEventListener('dispensado', () => {
        try {
            sessionStorage.setItem('mapos:banner-carencia-dispensado', '1');
        } catch {
            // sessionStorage bloqueado
        }
    });

    if (corpo.dataset.assinaturaBloqueada === '1') {
        const abrirModalBloqueio = () => {
            const modal = document.getElementById('modal-bloqueio-assinatura');
            if (modal && typeof modal.showModal === 'function' && !modal.open) {
                modal.showModal();
            }
        };

        corpo.addEventListener('click', (evento) => {
            const elemento = evento.target.closest?.('a, button');
            if (!elemento) return;

            if (elemento.closest('#modal-bloqueio-assinatura')) {
                return;
            }

            if (
                elemento.closest('[data-sidebar-alternar], [data-busca-abrir], [data-tema-escolher], [data-tema-cor-escolher], details[data-menu-suspenso]') ||
                elemento.hasAttribute('data-sidebar-alternar') ||
                elemento.hasAttribute('data-busca-abrir') ||
                elemento.hasAttribute('data-tema-escolher') ||
                elemento.hasAttribute('data-tema-cor-escolher')
            ) {
                return;
            }

            const href = (elemento.getAttribute('href') || '').toLowerCase();
            const dataModal = (elemento.getAttribute('data-modal-abrir') || '').toLowerCase();

            const rotasSeguras = ['/assinatura', '/login', '/mine', '/imprimir', '/download', '/visualizar', '/pesquisar', '/calendario'];
            if (rotasSeguras.some((rota) => href.includes(rota))) {
                return;
            }

            const acoesMutaveis = ['/adicionar', '/editar', '/excluir', '/faturar', '/cancelar', '/desfaturar', '/anexar', '/cadastrar'];
            const ehAcaoMutavelHref = acoesMutaveis.some((acao) => href.includes(acao));
            const ehAcaoMutavelModal = ['excluir', 'cancelar', 'faturar'].some((termo) => dataModal.includes(termo));
            const ehBotaoSubmit = elemento.type === 'submit' && elemento.closest('form');

            if (ehBotaoSubmit) {
                const action = (elemento.closest('form')?.getAttribute('action') || '').toLowerCase();
                if (action.includes('/pesquisar')) {
                    return;
                }
            }

            if (ehAcaoMutavelHref || ehAcaoMutavelModal || ehBotaoSubmit || dataModal === 'modal-bloqueio-assinatura') {
                evento.preventDefault();
                evento.stopPropagation();
                abrirModalBloqueio();
            }
        }, true);

        corpo.addEventListener('submit', (evento) => {
            const form = evento.target;
            const action = (form.getAttribute('action') || '').toLowerCase();
            if (action.includes('/pesquisar')) {
                return;
            }
            evento.preventDefault();
            evento.stopPropagation();
            abrirModalBloqueio();
        }, true);
    }
}
