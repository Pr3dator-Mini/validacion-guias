
// Puedes agregar cualquier código PHP aquí si lo necesitas
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema M6</title>
    
    <!-- Fuentes de Google -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            /* Ruta de la imagen de fondo */
            background: url('avion.png') center center / cover no-repeat;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
        }

        .menu-container {
            position: relative;
            text-align: center;
            z-index: 2;
        }

        .title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 50px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        }

        .menu {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .menu a {
            display: inline-block;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            margin: 15px 0;
            padding: 20px 30px;
            background-color: #33658a;
            border-radius: 50px;
            color: white;
            text-transform: uppercase;
            transition: transform 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
            opacity: 0;
            transform: translateY(100px);
            animation: menuItemFadeIn 1s ease-out forwards;
        }

        @keyframes menuItemFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .menu a:nth-child(1) {
            animation-delay: 0.3s;
        }

        .menu a:nth-child(2) {
            animation-delay: 0.6s;
        }

        .menu a:nth-child(3) {
            animation-delay: 0.9s;
        }

        .menu a:nth-child(4) {
            animation-delay: 1.2s;
        }

        .menu a:nth-child(5) {
            animation-delay: 1.5s;
        }

        .menu a:hover {
            transform: translateY(-5px);
            background-color: #f2a900;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .menu a i {
            margin-right: 10px;
        }

        /* Efecto de sombra sutil para los botones */
        .menu a {
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body>

    <div class="menu-container">
        <div class="title">Sistema M6</div>
        <div class="menu">
            <a href="validacionguias.php"><i class="fas fa-cogs"></i> Validacion de guias</a>
            <a href="conversiondimension.php"><i class="fas fa-random"></i> Conversión de Dimensiones</a>
            <a href="horazulu.php"><i class="fas fa-clock"></i> Hora Zulu</a>
            <a href="posicion.php"><i class="fas fa-plane"></i> Posición</a>
            <a href="tarifas.php"><i class="fas fa-tags"></i> Tarifas</a>
        </div>
    </div>

</body>
</html>
