<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "hotpink.php";

$error = '';
$success = '';

if (isset($_POST['login'])) {
    if (hotpink::login($_POST['usuario'], $_POST['password'])) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}

if (isset($_POST['register'])) {
    if (hotpink::registrarUsuario($_POST['nuevo_usuario'], $_POST['nueva_password'], $_POST['confirmar_password'])) {
        $success = 'Usuario creado exitosamente. Ya puedes iniciar sesión.';
    } else {
        $error = 'Error al crear el usuario. El usuario ya existe o las contraseñas no coinciden.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HotPink Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Pestañas de navegación -->
                <ul class="nav nav-tabs mb-3" id="loginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" 
                                data-bs-target="#login" type="button" role="tab">
                            Iniciar Sesión
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" 
                                data-bs-target="#register" type="button" role="tab">
                            Registrarse
                        </button>
                    </li>
                </ul>

                <!-- Contenido de las pestañas -->
                <div class="tab-content" id="loginTabsContent">
                    <!-- Pestaña de Login -->
                    <div class="tab-pane fade show active" id="login" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="text-center">Iniciar Sesión</h3>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Usuario:</label>
                                        <input type="text" name="usuario" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña:</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="login" class="btn btn-primary">
                                            Iniciar Sesión
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña de Registro -->
                    <div class="tab-pane fade" id="register" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="text-center">Registrarse</h3>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Nuevo Usuario:</label>
                                        <input type="text" name="nuevo_usuario" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña:</label>
                                        <input type="password" name="nueva_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirmar Contraseña:</label>
                                        <input type="password" name="confirmar_password" class="form-control" required>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="register" class="btn btn-success">
                                            Crear Cuenta
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>