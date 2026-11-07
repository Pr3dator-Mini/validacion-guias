
<?php
// SISTEMA M6 - PREMIUM 3.0
// IMPORTANTE: guardar este archivo como UTF-8 SIN BOM.

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ======================================================
// CONFIGURACIÓN
// ======================================================

const MAX_HISTORY = 20;
const IVA_RATE = 0.13;
const FUEL_RATE = 0.44;
const SECURITY_RATE = 0.10;
const PBA_RATE = 0.12;
const COLLECT_RATE = 0.05;

if (!isset($_SESSION['m6_history']) || !is_array($_SESSION['m6_history'])) {
    $_SESSION['m6_history'] = [];
}

if (empty($_SESSION['m6_token'])) {
    $_SESSION['m6_token'] = bin2hex(random_bytes(32));
}

// ======================================================
// FUNCIONES
// ======================================================

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function usd($value) {
    return '$' . number_format((float)$value, 2, '.', ',');
}

function getNumber($source, $key, $default = 0) {

    if (!isset($source[$key]) || $source[$key] === '') {
        return (float)$default;
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
            'No se permiten valores negativos.'
        );
    }

    return $value;
}

// ======================================================
// MOTOR DE CÁLCULO ORIGINAL
// ======================================================

function calculateM6($data) {

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
    $fuel = $peso
        ? max(15, 0.44 * $peso)
        : 0;

    // SECURITY
    $security = $peso
        ? max(10, 0.10 * $peso)
        : 0;

    // PBA
    $pba = $cargosAgente
        ? max(35, $cargosAgente * 0.12)
        : 0;

    // COLLECT FEE
    $collectFee = max(25, $flete * 0.05);

    // PEAK SEASON
    $peakSeasonCharge = 0;

    if ($data['peak_season'] === 'okra') {
        $peakSeasonCharge = max(10, 0.02 * $peso);
    } elseif ($data['peak_season'] === 'general') {
        $peakSeasonCharge = max(10, 0.05 * $peso);
    }

    // FUEL ESCALATION
    $fuelEscalation = $data['fuel_escalation']
        ? ($peso * $rate)
        : 0;

    // COLLECT FEE APLICADO
    $collectApplied = $data['collect_fee']
        ? $collectFee
        : 0;

    // SUBTOTAL ORIGINAL
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
        ? ($subtotal * 0.13)
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

// ======================================================
// VALORES INICIALES
// ======================================================

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

// ======================================================
// BORRAR HISTORIAL
// ======================================================

if (isset($_GET['clear_history'])) {

    $token = isset($_GET['token']) ? (string)$_GET['token'] : '';

    if (!hash_equals($_SESSION['m6_token'], $token)) {
        http_response_code(403);
        exit('Solicitud no autorizada.');
    }

    $_SESSION['m6_history'] = [];

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ======================================================
// PROCESAR COTIZACIÓN
// ======================================================

if ($isPost) {

    try {

        $token = isset($_POST['csrf_token'])
            ? (string)$_POST['csrf_token']
            : '';

        if (!hash_equals($_SESSION['m6_token'], $token)) {
            throw new InvalidArgumentException(
                'La sesión expiró. Recargá la página.'
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

        $data['peak_season'] = isset($_POST['peak_season'])
            ? (string)$_POST['peak_season']
            : '';

        if (!in_array($data['peak_season'], ['', 'okra', 'general'], true)) {
            throw new InvalidArgumentException('Peak Season inválido.');
        }

        $data['collect_fee'] = isset($_POST['collect_fee']);
        $data['iva'] = isset($_POST['iva']);
        $data['fuel_escalation'] = isset($_POST['fuel_escalation']);

        if ($data['peso_volumetrico'] <= 0) {
            throw new InvalidArgumentException(
                'El peso volumétrico debe ser mayor que cero.'
            );
        }

        // CÁLCULO DEFINITIVO EN PHP
        $results = calculateM6($data);

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

    } catch (InvalidArgumentException $exception) {

        $errors[] = $exception->getMessage();

    } catch (Throwable $exception) {

        $errors[] = 'Ocurrió un error al procesar la cotización.';
    }
}

$history = $_SESSION['m6_history'];

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

/* ======================================================
   RESET Y CONFIGURACIÓN GLOBAL
====================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    width: 100%;
    min-height: 100%;
    overflow-x: hidden;
}

body {
    display: block;
    width: 100%;
    min-height: 100vh;
    overflow-x: hidden;

    font-family: 'Inter', sans-serif;
    color: #f1f5f9;

    background:
        radial-gradient(ellipse at 10% 0%, rgba(14,165,233,.16), transparent 35%),
        radial-gradient(ellipse at 95% 90%, rgba(37,99,235,.12), transparent 35%),
        #080e1c;

    padding: 35px 20px;
}

button, input, select {
    font-family: inherit;
}

button {
    -webkit-tap-highlight-color: transparent;
}

/* ======================================================
   CONTENEDOR CENTRADO
====================================================== */

.container {
    display: block;
    width: 100%;
    max-width: 960px;
    margin: 0 auto;
}

/* ======================================================
   HEADER
====================================================== */

.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}

.brand-icon {
    width: 58px;
    height: 58px;
    flex-shrink: 0;

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
    font-size: 11px;
    color: #94a3b8;
    margin-top: 5px;
    letter-spacing: 1.5px;
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
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 10px #22c55e;
}

/* ======================================================
   PANELES
====================================================== */

.panel,
.history-card {
    width: 100%;
    min-width: 0;

    background: rgba(16,26,46,.94);
    border: 1px solid rgba(148,163,184,.13);
    border-radius: 22px;

    padding: 28px;

    box-shadow: 0 20px 55px rgba(0,0,0,.22);
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 13px;
    margin-bottom: 25px;
}

.section-heading > i {
    color: #38bdf8;
    font-size: 21px;
}

.section-heading h2 {
    font-size: 17px;
    font-weight: 700;
}

.section-heading p {
    color: #94a3b8;
    font-size: 12px;
    margin-top: 5px;
}

/* ======================================================
   FORMULARIO
====================================================== */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 9px;
    min-width: 0;
}

.form-group label {
    color: #cbd5e1;
    font-size: 12px;
    font-weight: 600;
}

.input-wrapper {
    position: relative;
    width: 100%;
    min-width: 0;
}

.input-wrapper > i {
    position: absolute;
    top: 50%;
    left: 15px;
    transform: translateY(-50%);

    color: #64748b;
    font-size: 14px;

    pointer-events: none;
}

.input-wrapper input,
.input-wrapper select {
    width: 100%;
    height: 49px;

    border: 1px solid rgba(148,163,184,.15);
    border-radius: 12px;

    background: #0b1426;
    color: #f8fafc;

    font-size: 13px;

    outline: none;
    transition: .25s;
}

.input-wrapper input {
    padding: 0 15px 0 43px;
}

.input-wrapper select {
    padding: 0 35px 0 43px;
    cursor: pointer;
}

.input-wrapper input:focus,
.input-wrapper select:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px rgba(56,189,248,.10);
}

.input-wrapper input::placeholder {
    color: #64748b;
}

.input-wrapper select option {
    background: #0b1426;
}

/* ======================================================
   OPCIONES
====================================================== */

.options-title {
    margin: 28px 0 15px;
    color: #cbd5e1;
    font-size: 13px;
    font-weight: 700;
}

.options-title i {
    color: #38bdf8;
    margin-right: 5px;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.option-card {
    display: flex;
    align-items: center;
    gap: 10px;

    min-width: 0;
    padding: 15px;

    background: #0b1426;
    border: 1px solid rgba(148,163,184,.14);
    border-radius: 12px;

    cursor: pointer;
    transition: .2s;
}

.option-card:hover {
    border-color: rgba(56,189,248,.5);
    background: #101e34;
}

.option-card input {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    accent-color: #0ea5e9;
    cursor: pointer;
}

.option-card span {
    font-size: 11px;
    font-weight: 600;
    color: #cbd5e1;
}

/* ======================================================
   BOTONES
====================================================== */

.button-group {
    display: flex;
    gap: 12px;
    margin-top: 28px;
}

.btn {
    min-height: 48px;
    padding: 0 20px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;

    border: 1px solid transparent;
    border-radius: 12px;

    font-size: 12px;
    font-weight: 700;

    text-decoration: none;
    cursor: pointer;

    transition: .2s;
}

.btn-primary {
    flex: 1;
    color: white;
    background: linear-gradient(100deg, #0ea5e9, #2563eb);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37,99,235,.3);
}

.btn-secondary {
    color: #cbd5e1;
    background: #1e293b;
    border-color: rgba(148,163,184,.12);
}

.btn-secondary:hover {
    background: #334155;
}

.btn-copy {
    color: #38bdf8;
    background: rgba(56,189,248,.10);
    border-color: rgba(56,189,248,.2);
}

.btn-copy:hover {
    background: rgba(56,189,248,.18);
}

/* ======================================================
   RESULTADOS
====================================================== */

.result-section {
    width: 100%;
    margin-top: 25px;
}

.result-card {
    width: 100%;
    padding: 26px;

    background: linear-gradient(145deg, #111f36, #0c1729);
    border: 1px solid rgba(56,189,248,.19);
    border-radius: 20px;
}

.result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.result-header h2 {
    font-size: 16px;
}

.result-header h2 i {
    color: #38bdf8;
    margin-right: 6px;
}

.live-label {
    color: #38bdf8;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    white-space: nowrap;
}

.result-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;

    padding: 12px 0;

    border-bottom: 1px solid rgba(255,255,255,.055);

    font-size: 12px;
}

.result-row span:first-child {
    color: #94a3b8;
}

.result-row strong {
    color: #e2e8f0;
    font-size: 12px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.result-row.subtotal {
    border: none;
    padding-top: 18px;
}

.result-row.subtotal strong {
    color: #38bdf8;
    font-size: 15px;
}

.total-box {
    margin-top: 16px;
    padding: 23px;

    border-radius: 15px;

    background: linear-gradient(
        120deg,
        rgba(34,197,94,.12),
        rgba(16,185,129,.04)
    );

    border: 1px solid rgba(34,197,94,.2);
    text-align: center;
}

.total-box p {
    color: #86efac;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.total-box h3 {
    margin-top: 10px;
    color: #4ade80;
    font-size: 35px;
    font-weight: 800;
    letter-spacing: -1px;
    font-variant-numeric: tabular-nums;
}

.result-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

.copy-feedback {
    min-height: 16px;
    margin-top: 10px;
    color: #4ade80;
    font-size: 11px;
    text-align: center;
}

/* ======================================================
   HISTORIAL
====================================================== */

.history-section {
    margin-top: 25px;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.history-header h2 {
    font-size: 16px;
}

.history-header h2 i {
    color: #38bdf8;
    margin-right: 7px;
}

.history-count {
    padding: 6px 11px;
    border-radius: 20px;
    background: rgba(56,189,248,.1);
    color: #38bdf8;
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
    gap: 12px;

    padding: 15px;

    background: #0b1426;
    border: 1px solid rgba(148,163,184,.12);
    border-radius: 12px;
}

.history-item b {
    font-size: 12px;
}

.history-item p {
    color: #94a3b8;
    font-size: 10px;
    margin-top: 6px;
}

.history-item strong {
    color: #4ade80;
    font-size: 14px;
    white-space: nowrap;
}

.empty-history {
    padding: 30px;
    text-align: center;
    color: #64748b;
}

.empty-history i {
    display: block;
    font-size: 30px;
    margin-bottom: 12px;
}

.history-footer {
    margin-top: 18px;
}

/* ======================================================
   ALERTAS
====================================================== */

.alert {
    padding: 13px 15px;
    margin-bottom: 18px;
    border-radius: 10px;
    font-size: 12px;
}

.alert-error {
    color: #fca5a5;
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.2);
}

/* ======================================================
   FOOTER
====================================================== */

.footer {
    margin-top: 28px;
    padding: 15px;
    text-align: center;
    color: #64748b;
    font-size: 10px;
    line-height: 1.8;
    letter-spacing: .7px;
}

.footer strong {
    color: #94a3b8;
}

/* ======================================================
   TABLET
====================================================== */

@media (max-width: 900px) {

    .container {
        max-width: 760px;
    }

}

/* ======================================================
   MÓVIL
====================================================== */

@media (max-width: 600px) {

    body {
        padding: 16px 11px;
    }

    .container {
        width: 100%;
        max-width: 100%;
    }

    .header {
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 20px;
    }

    .brand-icon {
        width: 49px;
        height: 49px;
        font-size: 21px;
    }

    .brand h1 {
        font-size: 21px;
    }

    .brand p {
        font-size: 9px;
    }

    .status {
        padding: 8px 11px;
        font-size: 10px;
    }

    .panel,
    .history-card,
    .result-card {
        padding: 18px;
        border-radius: 17px;
    }

    .form-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .options-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .button-group,
    .result-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .result-header h2 {
        font-size: 14px;
    }

    .total-box h3 {
        font-size: 30px;
    }

    .history-item {
        padding: 12px;
    }

    .history-item strong {
        font-size: 12px;
    }
}

@media (max-width: 360px) {

    .brand h1 {
        font-size: 18px;
    }

    .result-row {
        font-size: 11px;
    }

    .result-row strong {
        font-size: 11px;
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

    <!-- FORMULARIO -->

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
                <?= e($error) ?>
            </div>

        <?php endforeach; ?>

        <form method="POST" id="calcForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['m6_token']) ?>"
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
                            value="<?= e($data['peso_volumetrico']) ?>"
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
                            value="<?= e($data['tarifa_min']) ?>"
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
                            value="<?= e($data['tarifa_aplicar']) ?>"
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
                            value="<?= e($data['cargos_agente']) ?>"
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
                            value="<?= e($data['other']) ?>"
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
                            value="<?= e($data['fuel_escalation_rate']) ?>"
                        >
                    </div>

                </div>

            </div>

            <!-- CARGOS ADICIONALES -->

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

                <span class="live-label">
                    <i class="fas fa-bolt"></i>
                    EN VIVO
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

            <div class="copy-feedback" id="copyFeedback"></div>

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

                        <p>Todavía no hay cotizaciones guardadas.</p>

                    </div>

                <?php else: ?>

                    <?php foreach ($history as $item): ?>

                        <div class="history-item">

                            <div>

                                <b>
                                    Cotización #<?= e($item['id']) ?>
                                </b>

                                <p>
                                    <?= e($item['date']) ?>
                                    · Peso:
                                    <?= number_format(
                                        (float)$item['data']['peso_volumetrico'],
                                        2,
                                        '.',
                                        ','
                                    ) ?> kg
                                </p>

                            </div>

                            <strong>
                                <?= usd($item['results']['total']) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <?php if (!empty($history)): ?>

                <div class="history-footer">

                    <a
                        href="?clear_history=1&amp;token=<?= e($_SESSION['m6_token']) ?>"
                        class="btn btn-secondary"
                        onclick="return confirm('¿Eliminar todas las cotizaciones del historial?')"
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

        <br>

        <strong>VALIDACIÓN DE GUÍAS</strong>

        <br>

        PREMIUM EDITION · USD

    </footer>

</div>

<script>

/* ======================================================
   SISTEMA M6 - MOTOR AUTOMÁTICO
====================================================== */

const form = document.getElementById('calcForm');

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

function isChecked(id) {
    return document.getElementById(id).checked;
}

function showMoney(id, value) {

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

    const collectEnabled = isChecked('collect_fee');
    const ivaEnabled = isChecked('iva');
    const fuelEnabled = isChecked('fuel_escalation');

    // FLETE
    const flete = tarifaMin > 0
        ? tarifaMin
        : tarifaAplicar * peso;

    // FUEL
    const fuel = peso
        ? Math.max(15, 0.44 * peso)
        : 0;

    // SECURITY
    const security = peso
        ? Math.max(10, 0.10 * peso)
        : 0;

    // PBA
    const pba = cargosAgente
        ? Math.max(35, cargosAgente * 0.12)
        : 0;

    // COLLECT FEE
    const collectFee = Math.max(25, flete * 0.05);

    // PEAK SEASON
    let peak = 0;

    if (peakSeason === 'okra') {
        peak = Math.max(10, 0.02 * peso);
    } else if (peakSeason === 'general') {
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

    // SUBTOTAL ORIGINAL
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

    // ACTUALIZAR PANTALLA
    showMoney('r_flete', flete);
    showMoney('r_fuel', fuel);
    showMoney('r_fuel_escalation', fuelEscalation);
    showMoney('r_security', security);
    showMoney('r_pba', pba);
    showMoney('r_cargos_agente', cargosAgente);
    showMoney('r_other', other);
    showMoney('r_collect_fee', collectApplied);
    showMoney('r_peak_season', peak);
    showMoney('r_subtotal', subtotal);
    showMoney('r_iva', iva);
    showMoney('r_total', total);

    return {
        peso,
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

/* ======================================================
   ACTUALIZACIÓN EN TIEMPO REAL
====================================================== */

form.addEventListener('input', calculateLive);
form.addEventListener('change', calculateLive);

calculateLive();

/* ======================================================
   LIMPIAR - UN CLIC, SIN PREGUNTAS
   CONSERVA EL HISTORIAL
====================================================== */

document.getElementById('resetBtn').addEventListener('click', function() {

    window.location.href = window.location.pathname;

});

/* ======================================================
   COPIAR RESUMEN
====================================================== */

document.getElementById('copyBtn').addEventListener('click', async function() {

    const result = calculateLive();

    const peak = document.getElementById('peak_season');

    const peakLabel =
        peak.value === 'okra'
            ? 'OKRA'
            : peak.value === 'general'
                ? 'Carga General'
                : 'Sin Peak Season';

    const lines = [
        '================================',
        '          SISTEMA M6',
        '       VALIDACIÓN DE GUÍAS',
        '================================',
        '',
        'Peso volumétrico: ' + result.peso.toFixed(2) + ' kg',
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
        '          SISTEMA M6',
        '================================'
    ];

    const text = lines.join('\n');

    const feedback = document.getElementById('copyFeedback');
    const button = document.getElementById('copyBtn');

    try {

        if (navigator.clipboard && window.isSecureContext) {

            await navigator.clipboard.writeText(text);

        } else {

            const textarea = document.createElement('textarea');

            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';

            document.body.appendChild(textarea);
            textarea.select();

            const copied = document.execCommand('copy');

            textarea.remove();

            if (!copied) {
                throw new Error('No se pudo copiar');
            }
        }

        feedback.textContent = '✓ Resumen copiado correctamente';

        button.innerHTML =
            '<i class="fas fa-check"></i> ¡Copiado!';

        setTimeout(function() {

            button.innerHTML =
                '<i class="fas fa-copy"></i> Copiar resumen';

            feedback.textContent = '';

        }, 2500);

    } catch (error) {

        feedback.textContent =
            'No se pudo copiar. Revisá los permisos del navegador.';

    }

});

</script>

</body>
</html>
