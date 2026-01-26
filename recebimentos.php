<?php
// recebimentos.php
require_once 'src/config.php';

// 1. Controle de Datas (Igual ao Index)
$mesAtual = $_GET['mes'] ?? date('m');
$anoAtual = $_GET['ano'] ?? date('Y');

// Validação de data
if (!checkdate($mesAtual, 01, $anoAtual)) {
    $mesAtual = date('m');
    $anoAtual = date('Y');
}

$dataAtualObj = DateTime::createFromFormat('Y-m-d', "$anoAtual-$mesAtual-01");
$prev = clone $dataAtualObj; $prev->modify('-1 month');
$next = clone $dataAtualObj; $next->modify('+1 month');

$startDate = $dataAtualObj->format('Y-m-01');
$endDate   = $dataAtualObj->format('Y-m-t');

// Meses para navegação
$mesesExtenso = [
    '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
    '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];

// 2. BUSCA GRUPO 1: MEUS GANHOS (Salário, Freelas, etc)
// type = entrada E status = pendente
$sqlEntradas = "
    SELECT t.*, c.name as category_name, a.name as account_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN accounts a ON t.account_id = a.id
    WHERE t.type = 'entrada'
      AND t.status = 'pendente'
      AND t.due_date BETWEEN '$startDate' AND '$endDate'
    ORDER BY t.due_date ASC
";
$entradas = $pdo->query($sqlEntradas)->fetchAll(PDO::FETCH_ASSOC);

// 3. BUSCA GRUPO 2: REEMBOLSOS (Terceiros)
// type = saida E person_id != 1 E status != reembolsado
// Nota: Filtramos pela data da despesa (due_date) ou fatura (invoice_date) que cai neste mês
$sqlTerceiros = "
    SELECT t.*, c.name as category_name, p.name as person_name, cc.name as card_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN people p ON t.person_id = p.id
    LEFT JOIN credit_cards cc ON t.credit_card_id = cc.id
    WHERE t.type = 'saida'
      AND t.person_id != 1
      AND t.status = 'pendente'
      AND (
        (t.credit_card_id IS NULL AND t.due_date BETWEEN '$startDate' AND '$endDate') OR
        (t.credit_card_id IS NOT NULL AND t.invoice_date BETWEEN '$startDate' AND '$endDate')
      )
    ORDER BY t.due_date ASC
";
$terceiros = $pdo->query($sqlTerceiros)->fetchAll(PDO::FETCH_ASSOC);

// Contas para o modal de recebimento
$contasBancarias = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();

// Totais
$totalEntradas = array_sum(array_column($entradas, 'amount'));
$totalTerceiros = array_sum(array_column($terceiros, 'amount'));
$totalGeral = $totalEntradas + $totalTerceiros;
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
                    Recebimentos <span class="text-green-600"><?php echo $mesesExtenso[$dataAtualObj->format('m')]; ?> <?php echo $anoAtual; ?></span>
                </h2>
                <span class="text-sm text-gray-500 font-bold">Total Previsto: R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></span>
            </div>
            <a href="?mes=<?php echo $next->format('m'); ?>&ano=<?php echo $next->format('Y'); ?>" 
               class="flex items-center text-gray-600 hover:text-blue-600 font-bold transition">
                <?php echo $mesesExtenso[$next->format('m')]; ?>
                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div>
                <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="bg-green-100 text-green-700 p-1 rounded">💰</span>
                    Minhas Entradas
                    <span class="ml-auto text-sm bg-green-200 text-green-800 px-2 py-1 rounded-full">R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?></span>
                </h3>
                
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <?php if(empty($entradas)): ?>
                        <div class="p-6 text-center text-gray-500 text-sm">Nenhuma entrada prevista para você.</div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($entradas as $item): ?>
                                    <tr class="hover:bg-green-50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-bold text-gray-800"><?php echo $item['description']; ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('d/m', strtotime($item['due_date'])); ?> • <?php echo $item['category_name']; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="text-sm font-bold text-green-600">+ R$ <?php echo number_format($item['amount'], 2, ',', '.'); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo $item['account_name'] ?? 'Conta indefinida'; ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form action="src/actions.php" method="POST">
                                                <input type="hidden" name="action" value="baixar_conta"> <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="text-green-600 hover:text-green-800 bg-green-100 hover:bg-green-200 p-2 rounded-full transition" title="Confirmar Recebimento">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="bg-purple-100 text-purple-700 p-1 rounded">👥</span>
                    A Receber de Terceiros
                    <span class="ml-auto text-sm bg-purple-200 text-purple-800 px-2 py-1 rounded-full">R$ <?php echo number_format($totalTerceiros, 2, ',', '.'); ?></span>
                </h3>

                <div class="bg-white shadow rounded-lg overflow-hidden border-t-4 border-purple-500">
                     <?php if(empty($terceiros)): ?>
                        <div class="p-6 text-center text-gray-500 text-sm">Nenhum reembolso pendente neste mês.</div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($terceiros as $item): 
                                    $origem = $item['credit_card_id'] ? "Fatura Cartão" : "Boleto/Conta";
                                ?>
                                    <tr class="hover:bg-purple-50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-bold text-gray-800"><?php echo $item['description']; ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('d/m', strtotime($item['due_date'])); ?> • 
                                                <span class="text-purple-600 font-bold"><?php echo $item['person_name']; ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="text-sm font-bold text-purple-600">R$ <?php echo number_format($item['amount'], 2, ',', '.'); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo $origem; ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form action="src/actions.php" method="POST" class="flex items-center justify-end gap-1">
                                                <input type="hidden" name="action" value="receber_reembolso">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                
                                                <select name="account_id" required class="text-[10px] border rounded p-1 w-20 bg-gray-50">
                                                    <option value="" disabled selected>Entrou em...</option>
                                                    <?php foreach($contasBancarias as $conta): ?>
                                                        <option value="<?php echo $conta['id']; ?>"><?php echo $conta['name']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <button type="submit" onclick="return confirm('Confirmar recebimento?')" class="text-green-600 hover:text-green-800 bg-green-100 hover:bg-green-200 p-2 rounded-full transition" title="Receber">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>
</body>
</html>