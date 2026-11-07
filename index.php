
<?php
// ======================================================
// CÁLCULOS M6 - VALIDACIÓN DE GUÍAS
// SIN SESIONES - SIN HISTORIAL
// ======================================================

// ======================================================
// FUNCIONES
// ======================================================

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getNumber($key, $default = 0) {

    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        return (float)$default;
    }

    $value = filter_var($_POST[$key], FILTER_VALIDATE_FLOAT);

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

$data = [
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

$results = null;
$errors = [];

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// ======================================================
// PROCESAR
// ======================================================

if ($isPost) {

    try {

        $data['peso_volumetrico'] = getNumber('peso_volumetrico');
        $data['tarifa_min'] = getNumber('tarifa_min');
        $data['tarifa_aplicar'] = getNumber('tarifa_aplicar');
        $data['cargos_agente'] = getNumber('cargos_agente');
        $data['other'] = getNumber('other');

        $data['fuel_escalation_rate'] = getNumber(
            'fuel_escalation_rate',
            0.20
        );

        if ($data['fuel_escalation_rate'] > 1) {
            throw new InvalidArgumentException(
                'Fuel Escalation debe estar entre 0 y 1. Ejemplo: 0.20 = 20%.'
            );
        }

        $data['peak_season'] = $_POST['peak_season'] ?? '';

        if (!in_array($data['peak_season'], ['', 'okra', 'general'], true)) {
            throw new InvalidArgumentException(
                'La modalidad Peak Season no es válida.'
            );
        }

        $data['collect_fee'] = isset($_POST['collect_fee']);
        $data['iva'] = isset($_POST['iva']);
        $data['fuel_escalation'] = isset($_POST['fuel_escalation']);

        if ($data['peso_volumetrico'] <= 0) {
            throw new InvalidArgumentException(
                'El peso volumétrico debe ser mayor que cero.'
            );
        }

        $results = calculateM6($data);

    } catch (InvalidArgumentException $exception) {

        $errors[] = $exception->getMessage();

    } catch (Throwable $exception) {

        $errors[] = 'Ocurrió un error al procesar la cotización.';
    }
}


$tariffData = json_decode('[{"code":"ANU","name":"St Johns, Antigua","mode":"AIR","min":255.0,"rates":[5.67,2.57,2.21,2.21,2.21,2.21,2.21],"route":""},{"code":"AXA","name":"Anguilla, British WI ***","mode":"AIR","min":330.0,"rates":[7.33,3.35,2.99,2.99,2.99,2.84,2.84],"route":"VIA SMX/+1"},{"code":"BGI","name":"Christ Church, Barbados, WI","mode":"AIR","min":245.0,"rates":[5.44,2.49,2.13,2.13,2.13,2.13,2.13],"route":""},{"code":"BZE","name":"Belize City, Belize","mode":"AIR","min":240.0,"rates":[5.33,2.36,2.0,2.0,2.0,2.0,2.0],"route":""},{"code":"CUR","name":"Curacao, Netherlands Antilles","mode":"AIR","min":265.0,"rates":[5.89,2.69,2.33,2.33,2.33,2.33,2.33],"route":""},{"code":"DOM","name":"Marigot, Dominica ***","mode":"AIR","min":285.0,"rates":[6.33,2.88,2.51,2.51,2.51,2.51,2.51],"route":"VIA SMX/+1"},{"code":"EIS","name":"Tortola, Beef Island ***","mode":"AIR","min":310.0,"rates":[6.89,4.09,3.73,3.73,3.73,3.73,3.73],"route":"VIA SJU"},{"code":"GEO","name":"Georgetown, Guayana","mode":"AIR","min":275.0,"rates":[6.11,2.59,2.23,2.23,2.23,2.23,2.23],"route":""},{"code":"GND","name":"St Georges, Grenada, WI","mode":"AIR","min":295.0,"rates":[6.56,2.96,2.55,2.55,2.55,2.55,2.55],"route":""},{"code":"GUA","name":"Guatemala, GT","mode":"AIR","min":210.0,"rates":[210.0,4.57,1.55,1.55,1.41,1.35,1.35],"route":""},{"code":"KIN","name":"Kingston, Jamaica","mode":"AIR","min":220.0,"rates":[4.89,2.2,1.84,1.84,1.84,1.84,1.84],"route":""},{"code":"MGA","name":"Managua, Nicaragua","mode":"AIR","min":180.0,"rates":[4.0,1.15,1.15,1.15,1.15,1.15,1.15],"route":""},{"code":"MIA","name":"Miami, FL","mode":"AIR","min":125.0,"rates":[2.78,0.97,0.89,0.89,0.89,0.89,0.89],"route":""},{"code":"MNI","name":"Montserrat, BWI ***","mode":"AIR","min":315.0,"rates":[7.0,3.12,2.76,2.76,2.76,2.76,2.76],"route":""},{"code":"NLU","name":"Felipe Angeles, Mexico","mode":"AIR","min":275.0,"rates":[6.11,2.64,2.28,2.28,2.28,2.28,2.28],"route":""},{"code":"PAP","name":"Port Au Prince, Haiti, WI","mode":"AIR","min":435.0,"rates":[9.67,4.35,3.99,3.99,3.99,3.99,3.99],"route":""},{"code":"PBM","name":"Paramaribo, Suriname","mode":"AIR","min":275.0,"rates":[6.11,2.64,2.42,2.42,2.42,2.42,2.42],"route":""},{"code":"POP","name":"Puerto Plata, Dominican Republic ***","mode":"AIR","min":245.0,"rates":[5.44,2.49,2.13,2.13,2.13,2.13,2.13],"route":"VIA STI/ +1"},{"code":"POS","name":"Port of Spain, Trinidad and Tobago","mode":"AIR","min":260.0,"rates":[5.78,2.64,2.28,2.28,2.28,2.28,2.28],"route":""},{"code":"PTY","name":"Panama City, Panama","mode":"AIR","min":180.0,"rates":[4.0,1.25,1.25,1.25,1.25,1.25,1.25],"route":""},{"code":"PUJ","name":"Punta Cana, Dominican Republic","mode":"AIR","min":270.0,"rates":[6.0,2.74,2.52,2.52,2.52,2.52,2.52],"route":"VIA SDQ/+1"},{"code":"SAP","name":"San Pedro Sula, Honduras","mode":"AIR","min":180.0,"rates":[4.0,1.15,1.15,1.15,1.15,1.15,1.15],"route":""},{"code":"SBH","name":"St Barthelemy, FWI ***","mode":"AIR","min":335.0,"rates":[7.44,3.3,2.94,2.94,2.94,2.94,2.94],"route":"VIA SMX/+1"},{"code":"SDQ","name":"Santo Domingo, Dominican Republic","mode":"AIR","min":235.0,"rates":[5.22,2.16,1.8,1.8,1.8,1.8,1.8],"route":""},{"code":"SJU","name":"San Juan, Puerto Rico","mode":"AIR","min":315.0,"rates":[7.0,2.49,2.13,2.13,2.13,2.13,2.13],"route":""},{"code":"SKB","name":"St. Kitts, WI","mode":"AIR","min":275.0,"rates":[6.11,2.75,2.39,2.39,2.39,2.39,2.39],"route":""},{"code":"SLU","name":"Castries, St. Lucia, WI","mode":"AIR","min":290.0,"rates":[6.44,2.89,2.53,2.53,2.53,2.53,2.53],"route":""},{"code":"STI","name":"Santiago, Dominican Republic","mode":"AIR","min":235.0,"rates":[5.22,2.16,1.8,1.8,1.8,1.8,1.8],"route":""},{"code":"STT","name":"St Thomas, VI ***","mode":"AIR","min":425.0,"rates":[9.44,4.09,3.73,3.73,3.73,3.73,3.73],"route":"VIA SJU"},{"code":"STX","name":"St Croix, VI ***","mode":"AIR","min":425.0,"rates":[9.44,4.09,3.73,3.73,3.73,3.73,3.73],"route":"VIA SJU"},{"code":"SVD","name":"St. Vincent, WI","mode":"AIR","min":325.0,"rates":[7.22,2.94,2.58,2.58,2.58,2.58,2.58],"route":""},{"code":"SXM","name":"St. Maarten, Netherlands Antilles","mode":"AIR","min":275.0,"rates":[6.11,2.47,2.11,2.11,2.11,2.11,2.11],"route":""},{"code":"TGU","name":"Tegucigalpa, Honduras ***","mode":"AIR","min":255.0,"rates":[5.67,1.55,1.55,1.55,1.55,1.55,1.55],"route":"VIA XPL/ +1"},{"code":"UVF","name":"Hewannora, St Lucia","mode":"AIR","min":310.0,"rates":[6.89,2.89,2.53,2.53,2.53,2.53,2.53],"route":""},{"code":"XPL","name":"Palmerola, Honduras","mode":"AIR","min":185.0,"rates":[4.11,1.45,1.45,1.45,1.45,1.45,1.45],"route":""},{"code":"ABE","name":"Allentwn-Bethlehem-Eastom","mode":"TRUCK","min":360.0,"rates":[8.0,2.6,2.45,2.45,2.45,2.45,2.45],"route":""},{"code":"ABQ","name":"Albuquerque","mode":"TRUCK","min":285.0,"rates":[6.33,2.35,2.24,2.24,2.24,2.24,2.24],"route":""},{"code":"ALB","name":"Albany New York,","mode":"TRUCK","min":285.0,"rates":[6.33,2.2,2.05,2.05,2.05,2.05,2.05],"route":""},{"code":"ATL","name":"Atlanta, GA","mode":"TRUCK","min":185.0,"rates":[4.11,1.39,1.27,1.27,1.27,1.27,1.27],"route":""},{"code":"AUS","name":"Austin","mode":"TRUCK","min":205.0,"rates":[4.56,1.85,1.71,1.71,1.71,1.71,1.71],"route":""},{"code":"BDL","name":"Hartford-Springfield","mode":"TRUCK","min":255.0,"rates":[5.67,1.9,1.98,1.98,1.98,1.98,1.98],"route":""},{"code":"BHM","name":"Birmingham","mode":"TRUCK","min":205.0,"rates":[4.56,1.68,1.58,1.58,1.58,1.58,1.58],"route":""},{"code":"BNA","name":"Nashville","mode":"TRUCK","min":205.0,"rates":[4.56,1.68,1.58,1.58,1.58,1.58,1.58],"route":""},{"code":"BOS","name":"Boston","mode":"TRUCK","min":265.0,"rates":[5.89,1.95,1.89,1.89,1.89,1.89,1.89],"route":""},{"code":"BRL","name":"Burlington","mode":"TRUCK","min":265.0,"rates":[5.89,2.33,2.28,2.28,2.28,2.28,2.28],"route":""},{"code":"BUF","name":"Buffalo","mode":"TRUCK","min":235.0,"rates":[5.22,2.05,1.89,1.89,1.89,1.89,1.89],"route":""},{"code":"BWI","name":"Baltimore","mode":"TRUCK","min":210.0,"rates":[4.67,1.75,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"CAE","name":"Columbia","mode":"TRUCK","min":255.0,"rates":[5.67,1.85,1.72,1.72,1.72,1.72,1.72],"route":""},{"code":"CHA","name":"Chattanooga","mode":"TRUCK","min":235.0,"rates":[5.22,2.05,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"CHS","name":"Charleston S.C.","mode":"TRUCK","min":255.0,"rates":[5.67,1.85,1.72,1.72,1.72,1.72,1.72],"route":""},{"code":"CID","name":"Iowa City","mode":"TRUCK","min":325.0,"rates":[7.22,2.53,2.4,2.4,2.35,2.35,2.35],"route":""},{"code":"CLE","name":"Cleveland Ohio","mode":"TRUCK","min":195.0,"rates":[4.33,1.72,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"CLT","name":"Charlotte","mode":"TRUCK","min":195.0,"rates":[4.33,1.65,1.48,1.48,1.48,1.48,1.48],"route":""},{"code":"CMH","name":"Columbus Ohio","mode":"TRUCK","min":205.0,"rates":[4.56,1.8,1.68,1.68,1.68,1.68,1.68],"route":""},{"code":"CRP","name":"Corpus","mode":"TRUCK","min":265.0,"rates":[5.89,2.35,2.29,2.29,2.29,2.29,2.29],"route":""},{"code":"CVG","name":"Cincinnati","mode":"TRUCK","min":175.0,"rates":[3.89,1.71,1.57,1.57,1.57,1.57,1.57],"route":""},{"code":"DAY","name":"Dayton","mode":"TRUCK","min":205.0,"rates":[4.56,1.86,1.78,1.78,1.78,1.78,1.78],"route":""},{"code":"DEN","name":"Denver","mode":"TRUCK","min":210.0,"rates":[4.67,1.92,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"DFW","name":"Dallas Fort Worth, TX","mode":"TRUCK","min":165.0,"rates":[3.67,1.57,1.32,1.32,1.32,1.32,1.32],"route":""},{"code":"DSM","name":"Des Moines Iowa","mode":"TRUCK","min":295.0,"rates":[6.56,2.38,2.25,2.25,2.25,2.25,2.25],"route":""},{"code":"DTW","name":"Detroit","mode":"TRUCK","min":195.0,"rates":[4.33,1.76,1.66,1.66,1.66,1.66,1.66],"route":""},{"code":"ELP","name":"El Paso Tex","mode":"TRUCK","min":235.0,"rates":[5.22,1.95,1.87,1.87,1.87,1.87,1.87],"route":""},{"code":"EVV","name":"Evansville","mode":"TRUCK","min":305.0,"rates":[6.78,2.28,2.19,2.19,2.19,2.19,2.19],"route":""},{"code":"EWR","name":"Newark, NJ","mode":"TRUCK","min":205.0,"rates":[4.56,1.72,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"FWA","name":"Fort Wayne","mode":"TRUCK","min":210.0,"rates":[4.67,2.12,2.0,2.0,2.0,2.0,1.9],"route":""},{"code":"GRR","name":"Grand Rapids Mich","mode":"TRUCK","min":335.0,"rates":[7.44,2.58,2.55,2.55,2.45,2.45,2.45],"route":""},{"code":"GSO","name":"Greensboro","mode":"TRUCK","min":195.0,"rates":[4.33,1.68,1.54,1.54,1.54,1.54,1.54],"route":""},{"code":"GSP","name":"Greenville-Spartanburg","mode":"TRUCK","min":205.0,"rates":[4.56,1.67,1.57,1.57,1.57,1.57,1.57],"route":""},{"code":"HSV","name":"Huntsville","mode":"TRUCK","min":185.0,"rates":[4.11,1.7,1.49,1.49,1.49,1.49,1.49],"route":""},{"code":"IAD","name":"Washington","mode":"TRUCK","min":195.0,"rates":[4.33,1.57,1.4,1.4,1.4,1.4,1.4],"route":""},{"code":"IAH","name":"Houston, TX","mode":"TRUCK","min":185.0,"rates":[4.11,1.55,1.3,1.3,1.3,1.3,1.3],"route":""},{"code":"IND","name":"Indianapolis","mode":"TRUCK","min":215.0,"rates":[4.78,1.87,1.77,1.77,1.77,1.77,1.77],"route":""},{"code":"JAX","name":"Jacksonville, FL","mode":"TRUCK","min":180.0,"rates":[4.0,1.48,1.27,1.27,1.27,1.27,1.27],"route":""},{"code":"JFK","name":"New York","mode":"TRUCK","min":195.0,"rates":[4.33,1.57,1.47,1.47,1.47,1.47,1.47],"route":""},{"code":"LAS","name":"Las Vegas","mode":"TRUCK","min":285.0,"rates":[6.33,2.05,1.98,1.98,1.98,1.98,1.98],"route":""},{"code":"LAX","name":"Los Angeles, CA","mode":"TRUCK","min":205.0,"rates":[4.56,1.78,1.65,1.65,1.65,1.65,1.65],"route":""},{"code":"LBB","name":"Lubbock","mode":"TRUCK","min":325.0,"rates":[7.22,2.52,2.57,2.57,2.57,2.57,2.57],"route":""},{"code":"LFT","name":"Lafayette","mode":"TRUCK","min":305.0,"rates":[6.78,1.91,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"LIT","name":"Little Rock Ark","mode":"TRUCK","min":295.0,"rates":[6.56,1.86,1.75,1.75,1.75,1.75,1.75],"route":""},{"code":"LRD","name":"Laredo","mode":"TRUCK","min":305.0,"rates":[6.78,2.22,1.99,1.99,1.99,1.99,1.99],"route":""},{"code":"MBS","name":"Saginaw-Bay","mode":"TRUCK","min":325.0,"rates":[7.22,2.41,2.35,2.35,2.35,2.35,2.35],"route":""},{"code":"MCI","name":"Kansas City","mode":"TRUCK","min":295.0,"rates":[6.56,1.89,1.85,1.85,1.85,1.85,1.85],"route":""},{"code":"MCO","name":"Orlando, FL","mode":"TRUCK","min":195.0,"rates":[4.33,1.35,1.22,1.22,1.22,1.22,1.22],"route":""},{"code":"MDT","name":"Harrisburg-York","mode":"TRUCK","min":305.0,"rates":[6.78,2.26,2.18,2.18,2.18,2.18,2.18],"route":""},{"code":"MEM","name":"Memphis","mode":"TRUCK","min":280.0,"rates":[6.22,1.76,1.55,1.55,1.55,1.55,1.55],"route":""},{"code":"MFE","name":"Mission-Mcallen-Edinburg","mode":"TRUCK","min":315.0,"rates":[7.0,2.37,2.29,2.29,2.29,2.29,2.29],"route":""},{"code":"MGM","name":"Montgomery","mode":"TRUCK","min":280.0,"rates":[6.22,1.76,1.68,1.68,1.68,1.68,1.68],"route":""},{"code":"MKE","name":"Milwaukee Wisc","mode":"TRUCK","min":295.0,"rates":[6.56,1.88,1.73,1.73,1.73,1.73,1.73],"route":""},{"code":"MLI","name":"Moline-Davenport","mode":"TRUCK","min":315.0,"rates":[7.0,2.46,2.38,2.38,2.38,2.38,2.38],"route":""},{"code":"MOB","name":"Mobile","mode":"TRUCK","min":210.0,"rates":[4.67,1.73,1.51,1.51,1.51,1.51,1.51],"route":""},{"code":"MSP","name":"Minneapolis, MN","mode":"TRUCK","min":265.0,"rates":[5.89,1.95,1.82,1.82,1.82,1.82,1.82],"route":""},{"code":"MSY","name":"New Orleans, LA","mode":"TRUCK","min":265.0,"rates":[5.89,1.53,1.4,1.4,1.4,1.4,1.4],"route":""},{"code":"OKC","name":"Oklahoma","mode":"TRUCK","min":275.0,"rates":[6.11,1.72,1.58,1.58,1.58,1.58,1.58],"route":""},{"code":"OMA","name":"Omaha","mode":"TRUCK","min":315.0,"rates":[7.0,2.37,2.24,2.24,2.24,2.24,2.24],"route":""},{"code":"ORD","name":"Chicago, IL","mode":"TRUCK","min":205.0,"rates":[4.56,1.53,1.43,1.43,1.43,1.43,1.43],"route":""},{"code":"ORF","name":"Norfolk-VA","mode":"TRUCK","min":285.0,"rates":[6.33,1.81,1.74,1.74,1.74,1.74,1.74],"route":""},{"code":"PDX","name":"Oregon","mode":"TRUCK","min":265.0,"rates":[5.89,2.51,2.37,2.37,2.37,2.37,2.37],"route":""},{"code":"PHL","name":"Philadelphia","mode":"TRUCK","min":215.0,"rates":[4.78,1.63,1.53,1.53,1.53,1.53,1.53],"route":""},{"code":"PHX","name":"Phoenix, AZ","mode":"TRUCK","min":305.0,"rates":[6.78,2.05,1.94,1.94,1.94,1.94,1.94],"route":""},{"code":"PIT","name":"Pittsurgh","mode":"TRUCK","min":265.0,"rates":[5.89,1.91,1.71,1.71,1.71,1.71,1.71],"route":""},{"code":"PNS","name":"Pensacola","mode":"TRUCK","min":295.0,"rates":[6.56,2.03,1.92,1.92,1.92,1.92,1.92],"route":""},{"code":"RDU","name":"Raleigh Durham, N. Carolina","mode":"TRUCK","min":285.0,"rates":[6.33,1.72,1.57,1.57,1.57,1.57,1.57],"route":""},{"code":"RIC","name":"Richmond","mode":"TRUCK","min":225.0,"rates":[5.0,1.86,1.74,1.74,1.74,1.74,1.74],"route":""},{"code":"RNO","name":"Reno-Tahoe","mode":"TRUCK","min":410.0,"rates":[9.11,4.02,3.95,3.95,3.95,3.95,3.95],"route":""},{"code":"ROC","name":"Rochester","mode":"TRUCK","min":275.0,"rates":[6.11,1.97,1.88,1.88,1.88,1.88,1.88],"route":""},{"code":"SAN","name":"San Diego, CA","mode":"TRUCK","min":325.0,"rates":[7.22,2.47,2.32,2.32,2.32,2.32,2.32],"route":""},{"code":"SAT","name":"San Antonio","mode":"TRUCK","min":295.0,"rates":[6.56,1.99,1.88,1.88,1.88,1.88,1.88],"route":""},{"code":"SAV","name":"Savannah GA","mode":"TRUCK","min":265.0,"rates":[5.89,1.78,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"SBN","name":"South Bend Ind","mode":"TRUCK","min":315.0,"rates":[7.0,2.32,2.15,2.15,2.15,2.15,2.15],"route":""},{"code":"SDF","name":"Lousville","mode":"TRUCK","min":275.0,"rates":[6.11,1.96,1.68,1.68,1.68,1.68,1.68],"route":""},{"code":"SEA","name":"Seattle, WA","mode":"TRUCK","min":325.0,"rates":[7.22,2.47,2.32,2.32,2.32,2.32,2.32],"route":""},{"code":"SFO","name":"San Francisco, CA","mode":"TRUCK","min":315.0,"rates":[7.0,2.54,2.36,2.36,2.36,2.36,2.36],"route":""},{"code":"SLC","name":"Utah","mode":"TRUCK","min":315.0,"rates":[7.0,2.28,2.02,2.02,2.02,2.02,2.02],"route":""},{"code":"SMF","name":"Sacramento","mode":"TRUCK","min":305.0,"rates":[6.78,2.14,1.98,1.98,1.98,1.98,1.98],"route":""},{"code":"STL","name":"St. Louis","mode":"TRUCK","min":275.0,"rates":[6.11,1.88,1.73,1.73,1.73,1.73,1.73],"route":""},{"code":"SWF","name":"Newburgh","mode":"TRUCK","min":325.0,"rates":[7.22,2.39,2.27,2.27,2.27,2.27,2.27],"route":""},{"code":"SYR","name":"Syracuse","mode":"TRUCK","min":305.0,"rates":[6.78,2.14,1.99,1.99,1.99,1.99,1.99],"route":""},{"code":"TOL","name":"Toledo","mode":"TRUCK","min":275.0,"rates":[6.11,1.93,1.75,1.75,1.75,1.75,1.75],"route":""},{"code":"TPA","name":"Tampa, FL","mode":"TRUCK","min":205.0,"rates":[4.56,1.41,1.22,1.22,1.22,1.22,1.22],"route":""},{"code":"TRI","name":"Bristol-Kngspt-Jhnsn","mode":"TRUCK","min":275.0,"rates":[6.11,1.71,1.64,1.64,1.64,1.64,1.64],"route":""},{"code":"TUL","name":"Tulsa","mode":"TRUCK","min":285.0,"rates":[6.33,1.81,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"TUS","name":"Tucson","mode":"TRUCK","min":345.0,"rates":[7.67,2.78,2.65,2.65,2.65,2.65,2.65],"route":""},{"code":"TVC","name":"Traverse","mode":"TRUCK","min":335.0,"rates":[7.44,2.56,2.42,2.42,2.42,2.42,2.42],"route":""},{"code":"TYS","name":"Knoxville","mode":"TRUCK","min":275.0,"rates":[6.11,1.81,1.64,1.64,1.64,1.64,1.64],"route":""},{"code":"YUL","name":"Montreal","mode":"TRUCK","min":325.0,"rates":[7.22,2.45,2.11,2.11,2.11,2.11,2.11],"route":""},{"code":"YVR","name":"Vancouver","mode":"TRUCK","min":320.0,"rates":[7.11,2.56,2.39,2.39,2.39,2.39,2.39],"route":""},{"code":"YYZ","name":"Toronto","mode":"TRUCK","min":295.0,"rates":[6.56,2.16,2.1,2.1,2.1,2.1,2.1],"route":""},{"code":"GDL","name":"Guadalajara, Mexico ***","mode":"TRUCK","min":365.0,"rates":[8.11,3.64,3.43,3.28,3.28,3.28,3.28],"route":"VIA NLU/+1"},{"code":"MEX","name":"Mexico City, Mexico ***","mode":"TRUCK","min":365.0,"rates":[8.11,2.89,2.63,2.63,2.63,2.63,2.63],"route":"VIA NLU/+1"},{"code":"MTY","name":"Monterrey, Mexico ***","mode":"TRUCK","min":335.0,"rates":[7.44,3.19,2.83,2.83,2.83,2.83,2.83],"route":"VIA NLU/+1"},{"code":"QRO","name":"Queretaro, Mexico ***","mode":"TRUCK","min":325.0,"rates":[7.22,3.14,2.78,2.78,2.78,2.78,2.78],"route":"VIA NLU/+1"}]', true);
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#080e1c">

<title>Cálculos M6 | Calculadora y Tarifas</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

/* ======================================================
   ESTILOS GENERALES
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

.container {
    width: 100%;
    max-width: 960px;
    margin: 0 auto;
}

/* HEADER */

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
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 10px #22c55e;
}

