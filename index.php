<?php
session_start();

function getInput($key, $default = '', $asFloat = false) {
    $value = $_POST[$key] ?? $default;
    return $asFloat ? floatval($value) : $value;
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// Captura de datos
$pesoVolumetrico = getInput('peso_volumetrico', 0, true);
$tarifaMin        = getInput('tarifa_min', 0, true);
$tarifaAplicar    = getInput('tarifa_aplicar', 0, true);
$cargosAgente     = getInput('cargos_agente', 0, true);
$other            = getInput('other', 0, true);

$collectFeeAplica     = isset($_POST['collect_fee']);
$ivaAplica            = isset($_POST['iva']);
$fuelEscalationAplica = isset($_POST['fuel_escalation']);

$peakSeason = $_POST['peak_season'] ?? '';

// Cálculos
if ($isPost) {

    // Flete
    $flete = $tarifaMin > 0
        ? $tarifaMin
        : ($tarifaAplicar * $pesoVolumetrico);

    // Fuel
    $fuel = $pesoVolumetrico
        ? max(15, 0.44 * $pesoVolumetrico)
        : 0;

    // Security
    $security = $pesoVolumetrico
        ? max(10, 0.10 * $pesoVolumetrico)
        : 0;

    // PBA
    $pba = $cargosAgente
        ? max(35, $cargosAgente * 0.12)
        : 0;

    // Collect Fee
    $collectFee = max(25, $flete * 0.05);

    // Peak Season
    $peakSeasonCharge = 0;

    if ($peakSeason === 'okra') {
        $peakSeasonCharge = max(10, 0.02 * $pesoVolumetrico);
    }

    elseif ($peakSeason === 'general') {
        $peakSeasonCharge = max(10, 0.05 * $pesoVolumetrico);
    }

    // Fuel Escalation
    $fuelEscalation = $fuelEscalationAplica
        ? ($pesoVolumetrico * 0.20)
        : 0;

    // Subtotal
    $subtotal =
        $flete +
        $fuel +
        $security +
        $pba +
        ($collectFeeAplica ? $collectFee : 0) +
        $cargosAgente +
        $other +
        $peakSeasonCharge +
        $fuelEscalation;

    // IVA
    $iva = $ivaAplica
        ? ($subtotal * 0.13)
        : 0;

    // Total
    $total = $subtotal + $iva;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sistema M6 - Validación de Guías</title>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{

    font-family:'Poppins',sans-serif;

    background:
    linear-gradient(
    135deg,
    #020617,
    #0f172a,
    #111827,
    #1e293b
    );

    min-height:100vh;

    display:flex;

    justify-content:center;

    align-items:center;

    padding:25px;

    color:white;
}

.container{

    width:100%;

    max-width:580px;

    background:rgba(15,23,42,0.85);

    backdrop-filter:blur(15px);

    border:1px solid rgba(255,255,255,0.08);

    border-radius:28px;

    padding:35px;

    box-shadow:
    0 10px 40px rgba(0,0,0,0.5);

}

.title{

    text-align:center;

    font-size:42px;

    font-weight:700;

    margin-bottom:30px;

    color:#38bdf8;

    letter-spacing:1px;
}

.title i{
    margin-right:10px;
}

.form-group{
    margin-bottom:18px;
}

input,
select{

    width:100%;

    padding:16px;

    border:none;

    border-radius:14px;

    background:#0f172a;

    border:1px solid rgba(255,255,255,0.08);

    color:white;

    font-size:15px;

    outline:none;

    transition:0.3s;
}

input:focus,
select:focus{

    border:1px solid #38bdf8;

    box-shadow:
    0 0 15px rgba(56,189,248,0.4);
}

input::placeholder{
    color:#94a3b8;
}

.checkbox{

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:16px;

    font-size:15px;
}

.checkbox input{
    width:auto;
    transform:scale(1.2);
}

button{

    width:100%;

    padding:16px;

    border:none;

    border-radius:16px;

    background:
    linear-gradient(
    90deg,
    #0ea5e9,
    #2563eb
    );

    color:white;

    font-size:17px;

    font-weight:600;

    cursor:pointer;

    transition:0.3s;
}

button:hover{

    transform:translateY(-2px);

    box-shadow:
    0 10px 25px rgba(37,99,235,0.4);
}

.result{

    margin-top:30px;

    background:#0f172a;

    border:1px solid rgba(255,255,255,0.08);

    padding:25px;

    border-radius:18px;
}

.result h2{

    margin-bottom:18px;

    color:#38bdf8;

    text-align:center;
}

.result p{

    margin-bottom:12px;

    font-size:15px;

    display:flex;

    justify-content:space-between;

    border-bottom:1px solid rgba(255,255,255,0.05);

    padding-bottom:8px;
}

.total{

    margin-top:25px;

    font-size:30px;

    font-weight:bold;

    color:#22c55e;

    text-align:center;
}

.footer-text{

    text-align:center;

    margin-top:20px;

    color:#64748b;

    font-size:13px;
}

@media(max-width:600px){

    .container{
        padding:25px;
    }

    .title{
        font-size:32px;
    }

}

</style>

</head>

<body>

<div class="container">

<div class="title">

    <i class="fas fa-plane"></i>

    Sistema M6

</div>

<form method="POST">

<div class="form-group">

<input
type="number"
name="peso_volumetrico"
step="0.01"
placeholder="Peso Volumétrico (kg)"
required
value="<?= htmlspecialchars($pesoVolumetrico ?: '') ?>">

</div>

<div class="form-group">

<input
type="number"
name="tarifa_min"
step="0.01"
placeholder="Tarifa Mínima ($)"
value="<?= htmlspecialchars($tarifaMin ?: '') ?>">

</div>

<div class="form-group">

<input
type="number"
name="tarifa_aplicar"
step="0.01"
placeholder="Tarifa a Aplicar ($)"
value="<?= htmlspecialchars($tarifaAplicar ?: '') ?>">

</div>

<div class="form-group">

<input
type="number"
name="cargos_agente"
step="0.01"
placeholder="Cargos de Agente ($)"
value="<?= htmlspecialchars($cargosAgente ?: '') ?>">

</div>

<div class="form-group">

<input
type="number"
name="other"
step="0.01"
placeholder="Otros Cargos ($)"
value="<?= htmlspecialchars($other ?: '') ?>">

</div>

<div class="form-group">

<select name="peak_season">

<option value="">
Peak Season
</option>

<option
value="okra"
<?= $peakSeason === 'okra' ? 'selected' : '' ?>>

OKRA

</option>

<option
value="general"
<?= $peakSeason === 'general' ? 'selected' : '' ?>>

Carga General

</option>

</select>

</div>

<div class="checkbox">

<input
type="checkbox"
name="collect_fee"
<?= $collectFeeAplica ? 'checked' : '' ?>>

<label>
Aplicar Collect Fee
</label>

</div>

<div class="checkbox">

<input
type="checkbox"
name="iva"
<?= $ivaAplica ? 'checked' : '' ?>>

<label>
Aplicar IVA
</label>

</div>

<div class="checkbox">

<input
type="checkbox"
name="fuel_escalation"
<?= $fuelEscalationAplica ? 'checked' : '' ?>>

<label>
Aplicar Fuel Escalation
</label>

</div>

<button type="submit">

<i class="fas fa-calculator"></i>

Procesar

</button>

</form>

<?php if($isPost): ?>

<div class="result">

<h2>
Resumen de Cálculos
</h2>

<p>
<span>Flete:</span>
<span>$<?= number_format($flete,2) ?></span>
</p>

<p>
<span>Fuel:</span>
<span>$<?= number_format($fuel,2) ?></span>
</p>

<p>
<span>Fuel Escalation:</span>
<span>$<?= number_format($fuelEscalation,2) ?></span>
</p>

<p>
<span>Security:</span>
<span>$<?= number_format($security,2) ?></span>
</p>

<p>
<span>PBA:</span>
<span>$<?= number_format($pba,2) ?></span>
</p>

<p>
<span>Collect Fee:</span>
<span>$<?= number_format($collectFeeAplica ? $collectFee : 0,2) ?></span>
</p>

<p>
<span>Peak Season:</span>
<span>$<?= number_format($peakSeasonCharge,2) ?></span>
</p>

<p>
<span>IVA:</span>
<span>$<?= number_format($iva,2) ?></span>
</p>

<div class="total">

TOTAL:
$<?= number_format($total,2) ?>

</div>

</div>

<?php endif; ?>

<div class="footer-text">

Sistema M6 • Validación de Guías

</div>

</div>

</body>
</html>
