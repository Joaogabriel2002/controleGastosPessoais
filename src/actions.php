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
    // 1. CRIAR NOVO LANÇAMENTO (COM PARCELAS)
    // -------------------------------------------------------------------------
    // ... dentro do if create_transaction ...

    if ($action == 'create_transaction') {
        $descBase   = $_POST['description'];
        $amount     = $_POST['amount'];
        $type       = $_POST['type']; 
        $categoryId = $_POST['category_id'];
        $installments = (int)$_POST['installments']; 
        $methodType = $_POST['method_type']; 
        $initialStatus = $_POST['initial_status'] ?? 'pendente'; 
        $personId   = $_POST['person_id']; 

        // VARIÁVEIS DO MÉTODO
        $cardId = ($methodType == 'credit_card') ? $_POST['credit_card_id'] : null;
        $accountId = ($methodType == 'account') ? $_POST['account_id'] : null;

        try {
            $pdo->beginTransaction(); 

            // =========================================================
            // LÓGICA DE DATA DE VENCIMENTO INTELIGENTE
            // =========================================================
            
            $dataVencimentoInicial = '';
            $invoiceDateObj = null; // Objeto de data da FATURA

            if ($methodType == 'credit_card') {
                // 1. Busca o dia de vencimento do cartão no banco
                $stmtC = $pdo->prepare("SELECT due_day FROM credit_cards WHERE id = ?");
                $stmtC->execute([$cardId]);
                $cartaoInfo = $stmtC->fetch();
                $diaVenc = $cartaoInfo['due_day'];

                // 2. Pega o Mês da Fatura escolhido (yyyy-mm)
                $mesFatura = $_POST['invoice_date']; // ex: 2026-02

                // 3. Monta a Data de Vencimento (yyyy-mm-dd)
                $dataVencimentoInicial = $mesFatura . '-' . $diaVenc; // ex: 2026-02-10
                
                // Configura objeto para loop de parcelas (Fatura)
                $invoiceDateObj = new DateTime($mesFatura . '-01');

            } else {
                // Se for conta, usa o input normal
                $dataVencimentoInicial = $_POST['due_date_account'];
            }

            // Cria o objeto para controlar o loop de parcelas (Vencimento Real)
            $dateObj = new DateTime($dataVencimentoInicial);


            // =========================================================
            // LOOP DAS PARCELAS
            // =========================================================
            for ($i = 1; $i <= $installments; $i++) {
                
                $finalDesc = ($installments > 1) ? $descBase . " ($i/$installments)" : $descBase;
                
                $status = 'pendente'; 
                $paidAt = null;
                $dbInvoiceDate = null;
                
                if ($methodType == 'credit_card') {
                    $status = 'pendente'; 
                    // Salva o mês de referência da fatura (Dia 01)
                    if ($invoiceDateObj) {
                        $dbInvoiceDate = $invoiceDateObj->format('Y-m-01');
                    }
                } 
                elseif ($methodType == 'account') {
                    // Se usuário marcou "Pago", apenas a 1ª parcela nasce paga
                    if ($initialStatus == 'pago' && $i == 1) {
                        $status = 'pago';
                        $paidAt = $dateObj->format('Y-m-d');
                    }
                }

                // INSERE
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
                    'due'      => $dateObj->format('Y-m-d'), // A data calculada
                    'paid'     => $paidAt,
                    'person'   => $personId
                ]);

                $lastId = $pdo->lastInsertId();

                // KARDEX (Se nasceu pago)
                if ($accountId && $status == 'pago') {
                    registrarKardex($pdo, $accountId, $type, $amount, $lastId);
                }

                // AVANÇA DATAS PARA PRÓXIMA PARCELA
                $dateObj->modify('+1 month'); // Avança vencimento
                if ($invoiceDateObj) {
                    $invoiceDateObj->modify('+1 month'); // Avança mês da fatura
                }
            }

            $pdo->commit(); 
            header('Location: ../novo_lancamento.php?status=success');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack(); 
            
            // MUDANÇA AQUI: Volta para o novo_lancamento com mensagem de erro
            // Usamos urlencode para garantir que caracteres especiais não quebrem a URL
            $erroMsg = urlencode($e->getMessage());
            header("Location: ../novo_lancamento.php?status=error&msg=$erroMsg");
            exit;
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

            // A. Marca todos os itens daquela fatura como PAGOS
            $sqlItems = "UPDATE transactions 
                         SET status = 'pago', paid_at = NOW() 
                         WHERE credit_card_id = :card_id 
                         AND invoice_date = :inv_date 
                         AND status = 'pendente'";
            $stmt = $pdo->prepare($sqlItems);
            $stmt->execute(['card_id' => $card_id, 'inv_date' => $invoice_date]);

            // B. Cria um registro de SAÍDA na conta bancária (o pagamento em si)
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

            // >>> KARDEX: Debita da conta bancária <<<
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
            
            // Pega o valor do item
            $item = $pdo->query("SELECT amount FROM transactions WHERE id = $transaction_id")->fetch();

            if (!$item) die('Item não encontrado');

            // Marca o item como pago (na data de hoje)
            $stmtUpdate = $pdo->prepare("UPDATE transactions SET status = 'pago', paid_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$transaction_id]);

            // >>> KARDEX: Debita da conta bancária escolhida <<<
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
    // 5. EDITAR LANÇAMENTO (COM CORREÇÃO DE SALDO / ESTORNO)
    // -------------------------------------------------------------------------
    elseif ($action == 'edit_transaction') {
        $id = $_POST['id'];
        
        // Dados do Formulário
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
        
        // Formata data fatura (yyyy-mm-01)
        if ($newInvDate) {
            $d = new DateTime($newInvDate);
            $newInvDate = $d->format('Y-m-01');
        }

        // --- CASO 1: EXCLUIR ---
        if (isset($_POST['delete']) && $_POST['delete'] == '1') {
            try {
                $pdo->beginTransaction();
                $old = $pdo->query("SELECT * FROM transactions WHERE id = $id")->fetch();

                // Se estava pago e afetava conta, faz o ESTORNO
                if ($old['status'] == 'pago' && $old['account_id']) {
                    // Inverte a lógica: Se era saída, entra dinheiro. Se entrada, sai.
                    $tipoEstorno = ($old['type'] == 'saida') ? 'entrada' : 'saida';
                    registrarKardex($pdo, $old['account_id'], $tipoEstorno, $old['amount'], $id); 
                }

                $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
                
                $pdo->commit();
                header("Location: ../index.php?msg=deletado"); exit;
            } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
        }

        // --- CASO 2: EDITAR ---
        try {
            $pdo->beginTransaction();

            // 1. Pega dados ANTIGOS
            $old = $pdo->query("SELECT * FROM transactions WHERE id = $id")->fetch();

            // 2. PASSO A: REVERTER O EFEITO ANTIGO (Se estava pago)
            if ($old['status'] == 'pago' && $old['account_id']) {
                $tipoReversao = ($old['type'] == 'saida') ? 'entrada' : 'saida';
                registrarKardex($pdo, $old['account_id'], $tipoReversao, $old['amount'], $id);
            }

            // 3. PASSO B: ATUALIZAR NO BANCO
            // Define data de pagamento
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

            // 4. PASSO C: APLICAR O NOVO EFEITO (Se ficou pago)
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
    // 6. CADASTROS GERAIS
    // -------------------------------------------------------------------------
    
    // CRIAR CATEGORIA
    elseif ($action == 'create_category') {
        $name = $_POST['name'];
        if (!empty($name)) {
            $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
        }
        header("Location: ../configuracoes.php"); exit;
    }

    // CRIAR PESSOA
    elseif ($action == 'create_person') {
        $name = $_POST['name'];
        if (!empty($name)) {
            $pdo->prepare("INSERT INTO people (name) VALUES (?)")->execute([$name]);
        }
        header("Location: ../configuracoes.php"); exit;
    }

    // CRIAR CONTA BANCÁRIA (COM KARDEX DO SALDO INICIAL)
    elseif ($action == 'create_account') {
        $name = $_POST['name'];
        $balance = (float)($_POST['balance'] ?: 0);
        
        if (!empty($name)) {
            try {
                $pdo->beginTransaction();

                // Cria a conta
                $stmt = $pdo->prepare("INSERT INTO accounts (name, current_balance) VALUES (?, ?)");
                $stmt->execute([$name, $balance]);
                $accId = $pdo->lastInsertId();

                // Se tiver saldo inicial > 0, cria uma transação de Ajuste para ficar registrado no Kardex
                if ($balance > 0) {
                    // Cria transação para justificar o saldo
                    $stmtTrans = $pdo->prepare("INSERT INTO transactions (description, amount, type, status, account_id, due_date, paid_at, person_id) VALUES (?, ?, 'entrada', 'pago', ?, NOW(), NOW(), 1)");
                    $stmtTrans->execute(["Saldo Inicial", $balance, $accId]);
                    $transId = $pdo->lastInsertId();

                    // Grava no histórico (Saldo anterior 0 -> Novo saldo X)
                    $sqlHist = "INSERT INTO account_history (account_id, transaction_id, operation_type, amount, previous_balance, new_balance) VALUES (?, ?, 'entrada', ?, 0, ?)";
                    $pdo->prepare($sqlHist)->execute([$accId, $transId, $balance, $balance]);
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
        header("Location: ../configuracoes.php"); exit;
    }

    // CRIAR CARTÃO DE CRÉDITO
    elseif ($action == 'create_credit_card') {
        $name = $_POST['name'];
        $closing = $_POST['closing_day'];
        $due = $_POST['due_day'];
        if (!empty($name)) {
            $pdo->prepare("INSERT INTO credit_cards (name, closing_day, due_day) VALUES (?, ?, ?)")->execute([$name, $closing, $due]);
        }
        header("Location: ../configuracoes.php"); exit;
    }
}
?>