<div class="space-y-4">
    <?php if (empty($faturasAbertas)): ?>
        <div class="bg-white p-6 rounded-lg shadow text-center text-gray-500">
            Nenhuma fatura em aberto neste mês.
        </div>
    <?php else: ?>
        <?php 
        $hoje = new DateTime(date('Y-m-d'));

        foreach ($faturasAbertas as $fatura): 
            $dataRef = new DateTime($fatura['invoice_date']);
            $diaVenc = $fatura['due_day'] ?? 10;
            $vencimentoReal = new DateTime($dataRef->format('Y-m-') . $diaVenc);
            
            $diff = $hoje->diff($vencimentoReal);
            $dias = (int)$diff->format('%r%a');

            // Lógica de Cores (igual anterior)
            $borderClass = "border-red-500"; 
            $alertBadge = "";
            if ($dias < 0) {
                $borderClass = "border-red-800 bg-red-50"; 
                $alertBadge = "<span class='bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded absolute top-2 right-2'>ATRASADO</span>";
            } elseif ($dias == 0) {
                $borderClass = "border-orange-500 bg-orange-50";
                $alertBadge = "<span class='bg-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded absolute top-2 right-2'>VENCE HOJE</span>";
            } elseif ($dias <= 3) {
                $borderClass = "border-yellow-400";
                $alertBadge = "<span class='bg-yellow-400 text-white text-[10px] font-bold px-2 py-1 rounded absolute top-2 right-2'>Vence em {$dias} dias</span>";
            }
        ?>
            <div class="bg-white rounded-lg shadow border-l-4 <?php echo $borderClass; ?> p-4 relative group hover:shadow-md transition">
                
                <?php echo $alertBadge; ?>

                <div class="flex justify-between items-start mt-2">
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <?php echo $fatura['card_name']; ?>
                        </h3>
                        <p class="text-sm text-gray-500 capitalize"><?php echo strftime('%B/%Y', $dataRef->getTimestamp()); ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            Vence dia <?php echo $vencimentoReal->format('d/m/Y'); ?>
                        </p>
                        
                        <div class="mt-3">
                            <a href="detalhes_fatura.php?card_id=<?php echo $fatura['card_id']; ?>&ref=<?php echo $fatura['invoice_date']; ?>" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Ver itens
                            </a>
                        </div>
                    </div>

                    <div class="text-right mt-4">
                        <p class="text-xl font-bold text-red-600">
                            R$ <?php echo number_format($fatura['total_fatura'], 2, ',', '.'); ?>
                        </p>
                        
                        <?php if ($fatura['total_terceiros'] > 0): ?>
                            <div class="mt-1 mb-2">
                                <span class="text-[10px] text-purple-700 bg-purple-100 px-2 py-1 rounded font-bold" title="Valor a receber de volta">
                                    - R$ <?php echo number_format($fatura['total_terceiros'], 2, ',', '.'); ?> (Terceiros)
                                </span>
                            </div>
                        <?php endif; ?>

                        <p class="text-xs text-gray-500 mb-2 mt-1"><?php echo $fatura['qtd_itens']; ?> lançamentos</p>
                        
                        <form action="src/actions.php" method="POST" onsubmit="return confirm('Pagar fatura inteira?');">
                            <input type="hidden" name="action" value="pay_full_invoice">
                            <input type="hidden" name="card_id" value="<?php echo $fatura['card_id']; ?>">
                            <input type="hidden" name="invoice_date" value="<?php echo $fatura['invoice_date']; ?>">
                            <input type="hidden" name="total_amount" value="<?php echo $fatura['total_fatura']; ?>">
                            <input type="hidden" name="bank_account_id" value="1"> 
                            
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-1 px-3 rounded shadow-sm">
                                Pagar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>