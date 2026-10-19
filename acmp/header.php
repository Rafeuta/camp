<?php
if (!isset($pdo)) require_once 'config.php';
require_once 'functions.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = basename($_SERVER['PHP_SELF']);
$iniciais = '';
if (isLoggedIn()) {
    $partes = explode(' ', trim($_SESSION['nome']));
    $iniciais = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · ACMP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Aplica o tema imediatamente (evita flash)
        (function() {
            const t = localStorage.getItem('acmp-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">A</div>
            <div>
                <div class="brand-name">ACMP</div>
                <div class="brand-sub">Acompanhamento PAP</div>
            </div>
        </div>

        <div class="sidebar-section">Menu</div>
        <nav class="sidebar-nav">
            <a class="nav-item <?= $activePage==='dashboard.php'?'active':'' ?>" href="dashboard.php">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>

            <?php if (isAluno()): ?>
                <a class="nav-item <?= $activePage==='projeto.php'?'active':'' ?>" href="projeto.php">
                    <i class="fa-solid fa-diagram-project"></i> O meu projeto
                </a>
                <a class="nav-item <?= $activePage==='tarefas.php'?'active':'' ?>" href="tarefas.php">
                    <i class="fa-solid fa-list-check"></i> Tarefas
                </a>
                <a class="nav-item <?= $activePage==='registos.php'?'active':'' ?>" href="registos.php">
                    <i class="fa-solid fa-book"></i> Registos diários
                </a>
                <a class="nav-item <?= $activePage==='recursos.php'?'active':'' ?>" href="recursos.php">
                    <i class="fa-solid fa-link"></i> Recursos
                </a>

                <a class="nav-item <?= $activePage==='calendario.php'?'active':'' ?>" href="calendario.php">
    <i class="fa-solid fa-calendar-days"></i> Calendário
</a>
            <?php endif; ?>

            <?php if (isProfessor()): ?>
                <a class="nav-item <?= $activePage==='projetos_orientados.php'?'active':'' ?>" href="projetos_orientados.php">
                    <i class="fa-solid fa-folder-open"></i> Projetos orientados
                </a>
            <?php endif; ?>

            <div class="sidebar-section">Comunicação</div>
            <a class="nav-item <?= $activePage==='feedbacks.php'?'active':'' ?>" href="feedbacks.php">
                <i class="fa-solid fa-comments"></i> Feedbacks
            </a>

            <a class="nav-item <?= $activePage==='calendario.php'?'active':'' ?>" href="calendario.php">
    <i class="fa-solid fa-calendar-days"></i> Calendário
</a>

        </nav>

        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar"><?= e($iniciais) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= e($_SESSION['nome']) ?></div>
                    <div class="user-role"><?= isAluno() ? 'Aluno' : 'Professor' ?></div>
                </div>
                <a href="logout.php" class="logout" title="Sair"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- MAIN -->
    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($pageTitle) ?></h1>

            <button class="icon-btn" id="themeToggle" title="Mudar tema">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
        </header>

        <div class="content">
            <?php if ($f = getFlash()): ?>
                <div class="alert alert-<?= $f['tipo']==='success'?'success':'error' ?>">
                    <i class="fa-solid <?= $f['tipo']==='success'?'fa-circle-check':'fa-circle-exclamation' ?>"></i>
                    <?= e($f['msg']) ?>
                </div>
            <?php endif; ?>
<?php else: ?>
    <?php if ($f = getFlash()): ?>
        <div style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:1000;max-width:500px;width:90%;">
            <div class="alert alert-success"><?= e($f['msg']) ?></div>
        </div>
    <?php endif; ?>
<?php endif; ?>