<?php
// index.php
require_once 'src/config.php';
require_once 'src/dashboard.php';
require_once 'src/faturas.php'; 

// 1. Controle de Datas (Navegação)
$mesAtual = $_GET['mes'] ?? date('m');
$anoAtual = $_GET['ano'] ?? date('Y');

// Cria objeto DateTime para facilitar manipulação
$dataAtualObj = DateTime::createFromFormat('Y-m-d', "$anoAtual-$mesAtual-01");

// Calcula Mês Anterior e Próximo
$prev = clone $dataAtualObj; $prev->modify('-1 month');
$next = clone $dataAtualObj; $next->modify('+1 month');

// Datas Limite para queries SQL
$startDate = $dataAtualObj->format('Y-m-01');
$endDate   = $dataAtualObj->format('Y-m-t');

// 2. Carrega Dados Filtrados
$kpis = getDashboardTotals($pdo, $mesAtual, $anoAtual); 
$faturasAbertas = getOpenInvoices($pdo, $mesAtual, $anoAtual);

// 3. Lista Pendentes (Não Cartão) Filtrada pelo Mês
$sqlPendentes = "
    SELECT t.*, c.name as category_name, a.name as account_name, p.name as person_name
    FROM transactions t 
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN accounts a ON t.account_id = a.id
    LEFT JOIN people p ON t.person_id = p.id
    WHERE t.status = 'pendente' 
      AND t.credit_card_id IS NULL 
      AND t.due_date BETWEEN '$startDate' AND '$endDate'
    ORDER BY t.due_date ASC
";
$pendentes = $pdo->query($sqlPendentes)->fetchAll(PDO::FETCH_ASSOC);

// Nome do mês para exibição (Extenso)
$mesesExtenso = [
    '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
    '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php require 'src/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        
        <div class="flex items-center justify-between bg-white p-4 rounded-lg shadow mb-8">
            <a href="?mes=<?php echo $prev->format('m'); ?>&ano=<?php echo $prev->format('Y'); ?>" 
               class="flex items-center text-gray-600 hover:text-blue-600 font-bold transition">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                <?php echo $mesesExtenso[$prev->format('m')]; ?>
            </a>

            <div class="text-center">
                <h2 class="text-xl font-bold text-gray-800 uppercase tracking-wide">
                    <?php echo $mesesExtenso[$dataAtualObj->format('m')]; ?> <span class="text-blue-600"><?php echo $anoAtual; ?></span>
                </h2>
                <?php if("$anoAtual-$mesAtual" == date('Y-m')): ?>
                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-bold">Mês Atual</span>
                <?php endif; ?>
            </div>

            <a href="?mes=<?php echo $next->format('m'); ?>&ano=<?php echo $next->format('Y'); ?>" 
               class="flex items-center text-gray-600 hover:text-blue-600 font-bold transition">
                <?php echo $mesesExtenso[$next->format('m')]; ?>
                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-10">
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">Saldo em Contas (Hoje)</h3>
                <p class="text-2xl font-bold text-gray-800 mt-1">
                    R$ <?php echo number_format($kpis['saldo_atual'], 2, ',', '.'); ?>
                </p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">A Receber (Mês)</h3>
                <p class="text-2xl font-bold text-green-600 mt-1">R$ <?php echo number_format($kpis['a_receber'], 2, ',', '.'); ?></p>
                <?php if($kpis['a_receber_terceiros'] > 0): ?>
                    <p class="text-[10px] text-gray-500 mt-1">Inclui R$ <?php echo number_format($kpis['a_receber_terceiros'], 2, ',', '.'); ?> de terceiros</p>
                <?php endif; ?>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-red-500">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">Dívidas (Mês)</h3>
                <p class="text-2xl font-bold text-red-600 mt-1">R$ <?php echo number_format($kpis['dividas'], 2, ',', '.'); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">Projeção Final</h3>
                <p class="text-2xl font-bold <?php echo $kpis['diferenca'] >= 0 ? 'text-purple-600' : 'text-red-600'; ?> mt-1">R$ <?php echo number_format($kpis['diferenca'], 2, ',', '.'); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div>
                 <h2 class="text-xl font-bold text-gray-800 mb-4">Contas a Pagar / Receber</h2>
                 <?php include 'src/partials_tabela_pendentes.php'; ?>
            </div>

            <div>
                 <h2 class="text-xl font-bold text-gray-800 mb-4">Cartões de Crédito</h2>
                 <?php include 'src/partials_faturas.php'; ?>
            </div>
        </div>

    </main>
</body>
</html>