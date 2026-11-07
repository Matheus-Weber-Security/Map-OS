// Carregador dos módulos de página da v5.
//
// A view marca um elemento com data-module="pasta/nome" (helper js_module() no
// PHP) e este arquivo importa assets/js/modules/pasta/nome.js, chamando o
// export default com o elemento. Sem bundler: são ES modules nativos.
//
// Incluído no layout com <script type="module">, que só roda depois de o HTML
// ser lido; o teste do readyState cobre os dois casos.

const NOME_VALIDO = /^[a-z0-9_-]+(\/[a-z0-9_-]+)*$/;

export function nomeDeModuloValido(nome) {
    return typeof nome === 'string' && NOME_VALIDO.test(nome);
}

export function iniciarAlternarSenha(raiz = document) {
    raiz.addEventListener('click', (event) => {
        const botao = event.target.closest('[data-alternar-senha]');
        if (!botao) {
            return;
        }

        const container = botao.closest('.relative') || botao.parentElement;
        const input = container ? container.querySelector('input') : null;
        if (!input) {
            return;
        }

        const estaOculto = input.type === 'password';
        input.type = estaOculto ? 'text' : 'password';

        const iconeAberto = botao.querySelector('[data-icone-olho-aberto]');
        const iconeFechado = botao.querySelector('[data-icone-olho-fechado]');

        if (iconeAberto && iconeFechado) {
            iconeAberto.classList.toggle('hidden', estaOculto);
            iconeFechado.classList.toggle('hidden', !estaOculto);
        }

        const rotulo = estaOculto ? 'Ocultar senha' : 'Ver senha';
        botao.setAttribute('aria-label', rotulo);
        botao.setAttribute('title', rotulo);
    });
}

export async function iniciarModulos(raiz = document) {
    const elementos = raiz.querySelectorAll('[data-module]');

    await Promise.all(Array.from(elementos, async (elemento) => {
        const nome = elemento.dataset.module;

        if (!nomeDeModuloValido(nome)) {
            console.error(`[app] nome de módulo inválido: "${nome}"`);
            return;
        }

        if (elemento.dataset.moduleIniciado === 'true') {
            return;
        }
        elemento.dataset.moduleIniciado = 'true';

        try {
            const modulo = await import(new URL(`./modules/${nome}.js`, import.meta.url));
            if (typeof modulo.default === 'function') {
                await modulo.default(elemento);
            }
        } catch (erro) {
            console.error(`[app] falha ao iniciar o módulo "${nome}"`, erro);
        }
    }));
}

if (typeof document !== 'undefined') {
    iniciarAlternarSenha(document);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => iniciarModulos());
    } else {
        iniciarModulos();
    }
}
