<?php
require_once 'config.php';
require_once 'functions.php';
requireAluno();

$pageTitle = 'Recursos';
$id = $_SESSION['id_utilizador'];

$stmt = $pdo->prepare("SELECT id_projeto FROM acomp_projetos WHERE id_aluno=?");
$stmt->execute([$id]);
$projeto = $stmt->fetch();
if (!$projeto) { setFlash("Cria primeiro o teu projeto.", 'error'); header("Location: projeto.php"); exit; }
$pid = $projeto['id_projeto'];

if (isset($_GET['apagar'])) {
    $stmt = $pdo->prepare("DELETE FROM acomp_recursos WHERE id_recurso=? AND id_projeto=?");
    $stmt->execute([$_GET['apagar'], $pid]);
    setFlash("Recurso eliminado.");
    header("Location: recursos.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO acomp_recursos (titulo, tipo, url, descricao, id_projeto)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        trim($_POST['titulo']),
        $_POST['tipo'],
        trim($_POST['url']),
        trim($_POST['descricao']),
        $pid
    ]);
    setFlash("Recurso adicionado.");
    header("Location: recursos.php"); exit;
}

$recursos = $pdo->prepare("SELECT * FROM acomp_recursos WHERE id_projeto=? ORDER BY data_adicao DESC");
$recursos->execute([$pid]);
$recursos = $recursos->fetchAll();

require 'header.php';
?>

<h1>🔗 Recursos</h1>

<div class="card">
    <h2>Novo recurso</h2>
    <form method="POST">
        <div><label>Título *</label><input type="text" name="titulo" required></div>
        <div><label>Tipo</label>
            <select name="tipo">
                <option>Site</option><option>Documentacao</option>
                <option>Tutorial</option><option>Video</option><option>Outro</option>
            </select>
        </div>
        <div><label>URL</label><input type="url" name="url" placeholder="https://..."></div>
        <div><label>Descrição</label><textarea name="descricao"></textarea></div>
        <button class="btn btn-success" type="submit">➕ Adicionar recurso</button>
    </form>
</div>

<h2>Recursos guardados</h2>
<?php if (!$recursos): ?>
    <div class="card">Ainda não há recursos.</div>
<?php else: ?>
<table>
    <thead><tr><th>Título</th><th>Tipo</th><th>URL</th><th>Data</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recursos as $r): ?>
        <tr>
            <td><?= e($r['titulo']) ?></td>
            <td><?= e($r['tipo']) ?></td>
            <td>
                <?php if ($r['url']): ?>
                    <a href="<?= e($r['url']) ?>" target="_blank">Abrir 🔗</a>
                <?php endif; ?>
            </td>
            <td><?= e($r['data_adicao']) ?></td>
            <td>
                <a href="?apagar=<?= $r['id_recurso'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Eliminar recurso?')">🗑️</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require 'footer.php'; ?>