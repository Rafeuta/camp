<?php
require_once 'config.php';
require_once 'functions.php';
requireProfessor();

$id = $_SESSION['id_utilizador'];
$pid = (int)($_GET['id'] ?? 0);

if (!$pid) {
    setFlash("Projeto inválido.", 'error');
    header("Location: projetos_orientados.php");
    exit;
}

// Buscar projeto — com verificação de que é DESTE professor
$stmt = $pdo->prepare("
    SELECT p.*, u.nome AS aluno, u.email AS aluno_email
    FROM acomp_projetos p
    JOIN acomp_utilizadores u ON u.id_utilizador = p.id_aluno
    WHERE p.id_projeto = ? AND p.id_professor = ?
");
$stmt->execute([$pid, $id]);
$projeto = $stmt->fetch();

if (!$projeto) {
    setFlash("Não tens permissão para ver esse projeto.", 'error');
    header("Location: projetos_orientados.php");
    exit;
}

$pageTitle = $projeto['tema'];

// Tarefas
$stmt = $pdo->prepare("SELECT * FROM acomp_tarefas WHERE id_projeto=? ORDER BY data_fim IS NULL, data_fim ASC");
$stmt->execute([$pid]);
$tarefas = $stmt->fetchAll();

// Registos
$stmt = $pdo->prepare("SELECT * FROM acomp_registos_diarios WHERE id_projeto=? ORDER BY data DESC");
$stmt->execute([$pid]);
$registos = $stmt->fetchAll();

// Recursos
$stmt = $pdo->prepare("SELECT * FROM acomp_recursos WHERE id_projeto=? ORDER BY data_adicao DESC");
$stmt->execute([$pid]);
$recursos = $stmt->fetchAll();

// Horas totais
$stmt = $pdo->prepare("SELECT COALESCE(SUM(horas_trabalho),0) FROM acomp_registos_diarios WHERE id_projeto=?");
$stmt->execute([$pid]);
$totalHoras = (float)$stmt->fetchColumn();

// Progresso
$totalTarefas = count($tarefas);
$tarefasConcluidas = 0;
foreach ($tarefas as $t) if ($t['estado'] === 'Concluida') $tarefasConcluidas++;
$progresso = $totalTarefas > 0 ? round(($tarefasConcluidas / $totalTarefas) * 100) : 0;

require 'header.php';
?>

<!-- BOTÃO VOLTAR -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="projetos_orientados.php" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Voltar
    </a>
    <div style="flex:1;"></div>
    <a href="feedbacks.php?projeto=<?= $pid ?>" class="btn btn-sm">
        <i class="fa-solid fa-comment"></i> Dar feedback
    </a>
    <a href="calendario.php?aluno=<?= $projeto['id_aluno'] ?>" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-calendar-days"></i> Ver calendário
    </a>
</div>

<!-- ==========================================
     CABEÇALHO DO PROJETO
     ========================================== -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex;gap:20px;align-items:start;flex-wrap:wrap;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--info));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:24px;flex-shrink:0;">
            <?= strtoupper(substr($projeto['aluno'], 0, 1)) ?>
        </div>

        <div style="flex:1;min-width:240px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                <h2 style="font-size:22px;font-weight:800;letter-spacing:-.4px;"><?= e($projeto['tema']) ?></h2>
                <span class="badge badge-primary"><?= e($projeto['estado']) ?></span>
            </div>
            <?php if ($projeto['titulo']): ?>
                <p style="color:var(--text-soft);margin-bottom:8px;font-size:15px;"><?= e($projeto['titulo']) ?></p>
            <?php endif; ?>
            <p style="color:var(--text-muted);font-size:13px;">
                <i class="fa-solid fa-user"></i> <?= e($projeto['aluno']) ?>
                &nbsp;·&nbsp;
                <i class="fa-solid fa-envelope"></i> <?= e($projeto['aluno_email']) ?>
            </p>
        </div>

        <div style="min-width:200px;">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Progresso geral</div>
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $progresso ?>%"></div></div>
                <span style="font-weight:800;"><?= $progresso ?>%</span>
            </div>
        </div>
    </div>

    <?php if ($projeto['descricao']): ?>
        <hr style="margin:18px 0;border:none;border-top:1px solid var(--border);">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">Descrição</div>
        <p style="color:var(--text-soft);line-height:1.65;white-space:pre-wrap;"><?= e($projeto['descricao']) ?></p>
    <?php endif; ?>
</div>

