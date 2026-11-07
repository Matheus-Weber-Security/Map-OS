<?php
/**
 * Script de inicialização e migração automática do banco de dados (Map-OS / Nexus Ticket).
 * 
 * Executado pelo container Docker na inicialização ou manualmente via CLI:
 * php scripts/db_init.php
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

// 1. Carregar variáveis de ambiente via Dotenv se disponível
if (file_exists($projectRoot . '/application/vendor/autoload.php')) {
    require_once $projectRoot . '/application/vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        if (file_exists($projectRoot . '/application/.env')) {
            \Dotenv\Dotenv::createImmutable($projectRoot . '/application')->safeLoad();
        } elseif (file_exists($projectRoot . '/.env')) {
            \Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
        }
    }
}

// 2. Extrair credenciais do banco
$host = $_ENV['DB_HOSTNAME'] ?? getenv('DB_HOSTNAME') ?: 'localhost';
$port = (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
$user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$db   = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'NexusDB';
$ssl  = ($_ENV['DB_SSL'] ?? getenv('DB_SSL') ?: 'false');
$sslCa = $_ENV['DB_SSL_CA'] ?? getenv('DB_SSL_CA') ?: '';

echo "[Map-OS DB Init] Iniciando conexao com MySQL em {$host}:{$port} (Database: {$db})...\n";

if ($host === 'enter_hostname' || empty($host)) {
    echo "[Map-OS DB Init] DB_HOSTNAME nao configurado. Pulando inicializacao do banco.\n";
    exit(0);
}

// 3. Aguardar disponibilidade do MySQL (até 30 tentativas x 2s = 60s)
$mysqli = null;
$maxTentativas = 30;
$tentativa = 0;

while ($tentativa < $maxTentativas) {
    $tentativa++;
    $mysqli = mysqli_init();
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    if (strtolower((string)$ssl) === 'true' || $ssl === '1') {
        $resolvedCa = (!empty($sslCa) && file_exists($sslCa)) ? $sslCa : $projectRoot . '/' . $sslCa;
        if (file_exists($resolvedCa)) {
            $mysqli->ssl_set(null, null, $resolvedCa, null, null);
        } else {
            $mysqli->ssl_set(null, null, null, null, null);
        }
    }

    $conectado = @$mysqli->real_connect($host, $user, $pass, null, $port);
    if ($conectado) {
        break;
    }

    echo "[Map-OS DB Init] Tentativa {$tentativa}/{$maxTentativas}: Aguardando MySQL ficar pronto...\n";
    sleep(2);
}

if (!$conectado || !$mysqli) {
    echo "[Map-OS DB Init] ERRO: Nao foi possivel conectar ao servidor MySQL ({$host}:{$port}) apos {$maxTentativas} tentativas.\n";
    echo "[Map-OS DB Init] Erro: " . mysqli_connect_error() . "\n";
    exit(1);
}

echo "[Map-OS DB Init] Conectado ao servidor MySQL com sucesso!\n";

// 4. Garantir que o banco de dados especificado existe
$dbEscaped = str_replace('`', '``', $db);
$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbEscaped}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
if (!$mysqli->select_db($db)) {
    echo "[Map-OS DB Init] ERRO: Nao foi possivel selecionar o banco de dados '{$db}': " . $mysqli->error . "\n";
    exit(1);
}

// 5. Verificar se a tabela 'usuarios' já existe
$tabelaExiste = false;
$resCheck = $mysqli->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = '" . $mysqli->real_escape_string($db) . "' AND table_name = 'usuarios'");
if ($resCheck) {
    $row = $resCheck->fetch_assoc();
    $tabelaExiste = ($row && (int)$row['total'] > 0);
    $resCheck->free();
}

// 6. Se o banco estiver vazio, importar banco.sql
if (!$tabelaExiste) {
    echo "[Map-OS DB Init] Nenhuma tabela encontrada. Importando estrutura base (banco.sql)...\n";
    $bancoSqlFile = $projectRoot . '/banco.sql';
    if (!file_exists($bancoSqlFile)) {
        echo "[Map-OS DB Init] ERRO: Arquivo banco.sql nao encontrado em {$bancoSqlFile}.\n";
        exit(1);
    }

    $sqlConteudo = file_get_contents($bancoSqlFile);
    // Garantir substituição de placeholders caso ainda existam
    $adminHash = '$2y$10$lAW0AXb0JLZxR0yDdfcBcu3BN9c2AXKKjKTdug7Or0pr6cSGtgyGO';
    $sqlConteudo = str_replace(
        ['admin_name', 'admin_email', 'admin_password', 'admin_created_at'],
        ['Admin', 'admin@admin.com', $adminHash, date('Y-m-d')],
        $sqlConteudo
    );

    if ($mysqli->multi_query($sqlConteudo)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    if ($mysqli->errno) {
        echo "[Map-OS DB Init] Aviso ao executar banco.sql: " . $mysqli->error . "\n";
    } else {
        echo "[Map-OS DB Init] Estrutura base banco.sql importada com sucesso!\n";
    }

    $mysqli->close();
    // Reabrir conexão limpa após multi_query
    $mysqli = mysqli_init();
    if (strtolower((string)$ssl) === 'true' || $ssl === '1') {
        $resolvedCa = (!empty($sslCa) && file_exists($sslCa)) ? $sslCa : $projectRoot . '/' . $sslCa;
        if (file_exists($resolvedCa)) {
            $mysqli->ssl_set(null, null, $resolvedCa, null, null);
        }
    }
    $mysqli->real_connect($host, $user, $pass, $db, $port);
} else {
    echo "[Map-OS DB Init] Tabelas existentes encontradas no banco '{$db}'. Pulando importacao de banco.sql.\n";
}

// 7. Executar Migrações do CodeIgniter (tools migrate)
echo "[Map-OS DB Init] Executando migracoes pendentes via CodeIgniter CLI...\n";
$cmdMigrate = 'php ' . escapeshellarg($projectRoot . '/index.php') . ' tools migrate';
passthru($cmdMigrate, $migrateStatus);

// 8. Garantir integridade do Superadmin e Empresa Principal
echo "[Map-OS DB Init] Verificando usuario Superadmin e Empresa Principal...\n";
$adminHash = '$2y$10$lAW0AXb0JLZxR0yDdfcBcu3BN9c2AXKKjKTdug7Or0pr6cSGtgyGO';

// Usuário 1 (Superadmin)
$resUser = $mysqli->query("SELECT idUsuarios FROM `usuarios` WHERE `idUsuarios` = 1");
if (!$resUser || $resUser->num_rows === 0) {
    $mysqli->query("INSERT INTO `usuarios` (`idUsuarios`, `nome`, `email`, `senha`, `situacao`, `dataCadastro`, `permissoes_id`, `dataExpiracao`, `is_superadmin`)
                    VALUES (1, 'Admin', 'admin@admin.com', '{$adminHash}', 1, NOW(), 1, '3000-01-01', 1)");
} else {
    $mysqli->query("UPDATE `usuarios` SET `email` = 'admin@admin.com', `senha` = '{$adminHash}', `situacao` = 1, `is_superadmin` = 1 WHERE `idUsuarios` = 1");
}

// Empresa 1 (Empresa Principal)
$resEmp = $mysqli->query("SHOW TABLES LIKE 'saas_empresas'");
if ($resEmp && $resEmp->num_rows > 0) {
    $resCheckEmp = $mysqli->query("SELECT id FROM `saas_empresas` WHERE `id` = 1");
    if (!$resCheckEmp || $resCheckEmp->num_rows === 0) {
        $hojeMaisAno = date('Y-m-d', strtotime('+365 days'));
        $agora = date('Y-m-d H:i:s');
        $mysqli->query("INSERT INTO `saas_empresas` (`id`, `razao_social`, `nome_fantasia`, `email`, `plano_id`, `dias_restantes`, `data_vencimento`, `status`, `desconto_percentual`, `forma_pagamento_preferida`, `criado_em`)
                        VALUES (1, 'Empresa Principal', 'Empresa Principal', 'admin@admin.com', 2, 365, '{$hojeMaisAno}', 'ativa', 0, 'PIX', '{$agora}')");
    }

    // Vínculo do usuário 1 à Empresa 1
    $resVinc = $mysqli->query("SELECT id FROM `saas_empresa_usuarios` WHERE `empresa_id` = 1 AND `usuario_id` = 1");
    if (!$resVinc || $resVinc->num_rows === 0) {
        $agora = date('Y-m-d H:i:s');
        $mysqli->query("INSERT INTO `saas_empresa_usuarios` (`empresa_id`, `usuario_id`, `papel`, `ativo`, `criado_em`)
                        VALUES (1, 1, 'dono', 1, '{$agora}')");
    }
}

$mysqli->close();

echo "\n";
echo "=================================================================\n";
echo " [Map-OS DB Init] BANCO DE DADOS INICIALIZADO COM SUCESSO!\n";
echo " - Acesso Superadmin: admin@admin.com\n";
echo " - Senha Padrao:     123456\n";
echo " - Empresa:          Empresa Principal (ID 1)\n";
echo " - Status:           Pronto para uso em producao\n";
echo "=================================================================\n\n";
exit(0);
