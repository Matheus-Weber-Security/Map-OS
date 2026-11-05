// Módulo do painel de Gerenciamento SaaS (views/saas/gerenciar.php).
//
// Renderiza os gráficos de Evolução da Receita e Formas de Pagamento
// utilizando Chart.js a partir do pacote vendor local com suporte a temas dinâmicos.

import { lerJson } from '../../lib/dados.js';
import { carregarScript } from '../../lib/script.js';
import { post } from '../../lib/http.js';
import { mostrarToast } from '../../lib/toast.js';

const VENDOR = new URL('../../../vendor/', import.meta.url);

function corDoToken(nome, estilo = getComputedStyle(document.documentElement)) {
    return estilo.getPropertyValue(`--color-${nome}`).trim();
}

function moeda(valor) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(valor) || 0);
}

export default async function iniciar(raiz, { doc = document } = {}) {
    const dados = lerJson('saas-dados', { mrr: 0, pix: 0, manual: 100 }, doc);
    const canvasEvolucao = raiz.querySelector('#chart-evolucao');
    const canvasPagamento = raiz.querySelector('#chart-pagamento');

    if (!canvasEvolucao && !canvasPagamento) {
        return;
    }

    await carregarScript(new URL('chart.js/chart.umd.min.js', VENDOR).href, doc);
    const { Chart } = globalThis;
    const graficos = [];

    const desenhar = () => {
        graficos.splice(0).forEach((grafico) => grafico.destroy());

        const estilo = getComputedStyle(doc.documentElement);
        const cor = (nome) => corDoToken(nome, estilo);
        const cores = {
            texto: cor('muted'),
            grade: cor('border'),
            fundo: cor('surface'),
            primary: cor('primary'),
            info: cor('info'),
            success: cor('success'),
        };

        Chart.defaults.font.family = getComputedStyle(doc.body).fontFamily;

        // 1. Gráfico de Evolução da Receita
        if (canvasEvolucao) {
            const ctxEvolucao = canvasEvolucao.getContext('2d');
            graficos.push(new Chart(ctxEvolucao, {
                type: 'line',
                data: {
                    labels: ['Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov'],
                    datasets: [{
                        label: 'Receita',
                        data: [0, 0, 0, 0, 0, dados.mrr],
                        borderColor: cores.primary || '#f97316',
                        backgroundColor: (cores.primary || '#f97316') + '20',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.25,
                        pointRadius: 4,
                        pointBackgroundColor: cores.primary || '#f97316',
                        pointHoverRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (contexto) => `Receita: ${moeda(contexto.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: cores.grade },
                            ticks: {
                                color: cores.texto,
                                callback: (val) => moeda(val),
                            },
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: cores.texto },
                        },
                    },
                },
            }));
        }

        // 2. Gráfico de Formas de Pagamento
        if (canvasPagamento) {
            const ctxPagamento = canvasPagamento.getContext('2d');
            graficos.push(new Chart(ctxPagamento, {
                type: 'doughnut',
                data: {
                    labels: ['Manual', 'PIX'],
                    datasets: [{
                        data: [dados.manual, dados.pix],
                        backgroundColor: [cores.primary || '#f97316', cores.info || '#0284c7'],
                        borderColor: cores.fundo,
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (contexto) => ` ${contexto.label}: ${contexto.parsed}%`,
                            },
                        },
                    },
                },
            }));
        }
    };

    desenhar();
    new MutationObserver(desenhar).observe(doc.documentElement, { attributes: true, attributeFilter: ['class', 'data-tema-modo'] });

    // Alternar status das formas de pagamento via switch AJAX
    const tabelaFormas = raiz.querySelector('#tabela-formas-pagamento');
    const urlAlternar = raiz.dataset.urlAlternar;

    if (tabelaFormas && urlAlternar) {
        tabelaFormas.addEventListener('change', async (e) => {
            const toggle = e.target.closest('.forma-toggle');
            if (!toggle) return;

            const id = toggle.dataset.id;
            const ativo = toggle.checked ? 1 : 0;
            const linha = toggle.closest('tr');
            const statusCol = linha ? linha.querySelector('.status-col') : null;
            const contadorEl = doc.querySelector('#contador-formas-ativas');
            const bolinhaEl = doc.querySelector('#indicador-bolinha');

            toggle.disabled = true;

            try {
                const res = await post(urlAlternar, { id, ativo });
                const json = await res.json();

                if (json.result) {
                    if (statusCol) {
                        statusCol.innerHTML = ativo
                            ? '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-caption font-semibold bg-success-soft text-success-ink"><span class="size-1.5 rounded-full bg-success"></span>Ativo</span>'
                            : '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-caption font-semibold bg-surface-subtle text-muted"><span class="size-1.5 rounded-full bg-border"></span>Inativo</span>';
                    }

                    // Recalcula total de formas ativas
                    const totalAtivas = raiz.querySelectorAll('.forma-toggle:checked').length;
                    if (contadorEl) {
                        contadorEl.textContent = `${totalAtivas} ${totalAtivas === 1 ? 'forma ativa' : 'formas ativas'}`;
                    }
                    if (bolinhaEl) {
                        bolinhaEl.className = `size-2 rounded-full ${totalAtivas > 0 ? 'bg-success' : 'bg-neutral'}`;
                    }

                    mostrarToast({ mensagem: json.message || 'Status atualizado com sucesso!', variante: 'success' }, doc);
                } else {
                    throw new Error(json.message || 'Falha ao atualizar status.');
                }
            } catch (err) {
                toggle.checked = !ativo;
                mostrarToast({ mensagem: err.message || 'Erro ao comunicar com o servidor.', variante: 'danger' }, doc);
            } finally {
                toggle.disabled = false;
            }
        });
    }
}
