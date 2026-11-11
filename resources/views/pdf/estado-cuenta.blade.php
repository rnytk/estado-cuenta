<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 32px; }
        * { box-sizing: border-box; }
        body { color: #000; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { margin-bottom: 14px; min-height: 74px; width: 100%; }
        .logo { float: left; height: auto; max-height: 68px; max-width: 155px; }
        .brand { margin-left: 175px; padding-top: 9px; }
        .brand h1 { color: #000; font-size: 17px; margin: 0 0 3px; }
        .brand h2 { color: #000; font-size: 15px; margin: 0; }
        .details { margin: 6px 0 16px 170px; }
        .details td { padding: 3px 0; }
        .detail-label { color: #000; font-weight: bold; width: 155px; }
        .currency { text-align: center; width: 25px; }
        .amount { width: 150px; }
        .payment-table { border-collapse: collapse; font-size: 9px; width: 100%; }
        .payment-table thead { display: table-header-group; }
        .payment-table th { background: #bcbcbc; border-bottom: 1px solid #000; border-top: 1px solid #000; color: #000; font-size: 9px; padding: 6px 4px; text-align: left; }
        .payment-table th:first-child { border-left: 1px solid #000; }
        .payment-table th:last-child { border-right: 1px solid #000; }
        .payment-table td { padding: 5px 7px; vertical-align: top; }
        .payment-table .number { text-align: right; }
        .payment-table .center { text-align: center; }
        .payment-table tfoot td { background: #bcbcbc; border-bottom: 1px solid #000; border-top: 1px solid #000; color: #000; font-weight: bold; padding: 6px 7px; }
        .payment-table tfoot td:first-child { border-left: 1px solid #000; }
        .payment-table tfoot td:last-child { border-right: 1px solid #000; }
        tr { page-break-inside: avoid; }
        .footer { color: #63666A; font-size: 7px; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <img class="logo" src="{{ public_path('images/logo-katoki.png') }}" alt="Cooperativa Kato-Ki, R.L.">
        <div class="brand">
            <h1>ESTADO DE CUENTA INDIVIDUAL</h1>
            <h2>KATOKI, R.L.</h2>
        </div>
    </div>

    <table class="details">
        <tr><td class="detail-label">Monto:</td><td class="amount" colspan="2">Q{{ number_format($resumen['capital'], 2) }}</td></tr>
        <tr><td class="detail-label">Nombre Cliente(a):</td><td colspan="2">{{ $cliente }}</td></tr>
        <tr><td class="detail-label">Código del Crédito:</td><td colspan="2">{{ $credito }}</td></tr>
        <tr><td class="detail-label">Fecha de Desembolso:</td><td colspan="2">{{ $fecha_desembolso ? \Carbon\Carbon::parse($fecha_desembolso)->format('d/m/Y') : 'No disponible' }}</td></tr>
    </table>

    <table class="payment-table">
        <colgroup>
            <col style="width: 13%;"><col style="width: 7%;"><col style="width: 28%;"><col style="width: 13%;"><col style="width: 13%;"><col style="width: 10%;"><col style="width: 16%;">
        </colgroup>
        <thead><tr><th>FECHA</th><th class="center">CUOTA</th><th>PAGO NÚMERO</th><th class="number">CAPITAL</th><th class="number">INTERÉS</th><th class="number">MORA</th><th class="number">TOTAL</th></tr></thead>
        <tbody>
            @foreach ($pagos as $pago)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($pago['fecha_pago'])->format('d/m/Y') }}</td>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $pago['pago_numero'] }}</td>
                    <td class="number">{{ number_format($pago['capital'], 2) }}</td>
                    <td class="number">{{ number_format($pago['interes'], 2) }}</td>
                    <td class="number">{{ number_format($pago['mora'], 2) }}</td>
                    <td class="number">{{ number_format($pago['total_pago'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td></td><td></td><td></td>
                <td class="number">{{ number_format($resumen['capital'], 2) }}</td>
                <td class="number">{{ number_format($resumen['interes'], 2) }}</td>
                <td class="number">{{ number_format($resumen['mora'], 2) }}</td>
                <td class="number">{{ number_format($resumen['total_pagado'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
    <p class="footer">Documento generado electrónicamente · Emitido el {{ $fecha_emision }}</p>
</body>
</html>