<!-- ==========================================
     ESTATÍSTICAS
     ========================================== -->
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat">
        <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
        <div class="stat-num"><?= $tarefasConcluidas ?>/<?= $totalTarefas ?></div>
        <div class="stat-label">Tarefas concluídas</div>
    </div>
    <div class="stat info">
        <div class="stat-icon"><i class="fa-solid fa-book"></i></div>
        <div class="stat-num"><?= count($registos) ?></div>
        <div class="stat-label">Registos diários</div>
    </div>
    <div class="stat success">
        <div class="stat-icon"><i class="fa-solid fa-link"></i></div>
        <div class="stat-num"><?= count($recursos) ?></div>
        <div class="stat-label">Recursos guardados</div>
    </div>
    <div class="stat warning">
        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-num"><?= number_format($totalHoras, 1) ?>h</div>
        <div class="stat-label">Horas registadas</div>
    </div>
</div>

<!-- ==========================================
     TAREFAS
     ========================================== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-list-check"></i> Tarefas (<?= $totalTarefas ?>)</div>
    </div>

    <?php if (!$tarefas): ?>
        <p style="color:var(--text-muted);padding:20px 0;text-align:center;">Ainda não há tarefas.</p>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($tarefas as $t):
            $atrasada = $t['data_fim'] && $t['estado'] !== 'Concluida' && strtotime($t['data_fim']) < strtotime('today');
            $clsEstado = $t['estado']==='Concluida' ? 'success' : ($t['estado']==='Em curso' ? 'info' : 'muted');
            $clsPrio = $t['prioridade']==='Alta' ? 'danger' : ($t['prioridade']==='Media' ? 'warning' : 'info');
        ?>
            <div style="padding:14px 16px;background:var(--bg-alt);border-radius:12px;<?= $atrasada ? 'border-left:3px solid var(--danger);' : '' ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:start;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                            <strong style="font-size:15px;"><?= e($t['titulo']) ?></strong>
                            <span class="badge badge-<?= $clsEstado ?>"><?= e($t['estado']) ?></span>
                            <span class="badge badge-<?= $clsPrio ?>"><?= e($t['prioridade']) ?></span>
                            <?php if ($atrasada): ?>
                                <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Atrasada</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($t['descricao']): ?>
                            <p style="color:var(--text-soft);font-size:13px;margin-bottom:6px;"><?= nl2br(e($t['descricao'])) ?></p>
                        <?php endif; ?>
                        <div style="display:flex;gap:14px;font-size:12px;color:var(--text-muted);flex-wrap:wrap;">
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
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     REGISTOS DIÁRIOS
     ========================================== -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-book"></i> Registos diários (<?= count($registos) ?>)</div>
    </div>

    <?php if (!$registos): ?>
        <p style="color:var(--text-muted);padding:20px 0;text-align:center;">Ainda não há registos.</p>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($registos as $r): ?>
                <div class="timeline-item">
                    <div class="tm-date" style="margin-bottom:6px;">
                        <strong style="color:var(--text);"><?= date('d/m/Y', strtotime($r['data'])) ?></strong>
                        &nbsp;·&nbsp;
                        <span class="badge badge-info" style="font-size:11px;"><?= e($r['horas_trabalho']) ?>h</span>
                    </div>
                    <div style="color:var(--text-soft);font-size:14px;line-height:1.55;white-space:pre-wrap;"><?= e($r['descricao']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     RECURSOS (com links clicáveis)
     ========================================== -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-link"></i> Recursos (<?= count($recursos) ?>)</div>
    </div>

    <?php if (!$recursos): ?>
        <p style="color:var(--text-muted);padding:20px 0;text-align:center;">Ainda não há recursos.</p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">

            <?php foreach ($recursos as $r):
                $icone = 'fa-link';
                if ($r['tipo'] === 'Video') $icone = 'fa-video';
                elseif ($r['tipo'] === 'Documentacao') $icone = 'fa-file-lines';
                elseif ($r['tipo'] === 'Tutorial') $icone = 'fa-graduation-cap';
                elseif ($r['tipo'] === 'Site') $icone = 'fa-globe';
            ?>
                <div style="padding:16px;background:var(--bg-alt);border-radius:12px;display:flex;flex-direction:column;gap:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">
                            <i class="fa-solid <?= $icone ?>"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= e($r['titulo']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);"><?= e($r['tipo']) ?></div>
                        </div>
                    </div>

                    <?php if ($r['descricao']): ?>
                        <p style="font-size:13px;color:var(--text-soft);"><?= e(mb_strimwidth($r['descricao'], 0, 100, '…')) ?></p>
                    <?php endif; ?>

                    <div style="display:flex;gap:8px;align-items:center;margin-top:auto;">
                        <?php if ($r['url']): ?>
                            <a href="<?= e($r['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm" style="flex:1;justify-content:center;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir
                            </a>
                        <?php else: ?>
                            <span style="font-size:12px;color:var(--text-muted);">Sem link</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size:11px;color:var(--text-muted);">
                        Adicionado a <?= date('d/m/Y', strtotime($r['data_adicao'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>