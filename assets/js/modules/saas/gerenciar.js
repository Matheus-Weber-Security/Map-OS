// Módulo do painel de Gerenciamento SaaS (views/saas/gerenciar.php).
//
// Renderiza os gráficos de Evolução da Receita e Formas de Pagamento
// utilizando Chart.js a partir do pacote vendor local com suporte a temas dinâmicos.

import { lerJson } from '../../lib/dados.js';
import { carregarScript } from '../../lib/script.js';

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
}