/* PANEL */

.panel {
    width: 100%;
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

/* FORMULARIO */

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

/* OPCIONES */

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
    padding: 15px;

    background: #0b1426;
    border: 1px solid rgba(148,163,184,.14);
    border-radius: 12px;

    cursor: pointer;
    transition: .2s;
}

.option-card:hover {
    border-color: rgba(56,189,248,.5);
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

/* BOTONES */

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

/* RESULTADOS */

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

.copy-feedback {
    min-height: 16px;
    margin-top: 10px;
    color: #4ade80;
    font-size: 11px;
    text-align: center;
}

/* ALERTAS */

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

/* FOOTER */

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

/* RESPONSIVE */

@media (max-width: 900px) {
    .container {
        max-width: 760px;
    }
}

@media (max-width: 600px) {

    body {
        padding: 16px 11px;
    }

    .container {
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

    .button-group {
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


/* MENU PRINCIPAL Y CONSULTA DE TARIFAS */
.main-menu{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:0 0 22px}
.menu-card{display:flex;align-items:center;gap:14px;text-align:left;padding:18px;border:1px solid rgba(148,163,184,.16);border-radius:16px;background:#101a2e;color:#e2e8f0;cursor:pointer;transition:.2s}
.menu-card:hover,.menu-card.active{border-color:#38bdf8;background:rgba(14,165,233,.12);transform:translateY(-1px)}
.menu-card i{font-size:23px;color:#38bdf8}.menu-card strong{display:block;font-size:13px}.menu-card small{display:block;color:#94a3b8;font-size:10px;margin-top:5px}
.module-view{display:none}.module-view.active{display:block}
.tariff-search{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:14px;margin-bottom:20px}
.tariff-search input,.tariff-search select{width:100%;height:52px;border-radius:12px;border:1px solid rgba(148,163,184,.18);background:#0b1426;color:#f8fafc;padding:0 15px;outline:none}
.tariff-search input:focus,.tariff-search select:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.1)}
.tariff-meta{display:flex;justify-content:space-between;align-items:center;gap:10px;color:#94a3b8;font-size:11px;margin:10px 0 14px;flex-wrap:wrap}
.tariff-table-wrap{width:100%;overflow:auto;border:1px solid rgba(148,163,184,.14);border-radius:13px}
.tariff-table{width:100%;border-collapse:collapse;min-width:850px;font-size:12px}
.tariff-table th{background:#0b1b39;color:#93c5fd;text-align:right;padding:13px 12px;white-space:nowrap}
.tariff-table th:first-child,.tariff-table td:first-child{text-align:left}
.tariff-table td{padding:13px 12px;border-bottom:1px solid rgba(148,163,184,.10);text-align:right;color:#e2e8f0;font-variant-numeric:tabular-nums;white-space:nowrap}
.tariff-table tr:last-child td{border-bottom:0}.tariff-table tbody tr:hover{background:rgba(56,189,248,.06)}
.code-pill{display:inline-block;background:rgba(56,189,248,.12);color:#7dd3fc;border-radius:7px;padding:5px 8px;font-weight:800;letter-spacing:.5px}
.tariff-empty{padding:30px;text-align:center;color:#94a3b8;font-size:13px}
.tariff-copy{margin-top:16px}.tariff-note{color:#64748b;font-size:10px;line-height:1.7;margin-top:14px}
@media(max-width:600px){.main-menu{grid-template-columns:1fr}.tariff-search{grid-template-columns:1fr}.menu-card{padding:15px}}


/* PROFORMA */
.proforma-layout{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);gap:20px;align-items:start;width:100%;min-width:0}
.proforma-layout>*{min-width:0;max-width:100%}
.proforma-controls{background:#101a2e;border:1px solid rgba(148,163,184,.14);border-radius:16px;padding:20px}
.proforma-controls h3{font-size:14px;margin-bottom:16px;color:#e2e8f0}
.proforma-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
.proforma-field{display:flex;flex-direction:column;gap:7px;min-width:0}
.proforma-field.full{grid-column:1/-1}
.proforma-field label{font-size:11px;color:#cbd5e1;font-weight:600}
.proforma-field input,.proforma-field textarea{width:100%;min-width:0;border:1px solid rgba(148,163,184,.18);border-radius:10px;background:#0b1426;color:#f8fafc;padding:11px 12px;font:inherit;font-size:12px;outline:none}
.proforma-field input:focus,.proforma-field textarea:focus{border-color:#1e5a91;box-shadow:0 0 0 3px rgba(30,90,145,.18)}
.proforma-field textarea{min-height:76px;resize:vertical}
.proforma-hint{font-size:10px;color:#94a3b8;line-height:1.5;margin-top:12px}
.proforma-actions{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}
.proforma-actions .btn{flex:1}
.proforma-paper{background:#fff;color:#111827;padding:28px 26px;box-shadow:0 18px 50px rgba(0,0,0,.25);border-radius:4px;min-height:500px;min-width:0;width:100%;max-width:100%;overflow:hidden;box-sizing:border-box;font-family:Arial,sans-serif}
.proforma-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;min-height:112px;min-width:0}
.proforma-logo{width: min(220px,48%);max-width:48%;height:auto;object-fit:contain;object-position:left top;flex:0 1 48%}
.proforma-title{text-align:right;flex:1;min-width:0;overflow-wrap:anywhere}
.proforma-title h2{font-size:clamp(15px,2vw,22px);font-weight:400;color:#111;margin:0 0 14px;white-space:normal;line-height:1.15}
.proforma-title p{font-size:10px;color:#64748b;line-height:1.35;margin:0}
.proforma-info{display:flex;justify-content:space-between;gap:20px;margin:24px 0 28px;font-size:11px;line-height:1.6}
.proforma-client{max-width:48%;overflow-wrap:anywhere}
.proforma-client strong{display:block;font-size:11px;margin-bottom:3px}
.proforma-dates{min-width:190px}
.proforma-date-line{display:flex;justify-content:space-between;gap:12px}
.proforma-date-line strong{white-space:nowrap}
.proforma-paper table{width:100%;border-collapse:collapse;font-size:11px}
.proforma-paper thead th{background:#0b3158;color:#fff;text-align:left;padding:10px 8px;font-size:10px}
.proforma-paper thead th:not(:first-child),.proforma-paper tbody td:not(:first-child){text-align:right}
.proforma-paper tbody td{padding:11px 8px;border-bottom:1px solid #d1d5db;vertical-align:top}
.proforma-summary{width:55%;margin:0 0 0 auto;font-size:11px}
.proforma-summary-row{display:flex;justify-content:space-between;gap:15px;padding:8px;border-bottom:1px solid #d1d5db}
.proforma-summary-row.total{border-top:2px solid #0b3158;border-bottom:0;font-size:17px;font-weight:700;color:#0b3158;padding-top:12px}
.proforma-paper-footer{font-size:9px;color:#64748b;margin-top:32px;text-align:center}
@media(max-width:800px){.proforma-layout{grid-template-columns:1fr}.proforma-paper{padding:20px 15px}}
@media(max-width:480px){.proforma-grid{grid-template-columns:1fr}.proforma-field.full{grid-column:auto}.proforma-info{flex-direction:column;margin:18px 0}.proforma-dates{min-width:0}.proforma-title h2{font-size:17px}.proforma-logo{width:170px}.proforma-summary{width:100%}}


/* ACTUALIZACION DE ITINERARIO */
.itinerary-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:start}
.itinerary-card{background:#101a2e;border:1px solid rgba(148,163,184,.14);border-radius:16px;padding:20px;min-width:0}
.itinerary-card h3{font-size:14px;color:#e2e8f0;margin-bottom:8px}
.itinerary-card>p{font-size:11px;color:#94a3b8;line-height:1.6;margin-bottom:16px}
.itinerary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
.itinerary-field{display:flex;flex-direction:column;gap:7px;min-width:0}
.itinerary-field.full{grid-column:1/-1}
.itinerary-field label{font-size:11px;color:#cbd5e1;font-weight:600}
.itinerary-field input,.itinerary-field select{width:100%;min-width:0;height:45px;border:1px solid rgba(148,163,184,.18);border-radius:10px;background:#0b1426;color:#f8fafc;padding:0 12px;font:inherit;font-size:12px;outline:none}
.itinerary-field input:focus,.itinerary-field select:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.1)}
.itinerary-results{display:grid;gap:10px;margin-top:17px}
.itinerary-result{display:flex;justify-content:space-between;gap:12px;align-items:center;background:#0b1426;border:1px solid rgba(56,189,248,.15);border-radius:11px;padding:13px}
.itinerary-result span{font-size:11px;color:#94a3b8}
.itinerary-result strong{font-size:16px;color:#e0f2fe;font-variant-numeric:tabular-nums;text-align:right}
.itinerary-result small{display:block;font-size:9px;color:#64748b;text-align:right;margin-top:3px}
.itinerary-message{width:100%;min-height:210px;resize:vertical;background:#0b1426;color:#e2e8f0;border:1px solid rgba(148,163,184,.18);border-radius:12px;padding:14px;font:12px/1.8 Inter,sans-serif;outline:none}
.itinerary-actions{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
.itinerary-actions .btn{flex:1}
.itinerary-help{font-size:10px;color:#94a3b8;line-height:1.6;margin-top:12px}
.itinerary-live{display:inline-flex;align-items:center;gap:7px;color:#4ade80;font-size:10px;font-weight:700;margin-bottom:12px}
.itinerary-live:before{content:"";width:7px;height:7px;background:#22c55e;border-radius:50%;box-shadow:0 0 8px #22c55e}
@media(max-width:800px){.itinerary-layout{grid-template-columns:1fr}}
@media(max-width:480px){.itinerary-grid{grid-template-columns:1fr}.itinerary-field.full{grid-column:auto}.itinerary-result strong{font-size:14px}}

@media print{
  @page{size:A4;margin:12mm}
  html,body{background:#fff!important;padding:0!important;margin:0!important;min-height:0!important}
  body{color:#111!important}
  .container{max-width:none!important;width:100%!important;margin:0!important}
  .header,.main-menu,.footer,#validationView,#tariffView,#itineraryView,.proforma-controls,.proforma-section-heading{display:none!important}
  #proformaView{display:block!important}
  #proformaView .panel{padding:0!important;margin:0!important;background:#fff!important;border:0!important;box-shadow:none!important}
  .proforma-layout{display:block!important}
  .proforma-paper{display:block!important;box-shadow:none!important;border-radius:0!important;padding:0!important;min-height:0!important}
  .proforma-paper thead th{background:#0b3158!important;color:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .proforma-summary-row.total{color:#0b3158!important}
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
                <h1>Cálculos M6</h1>
                <p>Herramientas de operaciones aéreas</p>
            </div>

        </div>

        <div class="status">
            <span class="status-dot"></span>
            Sistema operativo
        </div>

    </header>

    <nav class="main-menu" aria-label="Módulos de Cálculos M6">
        <button type="button" class="menu-card active" data-view="validationView">
            <i class="fas fa-calculator"></i><span><strong>Validación de guías</strong><small>Calculadora M6 y cargos originales</small></span>
        </button>
        <button type="button" class="menu-card" data-view="tariffView">
            <i class="fas fa-plane-departure"></i><span><strong>Consulta de tarifas</strong><small>Buscador por destino y escalas KG</small></span>
        </button>
        <button type="button" class="menu-card" data-view="proformaView">
            <i class="fas fa-file-invoice-dollar"></i><span><strong>Generar proforma</strong><small>Crear factura proforma e imprimir en PDF</small></span>
        </button>
        <button type="button" class="menu-card" data-view="itineraryView">
            <i class="fas fa-plane-circle-check"></i><span><strong>Actualización de itinerario</strong><small>Calculá ETA, ETD y llegada a Miami</small></span>
        </button>
    </nav>

    <div id="validationView" class="module-view active">
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
                <?= e($error) ?>
            </div>

        <?php endforeach; ?>

        <form method="POST" id="calcForm">

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
                            value="<?= $isPost ? e($data['peso_volumetrico']) : '' ?>"
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
                            value="<?= $isPost ? e($data['tarifa_min']) : '' ?>"
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
                            value="<?= $isPost ? e($data['tarifa_aplicar']) : '' ?>"
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
                            value="<?= $isPost ? e($data['cargos_agente']) : '' ?>"
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
                            value="<?= $isPost ? e($data['other']) : '' ?>"
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
                            value="<?= $isPost ? e($data['fuel_escalation_rate']) : '0.20' ?>"
                        >
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
                    Resumen de cálculo
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
    </div><!-- /validationView -->

    <section id="tariffView" class="module-view">
      <main class="panel">
        <div class="section-heading">
          <i class="fas fa-magnifying-glass"></i>
          <div><h2>Consulta de tarifas por destino</h2><p>Escribí un código IATA o una ciudad. Los resultados aparecen automáticamente.</p></div>
        </div>
        <div class="tariff-search">
          <input type="search" id="destinationSearch" placeholder="Ej. MIA, ATL, Miami, New York..." autocomplete="off" aria-label="Buscar destino">
          <select id="routeType" aria-label="Tipo de ruta"><option value="ALL">Todas las rutas</option><option value="AIR">Vía aérea</option><option value="TRUCK">Multimodal · Vía terrestre</option></select>
        </div>
        <div class="tariff-meta"><span id="tariffCount">Escribí un destino para consultar.</span><span><i class="fas fa-circle" style="color:#22c55e;font-size:7px"></i> Búsqueda en vivo</span></div>
        <div class="tariff-table-wrap">
          <table class="tariff-table"><thead><tr><th>Destino</th><th>Ciudad</th><th>MIN</th><th>+45 KG</th><th>+100 KG</th><th>+500 KG</th><th>+1,000 KG</th><th>+2,500 KG</th><th>+5,000 KG</th><th>+10,000 KG</th><th>Ruta</th></tr></thead><tbody id="tariffResults"><tr><td colspan="11" class="tariff-empty">Buscá un destino para ver sus tarifas.</td></tr></tbody></table>
        </div>
        <div class="button-group tariff-copy"><button type="button" class="btn btn-copy" id="copyTariffBtn"><i class="fas fa-copy"></i> Copiar tarifas mostradas</button></div>
        <div class="copy-feedback" id="tariffFeedback"></div>
        <p class="tariff-note">Tarifario Amerijet SAL · Vigencia indicada en el documento: 30 de agosto de 2026. Tarifas en USD. La consulta muestra los valores del documento; verificá vigencia y cargos adicionales con la aerolínea antes de cotizar.</p>
      </main>
    </section>


    <section id="itineraryView" class="module-view">
      <main class="panel">
        <div class="section-heading">
          <i class="fas fa-plane-circle-check"></i>
          <div><h2>Actualización de itinerario</h2><p>Ingresá el vuelo y la ETA. Los horarios y el mensaje se actualizan automáticamente.</p></div>
        </div>
        <div class="itinerary-layout">
          <div class="itinerary-card">
            <h3><i class="fas fa-sliders"></i> Datos del vuelo</h3>
            <p>Si elegís hora local de El Salvador, ingresá directamente la ETA de arribo a SAL. Si elegís Zulu, ingresá la hora de salida desde MIA en UTC. El vuelo dura 2 h 30 min; en SAL se atiende durante 1 h 30 min y el regreso a MIA tarda otras 2 h 30 min.</p>
            <div class="itinerary-grid">
              <div class="itinerary-field full"><label for="itFlight">Número de vuelo</label><input id="itFlight" type="text" placeholder="Ej. 720M6" value="720M6"></div>
              <div class="itinerary-field full"><label for="itDate" id="itDateLabel">Fecha de arribo en SAL</label><input id="itDate" type="date"></div>
              <div class="itinerary-field"><label for="itTimeMode">Formato de hora ingresada</label><select id="itTimeMode"><option value="local">Hora local de El Salvador</option><option value="zulu">Hora Zulu (UTC)</option></select></div>
              <div class="itinerary-field"><label for="itEtaInput" id="itEtaInputLabel">Hora de arribo en El Salvador</label><input id="itEtaInput" type="time" value="02:00"></div>
            </div>
            <div class="itinerary-results" aria-live="polite">
              <div class="itinerary-result"><div><span>ETA arriving at SAL</span><small id="itSalDate">Hora local de El Salvador</small></div><strong id="itEtaSal">02:00 a.m.</strong></div>
              <div class="itinerary-result"><div><span>ETD departing from SAL</span><small>ETA SAL + 1 h 30 min</small></div><strong id="itEtdSal">03:30 a.m.</strong></div>
              <div class="itinerary-result"><div><span>ETA at MIA</span><small id="itMiamiZone">Hora local de Miami</small></div><strong id="itEtaMia">07:00 hrs.</strong></div>
            </div>
            <p class="itinerary-help">La diferencia de Miami se calcula automáticamente con la zona horaria America/New_York y la fecha seleccionada. Así contempla UTC−5 en horario estándar y UTC−4 durante el horario de verano.</p>
          </div>
          <div class="itinerary-card">
            <h3><i class="fas fa-message"></i> Mensaje listo para enviar</h3>
            <div class="itinerary-live">ACTUALIZACIÓN EN VIVO</div>
            <textarea id="itineraryMessage" class="itinerary-message" readonly></textarea>
            <div class="itinerary-actions">
              <button type="button" class="btn btn-primary" id="itCopyMessage"><i class="fas fa-copy"></i> Copiar mensaje</button>
              <button type="button" class="btn btn-secondary" id="itClear"><i class="fas fa-rotate-left"></i> Limpiar</button>
            </div>
            <div class="copy-feedback" id="itFeedback"></div>
            <p class="itinerary-help">Herramienta externa para consultar el reloj y los cambios de horario de Miami: <a href="https://www.timeanddate.com/worldclock/usa/miami" target="_blank" rel="noopener" style="color:#38bdf8">Timeanddate · Miami</a>. El cálculo del sistema no depende de esa página: utiliza las reglas horarias del navegador.</p>
          </div>
        </div>
        <div class="itinerary-card" style="margin-top:18px">
          <h3><i class="fas fa-clock"></i> Conversor Zulu → hora de El Salvador</h3>
          <p>Podés usarlo por separado para convertir cualquier hora UTC a la hora local UTC−6.</p>
          <div class="itinerary-grid">
            <div class="itinerary-field"><label for="zuluDate">Fecha UTC</label><input id="zuluDate" type="date"></div>
            <div class="itinerary-field"><label for="zuluTime">Hora Zulu (UTC)</label><input id="zuluTime" type="time" value="00:00"></div>
          </div>
          <div class="itinerary-result" style="margin-top:14px"><div><span>Hora local de El Salvador</span><small id="zuluLocalDate">UTC−6 · El Salvador</small></div><strong id="zuluLocalTime">06:00 p.m.</strong></div>
        </div>
      </main>
    </section>

    <section id="proformaView" class="module-view">
      <main class="panel">
        <div class="section-heading proforma-section-heading">
          <i class="fas fa-file-invoice-dollar"></i>
          <div><h2>Generador de factura proforma</h2><p>Completá los datos y mirá la factura actualizarse automáticamente.</p></div>
        </div>
        <div class="proforma-layout">
          <div class="proforma-controls">
            <h3><i class="fas fa-pen-to-square"></i> Datos de la proforma</h3>
            <div class="proforma-grid">
              <div class="proforma-field full"><label for="pfClient">Nombre del cliente</label><input id="pfClient" type="text" placeholder="Nombre o razón social"></div>
              <div class="proforma-field"><label for="pfNumber">N.º de proforma</label><input id="pfNumber" type="text" value="001"></div>
              <div class="proforma-field"><label for="pfDate">Fecha</label><input id="pfDate" type="date"></div>
              <div class="proforma-field"><label for="pfDue">Vencimiento</label><input id="pfDue" type="date"></div>
              <div class="proforma-field full"><label for="pfDescription">Descripción</label><textarea id="pfDescription" placeholder="Descripción del servicio o cargo"></textarea></div>
              <div class="proforma-field"><label for="pfQuantity">Cantidad</label><input id="pfQuantity" type="number" min="0.01" step="0.01" value="1"></div>
              <div class="proforma-field"><label for="pfPrice">Precio unitario (USD)</label><input id="pfPrice" type="number" min="0" step="0.01" placeholder="0.00"></div>
            </div>
            <p class="proforma-hint">El subtotal se calcula con cantidad × precio unitario. El IVA del 13% se agrega automáticamente y se refleja en el total.</p>
            <div class="proforma-actions">
              <button type="button" class="btn btn-primary" id="pfPrint"><i class="fas fa-print"></i> Imprimir / Guardar PDF</button>
              <button type="button" class="btn btn-secondary" id="pfClear"><i class="fas fa-rotate-left"></i> Limpiar</button>
            </div>
          </div>
          <div class="proforma-paper" id="proformaPaper">
            <div class="proforma-top">
              <img class="proforma-logo" src="data:image/jpeg;base64,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" alt="Amerijet International Airlines">
              <div class="proforma-title"><h2>FACTURA PROFORMA</h2><p><strong>Amerijet</strong><br>San Salvador</p></div>
            </div>
            <div class="proforma-info">
              <div class="proforma-client"><strong>PARA</strong><span id="pfPreviewClient">NOMBRE DEL CLIENTE</span></div>
              <div class="proforma-dates">
                <div class="proforma-date-line"><span>Factura proforma n.º:</span><strong id="pfPreviewNumber">001</strong></div>
                <div class="proforma-date-line"><span>Fecha:</span><strong id="pfPreviewDate">—</strong></div>
                <div class="proforma-date-line"><span>Vencimiento:</span><strong id="pfPreviewDue">—</strong></div>
              </div>
            </div>
            <table>
              <thead><tr><th style="width:48%">DESCRIPCIÓN</th><th>CANTIDAD</th><th>PRECIO ($)</th><th>IVA %</th><th>TOTAL ($)</th></tr></thead>
              <tbody><tr><td id="pfPreviewDescription">—</td><td id="pfPreviewQuantity">1</td><td id="pfPreviewPrice">0.00</td><td>13%</td><td id="pfPreviewLineTotal">0.00</td></tr></tbody>
            </table>
            <div class="proforma-summary">
              <div class="proforma-summary-row"><strong>SUBTOTAL (USD):</strong><span id="pfPreviewSubtotal">$0.00</span></div>
              <div class="proforma-summary-row"><strong>IVA (13%):</strong><span id="pfPreviewTax">$0.00</span></div>
              <div class="proforma-summary-row total"><strong>TOTAL A PAGAR (USD)</strong><span id="pfPreviewTotal">$0.00</span></div>
            </div>
            <div class="proforma-paper-footer">Amerijet International · San Salvador</div>
          </div>
        </div>
      </main>
    </section>

    <!-- FOOTER -->

    <footer class="footer">

        Desarrollado por Cris · +50379979583 © 2026

    </footer>

</div>

<script>

/* ======================================================
   CÁLCULOS M6 - CÁLCULOS AUTOMÁTICOS
====================================================== */

const form = document.getElementById('calcForm');

// La calculadora trabaja completamente en vivo, sin procesar ni recargar la página.
form.addEventListener('submit', function(event) {
    event.preventDefault();
});

/* NAVEGACIÓN ENTRE MÓDULOS */
document.querySelectorAll('[data-view]').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-view]').forEach(b => b.classList.toggle('active', b === button));
        document.querySelectorAll('.module-view').forEach(view => view.classList.toggle('active', view.id === button.dataset.view));
    });
});


/* ACTUALIZACION DE ITINERARIO Y CONVERSION ZULU */
const itDate=document.getElementById('itDate');
const itFlight=document.getElementById('itFlight');
const itTimeMode=document.getElementById('itTimeMode');
const itEtaInput=document.getElementById('itEtaInput');
const itFormatter12=new Intl.DateTimeFormat('en-US',{timeZone:'America/El_Salvador',hour:'2-digit',minute:'2-digit',hour12:true});
const itFormatter24Mia=new Intl.DateTimeFormat('en-GB',{timeZone:'America/New_York',hour:'2-digit',minute:'2-digit',hour12:false});
const itDateSalFmt=new Intl.DateTimeFormat('en-GB',{timeZone:'America/El_Salvador',day:'2-digit',month:'2-digit',year:'numeric'});
const itDateMiaFmt=new Intl.DateTimeFormat('en-GB',{timeZone:'America/New_York',day:'2-digit',month:'2-digit',year:'numeric'});
function isoTodayInZone(zone){const parts=new Intl.DateTimeFormat('en-CA',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const o={};parts.forEach(p=>o[p.type]=p.value);return `${o.year}-${o.month}-${o.day}`;}
function parseDateParts(value){const a=(value||'').split('-').map(Number);return a.length===3&&a.every(Number.isFinite)?a:null;}
function buildItineraryInstant(dateValue,timeValue,mode){
    const d=parseDateParts(dateValue);if(!d||!timeValue)return null;
    const t=timeValue.split(':').map(Number);if(t.length<2||!Number.isFinite(t[0])||!Number.isFinite(t[1]))return null;
    const utcBase=Date.UTC(d[0],d[1]-1,d[2],t[0],t[1]);
    // La hora local de El Salvador es UTC-6 todo el año; Zulu ya es UTC.
    return new Date(mode==='zulu'?utcBase:utcBase+6*60*60000);
}
function updateItinerary(){
    const isZulu=itTimeMode.value==='zulu';
    // En modo local, la hora ingresada YA es la ETA de arribo a SAL.
    // En modo Zulu, la hora ingresada es la salida desde MIA en UTC;
    // por eso se suman las 2 h 30 min del tramo MIA-SAL.
    document.getElementById('itEtaInputLabel').textContent=isZulu?'Hora de salida desde MIA':'Hora de arribo en El Salvador';
    document.getElementById('itDateLabel').textContent=isZulu?'Fecha de salida desde MIA (UTC)':'Fecha de arribo en SAL';
    const horaBase=buildItineraryInstant(itDate.value,itEtaInput.value,itTimeMode.value);
    if(!horaBase){document.getElementById('itineraryMessage').value='Ingresá la fecha y la hora para generar la actualización.';return;}

    const etaSal=isZulu?new Date(horaBase.getTime()+150*60000):new Date(horaBase.getTime());
    // Atención y despacho en SAL: 1 h 30 min.
    const etd=new Date(etaSal.getTime()+90*60000);
    // Tramo SAL-MIA: 2 h 30 min.
    const mia=new Date(etd.getTime()+150*60000);

    const etaSalText=itFormatter12.format(etaSal).replace(' AM',' a.m.').replace(' PM',' p.m.');
    const etdSalText=itFormatter12.format(etd).replace(' AM',' a.m.').replace(' PM',' p.m.');
    const miaText=itFormatter24Mia.format(mia);
    const miaParts=new Intl.DateTimeFormat('en-US',{timeZone:'America/New_York',timeZoneName:'short'}).formatToParts(mia);
    const miaZone=(miaParts.find(p=>p.type==='timeZoneName')||{}).value||'Miami';

    document.getElementById('itEtaSal').textContent=etaSalText;
    document.getElementById('itEtdSal').textContent=etdSalText;
    document.getElementById('itEtaMia').textContent=miaText+' hrs.';
    document.getElementById('itSalDate').textContent='Fecha SAL: '+itDateSalFmt.format(etaSal);
    document.getElementById('itMiamiZone').textContent='Hora local de Miami · '+miaZone+' · '+itDateMiaFmt.format(mia);

    const message=`Buen dia estimados.

Les comparto actualización vuelo ${itFlight.value.trim()||'________'}:

ETA arriving at SAL: ${etaSalText}
ETD departing from SAL: ${etdSalText}
ETA at MIA: ${miaText} hrs., local time in MIA

Quedamos atentos,`;
    document.getElementById('itineraryMessage').value=message;
}
function updateZuluConverter(){
    const instant=buildItineraryInstant(document.getElementById('zuluDate').value,document.getElementById('zuluTime').value,'zulu');
    if(!instant)return;
    document.getElementById('zuluLocalTime').textContent=itFormatter12.format(instant).replace(' AM',' a.m.').replace(' PM',' p.m.');
    document.getElementById('zuluLocalDate').textContent='Fecha local: '+itDateSalFmt.format(instant)+' · UTC−6';
}
const todaySal=isoTodayInZone('America/El_Salvador');
itDate.value=todaySal;
document.getElementById('zuluDate').value=isoTodayInZone('UTC');
[itDate,itFlight,itTimeMode,itEtaInput].forEach(el=>el.addEventListener('input',updateItinerary));
[itDate,itTimeMode,itEtaInput].forEach(el=>el.addEventListener('change',updateItinerary));
['zuluDate','zuluTime'].forEach(id=>{document.getElementById(id).addEventListener('input',updateZuluConverter);document.getElementById(id).addEventListener('change',updateZuluConverter);});
document.getElementById('itCopyMessage').addEventListener('click',async()=>{
    const message=document.getElementById('itineraryMessage').value;const feedback=document.getElementById('itFeedback');
    try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(message);}else{const ta=document.createElement('textarea');ta.value=message;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();if(!document.execCommand('copy'))throw new Error('copy failed');ta.remove();}feedback.textContent='✓ Mensaje copiado correctamente.';}catch(e){feedback.textContent='No se pudo copiar. Seleccioná el mensaje manualmente.';}
});
document.getElementById('itClear').addEventListener('click',()=>{itFlight.value='';itDate.value=isoTodayInZone('America/El_Salvador');itTimeMode.value='local';itEtaInput.value='';document.getElementById('itFeedback').textContent='';updateItinerary();});
updateItinerary();updateZuluConverter();

/* CONSULTA EN VIVO DE TARIFAS */
const tariffData = [{"code":"ANU","name":"St Johns, Antigua","mode":"AIR","min":255.0,"rates":[5.67,2.57,2.21,2.21,2.21,2.21,2.21],"route":""},{"code":"AXA","name":"Anguilla, British WI ***","mode":"AIR","min":330.0,"rates":[7.33,3.35,2.99,2.99,2.99,2.84,2.84],"route":"VIA SMX/+1"},{"code":"BGI","name":"Christ Church, Barbados, WI","mode":"AIR","min":245.0,"rates":[5.44,2.49,2.13,2.13,2.13,2.13,2.13],"route":""},{"code":"BZE","name":"Belize City, Belize","mode":"AIR","min":240.0,"rates":[5.33,2.36,2.0,2.0,2.0,2.0,2.0],"route":""},{"code":"CUR","name":"Curacao, Netherlands Antilles","mode":"AIR","min":265.0,"rates":[5.89,2.69,2.33,2.33,2.33,2.33,2.33],"route":""},{"code":"DOM","name":"Marigot, Dominica ***","mode":"AIR","min":285.0,"rates":[6.33,2.88,2.51,2.51,2.51,2.51,2.51],"route":"VIA SMX/+1"},{"code":"EIS","name":"Tortola, Beef Island ***","mode":"AIR","min":310.0,"rates":[6.89,4.09,3.73,3.73,3.73,3.73,3.73],"route":"VIA SJU"},{"code":"GEO","name":"Georgetown, Guayana","mode":"AIR","min":275.0,"rates":[6.11,2.59,2.23,2.23,2.23,2.23,2.23],"route":""},{"code":"GND","name":"St Georges, Grenada, WI","mode":"AIR","min":295.0,"rates":[6.56,2.96,2.55,2.55,2.55,2.55,2.55],"route":""},{"code":"GUA","name":"Guatemala, GT","mode":"AIR","min":210.0,"rates":[210.0,4.57,1.55,1.55,1.41,1.35,1.35],"route":""},{"code":"KIN","name":"Kingston, Jamaica","mode":"AIR","min":220.0,"rates":[4.89,2.2,1.84,1.84,1.84,1.84,1.84],"route":""},{"code":"MGA","name":"Managua, Nicaragua","mode":"AIR","min":180.0,"rates":[4.0,1.15,1.15,1.15,1.15,1.15,1.15],"route":""},{"code":"MIA","name":"Miami, FL","mode":"AIR","min":125.0,"rates":[2.78,0.97,0.89,0.89,0.89,0.89,0.89],"route":""},{"code":"MNI","name":"Montserrat, BWI ***","mode":"AIR","min":315.0,"rates":[7.0,3.12,2.76,2.76,2.76,2.76,2.76],"route":""},{"code":"NLU","name":"Felipe Angeles, Mexico","mode":"AIR","min":275.0,"rates":[6.11,2.64,2.28,2.28,2.28,2.28,2.28],"route":""},{"code":"PAP","name":"Port Au Prince, Haiti, WI","mode":"AIR","min":435.0,"rates":[9.67,4.35,3.99,3.99,3.99,3.99,3.99],"route":""},{"code":"PBM","name":"Paramaribo, Suriname","mode":"AIR","min":275.0,"rates":[6.11,2.64,2.42,2.42,2.42,2.42,2.42],"route":""},{"code":"POP","name":"Puerto Plata, Dominican Republic ***","mode":"AIR","min":245.0,"rates":[5.44,2.49,2.13,2.13,2.13,2.13,2.13],"route":"VIA STI/ +1"},{"code":"POS","name":"Port of Spain, Trinidad and Tobago","mode":"AIR","min":260.0,"rates":[5.78,2.64,2.28,2.28,2.28,2.28,2.28],"route":""},{"code":"PTY","name":"Panama City, Panama","mode":"AIR","min":180.0,"rates":[4.0,1.25,1.25,1.25,1.25,1.25,1.25],"route":""},{"code":"PUJ","name":"Punta Cana, Dominican Republic","mode":"AIR","min":270.0,"rates":[6.0,2.74,2.52,2.52,2.52,2.52,2.52],"route":"VIA SDQ/+1"},{"code":"SAP","name":"San Pedro Sula, Honduras","mode":"AIR","min":180.0,"rates":[4.0,1.15,1.15,1.15,1.15,1.15,1.15],"route":""},{"code":"SBH","name":"St Barthelemy, FWI ***","mode":"AIR","min":335.0,"rates":[7.44,3.3,2.94,2.94,2.94,2.94,2.94],"route":"VIA SMX/+1"},{"code":"SDQ","name":"Santo Domingo, Dominican Republic","mode":"AIR","min":235.0,"rates":[5.22,2.16,1.8,1.8,1.8,1.8,1.8],"route":""},{"code":"SJU","name":"San Juan, Puerto Rico","mode":"AIR","min":315.0,"rates":[7.0,2.49,2.13,2.13,2.13,2.13,2.13],"route":""},{"code":"SKB","name":"St. Kitts, WI","mode":"AIR","min":275.0,"rates":[6.11,2.75,2.39,2.39,2.39,2.39,2.39],"route":""},{"code":"SLU","name":"Castries, St. Lucia, WI","mode":"AIR","min":290.0,"rates":[6.44,2.89,2.53,2.53,2.53,2.53,2.53],"route":""},{"code":"STI","name":"Santiago, Dominican Republic","mode":"AIR","min":235.0,"rates":[5.22,2.16,1.8,1.8,1.8,1.8,1.8],"route":""},{"code":"STT","name":"St Thomas, VI ***","mode":"AIR","min":425.0,"rates":[9.44,4.09,3.73,3.73,3.73,3.73,3.73],"route":"VIA SJU"},{"code":"STX","name":"St Croix, VI ***","mode":"AIR","min":425.0,"rates":[9.44,4.09,3.73,3.73,3.73,3.73,3.73],"route":"VIA SJU"},{"code":"SVD","name":"St. Vincent, WI","mode":"AIR","min":325.0,"rates":[7.22,2.94,2.58,2.58,2.58,2.58,2.58],"route":""},{"code":"SXM","name":"St. Maarten, Netherlands Antilles","mode":"AIR","min":275.0,"rates":[6.11,2.47,2.11,2.11,2.11,2.11,2.11],"route":""},{"code":"TGU","name":"Tegucigalpa, Honduras ***","mode":"AIR","min":255.0,"rates":[5.67,1.55,1.55,1.55,1.55,1.55,1.55],"route":"VIA XPL/ +1"},{"code":"UVF","name":"Hewannora, St Lucia","mode":"AIR","min":310.0,"rates":[6.89,2.89,2.53,2.53,2.53,2.53,2.53],"route":""},{"code":"XPL","name":"Palmerola, Honduras","mode":"AIR","min":185.0,"rates":[4.11,1.45,1.45,1.45,1.45,1.45,1.45],"route":""},{"code":"ABE","name":"Allentwn-Bethlehem-Eastom","mode":"TRUCK","min":360.0,"rates":[8.0,2.6,2.45,2.45,2.45,2.45,2.45],"route":""},{"code":"ABQ","name":"Albuquerque","mode":"TRUCK","min":285.0,"rates":[6.33,2.35,2.24,2.24,2.24,2.24,2.24],"route":""},{"code":"ALB","name":"Albany New York,","mode":"TRUCK","min":285.0,"rates":[6.33,2.2,2.05,2.05,2.05,2.05,2.05],"route":""},{"code":"ATL","name":"Atlanta, GA","mode":"TRUCK","min":185.0,"rates":[4.11,1.39,1.27,1.27,1.27,1.27,1.27],"route":""},{"code":"AUS","name":"Austin","mode":"TRUCK","min":205.0,"rates":[4.56,1.85,1.71,1.71,1.71,1.71,1.71],"route":""},{"code":"BDL","name":"Hartford-Springfield","mode":"TRUCK","min":255.0,"rates":[5.67,1.9,1.98,1.98,1.98,1.98,1.98],"route":""},{"code":"BHM","name":"Birmingham","mode":"TRUCK","min":205.0,"rates":[4.56,1.68,1.58,1.58,1.58,1.58,1.58],"route":""},{"code":"BNA","name":"Nashville","mode":"TRUCK","min":205.0,"rates":[4.56,1.68,1.58,1.58,1.58,1.58,1.58],"route":""},{"code":"BOS","name":"Boston","mode":"TRUCK","min":265.0,"rates":[5.89,1.95,1.89,1.89,1.89,1.89,1.89],"route":""},{"code":"BRL","name":"Burlington","mode":"TRUCK","min":265.0,"rates":[5.89,2.33,2.28,2.28,2.28,2.28,2.28],"route":""},{"code":"BUF","name":"Buffalo","mode":"TRUCK","min":235.0,"rates":[5.22,2.05,1.89,1.89,1.89,1.89,1.89],"route":""},{"code":"BWI","name":"Baltimore","mode":"TRUCK","min":210.0,"rates":[4.67,1.75,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"CAE","name":"Columbia","mode":"TRUCK","min":255.0,"rates":[5.67,1.85,1.72,1.72,1.72,1.72,1.72],"route":""},{"code":"CHA","name":"Chattanooga","mode":"TRUCK","min":235.0,"rates":[5.22,2.05,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"CHS","name":"Charleston S.C.","mode":"TRUCK","min":255.0,"rates":[5.67,1.85,1.72,1.72,1.72,1.72,1.72],"route":""},{"code":"CID","name":"Iowa City","mode":"TRUCK","min":325.0,"rates":[7.22,2.53,2.4,2.4,2.35,2.35,2.35],"route":""},{"code":"CLE","name":"Cleveland Ohio","mode":"TRUCK","min":195.0,"rates":[4.33,1.72,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"CLT","name":"Charlotte","mode":"TRUCK","min":195.0,"rates":[4.33,1.65,1.48,1.48,1.48,1.48,1.48],"route":""},{"code":"CMH","name":"Columbus Ohio","mode":"TRUCK","min":205.0,"rates":[4.56,1.8,1.68,1.68,1.68,1.68,1.68],"route":""},{"code":"CRP","name":"Corpus","mode":"TRUCK","min":265.0,"rates":[5.89,2.35,2.29,2.29,2.29,2.29,2.29],"route":""},{"code":"CVG","name":"Cincinnati","mode":"TRUCK","min":175.0,"rates":[3.89,1.71,1.57,1.57,1.57,1.57,1.57],"route":""},{"code":"DAY","name":"Dayton","mode":"TRUCK","min":205.0,"rates":[4.56,1.86,1.78,1.78,1.78,1.78,1.78],"route":""},{"code":"DEN","name":"Denver","mode":"TRUCK","min":210.0,"rates":[4.67,1.92,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"DFW","name":"Dallas Fort Worth, TX","mode":"TRUCK","min":165.0,"rates":[3.67,1.57,1.32,1.32,1.32,1.32,1.32],"route":""},{"code":"DSM","name":"Des Moines Iowa","mode":"TRUCK","min":295.0,"rates":[6.56,2.38,2.25,2.25,2.25,2.25,2.25],"route":""},{"code":"DTW","name":"Detroit","mode":"TRUCK","min":195.0,"rates":[4.33,1.76,1.66,1.66,1.66,1.66,1.66],"route":""},{"code":"ELP","name":"El Paso Tex","mode":"TRUCK","min":235.0,"rates":[5.22,1.95,1.87,1.87,1.87,1.87,1.87],"route":""},{"code":"EVV","name":"Evansville","mode":"TRUCK","min":305.0,"rates":[6.78,2.28,2.19,2.19,2.19,2.19,2.19],"route":""},{"code":"EWR","name":"Newark, NJ","mode":"TRUCK","min":205.0,"rates":[4.56,1.72,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"FWA","name":"Fort Wayne","mode":"TRUCK","min":210.0,"rates":[4.67,2.12,2.0,2.0,2.0,2.0,1.9],"route":""},{"code":"GRR","name":"Grand Rapids Mich","mode":"TRUCK","min":335.0,"rates":[7.44,2.58,2.55,2.55,2.45,2.45,2.45],"route":""},{"code":"GSO","name":"Greensboro","mode":"TRUCK","min":195.0,"rates":[4.33,1.68,1.54,1.54,1.54,1.54,1.54],"route":""},{"code":"GSP","name":"Greenville-Spartanburg","mode":"TRUCK","min":205.0,"rates":[4.56,1.67,1.57,1.57,1.57,1.57,1.57],"route":""},{"code":"HSV","name":"Huntsville","mode":"TRUCK","min":185.0,"rates":[4.11,1.7,1.49,1.49,1.49,1.49,1.49],"route":""},{"code":"IAD","name":"Washington","mode":"TRUCK","min":195.0,"rates":[4.33,1.57,1.4,1.4,1.4,1.4,1.4],"route":""},{"code":"IAH","name":"Houston, TX","mode":"TRUCK","min":185.0,"rates":[4.11,1.55,1.3,1.3,1.3,1.3,1.3],"route":""},{"code":"IND","name":"Indianapolis","mode":"TRUCK","min":215.0,"rates":[4.78,1.87,1.77,1.77,1.77,1.77,1.77],"route":""},{"code":"JAX","name":"Jacksonville, FL","mode":"TRUCK","min":180.0,"rates":[4.0,1.48,1.27,1.27,1.27,1.27,1.27],"route":""},{"code":"JFK","name":"New York","mode":"TRUCK","min":195.0,"rates":[4.33,1.57,1.47,1.47,1.47,1.47,1.47],"route":""},{"code":"LAS","name":"Las Vegas","mode":"TRUCK","min":285.0,"rates":[6.33,2.05,1.98,1.98,1.98,1.98,1.98],"route":""},{"code":"LAX","name":"Los Angeles, CA","mode":"TRUCK","min":205.0,"rates":[4.56,1.78,1.65,1.65,1.65,1.65,1.65],"route":""},{"code":"LBB","name":"Lubbock","mode":"TRUCK","min":325.0,"rates":[7.22,2.52,2.57,2.57,2.57,2.57,2.57],"route":""},{"code":"LFT","name":"Lafayette","mode":"TRUCK","min":305.0,"rates":[6.78,1.91,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"LIT","name":"Little Rock Ark","mode":"TRUCK","min":295.0,"rates":[6.56,1.86,1.75,1.75,1.75,1.75,1.75],"route":""},{"code":"LRD","name":"Laredo","mode":"TRUCK","min":305.0,"rates":[6.78,2.22,1.99,1.99,1.99,1.99,1.99],"route":""},{"code":"MBS","name":"Saginaw-Bay","mode":"TRUCK","min":325.0,"rates":[7.22,2.41,2.35,2.35,2.35,2.35,2.35],"route":""},{"code":"MCI","name":"Kansas City","mode":"TRUCK","min":295.0,"rates":[6.56,1.89,1.85,1.85,1.85,1.85,1.85],"route":""},{"code":"MCO","name":"Orlando, FL","mode":"TRUCK","min":195.0,"rates":[4.33,1.35,1.22,1.22,1.22,1.22,1.22],"route":""},{"code":"MDT","name":"Harrisburg-York","mode":"TRUCK","min":305.0,"rates":[6.78,2.26,2.18,2.18,2.18,2.18,2.18],"route":""},{"code":"MEM","name":"Memphis","mode":"TRUCK","min":280.0,"rates":[6.22,1.76,1.55,1.55,1.55,1.55,1.55],"route":""},{"code":"MFE","name":"Mission-Mcallen-Edinburg","mode":"TRUCK","min":315.0,"rates":[7.0,2.37,2.29,2.29,2.29,2.29,2.29],"route":""},{"code":"MGM","name":"Montgomery","mode":"TRUCK","min":280.0,"rates":[6.22,1.76,1.68,1.68,1.68,1.68,1.68],"route":""},{"code":"MKE","name":"Milwaukee Wisc","mode":"TRUCK","min":295.0,"rates":[6.56,1.88,1.73,1.73,1.73,1.73,1.73],"route":""},{"code":"MLI","name":"Moline-Davenport","mode":"TRUCK","min":315.0,"rates":[7.0,2.46,2.38,2.38,2.38,2.38,2.38],"route":""},{"code":"MOB","name":"Mobile","mode":"TRUCK","min":210.0,"rates":[4.67,1.73,1.51,1.51,1.51,1.51,1.51],"route":""},{"code":"MSP","name":"Minneapolis, MN","mode":"TRUCK","min":265.0,"rates":[5.89,1.95,1.82,1.82,1.82,1.82,1.82],"route":""},{"code":"MSY","name":"New Orleans, LA","mode":"TRUCK","min":265.0,"rates":[5.89,1.53,1.4,1.4,1.4,1.4,1.4],"route":""},{"code":"OKC","name":"Oklahoma","mode":"TRUCK","min":275.0,"rates":[6.11,1.72,1.58,1.58,1.58,1.58,1.58],"route":""},{"code":"OMA","name":"Omaha","mode":"TRUCK","min":315.0,"rates":[7.0,2.37,2.24,2.24,2.24,2.24,2.24],"route":""},{"code":"ORD","name":"Chicago, IL","mode":"TRUCK","min":205.0,"rates":[4.56,1.53,1.43,1.43,1.43,1.43,1.43],"route":""},{"code":"ORF","name":"Norfolk-VA","mode":"TRUCK","min":285.0,"rates":[6.33,1.81,1.74,1.74,1.74,1.74,1.74],"route":""},{"code":"PDX","name":"Oregon","mode":"TRUCK","min":265.0,"rates":[5.89,2.51,2.37,2.37,2.37,2.37,2.37],"route":""},{"code":"PHL","name":"Philadelphia","mode":"TRUCK","min":215.0,"rates":[4.78,1.63,1.53,1.53,1.53,1.53,1.53],"route":""},{"code":"PHX","name":"Phoenix, AZ","mode":"TRUCK","min":305.0,"rates":[6.78,2.05,1.94,1.94,1.94,1.94,1.94],"route":""},{"code":"PIT","name":"Pittsurgh","mode":"TRUCK","min":265.0,"rates":[5.89,1.91,1.71,1.71,1.71,1.71,1.71],"route":""},{"code":"PNS","name":"Pensacola","mode":"TRUCK","min":295.0,"rates":[6.56,2.03,1.92,1.92,1.92,1.92,1.92],"route":""},{"code":"RDU","name":"Raleigh Durham, N. Carolina","mode":"TRUCK","min":285.0,"rates":[6.33,1.72,1.57,1.57,1.57,1.57,1.57],"route":""},{"code":"RIC","name":"Richmond","mode":"TRUCK","min":225.0,"rates":[5.0,1.86,1.74,1.74,1.74,1.74,1.74],"route":""},{"code":"RNO","name":"Reno-Tahoe","mode":"TRUCK","min":410.0,"rates":[9.11,4.02,3.95,3.95,3.95,3.95,3.95],"route":""},{"code":"ROC","name":"Rochester","mode":"TRUCK","min":275.0,"rates":[6.11,1.97,1.88,1.88,1.88,1.88,1.88],"route":""},{"code":"SAN","name":"San Diego, CA","mode":"TRUCK","min":325.0,"rates":[7.22,2.47,2.32,2.32,2.32,2.32,2.32],"route":""},{"code":"SAT","name":"San Antonio","mode":"TRUCK","min":295.0,"rates":[6.56,1.99,1.88,1.88,1.88,1.88,1.88],"route":""},{"code":"SAV","name":"Savannah GA","mode":"TRUCK","min":265.0,"rates":[5.89,1.78,1.63,1.63,1.63,1.63,1.63],"route":""},{"code":"SBN","name":"South Bend Ind","mode":"TRUCK","min":315.0,"rates":[7.0,2.32,2.15,2.15,2.15,2.15,2.15],"route":""},{"code":"SDF","name":"Lousville","mode":"TRUCK","min":275.0,"rates":[6.11,1.96,1.68,1.68,1.68,1.68,1.68],"route":""},{"code":"SEA","name":"Seattle, WA","mode":"TRUCK","min":325.0,"rates":[7.22,2.47,2.32,2.32,2.32,2.32,2.32],"route":""},{"code":"SFO","name":"San Francisco, CA","mode":"TRUCK","min":315.0,"rates":[7.0,2.54,2.36,2.36,2.36,2.36,2.36],"route":""},{"code":"SLC","name":"Utah","mode":"TRUCK","min":315.0,"rates":[7.0,2.28,2.02,2.02,2.02,2.02,2.02],"route":""},{"code":"SMF","name":"Sacramento","mode":"TRUCK","min":305.0,"rates":[6.78,2.14,1.98,1.98,1.98,1.98,1.98],"route":""},{"code":"STL","name":"St. Louis","mode":"TRUCK","min":275.0,"rates":[6.11,1.88,1.73,1.73,1.73,1.73,1.73],"route":""},{"code":"SWF","name":"Newburgh","mode":"TRUCK","min":325.0,"rates":[7.22,2.39,2.27,2.27,2.27,2.27,2.27],"route":""},{"code":"SYR","name":"Syracuse","mode":"TRUCK","min":305.0,"rates":[6.78,2.14,1.99,1.99,1.99,1.99,1.99],"route":""},{"code":"TOL","name":"Toledo","mode":"TRUCK","min":275.0,"rates":[6.11,1.93,1.75,1.75,1.75,1.75,1.75],"route":""},{"code":"TPA","name":"Tampa, FL","mode":"TRUCK","min":205.0,"rates":[4.56,1.41,1.22,1.22,1.22,1.22,1.22],"route":""},{"code":"TRI","name":"Bristol-Kngspt-Jhnsn","mode":"TRUCK","min":275.0,"rates":[6.11,1.71,1.64,1.64,1.64,1.64,1.64],"route":""},{"code":"TUL","name":"Tulsa","mode":"TRUCK","min":285.0,"rates":[6.33,1.81,1.79,1.79,1.79,1.79,1.79],"route":""},{"code":"TUS","name":"Tucson","mode":"TRUCK","min":345.0,"rates":[7.67,2.78,2.65,2.65,2.65,2.65,2.65],"route":""},{"code":"TVC","name":"Traverse","mode":"TRUCK","min":335.0,"rates":[7.44,2.56,2.42,2.42,2.42,2.42,2.42],"route":""},{"code":"TYS","name":"Knoxville","mode":"TRUCK","min":275.0,"rates":[6.11,1.81,1.64,1.64,1.64,1.64,1.64],"route":""},{"code":"YUL","name":"Montreal","mode":"TRUCK","min":325.0,"rates":[7.22,2.45,2.11,2.11,2.11,2.11,2.11],"route":""},{"code":"YVR","name":"Vancouver","mode":"TRUCK","min":320.0,"rates":[7.11,2.56,2.39,2.39,2.39,2.39,2.39],"route":""},{"code":"YYZ","name":"Toronto","mode":"TRUCK","min":295.0,"rates":[6.56,2.16,2.1,2.1,2.1,2.1,2.1],"route":""},{"code":"GDL","name":"Guadalajara, Mexico ***","mode":"TRUCK","min":365.0,"rates":[8.11,3.64,3.43,3.28,3.28,3.28,3.28],"route":"VIA NLU/+1"},{"code":"MEX","name":"Mexico City, Mexico ***","mode":"TRUCK","min":365.0,"rates":[8.11,2.89,2.63,2.63,2.63,2.63,2.63],"route":"VIA NLU/+1"},{"code":"MTY","name":"Monterrey, Mexico ***","mode":"TRUCK","min":335.0,"rates":[7.44,3.19,2.83,2.83,2.83,2.83,2.83],"route":"VIA NLU/+1"},{"code":"QRO","name":"Queretaro, Mexico ***","mode":"TRUCK","min":325.0,"rates":[7.22,3.14,2.78,2.78,2.78,2.78,2.78],"route":"VIA NLU/+1"}];
const searchInput = document.getElementById('destinationSearch');
const routeSelect = document.getElementById('routeType');
const tariffBody = document.getElementById('tariffResults');
const tariffCount = document.getElementById('tariffCount');
const tariffMoney = new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'});
let visibleTariffs = [];
function renderTariffs(){
    const term = searchInput.value.trim().toLocaleUpperCase();
    const mode = routeSelect.value;
    if(!term){
        tariffBody.innerHTML = '<tr><td colspan="11" class="tariff-empty">Escribí un código IATA o una ciudad para consultar.</td></tr>';
        tariffCount.textContent = 'Mostrando '+tariffData.length+' destinos disponibles en el tarifario.';
        visibleTariffs=[]; return;
    }
    visibleTariffs = tariffData.filter(item => (mode==='ALL'||item.mode===mode) && (item.code.includes(term)||item.name.toLocaleUpperCase().includes(term)));
    tariffCount.textContent = visibleTariffs.length+' destino(s) encontrado(s)';
    if(!visibleTariffs.length){tariffBody.innerHTML='<tr><td colspan="11" class="tariff-empty">No encontramos ese destino en el tarifario.</td></tr>';return;}
    tariffBody.innerHTML=visibleTariffs.map(item=>{
        const cells=[item.min,...item.rates].map(value=>`<td>${tariffMoney.format(value)}</td>`).join('');
        return `<tr><td><span class="code-pill">${escapeHtml(item.code)}</span></td><td style="text-align:left">${escapeHtml(item.name)}<div style="color:#64748b;font-size:10px;margin-top:4px">${item.mode==='AIR'?'Vía aérea':'Multimodal · terrestre'}</div></td>${cells}<td>${escapeHtml(item.route||'—')}</td></tr>`;
    }).join('');
}
function escapeHtml(value){return String(value).replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));}
searchInput.addEventListener('input',renderTariffs);
routeSelect.addEventListener('change',renderTariffs);
renderTariffs();
document.getElementById('copyTariffBtn').addEventListener('click',async()=>{
    const feedback=document.getElementById('tariffFeedback');
    if(!visibleTariffs.length){feedback.textContent='Primero buscá un destino.';return;}
    const labels=['MIN','+45 KG','+100 KG','+500 KG','+1,000 KG','+2,500 KG','+5,000 KG','+10,000 KG'];
    const lines=visibleTariffs.map(item=>[item.code+' · '+item.name+' · '+(item.mode==='AIR'?'VÍA AÉREA':'VÍA TERRESTRE'),...labels.map((label,i)=>label+': '+Number(i===0?item.min:item.rates[i-1]).toFixed(2)), 'Ruta: '+(item.route||'—')].join('\n'));
    const text=lines.join('\n\n');
    try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(text);}else{const ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();if(!document.execCommand('copy'))throw new Error();ta.remove();}feedback.textContent='✓ Tarifas copiadas correctamente.';}catch(e){feedback.textContent='No se pudo copiar. Revisá los permisos del navegador.';}
});


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

/* CÁLCULOS EN VIVO */

form.addEventListener('input', calculateLive);
form.addEventListener('change', calculateLive);

calculateLive();

/* ======================================================
   LIMPIAR - UN CLIC Y LISTO
====================================================== */

document.getElementById('resetBtn').addEventListener('click', function() {

    form.reset();

    // Restablecer porcentaje original
    document.getElementById('fuel_escalation_rate').value = '0.20';

    // Actualizar todos los resultados
    calculateLive();

    // Quitar mensajes de error anteriores
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.remove();
    });

    // Limpiar mensaje de copiado
    document.getElementById('copyFeedback').textContent = '';

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
        '          CÁLCULOS M6',
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
        '          CÁLCULOS M6',
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


/* GENERADOR DE PROFORMA - ACTUALIZACIÓN EN VIVO */
const pfIds=['pfClient','pfNumber','pfDate','pfDue','pfDescription','pfQuantity','pfPrice'];
const pfMoney=new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',minimumFractionDigits:2,maximumFractionDigits:2});
const pfDateFormat=value=>{
    if(!value)return '—';
    const parts=value.split('-');
    return parts.length===3?`${parts[2]}/${parts[1]}/${parts[0]}`:value;
};
function updateProforma(){
    const quantity=Math.max(0,parseFloat(document.getElementById('pfQuantity').value)||0);
    const price=Math.max(0,parseFloat(document.getElementById('pfPrice').value)||0);
    const subtotal=quantity*price;
    const tax=subtotal*0.13;
    const total=subtotal+tax;
    const put=(id,value)=>{document.getElementById(id).textContent=value;};
    put('pfPreviewClient',document.getElementById('pfClient').value.trim()||'NOMBRE DEL CLIENTE');
    put('pfPreviewNumber',document.getElementById('pfNumber').value.trim()||'—');
    put('pfPreviewDate',pfDateFormat(document.getElementById('pfDate').value));
    put('pfPreviewDue',pfDateFormat(document.getElementById('pfDue').value));
    put('pfPreviewDescription',document.getElementById('pfDescription').value.trim()||'—');
    put('pfPreviewQuantity',quantity.toLocaleString('en-US',{maximumFractionDigits:2}));
    put('pfPreviewPrice',price.toFixed(2));
    put('pfPreviewLineTotal',pfMoney.format(total));
    put('pfPreviewSubtotal',pfMoney.format(subtotal));
    put('pfPreviewTax',pfMoney.format(tax));
    put('pfPreviewTotal',pfMoney.format(total));
}
function localDateInput(date){
    const offset=date.getTimezoneOffset();
    return new Date(date.getTime()-offset*60000).toISOString().slice(0,10);
}
const todayPF=new Date();
document.getElementById('pfDate').value=localDateInput(todayPF);
const duePF=new Date(todayPF);duePF.setDate(duePF.getDate()+14);
document.getElementById('pfDue').value=localDateInput(duePF);
pfIds.forEach(id=>document.getElementById(id).addEventListener('input',updateProforma));
document.getElementById('pfPrint').addEventListener('click',()=>{updateProforma();window.print();});
document.getElementById('pfClear').addEventListener('click',()=>{
    document.getElementById('pfClient').value='';
    document.getElementById('pfNumber').value='001';
    document.getElementById('pfDescription').value='';
    document.getElementById('pfQuantity').value='1';
    document.getElementById('pfPrice').value='';
    document.getElementById('pfDate').value=localDateInput(new Date());
    const due=new Date();due.setDate(due.getDate()+14);
    document.getElementById('pfDue').value=localDateInput(due);
    updateProforma();
});
updateProforma();

</script>

</body>
</html>
