<?php
// ARQUIVO: src/actions.php
require_once 'config.php';

// =============================================================================
// FUNÇÃO AUXILIAR: REGISTRAR NO KARDEX (HISTÓRICO)
// =============================================================================
function registrarKardex($pdo, $contaId, $tipoOperacao, $valor, $transacaoId = null) {
    // 1. Pega o saldo ATUAL da conta (que será o "Anterior" no histórico)
    $stmt = $pdo->prepare("SELECT current_balance FROM accounts WHERE id = ?");
    $stmt->execute([$contaId]);
    $saldoAtual = (float)$stmt->fetchColumn();

    // 2. Calcula o NOVO saldo
    // Se for entrada, soma. Se for saída, subtrai.
    if ($tipoOperacao == 'entrada') {
        $novoSaldo = $saldoAtual + $valor;
    } else {
        $novoSaldo = $saldoAtual - $valor;
    }

    // 3. Atualiza a tabela de Contas (Saldo Real)
    $stmtUp = $pdo->prepare("UPDATE accounts SET current_balance = ? WHERE id = ?");
    $stmtUp->execute([$novoSaldo, $contaId]);

    // 4. Grava no Histórico (Kardex)
    $sqlHist = "INSERT INTO account_history 
                (account_id, transaction_id, operation_type, amount, previous_balance, new_balance) 
                VALUES (?, ?, ?, ?, ?, ?)";
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute([$contaId, $transacaoId, $tipoOperacao, $valor, $saldoAtual, $novoSaldo]);
}

