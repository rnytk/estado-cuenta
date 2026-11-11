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
        .details { margin: 6px 0 16px; width: 100%; }
        .details > tbody > tr > td { padding: 0; vertical-align: top; width: 50%; }
        .details-column { width: 100%; }
        .details-column td { padding: 3px 0; }
        .detail-label { color: #000; font-weight: bold; width: 130px; }
        .statement-table { border-collapse: collapse; font-size: 8px; width: 100%; }
        .statement-table thead { display: table-header-group; }
        .statement-table th { background: #bcbcbc; border-bottom: 1px solid #000; border-top: 1px solid #000; color: #000; font-size: 8px; padding: 6px 4px; text-align: left; }
        .statement-table th:first-child { border-left: 1px solid #000; }
        .statement-table th:last-child { border-right: 1px solid #000; }
        .statement-table td { padding: 5px 6px; vertical-align: top; }
        .statement-table .number { text-align: right; }
        .statement-table tfoot td { background: #bcbcbc; border-bottom: 1px solid #000; border-top: 1px solid #000; color: #000; font-weight: bold; padding: 6px; }
        .statement-table tfoot td:first-child { border-left: 1px solid #000; }
        .statement-table tfoot td:last-child { border-right: 1px solid #000; }
        tr { page-break-inside: avoid; }
        .footer { color: #63666A; font-size: 7px; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <img class="logo" src="{{ public_path('images/logo-katoki.png') }}" alt="Cooperativa Kato-Ki, R.L.">
        <div class="brand">
            <h1>ESTADO DE CUENTA DE AHORRO</h1>
            <h2>KATOKI, R.L.</h2>
        </div>
    </div>

    <table class="details">
        <tr>
            <td>
                <table class="details-column">
                    <tr><td class="detail-label">Nombre Cliente(a):</td><td>{{ $asociado }}</td></tr>
                    <tr><td class="detail-label">DPI:</td><td>{{ $dpi }}</td></tr>
                    <tr><td class="detail-label">Dirección:</td><td>{{ $direccion }}</td></tr>
                </table>
            </td>
            <td>
                <table class="details-column">
                    <tr><td class="detail-label">Cuenta de Ahorro:</td><td>{{ $cuenta }}</td></tr>
                    <tr><td class="detail-label">Movimientos:</td><td>{{ $resumen['cantidad_movimientos'] }}</td></tr>
                    <tr>
                        <td class="detail-label">Periodo:</td>
                        <td>
                            @if ($fecha_desde && $fecha_hasta)
                                {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
                            @else
                                Todos los movimientos
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="statement-table">
        <colgroup>
            <col style="width: 12%;"><col style="width: 9%;"><col style="width: 15%;"><col style="width: 15%;"><col style="width: 24%;"><col style="width: 12%;"><col style="width: 13%;">
        </colgroup>
        <thead><tr><th>FECHA</th><th>PAGO</th><th>COMPROBANTE</th><th>BOLETA BANCO</th><th>REFERENCIA</th><th class="number">MONTO</th><th class="number">SALDO</th></tr></thead>
        <tbody>
            @foreach ($movimientos as $movimiento)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($movimiento['fecha_movimiento'])->format('d/m/Y') }}</td>
                    <td>{{ $movimiento['pago'] ?: '—' }}</td>
                    <td>{{ $movimiento['comprobante'] ?: '—' }}</td>
                    <td>{{ $movimiento['boleta_banco'] ?: '—' }}</td>
                    <td>{{ $movimiento['referencia'] ?: '—' }}</td>
                    <td class="number">{{ number_format($movimiento['monto'], 2) }}</td>
                    <td class="number">{{ number_format($movimiento['saldo'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">SALDO AL CORTE</td>
                <td></td>
                <td class="number">{{ number_format($resumen['saldo_actual'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
     <p class="footer">Documento generado electrónicamente · Emitido el {{ $fecha_emision }}</p>
</body>
</html>
