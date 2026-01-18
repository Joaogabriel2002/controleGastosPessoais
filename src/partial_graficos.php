<?php
// Prepara dados para o Chart.js
$cats = getDespesasPorCategoria($pdo, $mesAtual, $anoAtual);
$evo  = getEvolucaoSemestral($pdo);

// Arrays para JS
$catLabels = [];
$catValues = [];
$catColors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#6366F1', '#EC4899', '#8B5CF6'];

foreach($cats as $c) {
    $catLabels[] = $c['name'];
    $catValues[] = $c['total'];
}

$evoLabels = [];
$evoValues = [];
foreach($evo as $e) {
    $dateObj = DateTime::createFromFormat('Y-m', $e['mes_ref']);
    $evoLabels[] = $dateObj ? strftime('%b/%y', $dateObj->getTimestamp()) : $e['mes_ref'];
    $evoValues[] = $e['total'];
}
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
    <div class="bg-white p-4 rounded-lg shadow h-80">
        <h3 class="text-gray-600 font-bold mb-4 text-center">Gastos por Categoria (<?php echo "$mesAtual/$anoAtual"; ?>)</h3>
        <div class="relative h-60 w-full">
            <canvas id="chartCategorias"></canvas>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow h-80">
        <h3 class="text-gray-600 font-bold mb-4 text-center">Evolução dos Últimos 6 Meses</h3>
        <div class="relative h-60 w-full">
            <canvas id="chartEvolucao"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Config Gráfico Categorias (Rosca)
    const ctxCat = document.getElementById('chartCategorias').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($catLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($catValues); ?>,
                backgroundColor: <?php echo json_encode($catColors); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });

    // Config Gráfico Evolução (Barras)
    const ctxEvo = document.getElementById('chartEvolucao').getContext('2d');
    new Chart(ctxEvo, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($evoLabels); ?>,
            datasets: [{
                label: 'Total Gasto (R$)',
                data: <?php echo json_encode($evoValues); ?>,
                backgroundColor: '#3B82F6',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>