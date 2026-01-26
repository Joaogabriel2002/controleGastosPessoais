<?php
// index.php
require_once 'src/config.php';
require_once 'src/dashboard.php';
require_once 'src/faturas.php'; 

// =============================================================================
// 1. CONTROLE DE DATAS E NAVEGAÇÃO
// =============================================================================
$mesAtual = $_GET['mes'] ?? date('m');
$anoAtual = $_GET['ano'] ?? date('Y');

// Validação simples para evitar erro se usuário digitar bobagem na URL
if (!checkdate($mesAtual, 01, $anoAtual)) {
    $mesAtual = date('m');
    $anoAtual = date('Y');
}

$dataAtualObj = DateTime::createFromFormat('Y-m-d', "$anoAtual-$mesAtual-01");

// Navegação Anterior/Próximo
$prev = clone $dataAtualObj; $prev->modify('-1 month');
$next = clone $dataAtualObj; $next->modify('+1 month');

// Datas Limite para consultas SQL
$startDate = $dataAtualObj->format('Y-m-01');
$endDate   = $dataAtualObj->format('Y-m-t');

// =============================================================================
// 2. CARREGAMENTO DE DADOS
// =============================================================================

// A. Totais dos Cards (KPIs)
$kpis = getDashboardTotals($pdo, $mesAtual, $anoAtual); 

// B. Faturas Abertas do Mês
$faturasAbertas = getOpenInvoices($pdo, $mesAtual, $anoAtual);

// C. Lista de Contas Pendentes (Boletos, Água, Luz, Pix Agendado)
// Exclui o que é cartão de crédito, pois já aparece na lista de faturas
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

// Array auxiliar para nomes dos meses
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
<body class="bg-gray-100 min-h-screen pb-20">

    <?php require 'src/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded shadow relative">
                <span class="block sm:inline">
                    <?php 
                        if($_GET['msg']=='fatura_paga') echo "Fatura paga com sucesso!";
                        elseif($_GET['msg']=='item_pago') echo "Item adiantado com sucesso!";
                        elseif($_GET['msg']=='editado') echo "Lançamento editado com sucesso!";
                        elseif($_GET['msg']=='deletado') echo "Lançamento excluído.";
                    ?>
                </span>
                <a href="index.php" class="absolute top-0 bottom-0 right-0 px-4 py-3 font-bold">&times;</a>
            </div>
        <?php endif; ?>

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
            
            <a href="recebimentos.php?mes=<?php echo $mesAtual; ?>&ano=<?php echo $anoAtual; ?>" class="block transition transform hover:scale-105">
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500 h-full">
                    <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">A Receber (Mês)</h3>
                    <p class="text-2xl font-bold text-green-600 mt-1">R$ <?php echo number_format($kpis['a_receber'], 2, ',', '.'); ?></p>
                    <?php if($kpis['a_receber_terceiros'] > 0): ?>
                        <p class="text-[10px] text-gray-500 mt-1">Inclui R$ <?php echo number_format($kpis['a_receber_terceiros'], 2, ',', '.'); ?> de terceiros</p>
                    <?php endif; ?>
                    <p class="text-xs text-blue-500 mt-2 font-medium">Ver detalhes &rarr;</p>
                </div>
            </a>
            
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-red-500">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">Dívidas (Mês)</h3>
                <p class="text-2xl font-bold text-red-600 mt-1">R$ <?php echo number_format($kpis['dividas'], 2, ',', '.'); ?></p>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider">Projeção Final</h3>
                <p class="text-2xl font-bold <?php echo $kpis['diferenca'] >= 0 ? 'text-purple-600' : 'text-red-600'; ?> mt-1">R$ <?php echo number_format($kpis['diferenca'], 2, ',', '.'); ?></p>
            </div>
        </div>

        <?php if(file_exists('src/partials_graficos.php')) include 'src/partials_graficos.php'; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
            
            <div>
                 <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Contas a Pagar
                 </h2>
                 <?php include 'src/partials_tabela_pendentes.php'; ?>
            </div>

            <div>
                 <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    Cartões de Crédito
                 </h2>
                 <?php include 'src/partials_faturas.php'; ?>
            </div>
        </div>

    </main>

    <a href="novo_lancamento.php" class="fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition transform hover:scale-105 flex items-center gap-2 z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span class="font-bold pr-2">Novo</span>
    </a>

</body>
</html>