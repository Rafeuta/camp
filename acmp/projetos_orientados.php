<?php
require_once 'config.php';
require_once 'functions.php';
requireProfessor();

$pageTitle = 'Projetos orientados';
$id = $_SESSION['id_utilizador'];

// Mudar estado do projeto (continua)
if (isset($_GET['estado']) && isset($_GET['id'])) {
    $novo = $_GET['estado'];
    if (in_array($novo, ['Planeamento','Em curso','Concluido','Avaliado'])) {
        $stmt = $pdo->prepare("UPDATE acomp_projetos SET estado=? WHERE id_projeto=? AND id_professor=?");
        $stmt->execute([$novo, $_GET['id'], $id]);
        setFlash("Estado do projeto atualizado.");
    }
    header("Location: projetos_orientados.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.nome AS aluno, u.email AS aluno_email
    FROM acomp_projetos p
    JOIN acomp_utilizadores u ON u.id_utilizador = p.id_aluno
    WHERE p.id_professor = ?
    ORDER BY p.data_inicio DESC
");
$stmt->execute([$id]);
$projetos = $stmt->fetchAll();

// Progresso + contagens por projeto
$info = [];
foreach ($projetos as $p) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_tarefas WHERE id_projeto=?");
    $stmt->execute([$p['id_projeto']]);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_tarefas WHERE id_projeto=? AND estado='Concluida'");
    $stmt->execute([$p['id_projeto']]);
    $feitas = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_registos_diarios WHERE id_projeto=?");
    $stmt->execute([$p['id_projeto']]);
    $registos = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_recursos WHERE id_projeto=?");
    $stmt->execute([$p['id_projeto']]);
    $recursos = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(horas_trabalho),0) FROM acomp_registos_diarios WHERE id_projeto=?");
    $stmt->execute([$p['id_projeto']]);
    $horas = (float)$stmt->fetchColumn();

    $info[$p['id_projeto']] = [
        'progresso' => $total > 0 ? round(($feitas / $total) * 100) : 0,
        'tarefas'   => $total,
        'registos'  => $registos,
        'recursos'  => $recursos,
        'horas'     => $horas,
    ];
}

require 'header.php';
?>

<h2 style="font-size:20px;font-weight:800;letter-spacing:-.3px;margin-bottom:20px;">
    <i class="fa-solid fa-folder-open" style="color:var(--primary);"></i> Projetos que oriento
</h2>

<?php if (!$projetos): ?>
    <div class="card empty">
        <i class="fa-solid fa-folder-open"></i>
        <h3>Sem projetos atribuídos</h3>
        <p>Quando um aluno te escolher como orientador, aparece aqui.</p>
    </div>
<?php else: ?>

    <div style="display:flex;flex-direction:column;gap:16px;">
    <?php foreach ($projetos as $p): $i = $info[$p['id_projeto']]; ?>
        <div class="card" style="padding:0;overflow:hidden;">

            <!-- CABEÇALHO DO CARD -->
            <div style="padding:20px;display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;">
                <div style="flex:1;min-width:260px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--info));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0;">
                            <?= strtoupper(substr($p['aluno'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:16px;"><?= e($p['aluno']) ?></div>
                            <div style="font-size:12px;color:var(--text-muted);"><?= e($p['aluno_email']) ?></div>
                        </div>
                    </div>

                    <h3 style="font-size:17px;font-weight:700;margin-bottom:4px;"><?= e($p['tema']) ?></h3>
                    <?php if ($p['titulo']): ?>
                        <p style="color:var(--text-soft);font-size:14px;margin-bottom:10px;"><?= e($p['titulo']) ?></p>
                    <?php endif; ?>

                    <?php if ($p['descricao']): ?>
                        <p style="color:var(--text-muted);font-size:13px;margin-bottom:12px;">
                            <?= e(mb_strimwidth($p['descricao'], 0, 160, '…')) ?>
                        </p>
                    <?php endif; ?>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:12px;">
                        <span class="badge badge-muted">
                            <i class="fa-regular fa-calendar"></i> Início: <?= $p['data_inicio'] ? date('d/m/Y', strtotime($p['data_inicio'])) : '—' ?>
                        </span>
                        <span class="badge badge-muted">
                            <i class="fa-regular fa-calendar-check"></i> Fim: <?= $p['data_fim_prevista'] ? date('d/m/Y', strtotime($p['data_fim_prevista'])) : '—' ?>
                        </span>
                    </div>
                </div>

                <!-- LADO DIREITO: ESTADO + BOTÕES -->
                <div style="min-width:180px;display:flex;flex-direction:column;gap:10px;align-items:flex-end;">
                    <select onchange="if(this.value) window.location='?estado='+this.value+'&id=<?= $p['id_projeto'] ?>';"
                            style="padding:8px 12px;font-size:13px;font-weight:600;border-radius:10px;cursor:pointer;background:var(--bg-alt);border:1px solid var(--border);color:var(--text);">
                        <?php foreach (['Planeamento','Em curso','Concluido','Avaliado'] as $est): ?>
                            <option value="<?= $est ?>" <?= $p['estado']===$est?'selected':'' ?>><?= $est ?></option>
                        <?php endforeach; ?>
                    </select>

                    <a href="projeto_ver.php?id=<?= $p['id_projeto'] ?>" class="btn btn-sm">
                        <i class="fa-solid fa-eye"></i> Ver projeto
                    </a>

                    <a href="feedbacks.php?projeto=<?= $p['id_projeto'] ?>" class="btn btn-ghost btn-sm">
                        <i class="fa-solid fa-comment"></i> Feedback
                    </a>

                    <a href="calendario.php?aluno=<?= $p['id_aluno'] ?>" class="btn btn-ghost btn-sm">
                        <i class="fa-solid fa-calendar-days"></i> Calendário
                    </a>
                </div>
            </div>

            <!-- BARRA INFERIOR: ESTATÍSTICAS -->
            <div style="background:var(--bg-alt);padding:14px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:14px;border-top:1px solid var(--border);">
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px;">Progresso</div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $i['progresso'] ?>%"></div></div>
                        <span style="font-weight:700;font-size:13px;"><?= $i['progresso'] ?>%</span>
                    </div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;">Tarefas</div>
                    <div style="font-weight:700;font-size:15px;"><i class="fa-solid fa-list-check" style="color:var(--primary);"></i> <?= $i['tarefas'] ?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;">Registos</div>
                    <div style="font-weight:700;font-size:15px;"><i class="fa-solid fa-book" style="color:var(--info);"></i> <?= $i['registos'] ?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;">Recursos</div>
                    <div style="font-weight:700;font-size:15px;"><i class="fa-solid fa-link" style="color:var(--success);"></i> <?= $i['recursos'] ?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;">Horas</div>
                    <div style="font-weight:700;font-size:15px;"><i class="fa-solid fa-clock" style="color:var(--warning);"></i> <?= number_format($i['horas'],1) ?>h</div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require 'footer.php'; ?>