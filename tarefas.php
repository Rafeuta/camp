<?php
require_once 'config.php';
require_once 'functions.php';
requireAluno();

$pageTitle = 'Tarefas';
$id = $_SESSION['id_utilizador'];

// Buscar projeto do aluno
$stmt = $pdo->prepare("SELECT id_projeto FROM acomp_projetos WHERE id_aluno = ?");
$stmt->execute([$id]);
$projeto = $stmt->fetch();

if (!$projeto) {
    setFlash("Cria primeiro o teu projeto.", 'error');
    header("Location: projeto.php");
    exit;
}
$pid = $projeto['id_projeto'];

/* =========================================================
   AÇÕES
   ========================================================= */

// MUDANÇA RÁPIDA DE ESTADO (via GET, retorna à mesma página)
if (isset($_GET['estado']) && isset($_GET['id'])) {
    $novoEstado = $_GET['estado'];
    if (in_array($novoEstado, ['Pendente','Em curso','Concluida'])) {
        $dataConclusao = $novoEstado === 'Concluida' ? date('Y-m-d') : null;
        $stmt = $pdo->prepare("UPDATE acomp_tarefas SET estado=?, data_conclusao=? WHERE id_tarefa=? AND id_projeto=?");
        $stmt->execute([$novoEstado, $dataConclusao, $_GET['id'], $pid]);
        setFlash("Estado atualizado.");
    }
    header("Location: tarefas.php");
    exit;
}

// APAGAR
if (isset($_GET['apagar'])) {
    $stmt = $pdo->prepare("DELETE FROM acomp_tarefas WHERE id_tarefa=? AND id_projeto=?");
    $stmt->execute([$_GET['apagar'], $pid]);
    setFlash("Tarefa eliminada.");
    header("Location: tarefas.php");
    exit;
}

// GUARDAR (criar ou editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idTarefa = $_POST['id_tarefa'] ?? null;
    $dados = [
        trim($_POST['titulo']),
        trim($_POST['descricao']),
        $_POST['estado'],
        $_POST['prioridade'],
        $_POST['data_inicio'] ?: null,
        $_POST['data_fim'] ?: null,
    ];

    // Data de conclusão automática
    $dataConclusao = ($_POST['estado'] === 'Concluida') ? date('Y-m-d') : null;

    if ($idTarefa) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE acomp_tarefas
            SET titulo=?, descricao=?, estado=?, prioridade=?, data_inicio=?, data_fim=?, data_conclusao=?
            WHERE id_tarefa=? AND id_projeto=?
        ");
        $stmt->execute([...$dados, $dataConclusao, $idTarefa, $pid]);
        setFlash("Tarefa atualizada com sucesso.");
    } else {
        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO acomp_tarefas (titulo, descricao, estado, prioridade, data_inicio, data_fim, data_conclusao, id_projeto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([...$dados, $dataConclusao, $pid]);
        setFlash("Tarefa adicionada.");
    }
    header("Location: tarefas.php");
    exit;
}

/* =========================================================
   CARREGAR DADOS
   ========================================================= */

// Se estiver em modo edição
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM acomp_tarefas WHERE id_tarefa=? AND id_projeto=?");
    $stmt->execute([$_GET['editar'], $pid]);
    $editar = $stmt->fetch();
}

$tarefas = $pdo->prepare("SELECT * FROM acomp_tarefas WHERE id_projeto=? ORDER BY data_fim IS NULL, data_fim ASC");
$tarefas->execute([$pid]);
$tarefas = $tarefas->fetchAll();

require 'header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-size:20px;font-weight:800;letter-spacing:-.3px;">Tarefas do projeto</h2>
        <p style="color:var(--text-muted);font-size:14px;margin-top:2px;"><?= count($tarefas) ?> tarefa(s) registada(s).</p>
    </div>
</div>