// =============================================================================
// PROCESSAMENTO DAS AÇÕES
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    $action = $_POST['action'];

    // -------------------------------------------------------------------------
    // 1. CRIAR NOVO LANÇAMENTO (COM PARCELAS E LÓGICA DE DATAS)
    // -------------------------------------------------------------------------
    if ($action == 'create_transaction') {
        $descBase   = $_POST['description'];
        $amount     = $_POST['amount'];      // Valor DA PARCELA
        $type       = $_POST['type'];        // entrada ou saida
        $categoryId = $_POST['category_id'];
        $installments = (int)$_POST['installments']; 
        
        $methodType = $_POST['method_type']; // account ou credit_card
        $initialStatus = $_POST['initial_status'] ?? 'pendente'; 
        $personId   = $_POST['person_id']; 

        // Variáveis específicas
        $cardId = ($methodType == 'credit_card') ? $_POST['credit_card_id'] : null;
        $accountId = ($methodType == 'account') ? $_POST['account_id'] : null;

        try {
            $pdo->beginTransaction(); 

            // --- Lógica de Data Inteligente ---
            $dataVencimentoInicial = '';
            $invoiceDateObj = null;

            if ($methodType == 'credit_card') {
                // Busca dia de vencimento do cartão
                $stmtC = $pdo->prepare("SELECT due_day FROM credit_cards WHERE id = ?");
                $stmtC->execute([$cardId]);
                $diaVenc = $stmtC->fetchColumn();

                // Monta data baseada na fatura escolhida
                $mesFatura = $_POST['invoice_date']; // yyyy-mm
                $dataVencimentoInicial = $mesFatura . '-' . $diaVenc; 
                
                $invoiceDateObj = new DateTime($mesFatura . '-01');
            } else {
                $dataVencimentoInicial = $_POST['due_date_account'];
            }

            $dateObj = new DateTime($dataVencimentoInicial);

            // --- Loop das Parcelas ---
            for ($i = 1; $i <= $installments; $i++) {
                
                $finalDesc = ($installments > 1) ? $descBase . " ($i/$installments)" : $descBase;
                
                $status      = 'pendente'; 
                $paidAt      = null;
                $dbInvoiceDate = null;
                
                if ($methodType == 'credit_card') {
                    $status = 'pendente'; 
                    if ($invoiceDateObj) {
                        $dbInvoiceDate = $invoiceDateObj->format('Y-m-01');
                    }
                } 
                elseif ($methodType == 'account') {
                    if ($initialStatus == 'pago' && $i == 1) {
                        $status = 'pago';
                        $paidAt = $dateObj->format('Y-m-d');
                    }
                }

                // Insere
                $sqlInsert = "INSERT INTO transactions 
                        (description, amount, type, status, category_id, account_id, credit_card_id, invoice_date, due_date, paid_at, person_id)
                        VALUES 
                        (:desc, :amount, :type, :status, :cat, :acc, :card, :inv_date, :due, :paid, :person)";
                
                $stmt = $pdo->prepare($sqlInsert);
                $stmt->execute([
                    'desc'     => $finalDesc,
                    'amount'   => $amount,
                    'type'     => $type,
                    'status'   => $status,
                    'cat'      => $categoryId,
                    'acc'      => $accountId,
                    'card'     => $cardId,
                    'inv_date' => $dbInvoiceDate,
                    'due'      => $dateObj->format('Y-m-d'),
                    'paid'     => $paidAt,
                    'person'   => $personId
                ]);

                $lastId = $pdo->lastInsertId();

                // Kardex (se nasceu pago)
                if ($accountId && $status == 'pago') {
                    registrarKardex($pdo, $accountId, $type, $amount, $lastId);
                }

                // Avança datas
                $dateObj->modify('+1 month');
                if ($invoiceDateObj) {
                    $invoiceDateObj->modify('+1 month');
                }
            }

            $pdo->commit(); 
            header('Location: ../novo_lancamento.php?status=success');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack(); 
            $erroMsg = urlencode($e->getMessage());
            header("Location: ../novo_lancamento.php?status=error&msg=$erroMsg");
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // 2. BAIXAR CONTA COMUM (Água, Luz, etc)
    // -------------------------------------------------------------------------
    elseif ($action == 'baixar_conta') {
        $id = $_POST['id'];
        $data_pagamento = $_POST['payment_date'] ?? date('Y-m-d'); 
        
        try {
            $pdo->beginTransaction();
            $transacao = $pdo->query("SELECT * FROM transactions WHERE id = $id")->fetch();

            if (!$transacao) die('Transação não encontrada');

            $stmt = $pdo->prepare("UPDATE transactions SET status = 'pago', paid_at = ? WHERE id = ?");
            $stmt->execute([$data_pagamento, $id]);
            
            if ($transacao['account_id']) {
                registrarKardex($pdo, $transacao['account_id'], $transacao['type'], $transacao['amount'], $id);
            }

            $pdo->commit();
            header("Location: ../index.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao baixar: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 3. PAGAR FATURA DE CARTÃO (COMPLETA)
    // -------------------------------------------------------------------------
    elseif ($action == 'pay_full_invoice') {
        $card_id         = $_POST['card_id'];
        $invoice_date    = $_POST['invoice_date'];
        $bank_account_id = $_POST['bank_account_id'];
        $total_amount    = $_POST['total_amount'];

        try {
            $pdo->beginTransaction();

            // A. Marca itens como pagos
            $sqlItems = "UPDATE transactions 
                         SET status = 'pago', paid_at = NOW() 
                         WHERE credit_card_id = :card_id 
                         AND invoice_date = :inv_date 
                         AND status = 'pendente'";
            $stmt = $pdo->prepare($sqlItems);
            $stmt->execute(['card_id' => $card_id, 'inv_date' => $invoice_date]);

            // B. Cria registro de SAÍDA (Pagamento)
            $sqlPayment = "INSERT INTO transactions 
                           (description, amount, type, status, account_id, paid_at, is_invoice_payment, person_id, due_date)
                           VALUES 
                           (:desc, :amount, 'saida', 'pago', :acc_id, NOW(), 1, 1, NOW())";
            
            $stmt = $pdo->prepare($sqlPayment);
            $stmt->execute([
                'desc' => "Pagamento Fatura Cartão",
                'amount' => $total_amount,
                'acc_id' => $bank_account_id
            ]);
            
            $idPagamento = $pdo->lastInsertId();

            // Kardex
            registrarKardex($pdo, $bank_account_id, 'saida', $total_amount, $idPagamento);

            $pdo->commit();
            header("Location: ../index.php?msg=fatura_paga");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao pagar fatura: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 4. PAGAR ITEM AVULSO DE CARTÃO (ADIANTAMENTO)
    // -------------------------------------------------------------------------
    elseif ($action == 'pay_single_card_item') {
        $transaction_id  = $_POST['id'];
        $bank_account_id = $_POST['bank_account_id'];
        
        try {
            $pdo->beginTransaction();
            $item = $pdo->query("SELECT amount FROM transactions WHERE id = $transaction_id")->fetch();

            if (!$item) die('Item não encontrado');

            $stmtUpdate = $pdo->prepare("UPDATE transactions SET status = 'pago', paid_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$transaction_id]);

            registrarKardex($pdo, $bank_account_id, 'saida', $item['amount'], $transaction_id);
            
            $pdo->commit();
            header("Location: ../index.php?msg=item_pago");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 5. EDITAR LANÇAMENTO (COM ESTORNO/CORREÇÃO)
    // -------------------------------------------------------------------------
    elseif ($action == 'edit_transaction') {
        $id = $_POST['id'];
        
        $newDesc    = $_POST['description'];
        $newAmount  = (float)$_POST['amount'];
        $newType    = $_POST['type'];
        $newDueDate = $_POST['due_date'];
        $newStatus  = $_POST['status'];
        $newPerson  = $_POST['person_id'];
        $newCat     = $_POST['category_id'];
        
        $methodType = $_POST['method_type'];
        $newAccId   = ($methodType == 'account') ? $_POST['account_id'] : null;
        $newCardId  = ($methodType == 'credit_card') ? $_POST['credit_card_id'] : null;
        $newInvDate = ($methodType == 'credit_card') ? $_POST['invoice_date'] : null;
        
        if ($newInvDate) {
            $d = new DateTime($newInvDate);
            $newInvDate = $d->format('Y-m-01');
        }

        // --- EXCLUIR ---
        if (isset($_POST['delete']) && $_POST['delete'] == '1') {
            try {
                $pdo->beginTransaction();
                $old = $pdo->query("SELECT * FROM transactions WHERE id = $id")->fetch();

                // Estorno se estava pago
                if ($old['status'] == 'pago' && $old['account_id']) {
                    $tipoEstorno = ($old['type'] == 'saida') ? 'entrada' : 'saida';
                    registrarKardex($pdo, $old['account_id'], $tipoEstorno, $old['amount'], $id); 
                }

                $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
                
                $pdo->commit();
                header("Location: ../index.php?msg=deletado"); exit;
            } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
        }

        // --- EDITAR ---
        try {
            $pdo->beginTransaction();

            $old = $pdo->query("SELECT * FROM transactions WHERE id = $id")->fetch();

            // Reverter efeito antigo
            if ($old['status'] == 'pago' && $old['account_id']) {
                $tipoReversao = ($old['type'] == 'saida') ? 'entrada' : 'saida';
                registrarKardex($pdo, $old['account_id'], $tipoReversao, $old['amount'], $id);
            }

            // Atualizar
            $newPaidAt = ($newStatus == 'pago') ? ($old['paid_at'] ?? date('Y-m-d')) : null;

            $sqlUpdate = "UPDATE transactions SET 
                description=?, amount=?, type=?, status=?, category_id=?, 
                account_id=?, credit_card_id=?, invoice_date=?, due_date=?, 
                person_id=?, paid_at=? 
                WHERE id=?";
            
            $stmt = $pdo->prepare($sqlUpdate);
            $stmt->execute([
                $newDesc, $newAmount, $newType, $newStatus, $newCat, 
                $newAccId, $newCardId, $newInvDate, $newDueDate, 
                $newPerson, $newPaidAt, $id
            ]);

            // Aplicar novo efeito
            if ($newStatus == 'pago' && $newAccId) {
                registrarKardex($pdo, $newAccId, $newType, $newAmount, $id);
            }

            $pdo->commit();
            header("Location: ../index.php?msg=editado"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro na edição: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 6. CADASTROS GERAIS (COM KARDEX P/ SALDO INICIAL)
    // -------------------------------------------------------------------------
    elseif ($action == 'create_account') {
        $name = $_POST['name'];
        $balance = (float)($_POST['balance'] ?: 0);
        
        if (!empty($name)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO accounts (name, current_balance) VALUES (?, ?)");
                $stmt->execute([$name, $balance]);
                $accId = $pdo->lastInsertId();

                if ($balance > 0) {
                    // Cria transação para justificar o saldo
                    $stmtTrans = $pdo->prepare("INSERT INTO transactions (description, amount, type, status, account_id, due_date, paid_at, person_id) VALUES (?, ?, 'entrada', 'pago', ?, NOW(), NOW(), 1)");
                    $stmtTrans->execute(["Saldo Inicial", $balance, $accId]);
                    $transId = $pdo->lastInsertId();

                    // Grava no histórico
                    $sqlHist = "INSERT INTO account_history (account_id, transaction_id, operation_type, amount, previous_balance, new_balance) VALUES (?, ?, 'entrada', ?, 0, ?)";
                    $pdo->prepare($sqlHist)->execute([$accId, $transId, $balance, $balance]);
                }
                $pdo->commit();
            } catch (Exception $e) { $pdo->rollBack(); }
        }
        header("Location: ../configuracoes.php"); exit;
    }

    elseif ($action == 'create_category') {
        $name = $_POST['name'];
        if (!empty($name)) $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
        header("Location: ../configuracoes.php"); exit;
    }

    elseif ($action == 'create_person') {
        $name = $_POST['name'];
        if (!empty($name)) $pdo->prepare("INSERT INTO people (name) VALUES (?)")->execute([$name]);
        header("Location: ../configuracoes.php"); exit;
    }

    elseif ($action == 'create_credit_card') {
        $name = $_POST['name'];
        $closing = $_POST['closing_day'];
        $due = $_POST['due_day'];
        if (!empty($name)) $pdo->prepare("INSERT INTO credit_cards (name, closing_day, due_day) VALUES (?, ?, ?)")->execute([$name, $closing, $due]);
        header("Location: ../configuracoes.php"); exit;
    }

    // -------------------------------------------------------------------------
    // 7. ATUALIZAÇÃO RÁPIDA DE FATURA (MODO EDITOR)
    // -------------------------------------------------------------------------
    elseif ($action == 'update_invoice_item') {
        $id = $_POST['id'];
        $amount = str_replace(',', '.', $_POST['amount']); 
        
        $card_id = $_POST['redirect_card'];
        $ref_date = $_POST['redirect_ref'];

        try {
            $stmt = $pdo->prepare("UPDATE transactions SET amount = ? WHERE id = ?");
            $stmt->execute([$amount, $id]);
            header("Location: ../detalhes_fatura.php?card_id=$card_id&ref=$ref_date&msg=atualizado");
            exit;
        } catch (Exception $e) {
            die("Erro ao atualizar: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 8. EXCLUSÃO RÁPIDA DE FATURA (MODO EDITOR)
    // -------------------------------------------------------------------------
    elseif ($action == 'delete_invoice_item') {
        $id = $_POST['id'];
        
        $card_id = $_POST['redirect_card'];
        $ref_date = $_POST['redirect_ref'];

        try {
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: ../detalhes_fatura.php?card_id=$card_id&ref=$ref_date&msg=excluido");
            exit;
        } catch (Exception $e) {
            die("Erro ao excluir: " . $e->getMessage());
        }
    }
    // ... dentro do actions.php ...

    // -------------------------------------------------------------------------
    // 12. RECEBER REEMBOLSO DE TERCEIROS
    // -------------------------------------------------------------------------
    elseif ($action == 'receber_reembolso') {
        $transaction_id = $_POST['id'];
        $account_id     = $_POST['account_id']; // Conta onde o dinheiro entrou

        try {
            $pdo->beginTransaction();

            // 1. Pega dados da dívida original
            $stmt = $pdo->prepare("SELECT amount, description, person_id FROM transactions WHERE id = ?");
            $stmt->execute([$transaction_id]);
            $divida = $stmt->fetch();

            if (!$divida) die("Lançamento não encontrado.");

            // 2. Marca a dívida como 'reembolsado'
            // Isso faz ela sumir da lista de "A Receber" e do Dashboard de terceiros
            $stmtUp = $pdo->prepare("UPDATE transactions SET status = 'reembolsado' WHERE id = ?");
            $stmtUp->execute([$transaction_id]);

            // 3. Adiciona o dinheiro na conta selecionada (Entrada no caixa)
            // Usamos a função registrarKardex que já faz o Update na conta e o Insert no History
            // OBS: Não criamos uma NOVA transação de entrada para não duplicar relatórios, 
            // apenas ajustamos o saldo da conta via Kardex.
            
            // Mas para ficar bonito no extrato, vamos registrar no Kardex com a referência da transação original
            registrarKardex($pdo, $account_id, 'entrada', $divida['amount'], $transaction_id);

            $pdo->commit();
            header("Location: ../terceiros.php?msg=reembolso_ok");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao processar reembolso: " . $e->getMessage());
        }
    }
}
?>