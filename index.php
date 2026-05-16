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

$collectFeeAplica = isset($_POST['collect_fee']);
$ivaAplica        = isset($_POST['iva']);

$peakSeason = $_POST['peak_season'] ?? '';

// Cálculos
if ($isPost) {

    $flete = $tarifaMin > 0
        ? $tarifaMin
        : ($tarifaAplicar * $pesoVolumetrico);

    $fuel = $pesoVolumetrico
        ? max(15, 0.44 * $pesoVolumetrico)
        : 0;

    $security = $pesoVolumetrico
        ? max(10, 0.10 * $pesoVolumetrico)
        : 0;

    $pba = $cargosAgente
        ? max(35, $cargosAgente * 0.12)
        : 0;

    $collectFee = max(25, $flete * 0.05);

    // Peak Season
    $peakSeasonCharge = 0;

    if ($peakSeason === 'okra') {
        $peakSeasonCharge = max(10, 0.02 * $pesoVolumetrico);
    }

    elseif ($peakSeason === 'general') {
        $peakSeasonCharge = max(10, 0.05 * $pesoVolumetrico);
    }

    // Subtotal
    $subtotal =
        $flete +
        $fuel +
        $security +
        $pba +
        ($collectFeeAplica ? $collectFee : 0) +
        $cargosAgente +
        $other +
        $peakSeasonCharge;

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
    background:#0f172a;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
    color:white;
}

.container{
    width:100%;
    max-width:550px;
    background:rgba(30,41,59,0.95);
    border-radius:25px;
    padding:35px;
    box-shadow:0 0 30px rgba(0,0,0,0.5);
}

.title{
    text-align:center;
    font-size:38px;
    font-weight:700;
    margin-bottom:25px;
    color:#38bdf8;
}

.form-group{
    margin-bottom:18px;
}

input,
select{
    width:100%;
    padding:14px;
    border:none;
    border-radius:12px;
    background:#1e293b;
    color:white;
    font-size:15px;
    outline:none;
}

input::placeholder{
    color:#94a3b8;
}

.checkbox{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:15px;
}

.checkbox input{
    width:auto;
}

button{
    width:100%;
    padding:15px;
    border:none;
    border-radius:14px;
    background:#38bdf8;
    color:white;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#0ea5e9;
    transform:translateY(-2px);
}

.result{
    margin-top:30px;
    background:#1e293b;
    padding:20px;
    border-radius:15px;
}

.result h2{
    margin-bottom:15px;
    color:#38bdf8;
}

.result p{
    margin-bottom:10px;
    font-size:15px;
}

.total{
    margin-top:20px;
    font-size:26px;
    font-weight:bold;
    color:#22c55e;
    text-align:center;
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
placeholder="Peso Volumétrico"
required
value="<?= htmlspecialchars($pesoVolumetrico ?: '') ?>">
</div>

<div class="form-group">
<input
type="number"
name="tarifa_min"
step="0.01"
placeholder="Tarifa Mínima"
value="<?= htmlspecialchars($tarifaMin ?: '') ?>">
</div>

<div class="form-group">
<input
type="number"
name="tarifa_aplicar"
step="0.01"
placeholder="Tarifa a Aplicar"
value="<?= htmlspecialchars($tarifaAplicar ?: '') ?>">
</div>

<div class="form-group">
<input
type="number"
name="cargos_agente"
step="0.01"
placeholder="Cargos de Agente"
value="<?= htmlspecialchars($cargosAgente ?: '') ?>">
</div>

<div class="form-group">
<input
type="number"
name="other"
step="0.01"
placeholder="Otros Cargos"
value="<?= htmlspecialchars($other ?: '') ?>">
</div>

<div class="form-group">

<select name="peak_season">

<option value="">Peak Season</option>

<option value="okra"
<?= $peakSeason === 'okra' ? 'selected' : '' ?>>
OKRA
</option>

<option value="general"
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
<label>Aplicar Collect Fee</label>
</div>

<div class="checkbox">
<input
type="checkbox"
name="iva"
<?= $ivaAplica ? 'checked' : '' ?>>
<label>Aplicar IVA</label>
</div>

<button type="submit">
<i class="fas fa-calculator"></i>
Procesar
</button>

</form>

<?php if($isPost): ?>

<div class="result">

<h2>Resumen</h2>

<p>Flete: $<?= number_format($flete,2) ?></p>

<p>Fuel: $<?= number_format($fuel,2) ?></p>

<p>Security: $<?= number_format($security,2) ?></p>

<p>PBA: $<?= number_format($pba,2) ?></p>

<p>Collect Fee: $<?= number_format($collectFeeAplica ? $collectFee : 0,2) ?></p>

<p>Peak Season: $<?= number_format($peakSeasonCharge,2) ?></p>

<p>IVA: $<?= number_format($iva,2) ?></p>

<div class="total">
TOTAL: $<?= number_format($total,2) ?>
</div>

</div>

<?php endif; ?>

</div>

</body>
</html>
