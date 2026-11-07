
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
                <p>Validación de Guías</p>
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

</script>

</body>
</html>
