<?php
// src/faturas.php

function getOpenInvoices($pdo, $mes, $ano) {
    $dataReferencia = "$ano-$mes-01";

    $sql = "
        SELECT 
            cc.id as card_id,
            cc.name as card_name,
            t.invoice_date,
            SUM(t.amount) as total_fatura,
            -- CALCULA QUANTO DESSA FATURA NÃO É SEU (ID != 1)
            SUM(CASE WHEN t.person_id != 1 THEN t.amount ELSE 0 END) as total_terceiros,
            COUNT(t.id) as qtd_itens,
            cc.due_day
        FROM transactions t
        JOIN credit_cards cc ON t.credit_card_id = cc.id
        WHERE t.type = 'saida'
          AND t.credit_card_id IS NOT NULL
          AND t.status = 'pendente'
          AND t.invoice_date = :dataRef
        GROUP BY cc.id, t.invoice_date, cc.name, cc.due_day
        ORDER BY cc.due_day ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['dataRef' => $dataReferencia]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>