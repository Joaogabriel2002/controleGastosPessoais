<?php
// src/dashboard.php

function getDashboardTotals($pdo, $mes, $ano) {
    // 1. Definições de Data
    $startDate = "$ano-$mes-01";
    $endDate   = date("Y-m-t", strtotime($startDate));

    $totals = [
        'saldo_atual' => 0,
        'a_receber' => 0, 
        'dividas' => 0,   
        'diferenca' => 0,
        'a_receber_terceiros' => 0
    ];

    // =======================================================
    // 1. SALDO ATUAL (Dinheiro que você tem AGORA)
    // =======================================================
    $stmt = $pdo->query("SELECT SUM(current_balance) FROM accounts");
    $totals['saldo_atual'] = (float)$stmt->fetchColumn();

    // =======================================================
    // 2. A RECEBER (Entradas suas + Reembolsos de terceiros)
    // =======================================================
    
    // A. Suas Entradas (Salário, etc)
    $entradas = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'entrada' 
          AND status = 'pendente'
          AND due_date BETWEEN '$startDate' AND '$endDate'
    ")->fetchColumn() ?: 0;
    
    // B. Reembolsos (O que gastaram no seu cartão/conta)
    // Nota: Aqui mantemos a lógica: se saiu dinheiro para terceiro, ele te deve.
    $terceiros = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'saida' 
          AND person_id != 1  -- Diferente de 'Eu'
          AND status = 'pendente' 
          AND (
            (credit_card_id IS NULL AND due_date BETWEEN '$startDate' AND '$endDate') OR
            (credit_card_id IS NOT NULL AND invoice_date BETWEEN '$startDate' AND '$endDate')
          )
    ")->fetchColumn() ?: 0;
    
    $totals['a_receber'] = $entradas + $terceiros;
    $totals['a_receber_terceiros'] = $terceiros;

    // =======================================================
    // 3. DÍVIDAS (AQUI ESTAVA O ERRO)
    // =======================================================
    // O sistema estava filtrando "AND person_id = 1". 
    // REMOVEMOS ISSO. A dívida é total, independente de quem gastou.
    
    // A. Contas Normais (Boletos) Pendentes
    $dividasContas = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'saida' 
          AND status = 'pendente' 
          AND credit_card_id IS NULL
          AND due_date BETWEEN '$startDate' AND '$endDate'
    ")->fetchColumn() ?: 0;

    // B. Faturas de Cartão Pendentes
    $dividasCartao = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'saida'
          AND credit_card_id IS NOT NULL
          AND invoice_date BETWEEN '$startDate' AND '$endDate'
          AND status = 'pendente'
    ")->fetchColumn() ?: 0;

    $totals['dividas'] = $dividasContas + $dividasCartao;

    // =======================================================
    // 4. PROJEÇÃO (Saldo + Entradas - Saídas Totais)
    // =======================================================
    // Agora a conta fecha: Se a dívida é 3000 e vc tem 1000 a receber de terceiros,
    // o impacto líquido será calculado corretamente aqui.
    $totals['diferenca'] = ($totals['saldo_atual'] + $totals['a_receber']) - $totals['dividas'];

    return $totals;
}


function getDespesasPorCategoria($pdo, $mes, $ano) {
    // Busca soma de saídas agrupadas por categoria neste mês
    $sql = "
        SELECT c.name, SUM(t.amount) as total
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.type = 'saida' 
          AND ((t.credit_card_id IS NULL AND t.due_date BETWEEN '$ano-$mes-01' AND '$ano-$mes-31')
            OR (t.credit_card_id IS NOT NULL AND t.invoice_date = '$ano-$mes-01'))
        GROUP BY c.name
        ORDER BY total DESC
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getEvolucaoSemestral($pdo) {
    // Pega os últimos 6 meses
    $sql = "
        SELECT 
            DATE_FORMAT(due_date, '%Y-%m') as mes_ref,
            SUM(amount) as total
        FROM transactions 
        WHERE type = 'saida' 
          AND due_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mes_ref
        ORDER BY mes_ref ASC
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
?>