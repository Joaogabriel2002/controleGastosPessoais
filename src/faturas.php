<?php
// src/faturas.php

function getOpenInvoices($pdo, $mes, $ano) {
    // 1. Monta a string da data de referência (ex: "2026-02-01")
    // O sistema salva faturas sempre no dia 01 do mês de referência
    $dataReferencia = "$ano-$mes-01";

    $sql = "
        SELECT 
            cc.id as card_id,
            cc.name as card_name,
            t.invoice_date,
            SUM(t.amount) as total_fatura,
            COUNT(t.id) as qtd_itens,
            cc.due_day
        FROM transactions t
        JOIN credit_cards cc ON t.credit_card_id = cc.id
        WHERE t.type = 'saida'
          AND t.credit_card_id IS NOT NULL
          AND t.status = 'pendente'       -- Só mostra faturas não pagas
          AND t.invoice_date = :dataRef   -- <--- O PULO DO GATO: Filtra o mês exato
        GROUP BY cc.id, t.invoice_date, cc.name, cc.due_day
        ORDER BY cc.due_day ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['dataRef' => $dataReferencia]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>