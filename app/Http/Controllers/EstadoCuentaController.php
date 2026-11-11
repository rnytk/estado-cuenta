<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EstadoCuentaController extends Controller
{
    public function findAssociate(Request $request): JsonResponse
    {
        $criteria = $this->validateAssociateSearch($request);

        try {
            $associate = DB::connection('sqlsrv')->selectOne(<<<'SQL'
                SELECT TOP 1
                    codCli AS CODIGO_ASOCIADO,
                    LTRIM(RTRIM(CONCAT(priNom, ' ', segNom, ' ', priApe, ' ', segApe))) AS NOMBRE_COMPLETO,
                    numIde AS DPI,
                    dirDom AS DIRECCION_DOMICILIO
                FROM cliRegCli
                WHERE codCli = ?
                   OR REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(numIde)), '-', ''), ' ', ''), '.', '') = ?
                ORDER BY CASE WHEN codCli = ? THEN 0 ELSE 1 END
            SQL, [$criteria, $criteria, $criteria]);
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar SQL Server. Verifique la configuración de conexión.');
        }

        if (! $associate) {
            abort(404, 'No se encontró un asociado con el código o DPI ingresado.');
        }

        $codigoAsociado = (string) $associate->CODIGO_ASOCIADO;

        return response()->json([
            'asociado' => [
                'codigo_asociado' => $codigoAsociado,
                'nombre_completo' => (string) $associate->NOMBRE_COMPLETO,
                'dpi' => (string) $associate->DPI,
                'direccion' => (string) $associate->DIRECCION_DOMICILIO,
            ],
            'creditos' => $this->creditsFor($codigoAsociado),
            'ahorros' => $this->savingsFor($codigoAsociado),
        ]);
    }

    public function creditsForAssociate(string $codigo): JsonResponse
    {
        $codigo = $this->validateAssociateCode($codigo);

        return response()->json([
            'asociado' => $codigo,
            'creditos' => $this->creditsFor($codigo),
        ]);
    }

    public function show(Request $request, string $codigo): JsonResponse
    {
        $codigo = $this->validateCreditCode($codigo);
        $pagos = $this->paymentsFor($codigo);

        return response()->json($this->payload($codigo, $pagos));
    }

    public function download(Request $request, string $codigo): Response
    {
        $codigo = $this->validateCreditCode($codigo);
        $pagos = $this->paymentsFor($codigo);

        if ($pagos->isEmpty()) {
            abort(404, 'No se encontraron pagos para este crédito.');
        }

        return Pdf::loadView('pdf.estado-cuenta', $this->payload($codigo, $pagos))
            ->setPaper('a4', 'landscape')
            ->download("estado-cuenta-{$codigo}.pdf");
    }

    public function savingsStatement(Request $request, string $codigo): JsonResponse
    {
        $codigo = $this->validateSavingsCode($codigo);
        $range = $this->validateSavingsDateRange($request);

        return response()->json($this->savingsPayload($codigo, $range));
    }

    public function downloadSavingsStatement(Request $request, string $codigo): Response
    {
        $codigo = $this->validateSavingsCode($codigo);
        $range = $this->validateSavingsDateRange($request);
        $payload = $this->savingsPayload($codigo, $range);

        if ($payload['movimientos']->isEmpty()) {
            abort(404, 'No se encontraron movimientos de ahorro para el rango seleccionado.');
        }

        return Pdf::loadView('pdf.estado-cuenta-ahorro', $payload)
            ->setPaper('a4', 'landscape')
            ->download("estado-cuenta-ahorro-{$codigo}.pdf");
    }

    private function validateCreditCode(string $codigo): string
    {
        $validated = validator(['codigo' => $codigo], [
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
        ])->validate();

        return $validated['codigo'];
    }

    private function validateAssociateCode(string $codigo): string
    {
        $validated = validator(['codigo' => $codigo], [
            'codigo' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
        ])->validate();

        return $validated['codigo'];
    }

    private function validateAssociateSearch(Request $request): string
    {
        $validated = validator($request->query(), [
            'criterio' => ['required', 'string', 'max:30', 'regex:/^[0-9 .-]+$/'],
        ])->validate();

        return str_replace([' ', '.', '-'], '', $validated['criterio']);
    }

    private function validateSavingsCode(string $codigo): string
    {
        $validated = validator(['codigo' => $codigo], [
            'codigo' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
        ])->validate();

        return $validated['codigo'];
    }

    /**
     * @return array{desde: ?string, hasta: ?string}
     */
    private function validateSavingsDateRange(Request $request): array
    {
        $range = validator($request->query(), [
            'desde' => ['nullable', 'required_with:hasta', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'required_with:desde', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ])->validate();

        return [
            'desde' => $range['desde'] ?? null,
            'hasta' => $range['hasta'] ?? null,
        ];
    }

    private function creditsFor(string $codigo): Collection
    {
        try {
            return collect(DB::connection('sqlsrv')->select(<<<'SQL'
                SELECT codCre AS CODIGO_CREDITO
                FROM creRegCre
                WHERE codCli = ?
                  --AND estCre = 'C'
                ORDER BY codCre ASC
            SQL, [$codigo]))
                ->map(fn (object $credito): array => [
                    'codigo_credito' => (string) $credito->CODIGO_CREDITO,
                ])
                ->values();
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar los créditos del asociado.');
        }
    }

    private function savingsFor(string $codigo): Collection
    {
        try {
            return collect(DB::connection('sqlsrv')->select(<<<'SQL'
                SELECT codAho AS CODIGO_AHORRO, estAho AS ESTADO_AHORRO
                FROM ahoRegAho
                WHERE codCli = ?
                  --AND estAho = 'C'
                ORDER BY codAho ASC
            SQL, [$codigo]))
                ->map(fn (object $savings): array => [
                    'codigo_ahorro' => trim((string) $savings->CODIGO_AHORRO),
                    'estado_ahorro' => trim((string) $savings->ESTADO_AHORRO),
                ])
                ->values();
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar los ahorros del asociado.');
        }
    }

    /**
     * @return array{cuenta: string, codigo_asociado: string, asociado: string, dpi: string}
     */
    private function savingsAccountFor(string $codigo): array
    {
        try {
            $account = DB::connection('sqlsrv')->selectOne(<<<'SQL'
                SELECT TOP 1
                    T0.codAho AS CODIGO_AHORRO,
                    T0.codCli AS CODIGO_ASOCIADO,
                    LTRIM(RTRIM(CONCAT(T1.priNom, ' ', T1.segNom, ' ', T1.priApe, ' ', T1.segApe))) AS NOMBRE_ASOCIADO,
                    T1.numIde AS DPI,
                    T1.dirDom AS DIRECCION_DOMICILIO
                FROM ahoRegAho AS T0
                INNER JOIN cliRegCli AS T1 ON T0.codCli = T1.codCli
                WHERE T0.codAho = ?
                 -- AND T0.estAho = 'C'
            SQL, [$codigo]);
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar los datos de la cuenta de ahorro.');
        }

        if (! $account) {
            abort(404, 'No se encontró una cuenta de ahorro cancelada con el código indicado.');
        }

        return [
            'cuenta' => trim((string) $account->CODIGO_AHORRO),
            'codigo_asociado' => trim((string) $account->CODIGO_ASOCIADO),
            'asociado' => (string) $account->NOMBRE_ASOCIADO,
            'dpi' => (string) $account->DPI,
            'direccion' => (string) $account->DIRECCION_DOMICILIO,
        ];
    }

    private function paymentsFor(string $codigo): Collection
    {
        try {
            return collect(DB::connection('sqlsrv')->select(<<<'SQL'
                SELECT
                    T0.fecNor AS FECHA_PAGO,
                    T0.codCre AS CODIGO_CREDITO,
                    SUM(CASE WHEN T0.desing = 'TP' THEN T0.moncuo ELSE 0 END) AS TOTAL_PAGO,
                    SUM(CASE WHEN T0.desing = 'KP' THEN T0.moncuo ELSE 0 END) AS CAPITAL,
                    SUM(CASE WHEN T0.desing IN ('IN', 'IP') THEN T0.moncuo ELSE 0 END) AS INTERES,
                    SUM(CASE WHEN T0.desing = 'MO' THEN T0.moncuo ELSE 0 END) AS MORA,
                    T0.numIng AS PAGO_NUMERO,
                    MAX(LTRIM(RTRIM(CONCAT(
                        T2.priNom, ' ', T2.segNom, ' ', T2.priApe, ' ', T2.segApe
                    )))) AS NOMBRE_CLIENTE
                FROM creKarDia AS T0
                INNER JOIN creRegCre AS T1 ON T0.codCre = T1.codCre
                LEFT JOIN cliRegCli AS T2 ON T1.codCli = T2.codCli
                WHERE T1.estCre = 'C'
                  AND T0.descob = 'R'
                  AND T0.codCre = ?
                GROUP BY T0.numIng, T0.fecNor, T0.codCre
                ORDER BY T0.codCre, T0.fecNor ASC
            SQL, [$codigo]));
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar SQL Server. Verifique la configuración de conexión.');
        }
    }

    private function disbursementDateFor(string $codigo): ?string
    {
        try {
            $disbursement = DB::connection('sqlsrv')->selectOne(<<<'SQL'
                SELECT MIN(fecNor) AS FECHA_DESEMBOLSO
                FROM creKarDia
                WHERE descob = 'D'
                  AND codCre = ?
            SQL, [$codigo]);
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar SQL Server. Verifique la configuración de conexión.');
        }

        if (! $disbursement?->FECHA_DESEMBOLSO) {
            return null;
        }

        return Carbon::parse($disbursement->FECHA_DESEMBOLSO)->format('Y-m-d');
    }

    private function savingsMovementsFor(string $codigo, ?string $desde, ?string $hasta): Collection
    {
        try {
            $query = <<<'SQL'
                WITH Movimientos AS (
                    SELECT
                        FECMOV AS FECHA_MOVIMIENTO,
                        FECMOD AS FECHA_ORDEN,
                        CASE
                            WHEN TIPPAG = 'T' THEN 'TR'
                            WHEN TIPPAG = 'E' THEN 'EFE'
                            WHEN TIPPAG = 'C' THEN 'CHQ'
                            WHEN TIPPAG = 'D' THEN 'BOL'
                            WHEN TIPPAG = 'NC' THEN 'NC'
                            ELSE ''
                        END AS PAGO,
                        NUMDOC AS COMPROBANTE,
                        CASE
                            WHEN T0.TIPPAG = 'D' THEN T0.NUMDOC
                            ELSE ''
                        END AS BOLETA_BANCO,
                        REFMOV AS REFERENCIA,
                        MONMOV AS MONTO,
                        SUM(CASE
                            WHEN TIPMOV = 'D' THEN MONMOV
                            WHEN TIPMOV = 'R' THEN -MONMOV
                            ELSE 0
                        END) OVER (
                            PARTITION BY CODAHO
                            ORDER BY FECMOD
                            ROWS UNBOUNDED PRECEDING
                        ) AS SALDO
                    FROM ahoKarAho AS T0
                    WHERE CODAHO = ?
                )
                SELECT
                    FECHA_MOVIMIENTO,
                    PAGO,
                    COMPROBANTE,
                    BOLETA_BANCO,
                    REFERENCIA,
                    MONTO,
                    SALDO
                FROM Movimientos
            SQL;
            $bindings = [$codigo];

            if ($desde && $hasta) {
                $query .= <<<'SQL'
                    WHERE FECHA_MOVIMIENTO >= CAST(? AS date)
                      AND FECHA_MOVIMIENTO < DATEADD(day, 1, CAST(? AS date))
                SQL;
                $bindings[] = $desde;
                $bindings[] = $hasta;
            }

            $query .= ' ORDER BY FECHA_ORDEN';
            $movements = DB::connection('sqlsrv')->select($query, $bindings);
        } catch (QueryException $exception) {
            report($exception);

            abort(503, 'No fue posible consultar los movimientos de ahorro.');
        }

        return collect($movements)
            ->map(function (object $movement): array {
                return [
                    'fecha_movimiento' => $movement->FECHA_MOVIMIENTO instanceof \DateTimeInterface
                        ? $movement->FECHA_MOVIMIENTO->format('Y-m-d')
                        : (string) $movement->FECHA_MOVIMIENTO,
                    'pago' => (string) $movement->PAGO,
                    'comprobante' => (string) $movement->COMPROBANTE,
                    'boleta_banco' => (string) $movement->BOLETA_BANCO,
                    'referencia' => (string) $movement->REFERENCIA,
                    'monto' => (float) $movement->MONTO,
                    'saldo' => (float) $movement->SALDO,
                ];
            })
            ->values();
    }

    /**
     * @param  array{desde: ?string, hasta: ?string}  $range
     * @return array{cuenta: string, asociado: string, dpi: string, fecha_emision: string, fecha_desde: ?string, fecha_hasta: ?string, movimientos: Collection, resumen: array{cantidad_movimientos: int, saldo_actual: float|int}}
     */
    private function savingsPayload(string $codigo, array $range): array
    {
        $account = $this->savingsAccountFor($codigo);
        $movements = $this->savingsMovementsFor($codigo, $range['desde'], $range['hasta']);

        return [
            'cuenta' => $account['cuenta'],
            'asociado' => $account['asociado'],
            'dpi' => $account['dpi'],
            'direccion' => $account['direccion'],
            'fecha_emision' => now()->format('d/m/Y H:i'),
            'fecha_desde' => $range['desde'],
            'fecha_hasta' => $range['hasta'],
            'movimientos' => $movements,
            'resumen' => [
                'cantidad_movimientos' => $movements->count(),
                'saldo_actual' => $movements->last()['saldo'] ?? 0,
            ],
        ];
    }

    private function payload(string $codigo, Collection $pagos): array
    {
        $items = $pagos->map(function (object $pago): array {
            return [
                'fecha_pago' => $pago->FECHA_PAGO instanceof \DateTimeInterface
                    ? $pago->FECHA_PAGO->format('Y-m-d')
                    : (string) $pago->FECHA_PAGO,
                'codigo_credito' => (string) $pago->CODIGO_CREDITO,
                'total_pago' => (float) $pago->TOTAL_PAGO,
                'capital' => (float) $pago->CAPITAL,
                'interes' => (float) $pago->INTERES,
                'mora' => (float) $pago->MORA,
                'pago_numero' => (string) $pago->PAGO_NUMERO,
                'nombre_cliente' => (string) $pago->NOMBRE_CLIENTE,
            ];
        })->values();

        return [
            'credito' => $codigo,
            'cliente' => $items->first()['nombre_cliente'] ?? 'No disponible',
            'fecha_desembolso' => $this->disbursementDateFor($codigo),
            'fecha_emision' => now()->format('d/m/Y H:i'),
            'pagos' => $items,
            'resumen' => [
                'cantidad_pagos' => $items->count(),
                'total_pagado' => $items->sum('total_pago'),
                'capital' => $items->sum('capital'),
                'interes' => $items->sum('interes'),
                'mora' => $items->sum('mora'),
            ],
        ];
    }
}
