document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.auto-calc').forEach(function(el) {
        el.addEventListener('input', function() {
            calcTotals();
        });
    });
});

function calcTotals() {
    const rows = document.querySelectorAll('.sale-row');
    let totalEur = 0;
    let totalBs = 0;
    rows.forEach(function(row) {
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const priceEur = parseFloat(row.querySelector('.price-eur').dataset.value) || 0;
        const priceBs = parseFloat(row.querySelector('.price-bs').dataset.value) || 0;
        const subtotalEur = qty * priceEur;
        const subtotalBs = qty * priceBs;
        row.querySelector('.subtotal-eur').textContent = subtotalEur.toFixed(2);
        row.querySelector('.subtotal-bs').textContent = subtotalBs.toFixed(2);
        totalEur += subtotalEur;
        totalBs += subtotalBs;
    });
    document.getElementById('total-eur').textContent = totalEur.toFixed(2);
    document.getElementById('total-bs').textContent = totalBs.toFixed(2);
}

function confirmDelete(msg) {
    return confirm(msg || '¿Está seguro de eliminar este registro?');
}

function printDiv(divId) {
    const content = document.getElementById(divId).innerHTML;
    const win = window.open('', '', 'width=800,height=600');
    win.document.write('<html><head><title>Imprimir</title>');
    win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    win.document.write('<style>body{padding:20px;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f5f5f5;}</style>');
    win.document.write('</head><body>');
    win.document.write(content);
    win.document.write('</body></html>');
    win.document.close();
    win.print();
}

function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    let html = table.outerHTML;
    const blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.xls';
    a.click();
    URL.revokeObjectURL(url);
}
