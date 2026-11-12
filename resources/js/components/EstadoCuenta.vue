<script setup>
import { computed, ref } from 'vue';

const criterio = ref('');
const asociado = ref(null);
const creditos = ref([]);
const ahorros = ref([]);
const apartadoActivo = ref('creditos');
const cargandoBusqueda = ref(false);
const errorBusqueda = ref('');
const estadoCredito = ref(null);
const cargandoCredito = ref(false);
const errorCredito = ref('');
const estadoAhorro = ref(null);
const cargandoAhorro = ref(false);
const errorAhorro = ref('');
const cuentaAhorroSeleccionada = ref(null);
const fechaDesde = ref('');
const fechaHasta = ref('');

const money = new Intl.NumberFormat('es-GT', {
    style: 'currency',
    currency: 'GTQ',
    minimumFractionDigits: 2,
});

const fecha = new Intl.DateTimeFormat('es-GT', {
    day: '2-digit', month: 'short', year: 'numeric',
});

const criterioLimpio = computed(() => criterio.value.trim());

function formatMoney(value) {
    return money.format(Number(value || 0));
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const dateValue = String(value).trim();
    const normalizedDate = /^\d{4}-\d{2}-\d{2}$/.test(dateValue)
        ? `${dateValue}T00:00:00`
        : dateValue.replace(' ', 'T');
    const parsedDate = new Date(normalizedDate);

    return Number.isNaN(parsedDate.valueOf()) ? dateValue : fecha.format(parsedDate);
}

function limpiarReportes() {
    estadoCredito.value = null;
    errorCredito.value = '';
    estadoAhorro.value = null;
    errorAhorro.value = '';
    cuentaAhorroSeleccionada.value = null;
    fechaDesde.value = '';
    fechaHasta.value = '';
}

async function buscarAsociado() {
    errorBusqueda.value = '';
    limpiarReportes();

    if (!criterioLimpio.value) {
        errorBusqueda.value = 'Ingresa un código de asociado o DPI para continuar.';
        return;
    }

    cargandoBusqueda.value = true;
    try {
        const params = new URLSearchParams({ criterio: criterioLimpio.value });
        const response = await fetch(`/api/asociados/buscar?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'No fue posible encontrar al asociado.');
        }

        asociado.value = data.asociado;
        creditos.value = data.creditos || [];
        ahorros.value = data.ahorros || [];
        apartadoActivo.value = 'creditos';
    } catch (exception) {
        asociado.value = null;
        creditos.value = [];
        ahorros.value = [];
        errorBusqueda.value = exception.message;
    } finally {
        cargandoBusqueda.value = false;
    }
}

async function seleccionarCredito(codigoCredito) {
    errorCredito.value = '';
    estadoCredito.value = null;
    apartadoActivo.value = 'creditos';
    cargandoCredito.value = true;

    try {
        const response = await fetch(`/api/creditos/${encodeURIComponent(codigoCredito)}/estado-cuenta`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'No fue posible generar el estado de cuenta del crédito.');
        }

        estadoCredito.value = data;
    } catch (exception) {
        errorCredito.value = exception.message;
    } finally {
        cargandoCredito.value = false;
    }
}

async function seleccionarAhorro(codigoAhorro) {
    errorAhorro.value = '';
    estadoAhorro.value = null;
    apartadoActivo.value = 'ahorros';
    cuentaAhorroSeleccionada.value = codigoAhorro;
    fechaDesde.value = '';
    fechaHasta.value = '';

    await generarEstadoAhorro();
}

async function generarEstadoAhorro() {
    errorAhorro.value = '';
    estadoAhorro.value = null;

    if (!cuentaAhorroSeleccionada.value) {
        errorAhorro.value = 'Selecciona una cuenta de ahorro para generar el estado de cuenta.';
        return;
    }

    if ((fechaDesde.value && !fechaHasta.value) || (!fechaDesde.value && fechaHasta.value)) {
        errorAhorro.value = 'Para filtrar por período, selecciona ambas fechas: Desde y Hasta.';
        return;
    }

    if (fechaDesde.value && fechaHasta.value && fechaHasta.value < fechaDesde.value) {
        errorAhorro.value = 'La fecha Hasta debe ser igual o posterior a la fecha Desde.';
        return;
    }

    cargandoAhorro.value = true;

    try {
        const params = new URLSearchParams();

        if (fechaDesde.value && fechaHasta.value) {
            params.set('desde', fechaDesde.value);
            params.set('hasta', fechaHasta.value);
        }

        const queryString = params.toString();
        const response = await fetch(`/api/ahorros/${encodeURIComponent(cuentaAhorroSeleccionada.value)}/estado-cuenta${queryString ? `?${queryString}` : ''}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'No fue posible generar el estado de cuenta del ahorro.');
        }

        estadoAhorro.value = data;
    } catch (exception) {
        errorAhorro.value = exception.message;
    } finally {
        cargandoAhorro.value = false;
    }
}

