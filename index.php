<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "hotpink.php";

if (!isset($_SESSION['autenticado'])) {
    header('Location: login.php');
    exit;
}

if (isset($_POST['logout'])) {
    hotpink::logout();
}

$mensaje = '';
if (isset($_POST['convertir'])) {
    try {
        $formato = $_POST['formato_salida'];
        $nombre_base = $_POST['nombre_archivo'];
        
        // Asegurar la extensión correcta según el formato
        $extensiones = [
            'aCSV' => '.csv',
            'aJSON' => '.json',
            'aXML' => '.xml',
            'aSQLite' => '.sqlite3'
        ];
        
        $nombre_sin_extension = pathinfo($nombre_base, PATHINFO_FILENAME);
        $nombre_archivo = $nombre_sin_extension . $extensiones[$formato];
        
        // Crear carpeta del usuario si no existe
        $carpeta_usuario = 'archivos/' . $_SESSION['usuario'];
        if (!file_exists($carpeta_usuario)) {
            mkdir($carpeta_usuario, 0777, true);
        }
        
        // Usar los valores del formulario para la conexión
        $servidor = $_POST['servidor'];
        $basedatos = $_POST['basedatos'];
        $usuario_db = $_POST['usuario_db'];
        $password_db = $_POST['password_db'];
        $tabla = $_POST['tabla'];
        
        $converter = hotpink::deMySQL($servidor, $basedatos, $usuario_db, $password_db, $tabla);
        $archivo = $converter->$formato($carpeta_usuario . '/' . $nombre_archivo);
        
        $mensaje = "Archivo convertido exitosamente: " . $carpeta_usuario . '/' . $nombre_archivo;
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HotPink Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">HotPink Converter</a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3">Usuario: <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                <form method="post" class="d-flex">
                    <button type="submit" name="logout" class="btn btn-outline-light">Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <?php if ($mensaje): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Convertir archivo</h5>
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Configuración de Base de Datos</h6>
                            <div class="mb-3">
                                <label class="form-label">Servidor MySQL:</label>
                                <input type="text" name="servidor" class="form-control" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Base de datos:</label>
                                <input type="text" name="basedatos" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Usuario de MySQL:</label>
                                <input type="text" name="usuario_db" class="form-control" autocomplete="off" value=""   required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña de MySQL:</label>
                                <input type="password" name="password_db" class="form-control" autocomplete="off" value=""   required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tabla:</label>
                                <input type="text" name="tabla" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="mb-3">Configuración de Salida</h6>
                            <div class="mb-3">
                                <label class="form-label">Formato de salida:</label>
                                <select name="formato_salida" class="form-select" required>
                                    <option value="aCSV">CSV</option>
                                    <option value="aJSON">JSON</option>
                                    <option value="aXML">XML</option>
                                    <option value="aSQLite">SQLite</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre del archivo (sin extensión):</label>
                                <input type="text" name="nombre_archivo" class="form-control" required 
                                       placeholder="Ejemplo: mi_archivo">
                                <small class="text-muted">La extensión se agregará automáticamente según el formato seleccionado</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="convertir" class="btn btn-primary">Convertir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>