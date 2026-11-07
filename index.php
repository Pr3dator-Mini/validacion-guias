
<?php
declare(strict_types=1);
session_start();

/*
|--------------------------------------------------------------------------
| SISTEMA M6 - VALIDACIÓN DE GUÍAS
| Versión Premium 2.0
|--------------------------------------------------------------------------
| - Cálculos automáticos en JavaScript.
| - Validación y cálculo definitivo en PHP.
| - Historial de las últimas 20 cotizaciones por sesión.
| - Copiar resumen.
| - Moneda USD.
|--------------------------------------------------------------------------
*/

// ---------------------------------------------------------
// CONFIGURACIÓN
// ---------------------------------------------------------

const IVA_RATE = 0.13;
const FUEL_RATE = 0.44;
const SECURITY_RATE = 0.10;
const PBA_RATE = 0.12;
const COLLECT_RATE = 0.05;

const MAX_HISTORY = 20;

if (!isset($_SESSION['m6_history'])) {
    $_SESSION['m6_history'] = [];
}

if (empty($_SESSION['m6_token'])) {
    $_SESSION['m6_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------
// FUNCIONES
// ---------------------------------------------------------

function escape($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function money(float $value): string {
    return '$' . number_format($value, 2, '.', ',');
}

function getNumber(array $source, string $key, float $default = 0): float {

    if (!isset($source[$key]) || $source[$key] === '') {
        return $default;
    }

    $value = filter_var($source[$key], FILTER_VALIDATE_FLOAT);

    if ($value === false || !is_finite((float)$value)) {
        throw new InvalidArgumentException(
            "El campo {$key} contiene un valor inválido."
        );
    }

    $value = (float)$value;

    if ($value < 0) {
        throw new InvalidArgumentException(
            "No se permiten valores negativos."
        );
    }

    return $value;
}

function calculate(array $data): array {

    $peso = $data['peso_volumetrico'];
    $tarifaMin = $data['tarifa_min'];
    $tarifaAplicar = $data['tarifa_aplicar'];
    $cargosAgente = $data['cargos_agente'];
    $other = $data['other'];
    $rate = $data['fuel_escalation_rate'];

    // FLETE
    $flete = $tarifaMin > 0
        ? $tarifaMin
        : ($tarifaAplicar * $peso);

    // FUEL
    $fuel = $peso > 0
        ? max(15, FUEL_RATE * $peso)
        : 0;

    // SECURITY
    $security = $peso > 0
        ? max(10, SECURITY_RATE * $peso)
        : 0;

    // PBA
    $pba = $cargosAgente > 0
        ? max(35, $cargosAgente * PBA_RATE)
        : 0;

    // COLLECT FEE
    $collectFee = max(25, $flete * COLLECT_RATE);

    // PEAK SEASON
    $peakSeasonCharge = 0;

    if ($data['peak_season'] === 'okra') {

        $peakSeasonCharge = max(
            10,
            0.02 * $peso
        );

    } elseif ($data['peak_season'] === 'general') {

        $peakSeasonCharge = max(
            10,
            0.05 * $peso
        );
    }

    // FUEL ESCALATION
    $fuelEscalation = $data['fuel_escalation']
        ? ($peso * $rate)
        : 0;

    // COLLECT FEE APLICADO
    $collectApplied = $data['collect_fee']
        ? $collectFee
        : 0;

    // SUBTOTAL
    $subtotal =
        $flete +
        $fuel +
        $fuelEscalation +
        $security +
        $pba +
        $collectApplied +
        $cargosAgente +
        $other +
        $peakSeasonCharge;

    // IVA
    $iva = $data['iva']
        ? ($subtotal * IVA_RATE)
        : 0;

    // TOTAL
    $total = $subtotal + $iva;

    return [
        'flete' => $flete,
        'fuel' => $fuel,
        'fuel_escalation' => $fuelEscalation,
        'security' => $security,
        'pba' => $pba,
        'cargos_agente' => $cargosAgente,
        'other' => $other,
        'collect_fee' => $collectApplied,
        'peak_season' => $peakSeasonCharge,
        'subtotal' => $subtotal,
        'iva' => $iva,
        'total' => $total
    ];
}

// ---------------------------------------------------------
// VALORES INICIALES
// ---------------------------------------------------------

$defaults = [
    'peso_volumetrico' => 0,
    'tarifa_min' => 0,
    'tarifa_aplicar' => 0,
    'cargos_agente' => 0,
    'other' => 0,
    'fuel_escalation_rate' => 0.20,
    'peak_season' => '',
    'collect_fee' => false,
    'iva' => false,
    'fuel_escalation' => false
];

$data = $defaults;
$results = null;
$errors = [];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// ---------------------------------------------------------
// LIMPIAR HISTORIAL
// ---------------------------------------------------------

if (isset($_GET['clear_history'])) {
    $_SESSION['m6_history'] = [];

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ---------------------------------------------------------
// PROCESAR FORMULARIO
// ---------------------------------------------------------

if ($isPost) {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['m6_token'],
                (string)$_POST['csrf_token']
            )
        ) {
            throw new InvalidArgumentException(
                'La sesión expiró. Actualizá la página.'
            );
        }

        $data['peso_volumetrico'] = getNumber($_POST, 'peso_volumetrico');
        $data['tarifa_min'] = getNumber($_POST, 'tarifa_min');
        $data['tarifa_aplicar'] = getNumber($_POST, 'tarifa_aplicar');
        $data['cargos_agente'] = getNumber($_POST, 'cargos_agente');
        $data['other'] = getNumber($_POST, 'other');

        $data['fuel_escalation_rate'] = getNumber(
            $_POST,
            'fuel_escalation_rate',
            0.20
        );

        if ($data['fuel_escalation_rate'] > 1) {
            throw new InvalidArgumentException(
                'Fuel Escalation debe estar entre 0 y 1. Ejemplo: 0.20 = 20%.'
            );
        }

        $data['peak_season'] = $_POST['peak_season'] ?? '';

        if (!in_array(
            $data['peak_season'],
            ['', 'okra', 'general'],
            true
        )) {
            throw new InvalidArgumentException(
                'La modalidad Peak Season no es válida.'
            );
        }

        $data['collect_fee'] = isset($_POST['collect_fee']);
        $data['iva'] = isset($_POST['iva']);
        $data['fuel_escalation'] = isset($_POST['fuel_escalation']);

        // CÁLCULO FINAL EN PHP
        $results = calculate($data);

        // GUARDAR HISTORIAL
        $record = [
            'id' => bin2hex(random_bytes(4)),
            'date' => date('d/m/Y H:i:s'),
            'data' => $data,
            'results' => $results
        ];

        array_unshift($_SESSION['m6_history'], $record);

        $_SESSION['m6_history'] = array_slice(
            $_SESSION['m6_history'],
            0,
            MAX_HISTORY
        );

    } catch (InvalidArgumentException $e) {

        $errors[] = $e->getMessage();

    } catch (Throwable $e) {

        $errors[] = 'Ocurrió un error al procesar la cotización.';
    }
}

$history = $_SESSION['m6_history'];

// ---------------------------------------------------------
// FORMATO PARA JAVASCRIPT
// ---------------------------------------------------------

$jsData = [
    'peso_volumetrico' => (float)$data['peso_volumetrico'],
    'tarifa_min' => (float)$data['tarifa_min'],
    'tarifa_aplicar' => (float)$data['tarifa_aplicar'],
    'cargos_agente' => (float)$data['cargos_agente'],
    'other' => (float)$data['other'],
    'fuel_escalation_rate' => (float)$data['fuel_escalation_rate'],
    'peak_season' => $data['peak_season'],
    'collect_fee' => $data['collect_fee'],
    'iva' => $data['iva'],
    'fuel_escalation' => $data['fuel_escalation']
];

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<meta name="theme-color" content="#080e1c">

<title>Sistema M6 | Validación de Guías</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

/* =====================================================
   VARIABLES
===================================================== */

:root {
    --bg: #080e1c;
    --panel: #101a2e;
    --panel-light: #15223a;
    --border: rgba(148,163,184,.13);

    --primary: #38bdf8;
    --primary-dark: #2563eb;

    --success: #22c55e;
    --danger: #f87171;

    --text: #f1f5f9;
    --muted: #94a3b8;

    --radius: 18px;
}

/* =====================================================
   RESET
===================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background:
        radial-gradient(ellipse at top left, #12305b 0%, transparent 35%),
        radial-gradient(ellipse at bottom right, #102747 0%, transparent 35%),
        var(--bg);

    min-height: 100vh;
    color: var(--text);
    padding: 35px 18px;
}

button, input, select {
    font-family: inherit;
}

/* =====================================================
   CONTENEDOR
===================================================== */

.container {
    max-width: 1050px;
    margin: auto;
}

/* =====================================================
   HEADER
===================================================== */

.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 30px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 15px;
}

