<div class="bg-white shadow rounded-lg overflow-hidden">
    <?php if (empty($pendentes)): ?>
        <div class="p-6 text-center text-gray-500">Nenhuma conta pendente neste período.</div>
    <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desc.</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venc.</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php 
                $hoje = new DateTime(date('Y-m-d')); // Hoje meia-noite

                foreach($pendentes as $item): 
                    $venc = new DateTime($item['due_date']);
                    $diff = $hoje->diff($venc);
                    $dias = (int)$diff->format('%r%a'); // %r mostra sinal (- negativo é passado)

                    // Lógica de Status
                    $statusClass = "text-gray-500"; 
                    $badge = "";

                    if ($dias < 0) {
                        // ATRASADO (Passado)
                        $statusClass = "text-red-600 font-bold";
                        $badge = "<span class='bg-red-100 text-red-800 text-[10px] px-2 py-0.5 rounded-full ml-2'>Atrasado</span>";
                    } elseif ($dias <= 3) {
                        // PRÓXIMO (Hoje ou até 3 dias)
                        $statusClass = "text-orange-600 font-bold";
                        $msg = ($dias == 0) ? "Hoje!" : "Em {$dias} dias";
                        $badge = "<span class='bg-orange-100 text-orange-800 text-[10px] px-2 py-0.5 rounded-full ml-2'>{$msg}</span>";
                    }
                ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="font-medium flex items-center">
                                <?php echo $item['description']; ?>
                                <?php echo $badge; ?> </div>
                            <div class="text-xs text-gray-500">
                                <?php echo $item['category_name']; ?> 
                                <?php if($item['person_name']): ?>
                                    • <span class="text-purple-600"><?php echo $item['person_name']; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm <?php echo $statusClass; ?>">
                            <?php echo date('d/m', strtotime($item['due_date'])); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-bold <?php echo $item['type'] == 'entrada' ? 'text-green-600' : 'text-red-600'; ?>">
                            R$ <?php echo number_format($item['amount'], 2, ',', '.'); ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <form action="src/actions.php" method="POST" onsubmit="return confirm('Confirmar baixa deste item?');">
                                <input type="hidden" name="action" value="baixar_conta">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="text-blue-600 hover:text-blue-900 text-xs font-bold border border-blue-200 hover:bg-blue-50 px-2 py-1 rounded">
                                    Baixar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>