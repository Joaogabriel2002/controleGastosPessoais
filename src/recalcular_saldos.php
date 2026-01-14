<?php
// src/recalcular_saldos.php
require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'head.php'; ?>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow">
        <h2 class="text-2xl font-bold mb-4">Auditoria e Correção de Saldos</h2>
        <ul class="space-y-4">

<?php
try {
    $pdo->beginTransaction();

    // 1. Pega todas as contas
    $contas = $pdo->query("SELECT id, name, current_balance FROM accounts")->fetchAll();

    foreach ($contas as $conta) {
        $id = $conta['id'];
        $saldoNoBanco = (float)$conta['current_balance'];

        // 2. Soma ENTRADAS PAGAS (Baseado apenas em transações)
        $stmtEnt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE account_id = ? AND type = 'entrada' AND status = 'pago'");
        $stmtEnt->execute([$id]);
        $entradas = (float)$stmtEnt->fetchColumn();

        // 3. Soma SAÍDAS PAGAS
        $stmtSai = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE account_id = ? AND type = 'saida' AND status = 'pago'");
        $stmtSai->execute([$id]);
        $saidas = (float)$stmtSai->fetchColumn();

        // 4. Saldo Calculado apenas pelas transações
        $saldoTransacoes = $entradas - $saidas;

        // 5. Verifica a Diferença (O "Saldo Inicial" que não foi registrado)
        // Se o banco diz 1000, mas as transações somam 0, a diferença é 1000.
        $diferenca = $saldoNoBanco - $saldoTransacoes;

        // Formata para exibição
        $diferencaFmt = number_format($diferenca, 2, ',', '.');
        $saldoBancoFmt = number_format($saldoNoBanco, 2, ',', '.');
        $saldoCalcFmt = number_format($saldoTransacoes, 2, ',', '.');

        echo "<li class='border-b pb-2'>";
        echo "<strong>{$conta['name']}</strong><br>";
        echo "<span class='text-sm text-gray-500'>Saldo no Banco: R$ $saldoBancoFmt | Soma Transações: R$ $saldoCalcFmt</span><br>";

        // SE TIVER DIFERENÇA (Saldo Inicial não registrado ou erro manual)
        // Vamos criar uma transação para zerar essa diferença e oficializar o saldo.
        if (abs($diferenca) > 0.01) {
            
            $tipoAjuste = ($diferenca > 0) ? 'entrada' : 'saida';
            $valorAjuste = abs($diferenca);

            // Cria a transação de ajuste automaticamente
            $sqlAjuste = "INSERT INTO transactions 
                          (description, amount, type, status, account_id, category_id, paid_at, person_id) 
                          VALUES 
                          (:desc, :amount, :type, 'pago', :acc, NULL, NOW(), 1)";
            
            $stmtAj = $pdo->prepare($sqlAjuste);
            $stmtAj->execute([
                'desc'   => "Saldo Inicial / Ajuste Automático",
                'amount' => $valorAjuste,
                'type'   => $tipoAjuste,
                'acc'    => $id
            ]);

            // Grava no Kardex também para ficar bonito
            // Precisamos do ID que acabou de ser criado
            $idTransacao = $pdo->lastInsertId();
            
            // Registra no Kardex (Considerando que o saldo anterior era o saldo das transações)
            // Novo saldo = Saldo Transações + Diferença = Saldo No Banco (Correto)
            $sqlHist = "INSERT INTO account_history 
                        (account_id, transaction_id, operation_type, amount, previous_balance, new_balance) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmtHist = $pdo->prepare($sqlHist);
            $stmtHist->execute([$id, $idTransacao, $tipoAjuste, $valorAjuste, $saldoTransacoes, $saldoNoBanco]);

            echo "<span class='text-green-600 font-bold'>✓ Corrigido: Criada transação de '$tipoAjuste' de R$ " . number_format($valorAjuste, 2, ',', '.') . " para oficializar o saldo inicial.</span>";
        
        } else {
            echo "<span class='text-blue-600 font-bold'>✓ Tudo certo. O saldo bate com as transações.</span>";
        }
        echo "</li>";
    }

    $pdo->commit();
    echo "</ul>";
    echo "<div class='mt-6'><a href='../index.php' class='bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700'>Voltar para o Início</a></div>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p class='text-red-600 font-bold'>Erro: " . $e->getMessage() . "</p>";
}
?>
    </div>
</body>
</html>