<div class="grid-2" style="gap:20px;">

    <!-- FORMULÁRIO -->
    <div class="card" style="position:sticky;top:88px;align-self:start;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-<?= $editar ? 'pen' : 'plus' ?>"></i>
                <?= $editar ? 'Editar tarefa' : 'Nova tarefa' ?>
            </div>
            <?php if ($editar): ?>
                <a href="tarefas.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Cancelar</a>
            <?php endif; ?>
        </div>

        <form method="POST" class="form">
            <?php if ($editar): ?>
                <input type="hidden" name="id_tarefa" value="<?= $editar['id_tarefa'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Título <span class="req">*</span></label>
                <input type="text" name="titulo" required value="<?= e($editar['titulo'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao"><?= e($editar['descricao'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado">
                        <?php foreach (['Pendente','Em curso','Concluida'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($editar['estado'] ?? '') === $est ? 'selected' : '' ?>><?= $est ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Prioridade</label>
                    <select name="prioridade">
                        <?php foreach (['Baixa','Media','Alta'] as $pr): ?>
                            <option value="<?= $pr ?>" <?= ($editar['prioridade'] ?? 'Media') === $pr ? 'selected' : '' ?>><?= $pr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Data início</label>
                    <input type="date" name="data_inicio" value="<?= e($editar['data_inicio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data fim</label>
                    <input type="date" name="data_fim" value="<?= e($editar['data_fim'] ?? '') ?>">
                </div>
            </div>

            <button type="submit" class="btn <?= $editar ? '' : 'btn-success' ?>" style="justify-content:center;">
                <i class="fa-solid fa-<?= $editar ? 'floppy-disk' : 'plus' ?>"></i>
                <?= $editar ? 'Guardar alterações' : 'Adicionar tarefa' ?>
            </button>
        </form>
    </div>

    <!-- LISTA -->
    <div>
        <?php if (!$tarefas): ?>
            <div class="card empty">
                <i class="fa-solid fa-list-check"></i>
                <h3>Ainda não há tarefas</h3>
                <p>Cria a primeira tarefa no formulário ao lado.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($tarefas as $t): ?>
                <?php
                $clsEstado = $t['estado']==='Concluida' ? 'success' : ($t['estado']==='Em curso' ? 'info' : 'muted');
                $clsPrio   = $t['prioridade']==='Alta' ? 'danger' : ($t['prioridade']==='Media' ? 'warning' : 'info');
                $atrasada  = $t['data_fim'] && $t['estado'] !== 'Concluida' && strtotime($t['data_fim']) < strtotime('today');
                ?>
                <div class="card" style="padding:18px;<?= $atrasada ? 'border-color:var(--danger);' : '' ?>">
                    <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                                <h4 style="font-size:16px;font-weight:700;"><?= e($t['titulo']) ?></h4>
                                <span class="badge badge-<?= $clsPrio ?>"><?= e($t['prioridade']) ?></span>
                                <?php if ($atrasada): ?>
                                    <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Atrasada</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($t['descricao']): ?>
                                <p style="color:var(--text-soft);font-size:14px;margin-bottom:10px;"><?= nl2br(e($t['descricao'])) ?></p>
                            <?php endif; ?>

                            <div style="display:flex;gap:14px;font-size:13px;color:var(--text-muted);flex-wrap:wrap;">
                                <?php if ($t['data_inicio']): ?>
                                    <span><i class="fa-regular fa-calendar"></i> Início: <?= date('d/m/Y', strtotime($t['data_inicio'])) ?></span>
                                <?php endif; ?>
                                <?php if ($t['data_fim']): ?>
                                    <span><i class="fa-regular fa-calendar-check"></i> Fim: <?= date('d/m/Y', strtotime($t['data_fim'])) ?></span>
                                <?php endif; ?>
                                <?php if ($t['data_conclusao']): ?>
                                    <span style="color:var(--success);"><i class="fa-solid fa-circle-check"></i> Concluída a <?= date('d/m/Y', strtotime($t['data_conclusao'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- AÇÕES -->
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:start;">
                            <!-- Dropdown rápido de estado -->
                            <div style="position:relative;">
                                <select onchange="if(this.value) window.location='?estado='+this.value+'&id=<?= $t['id_tarefa'] ?>';"
                                        style="padding:6px 10px;font-size:12px;font-weight:600;border-radius:8px;cursor:pointer;background:var(--bg-alt);border:1px solid var(--border);color:var(--text);">
                                    <option value="">Estado ▾</option>
                                    <option value="Pendente">Pendente</option>
                                    <option value="Em curso">Em curso</option>
                                    <option value="Concluida">Concluída</option>
                                </select>
                            </div>

                            <span class="badge badge-<?= $clsEstado ?>" style="align-self:center;"><?= e($t['estado']) ?></span>

                            <a href="?editar=<?= $t['id_tarefa'] ?>" class="btn btn-ghost btn-sm" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="?apagar=<?= $t['id_tarefa'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Eliminar esta tarefa?')" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>