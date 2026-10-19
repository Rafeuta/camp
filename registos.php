<?php
require_once 'config.php';
require_once 'functions.php';
requireAluno();

$pageTitle = 'Registos diários';
$id = $_SESSION['id_utilizador'];

$stmt = $pdo->prepare("SELECT id_projeto FROM acomp_projetos WHERE id_aluno=?");
$stmt->execute([$id]);
$projeto = $stmt->fetch();

if (!$projeto) { setFlash("Cria primeiro o teu projeto.", 'error'); header("Location: projeto.php"); exit; }
$pid = $projeto['id_projeto'];

if (isset($_GET['apagar'])) {
    $stmt = $pdo->prepare("DELETE FROM acomp_registos_diarios WHERE id_registo=? AND id_projeto=?");
    $stmt->execute([$_GET['apagar'], $pid]);
    setFlash("Registo eliminado.");
    header("Location: registos.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO acomp_registos_diarios (data, descricao, horas_trabalho, id_projeto)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['data'],
        trim($_POST['descricao']),
        (float) $_POST['horas_trabalho'],
        $pid
    ]);
    setFlash("Registo guardado.");
    header("Location: registos.php"); exit;
}

$registos = $pdo->prepare("SELECT * FROM acomp_registos_diarios WHERE id_projeto=? ORDER BY data DESC");
$registos->execute([$pid]);
$registos = $registos->fetchAll();

require 'header.php';
?>

<h1>📓 Registos diários</h1>

<div class="card">
    <h2>Novo registo</h2>
    <form method="POST">
        <div><label>Data *</label><input type="date" name="data" required value="<?= date('Y-m-d') ?>"></div>
        <div><label>Descrição *</label><textarea name="descricao" required></textarea></div>
        <div><label>Horas trabalhadas</label><input type="number" step="0.5" min="0" name="horas_trabalho" value="1"></div>
        <button class="btn btn-success" type="submit">➕ Adicionar registo</button>
    </form>
</div>

<h2>Histórico</h2>
<?php if (!$registos): ?>
    <div class="card">Ainda não há registos.</div>
<?php else: ?>
<table>
    <thead><tr><th>Data</th><th>Descrição</th><th>Horas</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($registos as $r): ?>
        <tr>
            <td><?= e($r['data']) ?></td>
            <td><?= nl2br(e($r['descricao'])) ?></td>
            <td><?= e($r['horas_trabalho']) ?>h</td>
            <td>
                <a href="?apagar=<?= $r['id_registo'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Eliminar este registo?')">🗑️</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require 'footer.php'; ?>