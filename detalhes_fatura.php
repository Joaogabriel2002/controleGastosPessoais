<?php
// detalhes_fatura.php
require_once 'src/config.php';

// Validação básica
if (!isset($_GET['card_id']) || !isset($_GET['ref'])) {
    header('Location: index.php');
    exit;
}

$cardId = $_GET['card_id'];
$refDate = $_GET['ref']; // Formato YYYY-MM-01

// 1. Busca Informações do Cartão
$stmtCard = $pdo->prepare("SELECT * FROM credit_cards WHERE id = ?");
$stmtCard->execute([$cardId]);
$cardInfo = $stmtCard->fetch();

// 2. Busca os Itens da Fatura
$sql = "
    SELECT t.*, c.name as category_name, p.name as person_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN people p ON t.person_id = p.id
    WHERE t.credit_card_id = :cardId
      AND t.invoice_date = :refDate
      AND t.status = 'pendente'
    ORDER BY t.due_date ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['cardId' => $cardId, 'refDate' => $refDate]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcula totais
$totalFatura = 0;
foreach($itens as $i) $totalFatura += $i['amount'];

// Formatação da Data para Título
$dateObj = new DateTime($refDate);
$mesExtenso = strftime('%B de %Y', $dateObj->getTimestamp());
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
    <style>
        /* Classes para alternar visibilidade */
        .edit-mode { display: none; }
        .editor-active .view-mode { display: none; }
        .editor-active .edit-mode { display: block; }
        
        /* Animação suave na engrenagem */
        .gear-icon { transition: transform 0.5s ease; }
        .editor-active .gear-icon { transform: rotate(180deg); color: #2563EB; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php require 'src/header.php'; ?>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <a href="index.php" class="bg-white p-2 rounded-full text-gray-600 hover:text-blue-600 shadow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-gray-800">
                            Fatura: <?php echo htmlspecialchars($cardInfo['name']); ?>
                        </h2>
                        
                        <button onclick="toggleEditor()" title="Ativar Modo Editor" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="gear-icon h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-gray-500 capitalize"><?php echo $mesExtenso; ?></p>
                </div>
            </div>
            
            <div class="text-right bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase font-bold">Total desta fatura</p>
                <p class="text-2xl font-bold text-red-600">R$ <?php echo number_format($totalFatura, 2, ',', '.'); ?></p>
            </div>
        </div>

        <div id="editor-warning" class="hidden mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2 rounded text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            <strong>Modo Editor Ativo:</strong> Altere os valores e clique no botão azul para salvar, ou no vermelho para excluir.
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200" id="tabela-fatura">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quem?</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor (R$)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($itens)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                Nenhum item pendente nesta fatura.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($itens as $item): 
                            $parcelasInfo = "";
                            $restantes = "";
                            if (preg_match('/\((\d+)\/(\d+)\)/', $item['description'], $matches)) {
                                $atual = $matches[1];
                                $total = $matches[2];
                                $faltam = $total - $atual;
                                if ($faltam == 0) {
                                    $restantes = "<span class='text-xs text-green-600 font-bold bg-green-100 px-2 py-0.5 rounded'>Última!</span>";
                                } else {
                                    $restantes = "<span class='text-xs text-gray-500'>Faltam $faltam</span>";
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-50 group">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d/m/Y', strtotime($item['due_date'])); ?>
                            </td>

                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo $item['description']; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo $item['category_name']; ?> 
                                    <?php echo $restantes; ?>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($item['person_id'] == 1): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Eu</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800"><?php echo $item['person_name']; ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-800">
                                <div class="view-mode">
                                    R$ <?php echo number_format($item['amount'], 2, ',', '.'); ?>
                                </div>
                                
                                <div class="edit-mode">
                                    <form action="src/actions.php" method="POST" class="flex items-center justify-end gap-1">
                                        <input type="hidden" name="action" value="update_invoice_item">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="redirect_card" value="<?php echo $cardId; ?>">
                                        <input type="hidden" name="redirect_ref" value="<?php echo $refDate; ?>">
                                        
                                        <input type="number" step="0.01" name="amount" value="<?php echo $item['amount']; ?>" 
                                               class="w-24 border rounded px-2 py-1 text-right text-sm focus:ring-2 focus:ring-blue-500">
                                        
                                        <button type="submit" class="bg-blue-600 text-white p-1 rounded hover:bg-blue-700" title="Salvar Valor">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                
                                <div class="view-mode">
                                    <form action="src/actions.php" method="POST" onsubmit="return confirm('Pagar adiantado este item usando saldo da conta?');">
                                        <input type="hidden" name="action" value="pay_single_card_item">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        
                                        <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <select name="bank_account_id" required class="text-[10px] border rounded p-1 w-20">
                                                <option value="">Pagar com...</option>
                                                <?php 
                                                $contasRapidas = $pdo->query("SELECT id, name FROM accounts")->fetchAll();
                                                foreach($contasRapidas as $c): ?>
                                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="text-green-600 hover:text-green-900" title="Adiantar">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <div class="edit-mode">
                                    <form action="src/actions.php" method="POST" onsubmit="return confirm('Tem certeza que deseja EXCLUIR este item da fatura?');">
                                        <input type="hidden" name="action" value="delete_invoice_item">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="redirect_card" value="<?php echo $cardId; ?>">
                                        <input type="hidden" name="redirect_ref" value="<?php echo $refDate; ?>">

                                        <button type="submit" class="text-red-500 hover:text-red-700 font-bold text-xs flex items-center justify-center gap-1 w-full">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Excluir
                                        </button>
                                    </form>
                                </div>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleEditor() {
            const table = document.getElementById('tabela-fatura');
            const warning = document.getElementById('editor-warning');

            // Alterna a classe na tabela
            if (table.classList.contains('editor-active')) {
                table.classList.remove('editor-active');
                warning.classList.add('hidden');
            } else {
                table.classList.add('editor-active');
                warning.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>