<?php
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) { header("Location: dashboard.php"); exit; }

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM acomp_utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        $_SESSION['id_utilizador'] = $user['id_utilizador'];
        $_SESSION['nome']          = $user['nome'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['tipo']          = $user['tipo'];
        header("Location: dashboard.php");
        exit;
    } else {
        $erro = "Email ou password incorretos.";
    }
}
require 'header.php';
?>

<div class="auth-page">
    <div class="auth-side">
        <div class="auth-brand">
            <div class="logo">A</div>
            <div>
                <div style="font-weight:700;font-size:18px;">ACMP</div>
                <div style="font-size:12px;opacity:.8;letter-spacing:1px;text-transform:uppercase;">Acompanhamento PAP</div>
            </div>
        </div>
        <h2>Acompanha o teu projeto de PAP, do início ao fim.</h2>
        <p>Uma plataforma pensada para alunos e professores acompanharem cada fase do desenvolvimento, com tarefas, registos diários, recursos e feedback contínuo.</p>

        <div class="auth-features">
            <div class="auth-feature"><i class="fa-solid fa-list-check"></i> Gestão de tarefas</div>
            <div class="auth-feature"><i class="fa-solid fa-book"></i> Diário de trabalho</div>
            <div class="auth-feature"><i class="fa-solid fa-comments"></i> Feedback do orientador</div>
        </div>
    </div>

    <div class="auth-main">
        <div class="auth-box">
            <h1>Bem-vindo de volta 👋</h1>
            <p class="subtitle">Entra na tua conta para continuar.</p>

            <?php if ($erro): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($erro) ?></div>
            <?php endif; ?>

            <form method="POST" class="form">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="o.teu@email.pt">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn" style="justify-content:center;">
                    <i class="fa-solid fa-right-to-bracket"></i> Entrar
                </button>
            </form>

            <div class="link">Não tens conta? <a href="registo.php">Criar conta</a></div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>