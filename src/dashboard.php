<?php
// src/dashboard.php

function getDashboardTotals($pdo, $mes, $ano) {
    $myId = 1; 

    // Define o intervalo do mês (Do dia 01 ao dia 31)
    $startDate = "$ano-$mes-01";
    $endDate   = date("Y-m-t", strtotime($startDate)); // Último dia do mês

    $totals = [
        'saldo_atual' => 0,
        'a_receber' => 0, 
        'dividas' => 0,   
        'diferenca' => 0,
        'a_receber_terceiros' => 0
    ];

    // 1. SALDO ATUAL (Sempre Total Real, independente do mês)
    $stmt = $pdo->query("SELECT SUM(current_balance) FROM accounts");
    $totals['saldo_atual'] = $stmt->fetchColumn() ?: 0;

    // 2. A RECEBER DO MÊS (Filtrado por Data)
    // Entradas pendentes deste mês
    $entradas = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'entrada' 
          AND status = 'pendente'
          AND due_date BETWEEN '$startDate' AND '$endDate'
    ")->fetchColumn() ?: 0;
    
    // Gastos de Terceiros deste mês (ou faturas deste mês)
    $terceiros = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'saida' 
          AND person_id != $myId 
          AND status != 'reembolsado'
          AND (
            (credit_card_id IS NULL AND due_date BETWEEN '$startDate' AND '$endDate') OR
            (credit_card_id IS NOT NULL AND invoice_date BETWEEN '$startDate' AND '$endDate')
          )
    ")->fetchColumn() ?: 0;
    
    $totals['a_receber'] = $entradas + $terceiros;
    $totals['a_receber_terceiros'] = $terceiros;

    // 3. DÍVIDAS DO MÊS (Filtrado por Data)
    // Contas normais (vencimento no mês) + Cartão (fatura deste mês)
    // Apenas person_id = 1 (Eu)
    
    // A. Contas Normais (Sem cartão)
    $dividasContas = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'saida' 
          AND status = 'pendente' 
          AND person_id = $myId
          AND credit_card_id IS NULL
          AND due_date BETWEEN '$startDate' AND '$endDate'
    ")->fetchColumn() ?: 0;

    // B. Faturas de Cartão (Soma os itens da fatura deste mês)
    // Nota: Aqui somamos os itens individuais para compor a dívida do cartão prevista
    $dividasCartao = $pdo->query("
        SELECT SUM(amount) FROM transactions 
        WHERE type = 'saida'
          AND person_id = $myId
          AND credit_card_id IS NOT NULL
          AND invoice_date BETWEEN '$startDate' AND '$endDate'
          AND status = 'pendente'
    ")->fetchColumn() ?: 0;

    $totals['dividas'] = $dividasContas + $dividasCartao;

    // 4. PROJEÇÃO FINAL DO MÊS
    // Lógica: Se eu tenho X hoje + receber Y este mês - pagar Z este mês, termino com quanto?
    $totals['diferenca'] = ($totals['saldo_atual'] + $totals['a_receber']) - $totals['dividas'];

    return $totals;
}
?>