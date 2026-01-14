<div class="space-y-4">
    <?php if (empty($faturasAbertas)): ?>
        <div class="bg-white p-6 rounded-lg shadow text-center text-gray-500">
            Nenhuma fatura em aberto neste mês.
        </div>
    <?php else: ?>
        <?php foreach ($faturasAbertas as $fatura): ?>
            <?php 
                $dataObj = new DateTime($fatura['invoice_date']);
                $diaVenc = $fatura['due_day'] ?? 10; 
                $vencimentoEstimado = $diaVenc . '/' . $dataObj->format('m');
            ?>
            <div class="bg-white rounded-lg shadow border-l-4 border-red-500 p-4 relative group hover:shadow-md transition">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg"><?php echo $fatura['card_name']; ?></h3>
                        <p class="text-sm text-gray-500 capitalize"><?php echo strftime('%B/%Y', $dataObj->getTimestamp()); ?></p>
                        <p class="text-xs text-gray-400 mt-1">Vence dia <?php echo $vencimentoEstimado; ?></p>
                        
                        <div class="mt-3">
                            <a href="detalhes_fatura.php?card_id=<?php echo $fatura['card_id']; ?>&ref=<?php echo $fatura['invoice_date']; ?>" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Ver itens da fatura
                            </a>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-xl font-bold text-red-600">
                            R$ <?php echo number_format($fatura['total_fatura'], 2, ',', '.'); ?>
                        </p>
                        <p class="text-xs text-gray-500 mb-2"><?php echo $fatura['qtd_itens']; ?> lançamentos</p>
                        
                        <button onclick="alert('Funcionalidade de pagar fatura inteira (Front-end)')" 
                                class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-1 px-3 rounded shadow-sm">
                            Pagar
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>