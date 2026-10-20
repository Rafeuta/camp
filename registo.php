<?php
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) { header("Location: dashboard.php"); exit; }

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $codigo   = trim($_POST['codigo'] ?? '');

    if ($nome === '' || $email === '' || $password === '') {
        $erro = "Preenche todos os campos obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } elseif (strlen($password) < 8) {
        $erro = "A password deve ter pelo menos 8 caracteres.";
    } else {
        $stmt = $pdo->prepare("SELECT id_utilizador FROM acomp_utilizadores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = "Este email já está registado.";
        } else {
            $tipo = 'aluno';
            if ($codigo !== '') {
                $stmt = $pdo->prepare("SELECT id_codigo FROM acomp_codigos_professor WHERE codigo = ? AND ativo = TRUE");
                $stmt->execute([$codigo]);
                if ($stmt->fetch()) { $tipo = 'professor'; }
                else { $erro = "Código de professor inválido."; }
            }
            if ($erro === '') {
                $stmt = $pdo->prepare("INSERT INTO acomp_utilizadores (nome, email, password, tipo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $password, $tipo]);
                setFlash("Conta criada com sucesso! Faz login.", 'success');
                header("Location: login.php");
                exit;
            }
        }
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
        <h2>Cria a tua conta em segundos.</h2>
        <p>Se és aluno, basta o teu nome, email e password. Se és professor, tens um código que te dá acesso a funcionalidades extra.</p>
    </div>

    <div class="auth-main">
        <div class="auth-box">
            <h1>Criar conta</h1>
            <p class="subtitle">Preenche os dados para começar.</p>

            <?php if ($erro): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($erro) ?></div>
            <?php endif; ?>

            <form method="POST" class="form">
                <div class="form-group">
                    <label>Nome completo <span class="req">*</span></label>
                    <input type="text" name="nome" required value="<?= e($_POST['nome'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password <span class="req">*</span></label>
                    <input type="password" name="password" required minlength="8" placeholder="Mín. 8 caracteres">
                </div>

                <details class="codigo">
                    <summary>Tenho um código de professor</summary>
                    <input type="text" name="codigo" placeholder="Código fornecido pela escola">
                </details>

                <button type="submit" class="btn btn-success" style="justify-content:center;">
                    <i class="fa-solid fa-user-plus"></i> Criar conta
                </button>
            </form>

            <div class="link">Já tens conta? <a href="login.php">Entrar</a></div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