.brand-icon {
    width: 58px;
    height: 58px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 17px;

    background: linear-gradient(135deg, #0ea5e9, #2563eb);

    box-shadow: 0 8px 30px rgba(14,165,233,.25);

    font-size: 25px;
}

.brand h1 {
    font-size: 27px;
    font-weight: 800;
    letter-spacing: -.8px;
}

.brand p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 5px;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.status {
    display: flex;
    align-items: center;
    gap: 8px;

    background: rgba(34,197,94,.09);
    border: 1px solid rgba(34,197,94,.2);

    padding: 10px 14px;
    border-radius: 30px;

    color: #4ade80;
    font-size: 12px;
    font-weight: 600;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 10px #22c55e;
}

/* =====================================================
   PANEL
===================================================== */

.panel {
    background: rgba(16,26,46,.92);
    border: 1px solid var(--border);
    border-radius: 24px;

    padding: 30px;

    box-shadow: 0 20px 60px rgba(0,0,0,.25);

    backdrop-filter: blur(15px);
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 25px;
}

.section-heading i {
    color: var(--primary);
    font-size: 19px;
}

.section-heading h2 {
    font-size: 17px;
    font-weight: 700;
}

.section-heading p {
    color: var(--muted);
    font-size: 12px;
    margin-top: 5px;
}

/* =====================================================
   FORMULARIO
===================================================== */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    color: #cbd5e1;
    font-size: 12px;
    font-weight: 600;
}

