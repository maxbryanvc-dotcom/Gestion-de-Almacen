<?php
// ============================================================
// LAYOUT PRINCIPAL — sidebar, topbar, estilos globales
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

require_once __DIR__ . '/../config/app.php';

// Página actual para marcar enlace activo en el menú
$paginaActual = basename($_SERVER['PHP_SELF']);
$rolActual    = $_SESSION['rol'] ?? 'tecnico';

/**
 * Devuelve clase CSS "active" si la página coincide.
 */
function menuActivo(string $pagina): string {
    global $paginaActual;
    return ($paginaActual === $pagina) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — ERP</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        /* ===================== RESET ===================== */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Inter',sans-serif;
            background:#0f172a;
            color:white;
            overflow-x:hidden;
        }
        body.light-mode { background:#f1f5f9; color:#0f172a; }

        /* ==================== SIDEBAR ==================== */
        .sidebar {
            width:260px; height:100vh;
            background:#111827;
            position:fixed; top:0; left:0;
            padding:0;
            box-shadow:4px 0 20px rgba(0,0,0,0.25);
            z-index:1000;
            transition:width 0.3s ease;
            overflow-y:auto; overflow-x:hidden;
            display:flex; flex-direction:column;
        }
        body.light-mode .sidebar { background:#1e293b; }

        .sidebar.collapsed { width:72px; }
        .sidebar.collapsed .sidebar-label,
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .user-details { display:none !important; }
        .sidebar.collapsed .sidebar-link { justify-content:center; padding:14px; }
        .sidebar.collapsed .sidebar-badge { display:none; }

        /* Logo área */
        .sidebar-logo {
            padding:24px 20px 20px;
            border-bottom:1px solid rgba(255,255,255,0.06);
            display:flex; align-items:center; gap:12px;
            flex-shrink:0;
        }
        .logo-icon-sq {
            width:40px; height:40px; flex-shrink:0;
            background:linear-gradient(135deg,#3b82f6,#6366f1);
            border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; color:white;
        }
        .logo-text { font-weight:700; font-size:16px; color:white; }
        .logo-sub  { font-size:10px; color:#64748b; }

        /* Sección del usuario en sidebar */
        .sidebar-user {
            padding:16px 20px;
            border-bottom:1px solid rgba(255,255,255,0.06);
            display:flex; align-items:center; gap:10px; flex-shrink:0;
        }
        .user-avatar {
            width:36px; height:36px; flex-shrink:0;
            border-radius:10px;
            background:linear-gradient(135deg,#10b981,#059669);
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:14px; color:white;
        }
        .user-details { min-width:0; }
        .user-name  { font-size:13px; font-weight:600; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .user-role  { font-size:11px; color:#64748b; }

        /* Menú */
        .sidebar-nav { flex:1; padding:12px 12px; overflow-y:auto; }
        .sidebar-section-title {
            font-size:10px; font-weight:600; color:#475569;
            text-transform:uppercase; letter-spacing:0.08em;
            padding:12px 8px 6px;
        }
        .sidebar-link {
            display:flex; align-items:center; gap:12px;
            padding:11px 14px; border-radius:12px;
            color:#94a3b8; text-decoration:none;
            font-size:13.5px; font-weight:500;
            transition:all 0.2s; margin-bottom:2px;
            position:relative; white-space:nowrap;
        }
        .sidebar-link i { width:18px; text-align:center; font-size:14px; flex-shrink:0; }
        .sidebar-link:hover { background:rgba(255,255,255,0.06); color:white; }
        .sidebar-link.active {
            background:linear-gradient(135deg,rgba(59,130,246,0.2),rgba(99,102,241,0.15));
            color:#60a5fa; border:1px solid rgba(59,130,246,0.2);
        }
        .sidebar-badge {
            margin-left:auto; background:#ef4444;
            color:white; font-size:10px; font-weight:700;
            padding:1px 6px; border-radius:20px;
        }

        /* ==================== MAIN ==================== */
        .main-content {
            margin-left:260px; min-height:100vh;
            padding:24px; transition:margin-left 0.3s ease;
        }
        .main-content.expanded { margin-left:72px; }

        /* ==================== TOPBAR ==================== */
        .topbar {
            background:#111827;
            padding:14px 20px; border-radius:18px;
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:28px;
            box-shadow:0 4px 20px rgba(0,0,0,0.2);
        }
        body.light-mode .topbar { background:white; color:#0f172a; }

        .topbar-left { display:flex; align-items:center; gap:12px; }
        .topbar-right { display:flex; align-items:center; gap:10px; }

        .btn-icon {
            width:38px; height:38px; border-radius:10px;
            background:rgba(255,255,255,0.06); border:none; color:#94a3b8;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:0.2s; font-size:14px;
        }
        .btn-icon:hover { background:rgba(255,255,255,0.12); color:white; }
        body.light-mode .btn-icon { background:#f1f5f9; color:#64748b; }
        body.light-mode .btn-icon:hover { background:#e2e8f0; color:#0f172a; }

        .topbar-user {
            display:flex; align-items:center; gap:8px;
            padding:6px 14px; background:rgba(255,255,255,0.05);
            border-radius:12px; border:1px solid rgba(255,255,255,0.06);
        }
        body.light-mode .topbar-user { background:#f8fafc; border-color:#e2e8f0; }
        .topbar-uname { font-size:13px; font-weight:600; color:white; }
        body.light-mode .topbar-uname { color:#0f172a; }
        .topbar-role  { font-size:11px; color:#64748b; }

        /* ==================== CARDS ==================== */
        .card-dashboard {
            background:linear-gradient(145deg,#1e293b,#0f172a);
            border:1px solid rgba(255,255,255,0.04);
            border-radius:22px; padding:24px;
            box-shadow:0 8px 24px rgba(0,0,0,0.2);
            transition:transform 0.25s ease, box-shadow 0.25s ease;
            position:relative; overflow:hidden;
        }
        .card-dashboard::before {
            content:''; position:absolute;
            width:120px; height:120px;
            background:rgba(255,255,255,0.03);
            border-radius:50%; top:-30px; right:-30px;
        }
        .card-dashboard:hover {
            transform:translateY(-4px);
            box-shadow:0 16px 40px rgba(0,0,0,0.3);
        }
        body.light-mode .card-dashboard {
            background:white; color:#0f172a;
            box-shadow:0 4px 20px rgba(0,0,0,0.07);
            border-color:#e2e8f0;
        }

        /* ==================== TABLAS ==================== */
        .table { color:white; }
        body.light-mode .table { color:#0f172a; }
        .table thead { background:#111827; }
        body.light-mode .table thead { background:#f1f5f9; }
        .table tbody tr { transition:background 0.15s; }
        .table-hover tbody tr:hover { background:rgba(255,255,255,0.04) !important; }
        body.light-mode .table-hover tbody tr:hover { background:#f8fafc !important; }

        /* DataTables */
        .dataTables_wrapper { color:white; }
        body.light-mode .dataTables_wrapper { color:#0f172a; }
        .dataTables_filter input,
        .dataTables_length select {
            background:#1e293b !important; color:white !important;
            border:1px solid #334155 !important; border-radius:10px !important;
            padding:6px 10px !important;
        }
        body.light-mode .dataTables_filter input,
        body.light-mode .dataTables_length select {
            background:#f8fafc !important; color:#0f172a !important;
            border-color:#cbd5e1 !important;
        }
        .page-link { background:#1e293b !important; color:white !important; border:none !important; }
        .page-item.active .page-link { background:#3b82f6 !important; }
        body.light-mode .page-link { background:#f1f5f9 !important; color:#0f172a !important; }

        /* Form controls dentro de modales y formularios */
        .form-control, .form-select {
            background:#1e293b; border:1px solid #334155;
            color:white; border-radius:12px; padding:11px 14px;
        }
        .form-control:focus, .form-select:focus {
            background:#1e293b; border-color:#3b82f6;
            color:white; box-shadow:none;
        }
        .form-control::placeholder { color:#475569; }
        body.light-mode .form-control,
        body.light-mode .form-select {
            background:#f8fafc; border-color:#cbd5e1;
            color:#0f172a;
        }
        body.light-mode .form-control:focus,
        body.light-mode .form-select:focus {
            background:white; border-color:#3b82f6; color:#0f172a;
        }
        .form-label { font-weight:500; font-size:13px; color:#94a3b8; margin-bottom:6px; }
        body.light-mode .form-label { color:#475569; }

        /* Botones */
        .btn-custom { border-radius:12px; padding:9px 18px; font-weight:600; font-size:13.5px; }
        .btn-primary { background:#3b82f6; border-color:#3b82f6; }
        .btn-primary:hover { background:#2563eb; border-color:#2563eb; }

        /* Alertas visuales */
        .alert-card {
            display:flex; align-items:flex-start; gap:14px;
            padding:16px; border-radius:14px; margin-bottom:12px;
        }
        .alert-card i { font-size:20px; flex-shrink:0; margin-top:2px; }
        .alert-card.danger  { background:rgba(239,68,68,0.12);  border:1px solid rgba(239,68,68,0.2); }
        .alert-card.warning { background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.2); }
        .alert-card.info    { background:rgba(59,130,246,0.12); border:1px solid rgba(59,130,246,0.2); }
        .alert-card.success { background:rgba(34,197,94,0.12);  border:1px solid rgba(34,197,94,0.2); }

        /* Modales */
        .modal-content {
            background:#111827; border:1px solid rgba(255,255,255,0.08);
            border-radius:20px; color:white;
        }
        body.light-mode .modal-content { background:white; color:#0f172a; }
        .modal-header { border-bottom:1px solid rgba(255,255,255,0.06); padding:20px 24px; }
        .modal-body   { padding:24px; }
        .modal-footer { border-top:1px solid rgba(255,255,255,0.06); padding:16px 24px; }
        .btn-close-white { filter:invert(1); }

        /* ==================== RESPONSIVE ==================== */
        @media(max-width:992px){
            .sidebar { width:72px; }
            .sidebar .sidebar-label,
            .sidebar .logo-text,
            .sidebar .sidebar-section-title,
            .sidebar .user-details { display:none !important; }
            .sidebar .sidebar-link { justify-content:center; padding:14px; }
            .main-content { margin-left:72px; }
        }
        @media(max-width:576px){
            .main-content { padding:14px; }
            .topbar { border-radius:12px; }
        }

        /* ==================== MEJORAS VISUALES ==================== */

        /* 1. Scrollbar del sidebar */
        .sidebar::-webkit-scrollbar { width:4px; }
        .sidebar::-webkit-scrollbar-track { background:transparent; }
        .sidebar::-webkit-scrollbar-thumb { background:#334155; border-radius:10px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background:#475569; }

        /* 2. Enlace activo con barra lateral izquierda */
        .sidebar-link.active {
            background:linear-gradient(135deg,rgba(59,130,246,0.18),rgba(99,102,241,0.12));
            color:#93c5fd;
            border:1px solid rgba(59,130,246,0.25);
            box-shadow:0 2px 8px rgba(59,130,246,0.1);
            position:relative;
        }
        .sidebar-link.active::before {
            content:'';
            position:absolute;
            left:0; top:20%; bottom:20%;
            width:3px;
            background:linear-gradient(180deg,#3b82f6,#6366f1);
            border-radius:0 4px 4px 0;
        }
        .sidebar-link.active i { color:#60a5fa; }
        .sidebar-link:hover {
            background:rgba(255,255,255,0.07);
            color:#e2e8f0;
            transform:translateX(2px);
        }

        /* 3. Cards con acento de color en borde superior */
        .card-dashboard {
            background:linear-gradient(160deg,#1a2744 0%,#0f172a 100%);
            border:1px solid rgba(255,255,255,0.06);
            border-radius:20px; padding:24px;
            box-shadow:0 4px 24px rgba(0,0,0,0.25);
            transition:transform 0.25s ease, box-shadow 0.25s ease;
            position:relative; overflow:hidden;
        }
        .card-dashboard::after {
            content:'';
            position:absolute;
            top:0; left:0; right:0;
            height:2px;
            background:linear-gradient(90deg,#3b82f6,#6366f1,#8b5cf6);
            border-radius:20px 20px 0 0;
            opacity:0.6;
        }
        .card-dashboard:hover {
            transform:translateY(-3px);
            box-shadow:0 12px 36px rgba(0,0,0,0.35);
        }
        .card-dashboard:hover::after { opacity:1; }
        body.light-mode .card-dashboard {
            background:white; color:#0f172a;
            box-shadow:0 4px 16px rgba(0,0,0,0.07);
            border-color:#e2e8f0;
        }

        /* 4. Cabeceras de tabla mejoradas */
        .table thead tr th {
            background:linear-gradient(135deg,#1e293b,#0f172a);
            color:#94a3b8;
            font-size:11px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:0.06em;
            padding:14px 12px;
            border-bottom:2px solid rgba(59,130,246,0.3) !important;
            border-top:none !important;
        }
        .table tbody td {
            padding:12px;
            border-color:rgba(255,255,255,0.04) !important;
            vertical-align:middle;
        }
        body.light-mode .table thead tr th {
            background:#f8fafc; color:#475569;
            border-bottom:2px solid #e2e8f0 !important;
        }
        body.light-mode .table tbody td {
            border-color:#f1f5f9 !important;
        }

        /* 5. Botones con gradiente y sombra */
        .btn-primary {
            background:linear-gradient(135deg,#3b82f6,#6366f1) !important;
            border:none !important;
            box-shadow:0 4px 12px rgba(59,130,246,0.3);
            transition:all 0.2s !important;
        }
        .btn-primary:hover {
            background:linear-gradient(135deg,#2563eb,#4f46e5) !important;
            box-shadow:0 6px 20px rgba(59,130,246,0.45) !important;
            transform:translateY(-1px);
        }
        .btn-success {
            background:linear-gradient(135deg,#22c55e,#16a34a) !important;
            border:none !important;
            box-shadow:0 4px 12px rgba(34,197,94,0.25);
            transition:all 0.2s !important;
        }
        .btn-success:hover {
            box-shadow:0 6px 20px rgba(34,197,94,0.4) !important;
            transform:translateY(-1px);
        }
        .btn-danger {
            background:linear-gradient(135deg,#ef4444,#dc2626) !important;
            border:none !important;
            box-shadow:0 4px 12px rgba(239,68,68,0.25);
            transition:all 0.2s !important;
        }
        .btn-danger:hover {
            box-shadow:0 6px 20px rgba(239,68,68,0.4) !important;
            transform:translateY(-1px);
        }
        .btn-warning {
            background:linear-gradient(135deg,#f59e0b,#d97706) !important;
            border:none !important;
            box-shadow:0 4px 12px rgba(245,158,11,0.25);
            transition:all 0.2s !important;
        }
        .btn-warning:hover {
            box-shadow:0 6px 20px rgba(245,158,11,0.4) !important;
            transform:translateY(-1px);
        }

        /* 6. Inputs con glow al enfocar */
        .form-control:focus, .form-select:focus {
            background:#1e293b !important;
            border-color:#3b82f6 !important;
            color:white !important;
            box-shadow:0 0 0 3px rgba(59,130,246,0.15) !important;
        }
        body.light-mode .form-control:focus,
        body.light-mode .form-select:focus {
            background:white !important;
            color:#0f172a !important;
            box-shadow:0 0 0 3px rgba(59,130,246,0.12) !important;
        }

        /* 7. Animación fade-in de página */
        .main-content { animation:fadeInPage 0.35s ease; }
        @keyframes fadeInPage {
            from { opacity:0; transform:translateY(6px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* Badges más pulidos */
        .badge {
            font-weight:600;
            letter-spacing:0.02em;
            padding:5px 10px;
            border-radius:8px;
        }

        /* Secciones del sidebar con línea decorativa */
        .sidebar-section-title {
            display:flex; align-items:center; gap:8px;
        }
        .sidebar-section-title::after {
            content:'';
            flex:1;
            height:1px;
            background:rgba(255,255,255,0.05);
        }

        /* Logo con brillo al hover */
        .logo-icon-sq {
            transition:0.3s;
            box-shadow:0 4px 12px rgba(59,130,246,0.3);
        }
        .sidebar-logo:hover .logo-icon-sq {
            box-shadow:0 6px 20px rgba(59,130,246,0.5);
            transform:scale(1.05);
        }

        /* Topbar mejorado */
        .topbar {
            background:linear-gradient(135deg,#141e2e,#111827);
            border:1px solid rgba(255,255,255,0.05);
            box-shadow:0 4px 20px rgba(0,0,0,0.25);
        }

        /* Avatar de usuario con brillo */
        .user-avatar {
            box-shadow:0 0 0 2px rgba(16,185,129,0.3);
            transition:0.3s;
        }
        .sidebar-user:hover .user-avatar {
            box-shadow:0 0 0 3px rgba(16,185,129,0.5);
        }

        /* Scrollbar global */
        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background:rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background:#334155; border-radius:10px; }
        ::-webkit-scrollbar-thumb:hover { background:#475569; }
    </style>
</head>
<body>

<!-- ======================== SIDEBAR ======================== -->
<div class="sidebar" id="mainSidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon-sq"><i class="fa-solid fa-warehouse"></i></div>
        <div>
            <div class="logo-text"><?= APP_NAME ?></div>
            <div class="logo-sub">Sistema ERP</div>
        </div>
    </div>

    <!-- Info usuario -->
    <div class="sidebar-user">
        <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="user-details">
            <div class="user-name"><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') ?></div>
            <div class="user-role">
                <?= match($rolActual) {
                    'admin'   => 'Administrador',
                    'almacen' => 'Almacenero',
                    'tecnico' => 'Técnico',
                    default   => $rolActual,
                } ?>
            </div>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="sidebar-nav">

        <!-- GENERAL -->
        <div class="sidebar-section-title">General</div>

        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="sidebar-link <?= menuActivo('dashboard.php') ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span class="sidebar-label">Dashboard</span>
        </a>

        <!-- INVENTARIO -->
        <div class="sidebar-section-title">Inventario</div>

        <a href="<?= BASE_URL ?>/pages/materiales.php" class="sidebar-link <?= menuActivo('materiales.php') ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span class="sidebar-label">Materiales</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/entrada_material.php" class="sidebar-link <?= menuActivo('entrada_material.php') ?>">
            <i class="fa-solid fa-download"></i>
            <span class="sidebar-label">Entradas</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/salida_material.php" class="sidebar-link <?= menuActivo('salida_material.php') ?>">
            <i class="fa-solid fa-upload"></i>
            <span class="sidebar-label">Salidas</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/reingreso_material.php" class="sidebar-link <?= menuActivo('reingreso_material.php') ?>">
            <i class="fa-solid fa-rotate-left"></i>
            <span class="sidebar-label">Reingresos</span>
        </a>

        <!-- GESTIÓN -->
        <div class="sidebar-section-title">Gestión</div>

        <a href="<?= BASE_URL ?>/pages/ordenes_trabajo.php" class="sidebar-link <?= menuActivo('ordenes_trabajo.php') ?>">
            <i class="fa-solid fa-clipboard-list"></i>
            <span class="sidebar-label">Órdenes de Trabajo</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/requerimiento.php" class="sidebar-link <?= menuActivo('requerimiento.php') ?>">
            <i class="fa-solid fa-file-lines"></i>
            <span class="sidebar-label">Requerimientos</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/tecnicos.php" class="sidebar-link <?= menuActivo('tecnicos.php') ?>">
            <i class="fa-solid fa-hard-hat"></i>
            <span class="sidebar-label">Técnicos</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/kardex.php" class="sidebar-link <?= menuActivo('kardex.php') ?>">
            <i class="fa-solid fa-book-open"></i>
            <span class="sidebar-label">Kardex</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/historial.php" class="sidebar-link <?= menuActivo('historial.php') ?>">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span class="sidebar-label">Historial</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/liquidacion.php" class="sidebar-link <?= menuActivo('liquidacion.php') ?>">
            <i class="fa-solid fa-file-invoice"></i>
            <span class="sidebar-label">Liquidación</span>
        </a>

        <?php
        // ============================================================
        // MÓDULO PLANTILLAS DINÁMICAS — actualmente desactivado del menú
        // Para activar: quita el bloque de comentario /* */ de abajo
        // Archivo: pages/plantillas.php
        // ============================================================
        /*
        ?>
        <!-- CONFIGURACIÓN -->
        <div class="sidebar-section-title">Configuración</div>

        <a href="<?= BASE_URL ?>/pages/plantillas.php" class="sidebar-link <?= menuActivo('plantillas.php') ?>">
            <i class="fa-solid fa-file-contract"></i>
            <span class="sidebar-label">Plantillas</span>
        </a>
        <?php
        */
        ?>

        <!-- ADMINISTRACIÓN — solo admin y almacen -->
        <?php if (in_array($rolActual, ['admin','almacen'])): ?>
        <div class="sidebar-section-title">Exportación</div>

        <a href="<?= BASE_URL ?>/exports/exportar_materiales_excel.php" class="sidebar-link <?= menuActivo('exportar_materiales_excel.php') ?>">
            <i class="fa-solid fa-file-excel"></i>
            <span class="sidebar-label">Excel</span>
        </a>

        <a href="<?= BASE_URL ?>/exports/reporte_kardex.php" class="sidebar-link <?= menuActivo('reporte_kardex.php') ?>">
            <i class="fa-solid fa-file-pdf"></i>
            <span class="sidebar-label">PDF Kardex</span>
        </a>
        <?php endif; ?>

        <!-- ADMIN EXCLUSIVO -->
        <?php if ($rolActual === 'admin'): ?>
        <div class="sidebar-section-title">Administración</div>

        <a href="<?= BASE_URL ?>/pages/usuarios.php" class="sidebar-link <?= menuActivo('usuarios.php') ?>">
            <i class="fa-solid fa-users-gear"></i>
            <span class="sidebar-label">Usuarios</span>
        </a>
        <?php endif; ?>

    </nav>

</div><!-- /sidebar -->

<!-- ======================== CONTENIDO ======================== -->
<div class="main-content" id="mainContent">

    <!-- TOPBAR -->
    <div class="topbar">

        <div class="topbar-left">
            <!-- Toggle sidebar -->
            <button class="btn-icon" onclick="toggleSidebar()" title="Colapsar menú">
                <i class="fa-solid fa-bars"></i>
            </button>
            <!-- Breadcrumb con página actual -->
            <div style="display:flex;align-items:center;gap:6px;">
                <span style="font-size:12px;color:#475569;"><?= APP_NAME ?></span>
                <span style="color:#334155;font-size:12px;">/</span>
                <span style="font-size:13px;color:#94a3b8;font-weight:500;">
                    <?php
                    $nombres = [
                        'dashboard.php'          => 'Dashboard',
                        'materiales.php'         => 'Materiales',
                        'entrada_material.php'   => 'Entradas',
                        'salida_material.php'    => 'Salidas',
                        'reingreso_material.php' => 'Reingresos',
                        'requerimiento.php'      => 'Requerimientos',
                        'ordenes_trabajo.php'    => 'Órdenes de Trabajo',
                        'tecnicos.php'           => 'Técnicos',
                        'historial.php'          => 'Historial',
                        'kardex.php'             => 'Kardex',
                        'liquidacion.php'        => 'Liquidación',
                        'usuarios.php'           => 'Usuarios',
                        'plantillas.php'         => 'Plantillas',
                        'agregar_material.php'   => 'Agregar Material',
                        'editar_material.php'    => 'Editar Material',
                        'ver_requerimiento.php'  => 'Ver Requerimiento',
                    ];
                    echo $nombres[$paginaActual] ?? ucfirst(str_replace(['.php','_'], ['', ' '], $paginaActual));
                    ?>
                </span>
            </div>
        </div>

        <div class="topbar-right">
            <!-- Toggle dark/light mode -->
            <button class="btn-icon" onclick="toggleDarkMode()" title="Cambiar tema" id="btnTema">
                <i class="fa-solid fa-moon"></i>
            </button>

            <!-- Info usuario -->
            <div class="topbar-user">
                <i class="fa-solid fa-circle-user" style="color:#3b82f6;font-size:18px;"></i>
                <div>
                    <div class="topbar-uname"><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') ?></div>
                    <div class="topbar-role">
                        <?= match($rolActual) {
                            'admin'   => 'Administrador',
                            'almacen' => 'Almacenero',
                            'tecnico' => 'Técnico',
                            default   => $rolActual,
                        } ?>
                    </div>
                </div>
            </div>

            <!-- Cerrar sesión -->
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger btn-custom" title="Salir">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="d-none d-md-inline ms-1">Salir</span>
            </a>
        </div>

    </div><!-- /topbar -->

<!-- JS globales -->
<script>
/* ========= SIDEBAR ========= */
function toggleSidebar(){
    document.getElementById('mainSidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('expanded');
    localStorage.setItem('sidebar', document.getElementById('mainSidebar').classList.contains('collapsed') ? '1' : '0');
}

/* ========= DARK MODE ========= */
function toggleDarkMode(){
    const isDark = document.body.classList.toggle('light-mode');
    const icon   = document.getElementById('btnTema').querySelector('i');
    icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
}

/* ========= PERSISTENCIA ========= */
(function(){
    if(localStorage.getItem('sidebar') === '1'){
        document.getElementById('mainSidebar').classList.add('collapsed');
        document.getElementById('mainContent').classList.add('expanded');
    }
    if(localStorage.getItem('theme') === 'light'){
        document.body.classList.add('light-mode');
        const icon = document.getElementById('btnTema');
        if(icon) icon.querySelector('i').className = 'fa-solid fa-sun';
    }
})();

/* ========= BASE URL (para fetch/ajax) ========= */
const BASE_URL = '<?= BASE_URL ?>';

/* ========= DATATABLES IDIOMA ========= */
const dtLang = {
    search:"Buscar:",
    lengthMenu:"Mostrar _MENU_ registros",
    info:"Mostrando _START_ a _END_ de _TOTAL_ registros",
    infoEmpty:"Sin registros",
    zeroRecords:"No se encontraron resultados",
    paginate:{ next:"Siguiente", previous:"Anterior" }
};
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/materiales_ajax.js"></script>
