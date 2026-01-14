<?php
// configuracoes.php
require_once 'src/config.php';

// Busca dados existentes
$categorias = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$contas     = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();
$pessoas    = $pdo->query("SELECT * FROM people ORDER BY name")->fetchAll();
$cartoes    = $pdo->query("SELECT * FROM credit_cards ORDER BY name")->fetchAll(); // <--- NOVA BUSCA
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen">
    
    <?php require 'src/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2">Cadastros Gerais</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-blue-600">📂 Categorias</h3>
                <form action="src/actions.php" method="POST" class="mb-6 flex gap-2">
                    <input type="hidden" name="action" value="create_category">
                    <input type="text" name="name" required placeholder="Nova Categoria..." class="border rounded px-2 py-1 w-full text-sm">
                    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">+</button>
                </form>
                <ul class="divide-y text-sm text-gray-600 max-h-40 overflow-y-auto">
                    <?php foreach($categorias as $c): ?>
                        <li class="py-2"><?php echo $c['name']; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-green-600">🏦 Contas / Carteiras</h3>
                <form action="src/actions.php" method="POST" class="mb-6 space-y-2">
                    <input type="hidden" name="action" value="create_account">
                    <input type="text" name="name" required placeholder="Nome do Banco..." class="border rounded px-2 py-1 w-full text-sm">
                    <div class="flex gap-2">
                        <input type="number" step="0.01" name="balance" placeholder="Saldo Inicial (R$)" class="border rounded px-2 py-1 w-full text-sm">
                        <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">Add</button>
                    </div>
                </form>
                <ul class="divide-y text-sm text-gray-600 max-h-40 overflow-y-auto">
                    <?php foreach($contas as $a): ?>
                        <li class="py-2 flex justify-between">
                            <span><?php echo $a['name']; ?></span>
                            <span class="font-bold text-gray-800">R$ <?php echo number_format($a['current_balance'], 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-purple-600">👥 Pessoas / Responsáveis</h3>
                <form action="src/actions.php" method="POST" class="mb-6 flex gap-2">
                    <input type="hidden" name="action" value="create_person">
                    <input type="text" name="name" required placeholder="Nome da Pessoa..." class="border rounded px-2 py-1 w-full text-sm">
                    <button type="submit" class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700">+</button>
                </form>
                <ul class="divide-y text-sm text-gray-600 max-h-40 overflow-y-auto">
                    <?php foreach($pessoas as $p): ?>
                        <li class="py-2 flex justify-between">
                            <?php echo $p['name']; ?>
                            <?php if($p['id'] == 1): ?><span class="text-xs bg-gray-200 px-1 rounded">Padrão</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-red-500">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-red-600">💳 Cartões de Crédito</h3>
                
                <form action="src/actions.php" method="POST" class="mb-6 space-y-2">
                    <input type="hidden" name="action" value="create_credit_card">
                    <input type="text" name="name" required placeholder="Nome (Ex: Nubank)" class="border rounded px-2 py-1 w-full text-sm">
                    <div class="flex gap-2">
                        <input type="number" name="closing_day" min="1" max="31" required placeholder="Dia Fech." class="border rounded px-2 py-1 w-full text-sm" title="Dia de Fechamento">
                        <input type="number" name="due_day" min="1" max="31" required placeholder="Dia Venc." class="border rounded px-2 py-1 w-full text-sm" title="Dia de Vencimento">
                        <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">Add</button>
                    </div>
                </form>

                <ul class="divide-y text-sm text-gray-600 max-h-40 overflow-y-auto">
                    <?php foreach($cartoes as $card): ?>
                        <li class="py-2 flex justify-between">
                            <span><?php echo $card['name']; ?></span>
                            <span class="text-xs text-gray-400">Fecha dia <?php echo $card['closing_day']; ?> / Vence dia <?php echo $card['due_day']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>
    </main>
</body>
</html>