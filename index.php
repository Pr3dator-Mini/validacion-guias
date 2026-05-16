<?php
/**
 * Index - Punto de entrada de la aplicación
 * 
 * Este archivo es el punto de entrada principal de la aplicación.
 */

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Incluir archivo de configuración
require_once 'config.php';

// Incluir funciones globales
require_once 'includes/functions.php';

// Iniciar sesión
session_start();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación de Guías</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Sistema de Validación de Guías</h1>
        </header>
        
        <main>
            <section class="content">
                <h2>Bienvenido</h2>
                <p>Sistema de validación de guías y documentos.</p>
                
                <?php if (isset($_SESSION['usuario'])): ?>
                    <p>Hola, <?php echo htmlspecialchars($_SESSION['usuario']); ?></p>
                <?php else: ?>
                    <p><a href="login.php">Inicia sesión</a> para continuar.</p>
                <?php endif; ?>
            </section>
        </main>
        
        <footer>
            <p>&copy; 2026 Sistema de Validación. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>
