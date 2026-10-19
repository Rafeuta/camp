<?php
function isLoggedIn() { return isset($_SESSION['id_utilizador']); }
function isAluno()      { return isLoggedIn() && $_SESSION['tipo'] === 'aluno'; }
function isProfessor()  { return isLoggedIn() && $_SESSION['tipo'] === 'professor'; }

function requireLogin() {
    if (!isLoggedIn()) { header("Location: login.php"); exit; }
}
function requireAluno() {
    requireLogin();
    if (!isAluno()) { header("Location: dashboard.php"); exit; }
}
function requireProfessor() {
    requireLogin();
    if (!isProfessor()) { header("Location: dashboard.php"); exit; }
}

// Escapar output (prevenção de XSS)
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Mensagem flash
function setFlash($msg, $tipo = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}
function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}