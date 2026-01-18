<div class="bg-white shadow rounded-lg overflow-hidden">
    <?php if (empty($pendentes)): ?>
        <div class="p-6 text-center text-gray-500">Nenhuma conta pendente neste período.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desc.</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venc.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $hoje = new DateTime(date('Y-m-d')); 

                    foreach($pendentes as $item): 
                        $venc = new DateTime($item['due_date']);
                        $diff = $hoje->diff($venc);
                        $dias = (int)$diff->format('%r%a'); // %r mostra sinal (- negativo é passado)

                        // Lógica de Status (Cores)
                        $statusClass = "text-gray-500"; 
                        $badge = "";

                        if ($dias < 0) {
                            // ATRASADO
                            $statusClass = "text-red-600 font-bold";
                            $badge = "<span class='bg-red-100 text-red-800 text-[10px] px-2 py-0.5 rounded-full ml-2'>Atrasado</span>";
                        } elseif ($dias <= 3) {
                            // PRÓXIMO
                            $statusClass = "text-orange-600 font-bold";
                            $msg = ($dias == 0) ? "Hoje!" : "Em {$dias} dias";
                            $badge = "<span class='bg-orange-100 text-orange-800 text-[10px] px-2 py-0.5 rounded-full ml-2'>{$msg}</span>";
                        }
                    ?>
                        <tr class="hover:bg-gray-50 transition group">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium flex items-center">
                                    <?php echo $item['description']; ?>
                                    <?php echo $badge; ?>
                                </div>
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

                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-3">
                                    
                                    <form action="src/actions.php" method="POST" onsubmit="return confirm('Confirmar baixa deste item?');">
                                        <input type="hidden" name="action" value="baixar_conta">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50" title="Baixar / Pagar">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </form>

                                    <a href="editar_lancamento.php?id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    <form action="src/actions.php" method="POST" onsubmit="return confirm('Tem certeza que deseja EXCLUIR este lançamento?');">
                                        <input type="hidden" name="action" value="edit_transaction">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="delete" value="1">
                                        
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50" title="Excluir Definitivamente">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>