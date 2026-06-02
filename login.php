<?php
// login seguro con validación y manejo de errores mejorado
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

// Si ya está autenticado, ir al dashboard
if (isset($_SESSION['usuario'])) {
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$error = '';

// ── Protección contra fuerza bruta ──────────────────────────
// Máximo 5 intentos fallidos → bloqueo de 15 minutos
if (!isset($_SESSION['login_intentos']))  $_SESSION['login_intentos']  = 0;
if (!isset($_SESSION['login_bloqueado'])) $_SESSION['login_bloqueado'] = 0;

$maxIntentos   = 5;
$tiempoBloqueo = 15 * 60; // 15 minutos en segundos

if ($_SESSION['login_bloqueado'] > 0) {
    $restante = $_SESSION['login_bloqueado'] - time();
    if ($restante > 0) {
        $minutos  = ceil($restante / 60);
        $error = "Demasiados intentos fallidos. Espera $minutos minuto(s) para intentarlo nuevamente.";
    } else {
        // Tiempo de bloqueo expirado, reiniciar
        $_SESSION['login_intentos']  = 0;
        $_SESSION['login_bloqueado'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario  = trim($_POST['usuario']  ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($usuario) || empty($password)) {
        $error = 'Completa todos los campos.';
    } else {
        // Prepared statement — previene SQL injection
        $stmt = $conn->prepare(
            "SELECT id, usuario, nombre_completo, password, rol, estado
             FROM usuarios
             WHERE usuario = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) {
            $error = 'Usuario no encontrado.';
            $_SESSION['login_intentos']++;
        } elseif ((int)$fila['estado'] === 0) {
            $error = 'Cuenta desactivada. Contacte al administrador.';
        } elseif (!password_verify($password, $fila['password'])) {
            $error = 'Contraseña incorrecta.';
            $_SESSION['login_intentos']++;
        } else {
            // Login exitoso — reiniciar contadores
            $_SESSION['login_intentos']  = 0;
            $_SESSION['login_bloqueado'] = 0;
            // Sesión segura
            session_regenerate_id(true);
            $_SESSION['id_usuario']      = $fila['id'];
            $_SESSION['usuario']         = $fila['usuario'];
            $_SESSION['nombre_completo'] = $fila['nombre_completo'] ?: $fila['usuario'];
            $_SESSION['rol']             = $fila['rol'];
            $_SESSION['_last_regen']     = time();
            header("Location: " . BASE_URL . "/pages/dashboard.php");
            exit();
        }

        // Bloquear si supera el máximo de intentos
        if ($_SESSION['login_intentos'] >= $maxIntentos) {
            $_SESSION['login_bloqueado'] = time() + $tiempoBloqueo;
            $error = "Cuenta bloqueada por $maxIntentos intentos fallidos. Espera 15 minutos.";
        } elseif ($_SESSION['login_intentos'] > 0 && empty($_SESSION['login_bloqueado'])) {
            $restantes = $maxIntentos - $_SESSION['login_intentos'];
            $error .= " ($restantes intento(s) restante(s) antes del bloqueo)";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Inter',sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        /* Fondo animado con círculos */
        body::before, body::after {
            content:'';
            position:absolute;
            border-radius:50%;
            opacity:0.07;
            animation: float 8s ease-in-out infinite;
        }
        body::before {
            width:600px; height:600px;
            background: #3b82f6;
            top:-200px; right:-200px;
        }
        body::after {
            width:400px; height:400px;
            background: #8b5cf6;
            bottom:-150px; left:-150px;
            animation-delay: 4s;
        }
        @keyframes float {
            0%,100%{ transform:translateY(0) scale(1); }
            50%     { transform:translateY(-30px) scale(1.05); }
        }
        .login-card {
            background: rgba(17,24,39,0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.4);
            position: relative;
            z-index: 1;
        }
        .logo-area {
            text-align:center;
            margin-bottom:36px;
        }
        .logo-icon {
            width:72px; height:72px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius:20px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:30px;
            color:white;
            margin-bottom:16px;
            box-shadow: 0 8px 24px rgba(59,130,246,0.35);
        }
        .logo-area h2 {
            color:white;
            font-weight:700;
            font-size:22px;
            margin-bottom:4px;
        }
        .logo-area p { color:#64748b; font-size:13px; }
        .form-label {
            color:#94a3b8;
            font-size:13px;
            font-weight:500;
            margin-bottom:8px;
        }
        .input-group-addon {
            background: #1e293b;
            border: 1px solid #334155;
            border-right: none;
            color: #64748b;
            padding: 0 14px;
            border-radius: 12px 0 0 12px;
            display:flex; align-items:center;
        }
        .form-control {
            background: #1e293b;
            border: 1px solid #334155;
            border-left: none;
            color: white;
            padding: 14px 16px;
            border-radius: 0 12px 12px 0;
            font-size: 14px;
            transition: 0.2s;
        }
        .form-control:focus {
            background: #1e293b;
            border-color: #3b82f6;
            color: white;
            box-shadow: none;
        }
        .form-control::placeholder { color:#475569; }
        .btn-login {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border: none;
            color: white;
            padding: 14px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            transition: 0.3s;
            margin-top: 8px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59,130,246,0.4);
            color:white;
        }
        .alert-error {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            color: #fca5a5;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .footer-text {
            text-align:center;
            color:#334155;
            font-size:12px;
            margin-top:28px;
        }
    </style>
</head>
<body>

<div class="login-card">

    <!-- LOGO -->
    <div class="logo-area">
        <div class="logo-icon">
            <i class="fa-solid fa-warehouse"></i>
        </div>
        <h2><?= APP_NAME ?></h2>
        <p><?= APP_EMPRESA ?></p>
    </div>

    <!-- ERROR -->
    <?php if ($error): ?>
    <div class="alert-error">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <form method="POST" autocomplete="off" novalidate>

        <!-- Usuario -->
        <div class="mb-4">
            <label class="form-label">Usuario</label>
            <div class="d-flex">
                <div class="input-group-addon">
                    <i class="fa-solid fa-user"></i>
                </div>
                <input
                    type="text"
                    name="usuario"
                    class="form-control"
                    placeholder="Ingresa tu usuario"
                    value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                    required autofocus
                >
            </div>
        </div>

        <!-- Contraseña -->
        <div class="mb-4">
            <label class="form-label">Contraseña</label>
            <div class="d-flex">
                <div class="input-group-addon">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Ingresa tu contraseña"
                    required
                >
            </div>
        </div>

        <!-- Botón -->
        <button type="submit" class="btn-login">
            <i class="fa-solid fa-right-to-bracket me-2"></i>
            Ingresar al Sistema
        </button>

    </form>

    <p class="footer-text">
        v<?= APP_VERSION ?> &nbsp;|&nbsp; Sistema ERP de Almacén
    </p>

</div>

</body>
</html>
