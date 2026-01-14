<?php
// novo_lancamento.php
require_once 'src/config.php';

// Buscas no Banco de Dados para preencher os selects
$categorias = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$contas     = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();
$cartoes    = $pdo->query("SELECT * FROM credit_cards ORDER BY name")->fetchAll();
$pessoas    = $pdo->query("SELECT * FROM people ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

    <?php require 'src/header.php'; ?>

    <div class="max-w-lg mx-auto mt-4 px-4">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm flex justify-between items-center mb-6" role="alert">
                <div>
                    <p class="font-bold">Sucesso!</p>
                    <p class="text-sm">Lançamento registrado. Pronto para o próximo.</p>
                </div>
                <a href="novo_lancamento.php" class="text-green-800 hover:text-green-900 font-bold text-xl">&times;</a>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm flex justify-between items-center mb-6" role="alert">
                <div>
                    <p class="font-bold">Erro!</p>
                    <p class="text-sm"><?php echo htmlspecialchars($_GET['msg'] ?? 'Erro desconhecido'); ?></p>
                </div>
                <a href="novo_lancamento.php" class="text-red-800 hover:text-red-900 font-bold text-xl">&times;</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="flex-grow flex items-center justify-center pb-10">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-lg">
            <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">Novo Lançamento</h2>

            <form action="src/actions.php" method="POST">
                <input type="hidden" name="action" value="create_transaction">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Descrição</label>
                    <input type="text" name="description" required placeholder="Ex: Compra TV, Supermercado..." 
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Valor da Parcela (R$)</label>
                        <input type="number" step="0.01" name="amount" required 
                               class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Nº Parcelas</label>
                        <input type="number" name="installments" value="1" min="1" required
                               class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tipo de Operação</label>
                    <div class="flex gap-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="type" value="saida" checked class="mr-2">
                            <span class="text-red-600 font-bold">Saída (Gasto)</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="type" value="entrada" class="mr-2">
                            <span class="text-green-600 font-bold">Entrada (Ganho)</span>
                        </label>
                    </div>
                </div>

                <div class="mb-4 bg-purple-50 p-3 rounded border border-purple-200">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Responsável</label>
                    <div class="relative">
                        <select name="person_id" class="w-full border rounded px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 border-purple-200">
                            <?php foreach($pessoas as $pessoa): ?>
                                <option value="<?php echo $pessoa['id']; ?>" <?php echo ($pessoa['id'] == 1) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pessoa['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="text-[10px] text-gray-500 mt-1">* Útil para controlar gastos de terceiros no seu cartão.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Categoria</label>
                    <div class="relative">
                        <select name="category_id" class="w-full border rounded px-3 py-2 bg-white">
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <div class="mb-4">
                    <p class="text-xs font-bold text-gray-400 uppercase mb-2">Forma de Pagamento</p>
                    
                    <div class="flex gap-4 mb-4">
                        <label class="flex items-center">
                            <input type="radio" name="method_type" value="account" checked onclick="toggleSource('account')" class="mr-2">
                            Conta Bancária / Dinheiro
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="method_type" value="credit_card" onclick="toggleSource('card')" class="mr-2">
                            Cartão de Crédito
                        </label>
                    </div>

                    <div id="account_area">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Data do Pagamento/Vencimento</label>
                            <input type="date" name="due_date_account" value="<?php echo date('Y-m-d'); ?>"
                                   class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <select name="account_id" class="w-full border rounded px-3 py-2 bg-white mb-3">
                            <option value="">Selecione a Conta...</option>
                            <?php foreach($contas as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo $acc['name']; ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="bg-yellow-50 p-3 rounded border border-yellow-200">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Situação</label>
                            <div class="flex gap-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="initial_status" value="pago" class="mr-2 text-green-600">
                                    <span class="text-green-700 font-bold">Já foi Pago</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="initial_status" value="pendente" checked class="mr-2">
                                    <span class="text-gray-600">Agendar</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="card_area" class="hidden">
                        <div class="bg-red-50 p-4 rounded border border-red-100">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Cartão Utilizado</label>
                            <select name="credit_card_id" class="w-full border rounded px-3 py-2 bg-white mb-4">
                                <option value="">Selecione o Cartão...</option>
                                <?php foreach($cartoes as $card): ?>
                                    <option value="<?php echo $card['id']; ?>"><?php echo $card['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label class="block text-xs font-bold text-gray-500 mb-1">Fatura de Competência (Mês/Ano):</label>
                            <input type="month" name="invoice_date" value="<?php echo date('Y-m'); ?>" 
                                   class="w-full border rounded px-3 py-2 bg-white">
                            
                            <p class="text-[10px] text-gray-500 mt-2">
                                * O dia do vencimento será calculado automaticamente conforme o cadastro do cartão.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-8">
                    <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 font-bold">Cancelar</a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 shadow font-bold">Salvar Lançamento</button>
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