.input-wrapper {
    position: relative;
}

.input-wrapper i {
    position: absolute;
    top: 50%;
    left: 15px;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 14px;
    pointer-events: none;
}

input, select {
    width: 100%;
    height: 49px;

    background: #0b1426;

    border: 1px solid var(--border);
    border-radius: 12px;

    color: white;
    font-size: 14px;

    padding: 0 15px 0 43px;

    outline: none;
    transition: .25s;
}

select {
    padding-left: 15px;
    cursor: pointer;
}

input:focus, select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(56,189,248,.10);
}

input::placeholder {
    color: #64748b;
}

.input-wrapper small {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 11px;
    pointer-events: none;
}

/* =====================================================
   OPCIONES
===================================================== */

.options-title {
    margin: 30px 0 15px;
    font-size: 13px;
    color: #cbd5e1;
    font-weight: 700;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.option-card {
    background: #0b1426;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 15px;

    display: flex;
    align-items: center;
    gap: 11px;

    cursor: pointer;
    transition: .2s;
}

.option-card:hover {
    border-color: rgba(56,189,248,.5);
}

.option-card input {
    width: 17px;
    height: 17px;
    padding: 0;
    accent-color: #0ea5e9;
    cursor: pointer;
}

.option-card span {
    font-size: 12px;
    font-weight: 600;
}

/* =====================================================
   BOTONES
===================================================== */

.button-group {
    display: flex;
    gap: 12px;
    margin-top: 28px;
}

.btn {
    min-height: 48px;
    border: none;
    border-radius: 12px;

    padding: 0 22px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;

    font-size: 13px;
    font-weight: 700;

    cursor: pointer;
    transition: .25s;
    text-decoration: none;
}

.btn-primary {
    color: white;
    background: linear-gradient(100deg, #0ea5e9, #2563eb);
    flex: 1;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(37,99,235,.3);
}

.btn-secondary {
    color: #cbd5e1;
    background: #1e293b;
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: #334155;
}

.btn-copy {
    color: #38bdf8;
    background: rgba(56,189,248,.10);
    border: 1px solid rgba(56,189,248,.2);
}

.btn-copy:hover {
    background: rgba(56,189,248,.18);
}

/* =====================================================
   RESULTADOS
===================================================== */

.result-section {
    margin-top: 28px;
}

.result-card {
    background: linear-gradient(145deg, #111f36, #0c1729);
    border: 1px solid rgba(56,189,248,.18);
    border-radius: 20px;
    padding: 25px;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 23px;
}

.result-header h2 {
    font-size: 17px;
}

.result-header span {
    color: #38bdf8;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.result-list {
    display: flex;
    flex-direction: column;
}

.result-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;

    padding: 13px 0;

    border-bottom: 1px solid rgba(255,255,255,.06);

    font-size: 13px;
}

.result-row span:first-child {
    color: #94a3b8;
}

.result-row strong {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.result-row.subtotal {
    margin-top: 10px;
    border-bottom: none;
}

.result-row.subtotal strong {
    color: #38bdf8;
    font-size: 16px;
}

.total-box {
    margin-top: 20px;
    padding: 23px;

    border-radius: 16px;

    background: linear-gradient(
        120deg,
        rgba(34,197,94,.12),
        rgba(16,185,129,.04)
    );

    border: 1px solid rgba(34,197,94,.2);

    text-align: center;
}

.total-box p {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    color: #86efac;
    text-transform: uppercase;
}

.total-box h3 {
    margin-top: 9px;
    font-size: 36px;
    font-weight: 800;
    color: #4ade80;
    letter-spacing: -1px;
    font-variant-numeric: tabular-nums;
}

.result-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

/* =====================================================
   HISTORIAL
===================================================== */

.history-section {
    margin-top: 28px;
}

.history-card {
    background: rgba(16,26,46,.92);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 25px;
}

.history-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.history-header h2 {
    font-size: 16px;
}

.history-count {
    background: rgba(56,189,248,.10);
    color: #38bdf8;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;

    background: #0b1426;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 15px;
}

.history-item p {
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.history-item strong {
    color: #4ade80;
    font-size: 15px;
    white-space: nowrap;
}

.empty-history {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

.empty-history i {
    font-size: 30px;
    margin-bottom: 12px;
}

/* =====================================================
   ALERTAS Y FOOTER
===================================================== */

.alert {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert-error {
    background: rgba(239,68,68,.10);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,.2);
}

.footer {
    text-align: center;
    margin-top: 28px;
    color: #64748b;
    font-size: 11px;
    letter-spacing: .5px;
}

.footer strong {
    color: #94a3b8;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:700px) {

    body {
        padding: 20px 12px;
    }

    .panel, .history-card {
        padding: 20px;
    }

    .header {
        align-items: flex-start;
    }

    .brand h1 {
        font-size: 22px;
    }

    .status {
        font-size: 0;
        padding: 12px;
    }

    .status-dot {
        width: 9px;
        height: 9px;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 17px;
    }

    .form-group.full {
        grid-column: auto;
    }

    .options-grid {
        grid-template-columns: 1fr;
    }

    .button-group, .result-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .total-box h3 {
        font-size: 30px;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <header class="header">

        <div class="brand">

            <div class="brand-icon">
                <i class="fas fa-plane-departure"></i>
            </div>

            <div>
                <h1>Sistema M6</h1>
                <p>Validación de Guías</p>
            </div>

        </div>

        <div class="status">
            <span class="status-dot"></span>
            Sistema operativo
        </div>

    </header>

    <!-- CALCULADORA -->

    <main class="panel">

        <div class="section-heading">

            <i class="fas fa-calculator"></i>

            <div>
                <h2>Calculadora de Tarifas</h2>
                <p>Ingresá los datos para calcular el costo de la guía.</p>
            </div>

        </div>

        <?php foreach ($errors as $error): ?>

            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <?= escape($error) ?>
            </div>

        <?php endforeach; ?>

        <form method="POST" id="calcForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['m6_token']) ?>"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label for="peso_volumetrico">
                        Peso volumétrico (kg)
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-weight-hanging"></i>

                        <input
                            type="number"
                            id="peso_volumetrico"
                            name="peso_volumetrico"
                            min="0"
                            step="0.01"
                            placeholder="Ej. 150"
                            required
                            value="<?= escape($data['peso_volumetrico']) ?>"
                        >
                    </div>

                </div>

                <div class="form-group">

                    <label for="tarifa_min">
                        Tarifa mínima (USD)
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-dollar-sign"></i>

                        <input
                            type="number"
                            id="tarifa_min"
                            name="tarifa_min"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            value="<?= escape($data['tarifa_min']) ?>"
                        >
                    </div>

                </div>

                <div class="form-group">

                    <label for="tarifa_aplicar">
                        Tarifa a aplicar (USD)
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-tags"></i>

                        <input
                            type="number"
                            id="tarifa_aplicar"
                            name="tarifa_aplicar"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            value="<?= escape($data['tarifa_aplicar']) ?>"
                        >
                    </div>

                </div>

                <div class="form-group">

                    <label for="cargos_agente">
                        Cargos de agente (USD)
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-user-tie"></i>

                        <input
                            type="number"
                            id="cargos_agente"
                            name="cargos_agente"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            value="<?= escape($data['cargos_agente']) ?>"
                        >
                    </div>

                </div>

                <div class="form-group">

                    <label for="other">
                        Otros cargos (USD)
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-receipt"></i>

                        <input
                            type="number"
                            id="other"
                            name="other"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            value="<?= escape($data['other']) ?>"
                        >
                    </div>

                </div>

                <div class="form-group">

                    <label for="peak_season">
                        Peak Season
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-calendar-alt"></i>

                        <select name="peak_season" id="peak_season">

                            <option value="">Sin Peak Season</option>

                            <option
                                value="okra"
                                <?= $data['peak_season'] === 'okra' ? 'selected' : '' ?>
                            >
                                OKRA
                            </option>

                            <option
                                value="general"
                                <?= $data['peak_season'] === 'general' ? 'selected' : '' ?>
                            >
                                Carga General
                            </option>

                        </select>
                    </div>

                </div>

                <div class="form-group">

                    <label for="fuel_escalation_rate">
                        Fuel Escalation Rate
                    </label>

                    <div class="input-wrapper">
                        <i class="fas fa-gas-pump"></i>

                        <input
                            type="number"
                            id="fuel_escalation_rate"
                            name="fuel_escalation_rate"
                            min="0"
                            max="1"
                            step="0.01"
                            value="<?= escape($data['fuel_escalation_rate']) ?>"
                        >

                        <small>0.20 = 20%</small>
                    </div>

                </div>

            </div>

            <!-- OPCIONES -->

            <h3 class="options-title">
                <i class="fas fa-sliders"></i>
                Cargos adicionales
            </h3>

            <div class="options-grid">

                <label class="option-card">

                    <input
                        type="checkbox"
                        name="collect_fee"
                        id="collect_fee"
                        <?= $data['collect_fee'] ? 'checked' : '' ?>
                    >

                    <span>Collect Fee</span>

                </label>

                <label class="option-card">

                    <input
                        type="checkbox"
                        name="iva"
                        id="iva"
                        <?= $data['iva'] ? 'checked' : '' ?>
                    >

                    <span>Aplicar IVA (13%)</span>

                </label>

                <label class="option-card">

                    <input
                        type="checkbox"
                        name="fuel_escalation"
                        id="fuel_escalation"
                        <?= $data['fuel_escalation'] ? 'checked' : '' ?>
                    >

                    <span>Fuel Escalation</span>

                </label>

            </div>

            <!-- BOTONES -->

            <div class="button-group">

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar cotización
                </button>

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="resetBtn"
                >
                    <i class="fas fa-rotate-left"></i>
                    Limpiar
                </button>

            </div>

        </form>

    </main>

    <!-- RESULTADOS -->

    <section class="result-section">

        <div class="result-card">

            <div class="result-header">

                <h2>
                    <i class="fas fa-file-invoice-dollar"></i>
                    Resumen de cotización
                </h2>

                <span>
                    <i class="fas fa-bolt"></i>
                    En vivo
                </span>

            </div>

            <div class="result-list">

                <div class="result-row">
                    <span>Flete</span>
                    <strong id="r_flete">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Fuel</span>
                    <strong id="r_fuel">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Fuel Escalation</span>
                    <strong id="r_fuel_escalation">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Security</span>
                    <strong id="r_security">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>PBA</span>
                    <strong id="r_pba">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Cargos de agente</span>
                    <strong id="r_cargos_agente">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Otros cargos</span>
                    <strong id="r_other">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Collect Fee</span>
                    <strong id="r_collect_fee">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>Peak Season</span>
                    <strong id="r_peak_season">$0.00</strong>
                </div>

                <div class="result-row subtotal">
                    <span>Subtotal</span>
                    <strong id="r_subtotal">$0.00</strong>
                </div>

                <div class="result-row">
                    <span>IVA (13%)</span>
                    <strong id="r_iva">$0.00</strong>
                </div>

            </div>

            <div class="total-box">

                <p>Total a pagar</p>

                <h3 id="r_total">$0.00</h3>

            </div>

            <div class="result-actions">

                <button
                    type="button"
                    class="btn btn-copy"
                    id="copyBtn"
                >
                    <i class="fas fa-copy"></i>
                    Copiar resumen
                </button>

            </div>

        </div>

    </section>

    <!-- HISTORIAL -->

    <section class="history-section">

        <div class="history-card">

            <div class="history-header">

                <h2>
                    <i class="fas fa-clock-rotate-left"></i>
                    Historial de cotizaciones
                </h2>

                <span class="history-count">
                    <?= count($history) ?> / <?= MAX_HISTORY ?>
                </span>

            </div>

            <div class="history-list">

                <?php if (empty($history)): ?>

                    <div class="empty-history">

                        <i class="fas fa-folder-open"></i>

                        <p>
                            Todavía no hay cotizaciones guardadas.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($history as $item): ?>

                        <div class="history-item">

                            <div>

                                <b>
                                    Cotización #<?= escape($item['id']) ?>
                                </b>

                                <p>
                                    <?= escape($item['date']) ?>
                                    ·
                                    Peso:
                                    <?= number_format(
                                        $item['data']['peso_volumetrico'],
                                        2
                                    ) ?> kg
                                </p>

                            </div>

                            <strong>
                                <?= money((float)$item['results']['total']) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <?php if (!empty($history)): ?>

                <div style="margin-top:18px;">

                    <a
                        href="?clear_history=1"
                        class="btn btn-secondary"
                        onclick="return confirm('¿Seguro que querés borrar todo el historial?')"
                    >
                        <i class="fas fa-trash"></i>
                        Borrar historial
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <!-- FOOTER -->

    <footer class="footer">

        SISTEMA M6 © <?= date('Y') ?>

        <br><br>

        <strong>VALIDACIÓN DE GUÍAS</strong>
        · USD · PREMIUM EDITION

    </footer>

</div>

<script>

/* =====================================================
   SISTEMA M6 - MOTOR DE CÁLCULO AUTOMÁTICO
===================================================== */

const form = document.getElementById('calcForm');

const numberFields = [
    'peso_volumetrico',
    'tarifa_min',
    'tarifa_aplicar',
    'cargos_agente',
    'other',
    'fuel_escalation_rate'
];

const formatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
});

function getValue(id) {

    const element = document.getElementById(id);

    if (!element) return 0;

    const value = parseFloat(element.value);

    return Number.isFinite(value) && value > 0
        ? value
        : 0;
}

function getChecked(id) {
    return document.getElementById(id).checked;
}

function setMoney(id, value) {

    document.getElementById(id).textContent =
        formatter.format(value);
}

function calculateLive() {

    const peso = getValue('peso_volumetrico');
    const tarifaMin = getValue('tarifa_min');
    const tarifaAplicar = getValue('tarifa_aplicar');
    const cargosAgente = getValue('cargos_agente');
    const other = getValue('other');

    const rate = getValue('fuel_escalation_rate');

    const peakSeason =
        document.getElementById('peak_season').value;

    const collectEnabled = getChecked('collect_fee');
    const ivaEnabled = getChecked('iva');
    const fuelEnabled = getChecked('fuel_escalation');

    // FLETE
    const flete = tarifaMin > 0
        ? tarifaMin
        : tarifaAplicar * peso;

    // FUEL
    const fuel = peso > 0
        ? Math.max(15, 0.44 * peso)
        : 0;

    // SECURITY
    const security = peso > 0
        ? Math.max(10, 0.10 * peso)
        : 0;

    // PBA
    const pba = cargosAgente > 0
        ? Math.max(35, cargosAgente * 0.12)
        : 0;

    // COLLECT FEE
    const collectFee = Math.max(25, flete * 0.05);

    // PEAK SEASON
    let peak = 0;

    if (peakSeason === 'okra') {
        peak = Math.max(10, 0.02 * peso);
    }

    if (peakSeason === 'general') {
        peak = Math.max(10, 0.05 * peso);
    }

    // FUEL ESCALATION
    const fuelEscalation = fuelEnabled
        ? peso * rate
        : 0;

    // COLLECT APLICADO
    const collectApplied = collectEnabled
        ? collectFee
        : 0;

    // SUBTOTAL
    const subtotal =
        flete +
        fuel +
        fuelEscalation +
        security +
        pba +
        collectApplied +
        cargosAgente +
        other +
        peak;

    // IVA
    const iva = ivaEnabled
        ? subtotal * 0.13
        : 0;

    // TOTAL
    const total = subtotal + iva;

    // MOSTRAR RESULTADOS
    setMoney('r_flete', flete);
    setMoney('r_fuel', fuel);
    setMoney('r_fuel_escalation', fuelEscalation);
    setMoney('r_security', security);
    setMoney('r_pba', pba);
    setMoney('r_cargos_agente', cargosAgente);
    setMoney('r_other', other);
    setMoney('r_collect_fee', collectApplied);
    setMoney('r_peak_season', peak);
    setMoney('r_subtotal', subtotal);
    setMoney('r_iva', iva);
    setMoney('r_total', total);

    return {
        flete,
        fuel,
        fuelEscalation,
        security,
        pba,
        cargosAgente,
        other,
        collectApplied,
        peak,
        subtotal,
        iva,
        total
    };
}

/* =====================================================
   ACTUALIZACIÓN EN TIEMPO REAL
===================================================== */

form.addEventListener('input', calculateLive);
form.addEventListener('change', calculateLive);

calculateLive();

/* =====================================================
   LIMPIAR FORMULARIO
===================================================== */

document.getElementById('resetBtn').addEventListener('click', () => {

    if (!confirm('¿Querés limpiar todos los campos?')) {
        return;
    }

    window.location.href = window.location.pathname;

});

/* =====================================================
   COPIAR RESUMEN
===================================================== */

document.getElementById('copyBtn').addEventListener('click', async () => {

    const result = calculateLive();

    const peso = getValue('peso_volumetrico');

    const peak = document.getElementById('peak_season');

    const peakLabel =
        peak.value === 'okra'
            ? 'OKRA'
            : peak.value === 'general'
                ? 'Carga General'
                : 'Sin Peak Season';

    const lines = [
        '================================',
        '       SISTEMA M6',
        '    VALIDACIÓN DE GUÍAS',
        '================================',
        '',
        'Peso volumétrico: ' + peso.toFixed(2) + ' kg',
        'Peak Season: ' + peakLabel,
        '',
        'DETALLE DE CARGOS',
        '--------------------------------',
        'Flete: ' + formatter.format(result.flete),
        'Fuel: ' + formatter.format(result.fuel),
        'Fuel Escalation: ' + formatter.format(result.fuelEscalation),
        'Security: ' + formatter.format(result.security),
        'PBA: ' + formatter.format(result.pba),
        'Cargos de agente: ' + formatter.format(result.cargosAgente),
        'Otros cargos: ' + formatter.format(result.other),
        'Collect Fee: ' + formatter.format(result.collectApplied),
        'Peak Season: ' + formatter.format(result.peak),
        '--------------------------------',
        'Subtotal: ' + formatter.format(result.subtotal),
        'IVA: ' + formatter.format(result.iva),
        '',
        'TOTAL: ' + formatter.format(result.total),
        '',
        '================================',
        '       SISTEMA M6',
        '================================'
    ];

    const text = lines.join('\n');

    const button = document.getElementById('copyBtn');

    try {

        await navigator.clipboard.writeText(text);

        button.innerHTML =
            '<i class="fas fa-check"></i> ¡Copiado!';

        setTimeout(() => {

            button.innerHTML =
                '<i class="fas fa-copy"></i> Copiar resumen';

        }, 2000);

    } catch (error) {

        alert('No se pudo copiar. Verificá los permisos del navegador.');

    }

});

</script>

</body>
</html>