function descargarAhorro() {
    if (!estadoAhorro.value) {
        return;
    }

    const params = new URLSearchParams();

    if (fechaDesde.value && fechaHasta.value) {
        params.set('desde', fechaDesde.value);
        params.set('hasta', fechaHasta.value);
    }

    const queryString = params.toString();
    window.location.assign(`/ahorros/${encodeURIComponent(estadoAhorro.value.cuenta)}/estado-cuenta.pdf${queryString ? `?${queryString}` : ''}`);
}

function descargarCredito() {
    if (estadoCredito.value) {
        window.location.assign(`/creditos/${encodeURIComponent(estadoCredito.value.credito)}/estado-cuenta.pdf`);
    }
}
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-katoki-blue">
        <header class="border-b border-katoki-forest/20 bg-katoki-blue text-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <a href="/" class="flex items-center gap-3" aria-label="Inicio de créditos">
                    <img :src="'/images/logo-katoki-simbolo.png'" alt="Cooperativa Kato-Ki, R.L." class="h-11 w-auto" />
                    <span><b class="block tracking-tight">CRÉDITOS Y AHORROS</b><small class="text-white/70">Portal de consulta</small></span>
                </a>
                <span class="hidden rounded-full border border-katoki-lime/60 px-3 py-1 text-xs text-white sm:block">Consulta segura</span>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10 sm:py-14">
            <section v-if="!asociado" class="grid gap-8 lg:grid-cols-[1fr_0.82fr] lg:items-center">
                <div>
                    <p class="mb-3 text-sm font-semibold tracking-[0.16em] text-katoki-green">CONSULTA DE ASOCIADO</p>
                    <h1 class="max-w-2xl text-4xl font-semibold tracking-tight text-katoki-blue sm:text-5xl">Tus créditos y ahorros cancelados.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-katoki-gray">Busca con código de asociado o DPI para consultar las cuentas canceladasdisponibles.</p>
                </div>

                <form class="rounded-3xl bg-white p-6 shadow-[0_20px_60px_-25px_rgba(7,29,73,0.28)] ring-1 ring-katoki-green/20 sm:p-8" @submit.prevent="buscarAsociado">
                    <label for="criterio" class="text-sm font-semibold text-katoki-blue">Código de asociado o DPI</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                        <input id="criterio" v-model="criterio" class="min-w-0 flex-1 rounded-xl border border-katoki-gray/35 bg-white px-4 py-3 text-lg outline-none transition placeholder:text-katoki-gray/60 focus:border-katoki-green focus:ring-4 focus:ring-katoki-lime/30" placeholder="Ej. 02000808 o 1234567890101" autocomplete="off" :disabled="cargandoBusqueda" />
                        <button type="submit" class="rounded-xl bg-katoki-green px-5 py-3 font-semibold text-white transition hover:bg-katoki-forest focus:outline-none focus:ring-4 focus:ring-katoki-lime/40 disabled:cursor-wait disabled:opacity-70" :disabled="cargandoBusqueda">
                            {{ cargandoBusqueda ? 'Buscando…' : 'Buscar asociado' }}
                        </button>
                    </div>
                    <p v-if="errorBusqueda" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ errorBusqueda }}</p>
                    <p v-else class="mt-3 text-sm text-katoki-gray">El sistema identifica automáticamente si ingresaste código de asociado o DPI.</p>
                </form>
            </section>

            <template v-else>
                <section class="rounded-3xl bg-white p-6 shadow-[0_20px_60px_-25px_rgba(7,29,73,0.2)] ring-1 ring-katoki-green/20 sm:p-8">
                    <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-start">
                        <div>
                            <p class="text-sm font-semibold tracking-[0.16em] text-katoki-green">ASOCIADO ENCONTRADO</p>
                            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-katoki-blue">{{ asociado.nombre_completo }}</h1>
                            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-katoki-gray">
                                <p><span class="font-semibold text-katoki-forest">Código:</span> {{ asociado.codigo_asociado }}</p>
                                <p><span class="font-semibold text-katoki-forest">DPI:</span> {{ asociado.dpi }}</p>
                            </div>
                        </div>

                        <form class="w-full lg:max-w-md" @submit.prevent="buscarAsociado">
                            <label for="nuevo-criterio" class="text-sm font-semibold text-katoki-blue">Buscar otro asociado</label>
                            <div class="mt-2 flex gap-2">
                                <input id="nuevo-criterio" v-model="criterio" class="min-w-0 flex-1 rounded-xl border border-katoki-gray/35 bg-white px-4 py-2.5 outline-none transition placeholder:text-katoki-gray/60 focus:border-katoki-green focus:ring-4 focus:ring-katoki-lime/30" placeholder="Código o DPI" autocomplete="off" :disabled="cargandoBusqueda" />
                                <button type="submit" class="rounded-xl bg-katoki-green px-4 py-2.5 font-semibold text-white transition hover:bg-katoki-forest focus:outline-none focus:ring-4 focus:ring-katoki-lime/40 disabled:cursor-wait disabled:opacity-70" :disabled="cargandoBusqueda">
                                    {{ cargandoBusqueda ? '…' : 'Buscar' }}
                                </button>
                            </div>
                            <p v-if="errorBusqueda" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ errorBusqueda }}</p>
                        </form>
                    </div>
                </section>

                <section class="mt-8" aria-label="Productos del asociado">
                    <div class="flex gap-2 border-b border-katoki-green/20">
                        <button type="button" class="rounded-t-xl px-5 py-3 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-katoki-lime/30" :class="apartadoActivo === 'creditos' ? 'bg-katoki-blue text-white' : 'text-katoki-gray hover:bg-katoki-forest/5'" @click="apartadoActivo = 'creditos'">
                            Créditos <span class="ml-1 text-xs opacity-75">{{ creditos.length }}</span>
                        </button>
                        <button type="button" class="rounded-t-xl px-5 py-3 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-katoki-lime/30" :class="apartadoActivo === 'ahorros' ? 'bg-katoki-blue text-white' : 'text-katoki-gray hover:bg-katoki-forest/5'" @click="apartadoActivo = 'ahorros'">
                            Ahorros <span class="ml-1 text-xs opacity-75">{{ ahorros.length }}</span>
                        </button>
                    </div>

                    <div class="rounded-b-3xl rounded-tr-3xl bg-white p-6 shadow-[0_20px_60px_-25px_rgba(7,29,73,0.2)] ring-1 ring-katoki-green/20 sm:p-8">
                        <template v-if="apartadoActivo === 'creditos'">
                            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                                <div>
                                    <p class="text-sm font-semibold tracking-[0.16em] text-katoki-green">CRÉDITOS CANCELADOS</p>
                                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-katoki-blue">Selecciona el crédito que deseas consultar.</h2>
                                </div>
                                <p class="text-sm text-katoki-gray">{{ creditos.length }} encontrado(s)</p>
                            </div>

                           <div v-if="creditos.length" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="credito in creditos"
                                :key="credito.codigo_credito"
                                type="button"
                                class="group rounded-xl border bg-white p-4 text-left shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-wait disabled:opacity-70"
                                :class="{
                                    'border-katoki-green/30 hover:border-katoki-lime hover:bg-katoki-lime/10 focus:ring-katoki-lime/30': credito.estado_credito?.toUpperCase() === 'V',
                                    'border-amber-200 bg-amber-50/20 hover:border-amber-300 hover:bg-amber-50/60 focus:ring-amber-200/50': credito.estado_credito?.toUpperCase() === 'M',
                                    'border-rose-200 bg-rose-50/20 hover:border-rose-300 hover:bg-rose-50/60 focus:ring-rose-200/50': credito.estado_credito?.toUpperCase() === 'C',
                                    'border-slate-200 hover:border-slate-300 hover:bg-slate-50 focus:ring-slate-200/50': !['V', 'R', 'C'].includes(credito.estado_credito?.toUpperCase())
                                }"
                                :disabled="cargandoCredito"
                                @click="seleccionarCredito(credito.codigo_credito)"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span
                                        class="block text-xs font-semibold tracking-[0.12em]"
                                        :class="{
                                            'text-katoki-green': credito.estado_credito?.toUpperCase() === 'V',
                                            'text-amber-700/80': credito.estado_credito?.toUpperCase() === 'M',
                                            'text-rose-700/80': credito.estado_credito?.toUpperCase() === 'C',
                                            'text-slate-600': !['V', 'R', 'C'].includes(credito.estado_credito?.toUpperCase())
                                        }"
                                    >
                                        CRÉDITO
                                    </span>

                                    <!-- Badge elegante de estado -->
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20': credito.estado_credito?.toUpperCase() === 'V',
                                            'bg-amber-50 text-amber-700 ring-amber-600/20': credito.estado_credito?.toUpperCase() === 'M',
                                            'bg-rose-50 text-rose-700 ring-rose-600/20': credito.estado_credito?.toUpperCase() === 'C',
                                            'bg-slate-100 text-slate-600 ring-slate-500/20': !['V', 'R', 'C'].includes(credito.estado_credito?.toUpperCase())
                                        }"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="{
                                                'bg-emerald-500': credito.estado_credito?.toUpperCase() === 'V',
                                                'bg-amber-500': credito.estado_credito?.toUpperCase() === 'M',
                                                'bg-rose-500': credito.estado_credito?.toUpperCase() === 'C',
                                                'bg-slate-400': !['V', 'R', 'C'].includes(credito.estado_credito?.toUpperCase())
                                            }"
                                        ></span>
                                        {{
                                            credito.estado_credito?.toUpperCase() === 'V'
                                                ? 'ACTIVO'
                                                : credito.estado_credito?.toUpperCase() === 'M'
                                                ? 'NOVACIÓN'
                                                : credito.estado_credito?.toUpperCase() === 'C'
                                                ? 'CANCELADO'
                                                : credito.estado_credito
                                        }}
                                    </span>
                                </div>

                                <span class="mt-2 block font-semibold text-katoki-blue">
                                    {{ credito.codigo_credito }}
                                </span>

                                <span
                                    class="mt-3 block text-sm font-medium transition-colors"
                                    :class="{
                                        'text-katoki-forest group-hover:text-katoki-blue': credito.estado_credito?.toUpperCase() === 'V',
                                        'text-amber-700 group-hover:text-amber-900': credito.estado_credito?.toUpperCase() === 'M',
                                        'text-rose-700 group-hover:text-rose-900': credito.estado_credito?.toUpperCase() === 'C',
                                        'text-slate-600 group-hover:text-slate-800': !['V', 'R', 'C'].includes(credito.estado_credito?.toUpperCase())
                                    }"
                                >
                                    Ver estado de cuenta →
                                </span>
                            </button>
                        </div>
                            <div v-else class="mt-6 rounded-2xl border border-dashed border-katoki-green/40 bg-katoki-forest/5 px-6 py-10 text-center">
                                <p class="font-semibold text-katoki-blue">No hay créditos cancelados</p>
                                <p class="mt-1 text-sm text-katoki-gray">Este asociado no tiene créditos con estado C.</p>
                            </div>
                            <p v-if="errorCredito" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ errorCredito }}</p>
                        </template>

                        <template v-else>
                            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                                <div>
                                    <p class="text-sm font-semibold tracking-[0.16em] text-katoki-green">AHORROS CANCELADOS</p>
                                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-katoki-blue">Selecciona la cuenta que deseas consultar.</h2>
                                </div>
                                <p class="text-sm text-katoki-gray">{{ ahorros.length }} encontrado(s)</p>
                            </div>

                           <div v-if="ahorros.length" class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <button
                                    v-for="ahorro in ahorros"
                                    :key="ahorro.codigo_ahorro"
                                    type="button"
                                    class="group rounded-xl border bg-white p-4 text-left shadow-sm transition focus:outline-none focus:ring-4 disabled:cursor-wait disabled:opacity-70"
                                    :class="{
                                        'border-katoki-green/30 hover:border-katoki-lime hover:bg-katoki-lime/10 focus:ring-katoki-lime/30': ahorro.estado_ahorro?.toUpperCase() === 'A',
                                        'border-rose-200 bg-rose-50/20 hover:border-rose-300 hover:bg-rose-50/60 focus:ring-rose-200/50': ahorro.estado_ahorro?.toUpperCase() === 'C',
                                        'border-slate-200 hover:border-slate-300 hover:bg-slate-50 focus:ring-slate-200/50': !['A', 'C'].includes(ahorro.estado_ahorro?.toUpperCase())
                                    }"
                                    :disabled="cargandoAhorro"
                                    @click="seleccionarAhorro(ahorro.codigo_ahorro)"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span
                                            class="block text-xs font-semibold tracking-[0.12em]"
                                            :class="{
                                                'text-katoki-green': ahorro.estado_ahorro?.toUpperCase() === 'A',
                                                'text-rose-700/80': ahorro.estado_ahorro?.toUpperCase() === 'C',
                                                'text-slate-600': !['A', 'C'].includes(ahorro.estado_ahorro?.toUpperCase())
                                            }"
                                        >
                                            {{ ahorro.tipo_cuenta?.toUpperCase() }}
                                        </span>

                                        <!-- Badge elegante de estado -->
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20': ahorro.estado_ahorro?.toUpperCase() === 'A',
                                                'bg-rose-50 text-rose-700 ring-rose-600/20': ahorro.estado_ahorro?.toUpperCase() === 'C',
                                                'bg-slate-100 text-slate-600 ring-slate-500/20': !['A', 'C'].includes(ahorro.estado_ahorro?.toUpperCase())
                                            }"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="{
                                                    'bg-emerald-500': ahorro.estado_ahorro?.toUpperCase() === 'A',
                                                    'bg-rose-500': ahorro.estado_ahorro?.toUpperCase() === 'C',
                                                    'bg-slate-400': !['A', 'C'].includes(ahorro.estado_ahorro?.toUpperCase())
                                                }"
                                            ></span>
                                            {{
                                                ahorro.estado_ahorro?.toUpperCase() === 'A'
                                                    ? 'ACTIVO'
                                                    : ahorro.estado_ahorro?.toUpperCase() === 'C'
                                                    ? 'CANCELADO'
                                                    : ahorro.estado_ahorro
                                            }}
                                        </span>
                                    </div>

                                    <span class="mt-2 block font-semibold text-katoki-blue">
                                        {{ ahorro.codigo_ahorro }}
                                    </span>

                                    <span
                                        class="mt-3 block text-sm font-medium transition-colors"
                                        :class="{
                                            'text-katoki-forest group-hover:text-katoki-blue': ahorro.estado_ahorro?.toUpperCase() === 'A',
                                            'text-rose-700 group-hover:text-rose-900': ahorro.estado_ahorro?.toUpperCase() === 'C',
                                            'text-slate-600 group-hover:text-slate-800': !['A', 'C'].includes(ahorro.estado_ahorro?.toUpperCase())
                                        }"
                                    >
                                        Ver tabla completa →
                                    </span>
                                </button>
                            </div>
                            <div v-else class="mt-6 rounded-2xl border border-dashed border-katoki-green/40 bg-katoki-forest/5 px-6 py-10 text-center">
                                <p class="font-semibold text-katoki-blue">No hay ahorros cancelados</p>
                                <p class="mt-1 text-sm text-katoki-gray">Este asociado no tiene cuentas de ahorro con estado C.</p>
                            </div>
                        </template>
                    </div>
                </section>

                <section v-if="estadoCredito" class="mt-12" aria-live="polite">
                    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <p class="text-sm font-semibold tracking-[0.12em] text-katoki-green">ESTADO DE CUENTA DE CRÉDITO</p>
                            <h2 class="mt-1 text-2xl font-semibold text-katoki-blue">Crédito {{ estadoCredito.credito }}</h2>
                            <p class="mt-1 text-sm text-katoki-gray"><span class="font-semibold text-katoki-forest">Cliente:</span> {{ estadoCredito.cliente }}</p>
                            <p class="mt-1 text-sm text-katoki-gray"><span class="font-semibold text-katoki-forest">Fecha de desembolso:</span> {{ estadoCredito.fecha_desembolso ? formatDate(estadoCredito.fecha_desembolso) : 'No disponible' }}</p>
                            <p class="mt-1 text-sm text-katoki-gray">Emitido el {{ estadoCredito.fecha_emision }}</p>
                        </div>
                        <button v-if="estadoCredito.pagos.length" class="inline-flex items-center justify-center gap-2 rounded-xl border border-katoki-green bg-white px-4 py-2.5 font-semibold text-katoki-forest transition hover:bg-katoki-lime/15 focus:outline-none focus:ring-4 focus:ring-katoki-lime/30" type="button" @click="descargarCredito">
                            <span aria-hidden="true">↓</span> Descargar PDF
                        </button>
                    </div>

                    <div v-if="estadoCredito.pagos.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <article class="metric"><span>Pagos registrados</span><strong>{{ estadoCredito.resumen.cantidad_pagos }}</strong></article>
                        <article class="metric"><span>Total pagado</span><strong>{{ formatMoney(estadoCredito.resumen.total_pagado) }}</strong></article>
                        <article class="metric"><span>Capital</span><strong>{{ formatMoney(estadoCredito.resumen.capital) }}</strong></article>
                        <article class="metric"><span>Interés</span><strong>{{ formatMoney(estadoCredito.resumen.interes) }}</strong></article>
                        <article class="metric"><span>Mora</span><strong>{{ formatMoney(estadoCredito.resumen.mora) }}</strong></article>
                    </div>

                    <div v-if="estadoCredito.pagos.length" class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-katoki-green/20">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-katoki-blue text-xs uppercase tracking-wider text-white"><tr><th>Pago</th><th>Fecha</th><th>Total pagado</th><th>Capital</th><th>Interés</th><th>Mora</th></tr></thead>
                                <tbody class="divide-y divide-katoki-forest/10">
                                    <tr v-for="pago in estadoCredito.pagos" :key="`${pago.pago_numero}-${pago.fecha_pago}`" class="text-katoki-gray hover:bg-katoki-lime/10">
                                        <td class="font-medium text-katoki-blue">#{{ pago.pago_numero }}</td><td>{{ formatDate(pago.fecha_pago) }}</td>
                                        <td class="font-semibold">{{ formatMoney(pago.total_pago) }}</td><td>{{ formatMoney(pago.capital) }}</td><td>{{ formatMoney(pago.interes) }}</td><td>{{ formatMoney(pago.mora) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-katoki-green/40 bg-white px-6 py-12 text-center">
                        <p class="text-lg font-semibold text-katoki-blue">No hay pagos registrados</p>
                        <p class="mt-1 text-katoki-gray">El crédito seleccionado no tiene movimientos que cumplan los criterios del reporte.</p>
                    </div>
                </section>

                <section v-if="cuentaAhorroSeleccionada" class="mt-12 rounded-3xl bg-white p-6 shadow-[0_20px_60px_-25px_rgba(7,29,73,0.2)] ring-1 ring-katoki-green/20 sm:p-8">
                    <p class="text-sm font-semibold tracking-[0.12em] text-katoki-green">RANGO DE FECHAS OPCIONAL</p>
                    <h2 class="mt-1 text-2xl font-semibold text-katoki-blue">Cuenta {{ cuentaAhorroSeleccionada }}</h2>
                    <p class="mt-2 text-sm text-katoki-gray">La tabla completa se carga automáticamente. Selecciona ambas fechas solamente si deseas filtrar el período.</p>

                    <form class="mt-6 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="generarEstadoAhorro">
                        <div>
                            <label for="fecha-desde" class="text-sm font-semibold text-katoki-blue">Desde</label>
                            <input id="fecha-desde" v-model="fechaDesde" type="date" class="mt-2 w-full rounded-xl border border-katoki-gray/35 bg-white px-4 py-3 outline-none transition focus:border-katoki-green focus:ring-4 focus:ring-katoki-lime/30" :disabled="cargandoAhorro" />
                        </div>
                        <div>
                            <label for="fecha-hasta" class="text-sm font-semibold text-katoki-blue">Hasta</label>
                            <input id="fecha-hasta" v-model="fechaHasta" type="date" class="mt-2 w-full rounded-xl border border-katoki-gray/35 bg-white px-4 py-3 outline-none transition focus:border-katoki-green focus:ring-4 focus:ring-katoki-lime/30" :disabled="cargandoAhorro" />
                        </div>
                        <button type="submit" class="rounded-xl bg-katoki-green px-5 py-3 font-semibold text-white transition hover:bg-katoki-forest focus:outline-none focus:ring-4 focus:ring-katoki-lime/40 disabled:cursor-wait disabled:opacity-70" :disabled="cargandoAhorro">
                            {{ cargandoAhorro ? 'Actualizando…' : 'Aplicar rango' }}
                        </button>
                    </form>
                    <p v-if="errorAhorro" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ errorAhorro }}</p>
                </section>

                <section v-if="estadoAhorro" class="mt-12" aria-live="polite">
                    <div class="mb-5">
                        <p class="text-sm font-semibold tracking-[0.12em] text-katoki-green"> DE CUENTA DE AHORRO</p>
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                            <div>
                                <h2 class="mt-1 text-2xl font-semibold text-katoki-blue">Cuenta {{ estadoAhorro.cuenta }}</h2>
                                <p class="mt-1 text-sm text-katoki-gray"><span class="font-semibold text-katoki-forest">Asociado:</span> {{ estadoAhorro.asociado }}</p>
                                <p class="mt-1 text-sm text-katoki-gray"><span class="font-semibold text-katoki-forest">Dirección:</span> {{ estadoAhorro.direccion }}</p>
                                <p class="mt-1 text-sm text-katoki-gray"><span class="font-semibold text-katoki-forest">Periodo:</span> {{ estadoAhorro.fecha_desde && estadoAhorro.fecha_hasta ? `${formatDate(estadoAhorro.fecha_desde)} al ${formatDate(estadoAhorro.fecha_hasta)}` : 'Todos los movimientos' }}</p>
                            </div>
                            <button v-if="estadoAhorro.movimientos.length" class="inline-flex items-center justify-center gap-2 rounded-xl border border-katoki-green bg-white px-4 py-2.5 font-semibold text-katoki-forest transition hover:bg-katoki-lime/15 focus:outline-none focus:ring-4 focus:ring-katoki-lime/30" type="button" @click="descargarAhorro">
                                <span aria-hidden="true">↓</span> Descargar PDF
                            </button>
                        </div>
                    </div>

                    <div v-if="estadoAhorro.movimientos.length" class="grid gap-3 sm:grid-cols-2">
                        <article class="metric"><span>Movimientos registrados</span><strong>{{ estadoAhorro.resumen.cantidad_movimientos }}</strong></article>
                        <article class="metric"><span>Saldo actual</span><strong>{{ formatMoney(estadoAhorro.resumen.saldo_actual) }}</strong></article>
                    </div>

                    <div v-if="estadoAhorro.movimientos.length" class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-katoki-green/20">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-katoki-blue text-xs uppercase tracking-wider text-white"><tr><th>Fecha</th><th>Pago</th><th>Comprobante</th><th>Boleta banco</th><th>Referencia</th><th>Monto</th><th>Saldo</th></tr></thead>
                                <tbody class="divide-y divide-katoki-forest/10">
                                    <tr v-for="(movimiento, index) in estadoAhorro.movimientos" :key="`${movimiento.fecha_movimiento}-${movimiento.comprobante}-${index}`" class="text-katoki-gray hover:bg-katoki-lime/10">
                                        <td>{{ formatDate(movimiento.fecha_movimiento) }}</td>
                                        <td class="font-medium text-katoki-blue">{{ movimiento.pago || '—' }}</td>
                                        <td>{{ movimiento.comprobante || '—' }}</td>
                                        <td>{{ movimiento.boleta_banco || '—' }}</td>
                                        <td>{{ movimiento.referencia || '—' }}</td>
                                        <td>{{ formatMoney(movimiento.monto) }}</td>
                                        <td class="font-semibold">{{ formatMoney(movimiento.saldo) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-katoki-green/40 bg-white px-6 py-12 text-center">
                        <p class="text-lg font-semibold text-katoki-blue">No hay movimientos registrados</p>
                        <p class="mt-1 text-katoki-gray">La cuenta de ahorro seleccionada no tiene movimientos para mostrar.</p>
                    </div>
                </section>
            </template>
        </main>
    </div>
</template>
