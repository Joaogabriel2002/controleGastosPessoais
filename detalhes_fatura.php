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
// Join com Categorias e PESSOAS
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
                    <h2 class="text-2xl font-bold text-gray-800">
                        Fatura: <?php echo htmlspecialchars($cardInfo['name']); ?>
                    </h2>
                    <p class="text-gray-500 capitalize"><?php echo $mesExtenso; ?></p>
                </div>
            </div>
            
            <div class="text-right bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase font-bold">Total desta fatura</p>
                <p class="text-2xl font-bold text-red-600">R$ <?php echo number_format($totalFatura, 2, ',', '.'); ?></p>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Compra</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição / Parcelas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quem?</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
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
                            // Lógica para extrair parcelas do texto "(x/y)"
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
                        <tr class="hover:bg-gray-50">
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
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Eu
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                        <?php echo $item['person_name']; ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-800">
                                R$ <?php echo number_format($item['amount'], 2, ',', '.'); ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <form action="src/actions.php" method="POST" onsubmit="return confirm('Pagar adiantado este item usando saldo da conta?');">
                                    <input type="hidden" name="action" value="pay_single_card_item">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    
                                    <div class="flex items-center justify-center gap-1">
                                        <select name="bank_account_id" required class="text-xs border rounded p-1 w-24">
                                            <option value="">Pagar com...</option>
                                            <?php 
                                            // Gambiarra rápida para listar contas aqui sem ferir a arquitetura MVC
                                            $contasRapidas = $pdo->query("SELECT id, name FROM accounts")->fetchAll();
                                            foreach($contasRapidas as $c): ?>
                                                <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-900" title="Adiantar Pagamento">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </form>
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