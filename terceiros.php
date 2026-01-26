<?php
// terceiros.php
require_once 'src/config.php';

// 1. Busca TODAS as transações de terceiros pendentes
// Ordenadas por Data para facilitar o agrupamento
$sql = "
    SELECT 
        t.*, 
        p.name as person_name, 
        p.id as person_id,
        c.name as category_name, 
        cc.name as card_name
    FROM transactions t
    JOIN people p ON t.person_id = p.id
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN credit_cards cc ON t.credit_card_id = cc.id
    WHERE t.type = 'saida'
      AND t.person_id != 1         -- Não é você
      AND t.status != 'reembolsado' -- Ainda deve
    ORDER BY t.due_date ASC, p.name ASC
";
$raw_data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 2. Processamento PHP: Agrupar por Mês -> Pessoa
$porMes = [];
$totalGeral = 0;

foreach ($raw_data as $row) {
    // Chave do Mês (ex: 2026-02)
    $mesChave = date('Y-m', strtotime($row['due_date']));
    
    // Inicializa arrays se não existirem
    if (!isset($porMes[$mesChave])) {
        $porMes[$mesChave] = [
            'total_mes' => 0,
            'pessoas' => []
        ];
    }
    
    $pid = $row['person_id'];
    if (!isset($porMes[$mesChave]['pessoas'][$pid])) {
        $porMes[$mesChave]['pessoas'][$pid] = [
            'nome' => $row['person_name'],
            'total_pessoa' => 0,
            'itens' => []
        ];
    }

    // Soma e Adiciona
    $porMes[$mesChave]['total_mes'] += $row['amount'];
    $porMes[$mesChave]['pessoas'][$pid]['total_pessoa'] += $row['amount'];
    $porMes[$mesChave]['pessoas'][$pid]['itens'][] = $row;
    
    $totalGeral += $row['amount'];
}

// Contas para o modal de recebimento
$contasBancarias = $pdo->query("SELECT * FROM accounts ORDER BY name")->fetchAll();

// Auxiliar para datas
$mesesPt = [
    '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
    '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require 'src/head.php'; ?>
    <script>
        function toggleMonth(mesKey) {
            const el = document.getElementById('month-body-' + mesKey);
            const icon = document.getElementById('icon-' + mesKey);
            el.classList.toggle('hidden');
            if(el.classList.contains('hidden')) {
                icon.style.transform = 'rotate(0deg)';
            } else {
                icon.style.transform = 'rotate(180deg)';
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">

    <?php require 'src/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        
        <div class="flex flex-col md:flex-row items-center justify-between mb-8 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Controle de Terceiros
                </h2>
                <p class="text-gray-500 text-sm mt-1">Consolidado por mês de vencimento</p>
            </div>
            
            <div class="bg-purple-600 text-white px-6 py-3 rounded-lg shadow text-center md:text-right w-full md:w-auto">
                <span class="text-xs uppercase font-bold opacity-75 block">Total Geral a Receber</span>
                <span class="text-3xl font-bold">R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></span>
            </div>
        </div>

        <?php if (empty($porMes)): ?>
            <div class="text-center py-20 bg-white rounded-lg shadow">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="text-gray-500 text-lg">Ninguém te deve nada no momento. 🎉</p>
            </div>
        <?php else: ?>
            
            <div class="space-y-6">
                <?php foreach($porMes as $mesKey => $dadosMes): 
                    $ano = substr($mesKey, 0, 4);
                    $mes = substr($mesKey, 5, 2);
                    $mesNome = $mesesPt[$mes] ?? 'Mês ' . $mes;
                    // Verifica se o mês já passou (atrasado)
                    $isPast = $mesKey < date('Y-m');
                    $borderClass = $isPast ? 'border-red-400' : 'border-purple-200';
                    $bgHeader = $isPast ? 'bg-red-50' : 'bg-purple-50';
                    $textHeader = $isPast ? 'text-red-800' : 'text-purple-800';
                ?>
                
                <div class="bg-white rounded-lg shadow overflow-hidden border <?php echo $borderClass; ?>">
                    
                    <div class="p-4 <?php echo $bgHeader; ?> flex justify-between items-center cursor-pointer transition hover:brightness-95" 
                         onclick="toggleMonth('<?php echo $mesKey; ?>')">
                        <div class="flex items-center gap-3">
                            <svg id="icon-<?php echo $mesKey; ?>" class="w-5 h-5 <?php echo $textHeader; ?> transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            <h3 class="text-lg font-bold <?php echo $textHeader; ?> uppercase">
                                <?php echo $mesNome; ?> <span class="text-sm font-normal opacity-75"><?php echo $ano; ?></span>
                            </h3>
                            <?php if($isPast): ?>
                                <span class="bg-red-200 text-red-800 text-[10px] px-2 py-0.5 rounded font-bold">ATRASADO</span>
                            <?php endif; ?>
                        </div>
                        <div class="font-bold text-xl <?php echo $textHeader; ?>">
                            R$ <?php echo number_format($dadosMes['total_mes'], 2, ',', '.'); ?>
                        </div>
                    </div>

                    <div id="month-body-<?php echo $mesKey; ?>" class="">
                        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            
                            <?php foreach($dadosMes['pessoas'] as $pessoa): ?>
                                <div class="bg-white border rounded-lg p-4 hover:shadow-md transition relative">
                                    <div class="flex justify-between items-start mb-3 border-b pb-2">
                                        <div class="font-bold text-gray-700"><?php echo $pessoa['nome']; ?></div>
                                        <div class="font-bold text-purple-600">R$ <?php echo number_format($pessoa['total_pessoa'], 2, ',', '.'); ?></div>
                                    </div>

                                    <ul class="space-y-3">
                                        <?php foreach($pessoa['itens'] as $item): 
                                            $origem = $item['credit_card_id'] ? $item['card_name'] : "Boleto/Conta";
                                        ?>
                                        <li class="text-sm text-gray-600 flex flex-col">
                                            <div class="flex justify-between">
                                                <span><?php echo $item['description']; ?></span>
                                                <span class="font-bold">R$ <?php echo number_format($item['amount'], 2, ',', '.'); ?></span>
                                            </div>
                                            <div class="flex justify-between text-xs text-gray-400 mt-1">
                                                <span>Venc: <?php echo date('d/m', strtotime($item['due_date'])); ?> (<?php echo $origem; ?>)</span>
                                                
                                                <form action="src/actions.php" method="POST" class="inline-flex items-center">
                                                    <input type="hidden" name="action" value="receber_reembolso">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    
                                                    <select name="account_id" required class="text-[10px] border rounded p-0.5 w-24 mr-1 bg-gray-50 h-5">
                                                        <option value="" disabled selected>Entrou em...</option>
                                                        <?php foreach($contasBancarias as $c): ?>
                                                            <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" onclick="return confirm('Confirmar recebimento?')" class="text-green-600 hover:text-green-800" title="Receber">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>

                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>
</body>
</html>