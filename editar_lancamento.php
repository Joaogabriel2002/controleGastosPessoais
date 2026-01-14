<?php
// editar_lancamento.php
require_once 'src/config.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// 1. Busca os dados do lançamento
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$t) {
    die("Lançamento não encontrado.");
}

// 2. Busca listas para os selects
$categorias = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$contas     = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();
$cartoes    = $pdo->query("SELECT * FROM credit_cards ORDER BY name")->fetchAll();
$pessoas    = $pdo->query("SELECT * FROM people ORDER BY name ASC")->fetchAll();

// 3. Define qual método está selecionado (Conta ou Cartão)
$methodType = $t['credit_card_id'] ? 'credit_card' : 'account';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <?php require 'src/header.php'; ?>

    <div class="flex-grow flex items-center justify-center pb-10">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-lg">
            <div class="flex justify-between items-center mb-6 border-b pb-2">
                <h2 class="text-2xl font-bold text-gray-800">Editar Lançamento</h2>
                <span class="text-xs font-mono text-gray-400">ID: <?php echo $t['id']; ?></span>
            </div>

            <form action="src/actions.php" method="POST">
                <input type="hidden" name="action" value="edit_transaction">
                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Descrição</label>
                    <input type="text" name="description" required value="<?php echo htmlspecialchars($t['description']); ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" required value="<?php echo $t['amount']; ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Data Vencimento</label>
                    <input type="date" name="due_date" required value="<?php echo $t['due_date']; ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tipo de Operação</label>
                    <div class="flex gap-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="type" value="saida" <?php echo ($t['type'] == 'saida') ? 'checked' : ''; ?> class="mr-2">
                            <span class="text-red-600 font-bold">Saída (Gasto)</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="type" value="entrada" <?php echo ($t['type'] == 'entrada') ? 'checked' : ''; ?> class="mr-2">
                            <span class="text-green-600 font-bold">Entrada (Ganho)</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4 bg-gray-50 p-3 rounded border">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status Atual</label>
                    <div class="flex gap-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="status" value="pendente" <?php echo ($t['status'] == 'pendente') ? 'checked' : ''; ?> class="mr-2">
                            <span class="text-gray-600">Pendente</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="status" value="pago" <?php echo ($t['status'] == 'pago') ? 'checked' : ''; ?> class="mr-2">
                            <span class="text-green-600 font-bold">Pago / Realizado</span>
                        </label>
                    </div>
                    <p class="text-xs text-red-500 mt-1">* Cuidado: Alterar de Pago para Pendente (ou vice-versa) afetará seu saldo atual.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Quem?</label>
                    <select name="person_id" class="w-full border rounded px-3 py-2 bg-white">
                        <?php foreach($pessoas as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $t['person_id']) ? 'selected' : ''; ?>>
                                <?php echo $p['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Categoria</label>
                    <select name="category_id" class="w-full border rounded px-3 py-2 bg-white">
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $t['category_id']) ? 'selected' : ''; ?>>
                                <?php echo $cat['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr class="my-6 border-gray-200">

                <div class="mb-4">
                    <p class="text-xs font-bold text-gray-400 uppercase mb-2">Forma de Pagamento</p>
                    
                    <div class="flex gap-4 mb-4">
                        <label class="flex items-center">
                            <input type="radio" name="method_type" value="account" onclick="toggleSource('account')" 
                                   <?php echo ($methodType == 'account') ? 'checked' : ''; ?> class="mr-2">
                            Conta Bancária
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="method_type" value="credit_card" onclick="toggleSource('card')" 
                                   <?php echo ($methodType == 'credit_card') ? 'checked' : ''; ?> class="mr-2">
                            Cartão de Crédito
                        </label>
                    </div>

                    <div id="account_area" class="<?php echo ($methodType == 'account') ? '' : 'hidden'; ?>">
                        <select name="account_id" class="w-full border rounded px-3 py-2 bg-white">
                            <option value="">Selecione a Conta...</option>
                            <?php foreach($contas as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>" <?php echo ($acc['id'] == $t['account_id']) ? 'selected' : ''; ?>>
                                    <?php echo $acc['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="card_area" class="<?php echo ($methodType == 'credit_card') ? '' : 'hidden'; ?>">
                        <select name="credit_card_id" class="w-full border rounded px-3 py-2 bg-white mb-2">
                            <option value="">Selecione o Cartão...</option>
                            <?php foreach($cartoes as $card): ?>
                                <option value="<?php echo $card['id']; ?>" <?php echo ($card['id'] == $t['credit_card_id']) ? 'selected' : ''; ?>>
                                    <?php echo $card['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="block text-xs font-bold text-gray-500 mt-2 mb-1">Mês Fatura (yyyy-mm-01):</label>
                        <input type="date" name="invoice_date" value="<?php echo $t['invoice_date']; ?>" class="w-full border rounded px-3 py-2">
                    </div>
                </div>

                <div class="flex justify-between mt-8">
                     <button type="submit" name="delete" value="1" onclick="return confirm('Tem certeza que deseja EXCLUIR? O saldo será estornado se estiver pago.')" class="text-red-500 text-sm underline hover:text-red-700">Excluir Lançamento</button>

                    <div class="flex gap-2">
                        <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 font-bold">Cancelar</a>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 shadow font-bold">Salvar Alterações</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSource(type) {
            const accArea = document.getElementById('account_area');
            const cardArea = document.getElementById('card_area');
            if (type === 'account') {
                accArea.classList.remove('hidden');
                cardArea.classList.add('hidden');
            } else {
                accArea.classList.add('hidden');
                cardArea.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>