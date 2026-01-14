<?php
// kardex.php
require_once 'src/config.php';

// Filtro de Conta
$contaSelecionada = $_GET['account_id'] ?? '';
$contas = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();

// Query do Histórico
$sql = "
    SELECT h.*, t.description, a.name as account_name
    FROM account_history h
    JOIN accounts a ON h.account_id = a.id
    LEFT JOIN transactions t ON h.transaction_id = t.id
    WHERE 1=1
";

$params = [];
if ($contaSelecionada) {
    $sql .= " AND h.account_id = ?";
    $params[] = $contaSelecionada;
}

$sql .= " ORDER BY h.created_at DESC LIMIT 50"; // Mostra os últimos 50

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historico = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
    
    <?php require 'src/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                📜 Kardex / Extrato
            </h2>
            
            <form class="flex gap-2">
                <select name="account_id" class="border rounded px-3 py-2 bg-white text-sm" onchange="this.form.submit()">
                    <option value="">Todas as Contas</option>
                    <?php foreach($contas as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $contaSelecionada ? 'selected' : ''; ?>>
                            <?php echo $c['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data/Hora</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conta</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição (Origem)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Anterior</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Novo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    <?php if(empty($historico)): ?>
                        <tr><td colspan="6" class="p-6 text-center text-gray-500">Nenhuma movimentação encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach($historico as $h): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-500">
                                    <?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-700">
                                    <?php echo $h['account_name']; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-900">
                                    <?php echo $h['description'] ?? 'Ajuste / Saldo Inicial'; ?>
                                </td>
                                <td class="px-6 py-4 text-right font-bold <?php echo $h['operation_type'] == 'entrada' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $h['operation_type'] == 'entrada' ? '+' : '-'; ?> 
                                    R$ <?php echo number_format($h['amount'], 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 text-right text-gray-500">
                                    R$ <?php echo number_format($h['previous_balance'], 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-gray-800 bg-gray-50">
                                    R$ <?php echo number_format($h['new_balance'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>