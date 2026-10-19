<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$id = $_SESSION['id_utilizador'];

/* ============================================================
   ALUNO
   ============================================================ */
if (isAluno()) {
    $stmt = $pdo->prepare("SELECT * FROM acomp_projetos WHERE id_aluno = ?");
    $stmt->execute([$id]);
    $projeto = $stmt->fetch();

    $numTarefas = $numRegistos = $numRecursos = $numFeedbacks = 0;
    $tarefasConcluidas = 0;
    $horasTotais = 0;
    $horasPorSemana = [];
    $tarefasRecentes = [];
    $registosRecentes = [];

    if ($projeto) {
        $pid = $projeto['id_projeto'];
        $numTarefas   = (int)$pdo->query("SELECT COUNT(*) FROM acomp_tarefas WHERE id_projeto = $pid")->fetchColumn();
        $tarefasConcluidas = (int)$pdo->query("SELECT COUNT(*) FROM acomp_tarefas WHERE id_projeto = $pid AND estado='Concluida'")->fetchColumn();
        $numRegistos  = (int)$pdo->query("SELECT COUNT(*) FROM acomp_registos_diarios WHERE id_projeto = $pid")->fetchColumn();
        $numRecursos  = (int)$pdo->query("SELECT COUNT(*) FROM acomp_recursos WHERE id_projeto = $pid")->fetchColumn();
        $numFeedbacks = (int)$pdo->query("SELECT COUNT(*) FROM acomp_feedbacks WHERE id_projeto = $pid")->fetchColumn();
        $horasTotais  = (float)$pdo->query("SELECT COALESCE(SUM(horas_trabalho),0) FROM acomp_registos_diarios WHERE id_projeto = $pid")->fetchColumn();

        $stmt = $pdo->prepare("SELECT titulo, estado, prioridade, data_fim FROM acomp_tarefas WHERE id_projeto=? ORDER BY id_tarefa DESC LIMIT 5");
        $stmt->execute([$pid]);
        $tarefasRecentes = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT data, descricao, horas_trabalho FROM acomp_registos_diarios WHERE id_projeto=? ORDER BY data DESC LIMIT 5");
        $stmt->execute([$pid]);
        $registosRecentes = $stmt->fetchAll();

        for ($i = 6; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i days"));
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(horas_trabalho),0) FROM acomp_registos_diarios WHERE id_projeto=? AND data=?");
            $stmt->execute([$pid, $dia]);
            $horasPorSemana[date('d/m', strtotime($dia))] = (float)$stmt->fetchColumn();
        }
    }

    $progresso = $numTarefas > 0 ? round(($tarefasConcluidas / $numTarefas) * 100) : 0;
}

/* ============================================================
   PROFESSOR
   ============================================================ */
else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome AS aluno, u.email AS aluno_email
        FROM acomp_projetos p
        JOIN acomp_utilizadores u ON u.id_utilizador = p.id_aluno
        WHERE p.id_professor = ?
        ORDER BY p.id_projeto DESC
    ");
    $stmt->execute([$id]);
    $meusProjetos = $stmt->fetchAll();

    $numProjetos = count($meusProjetos);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_feedbacks WHERE id_professor = ?");
    $stmt->execute([$id]);
    $numFeedbacksDados = (int)$stmt->fetchColumn();

    // Estados
    $porEstado = ['Planeamento' => 0, 'Em curso' => 0, 'Concluido' => 0, 'Avaliado' => 0];
    foreach ($meusProjetos as $p) {
        if (isset($porEstado[$p['estado']])) $porEstado[$p['estado']]++;
    }
    $totalProjetos = array_sum($porEstado);

    // Progresso por aluno
    $progressoPorAluno = [];
    $tarefasPorProjeto = [];
    $tarefasConcluidasPorProjeto = [];
    $labelsProjetos = [];
    foreach ($meusProjetos as $p) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_tarefas WHERE id_projeto = ?");
        $stmt->execute([$p['id_projeto']]);
        $total = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM acomp_tarefas WHERE id_projeto = ? AND estado='Concluida'");
        $stmt->execute([$p['id_projeto']]);
        $feitas = (int)$stmt->fetchColumn();

        $progressoPorAluno[$p['id_projeto']] = $total > 0 ? round(($feitas / $total) * 100) : 0;
        $labelsProjetos[] = mb_strimwidth($p['aluno'], 0, 14, '…');
        $tarefasPorProjeto[] = $total;
        $tarefasConcluidasPorProjeto[] = $feitas;
    }

    // Feedbacks recentes
    $stmt = $pdo->prepare("
        SELECT f.*, u.nome AS aluno
        FROM acomp_feedbacks f
        JOIN acomp_projetos p ON p.id_projeto = f.id_projeto
        JOIN acomp_utilizadores u ON u.id_utilizador = p.id_aluno
        WHERE f.id_professor = ?
        ORDER BY f.data DESC LIMIT 5
    ");
    $stmt->execute([$id]);
    $feedbacksRecentes = $stmt->fetchAll();

    // Total de horas
    $totalHoras = 0;
    foreach ($meusProjetos as $p) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(horas_trabalho),0) FROM acomp_registos_diarios WHERE id_projeto = ?");
        $stmt->execute([$p['id_projeto']]);
        $totalHoras += (float)$stmt->fetchColumn();
    }
}

require 'header.php';
?>

<?php if (isAluno()): ?>

    <!-- =====================================================
         DASHBOARD DO ALUNO
         ===================================================== -->
    <div style="margin-bottom:24px;">
        <h2 style="font-size:24px; font-weight:800; letter-spacing:-.5px;">Olá, <?= e(explode(' ', $_SESSION['nome'])[0]) ?> 👋</h2>
        <p style="color:var(--text-muted);margin-top:4px;">Aqui está o resumo do teu projeto.</p>
    </div>

    <?php if (!$projeto): ?>
        <div class="card empty">
            <i class="fa-solid fa-diagram-project"></i>
            <h3>Ainda não tens projeto</h3>
            <p>Cria o teu projeto de PAP para começares a acompanhar o desenvolvimento.</p>
            <a href="projeto.php" class="btn"><i class="fa-solid fa-plus"></i> Criar projeto</a>
        </div>
    <?php else: ?>

        <div class="stats-grid">
            <div class="stat">
                <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
                <div class="stat-num"><?= $numTarefas ?></div>
                <div class="stat-label">Tarefas totais</div>
            </div>
            <div class="stat success">
                <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-num"><?= $tarefasConcluidas ?></div>
                <div class="stat-label">Tarefas concluídas</div>
            </div>
            <div class="stat warning">
                <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-num"><?= number_format($horasTotais, 1) ?>h</div>
                <div class="stat-label">Horas registadas</div>
            </div>
            <div class="stat info">
                <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
                <div class="stat-num"><?= $numFeedbacks ?></div>
                <div class="stat-label">Feedbacks recebidos</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-diagram-project"></i> O meu projeto</div>
                    <a href="projeto.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-pen"></i> Editar</a>
                </div>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:6px;"><?= e($projeto['tema']) ?></h3>
                <?php if ($projeto['titulo']): ?>
                    <p style="color:var(--text-soft);margin-bottom:14px;"><?= e($projeto['titulo']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
                    <span class="badge badge-primary"><?= e($projeto['estado']) ?></span>
                    <?php if ($projeto['data_inicio']): ?>
                        <span class="badge badge-muted"><i class="fa-regular fa-calendar"></i> <?= date('d/m/Y', strtotime($projeto['data_inicio'])) ?></span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:20px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="font-size:13px;font-weight:600;color:var(--text-soft);">Progresso</span>
                        <span style="font-size:13px;font-weight:700;"><?= $progresso ?>%</span>
                    </div>
                    <div class="progress"><div class="progress-bar" style="width:<?= $progresso ?>%"></div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-line"></i> Horas por dia</div>
                </div>
                <canvas id="horasChart" height="180"></canvas>
            </div>
        </div>

        <div class="grid-half" style="margin-top:20px;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list-check"></i> Tarefas recentes</div>
                    <a href="tarefas.php" class="btn btn-ghost btn-sm">Ver todas</a>
                </div>
                <?php if (!$tarefasRecentes): ?>
                    <p style="color:var(--text-muted);padding:20px 0;text-align:center;">Sem tarefas.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($tarefasRecentes as $t): ?>
                            <div class="timeline-item">
                                <div class="tm-title"><?= e($t['titulo']) ?></div>
                                <div class="tm-date">
                                    <span class="badge badge-<?= $t['estado']==='Concluida'?'success':($t['estado']==='Em curso'?'info':'muted') ?>"><?= e($t['estado']) ?></span>
                                    <?php if ($t['data_fim']): ?> · <?= date('d/m', strtotime($t['data_fim'])) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-book"></i> Últimos registos</div>
                    <a href="registos.php" class="btn btn-ghost btn-sm">Ver todos</a>
                </div>
                <?php if (!$registosRecentes): ?>
                    <p style="color:var(--text-muted);padding:20px 0;text-align:center;">Sem registos.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($registosRecentes as $r): ?>
                            <div class="timeline-item">
                                <div class="tm-title"><?= e(mb_strimwidth($r['descricao'], 0, 60, '…')) ?></div>
                                <div class="tm-date"><?= date('d/m/Y', strtotime($r['data'])) ?> · <?= e($r['horas_trabalho']) ?>h</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        new Chart(document.getElementById('horasChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($horasPorSemana)) ?>,
                datasets: [{
                    label: 'Horas',
                    data: <?= json_encode(array_values($horasPorSemana)) ?>,
                    backgroundColor: 'rgba(99,102,241,.7)',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(128,128,128,.1)' }, ticks: { color: '#8b95a8' } },
                    x: { grid: { display: false }, ticks: { color: '#8b95a8' } }
                }
            }
        });
        </script>

    <?php endif; ?>

<?php else: ?>

    <!-- =====================================================
         DASHBOARD DO PROFESSOR (reformulado)
         ===================================================== -->
    <div style="margin-bottom:24px;">
        <h2 style="font-size:24px; font-weight:800; letter-spacing:-.5px;">Olá, <?= e(explode(' ', $_SESSION['nome'])[0]) ?> 👋</h2>
        <p style="color:var(--text-muted);margin-top:4px;">Visão geral dos teus projetos orientados.</p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat">
            <div class="stat-icon"><i class="fa-solid fa-folder-open"></i></div>
            <div class="stat-num"><?= $numProjetos ?></div>
            <div class="stat-label">Projetos orientados</div>
        </div>
        <div class="stat info">
            <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
            <div class="stat-num"><?= $numFeedbacksDados ?></div>
            <div class="stat-label">Feedbacks dados</div>
        </div>
        <div class="stat success">
            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-num"><?= $porEstado['Concluido'] + $porEstado['Avaliado'] ?></div>
            <div class="stat-label">Concluídos / Avaliados</div>
        </div>
        <div class="stat warning">
            <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-num"><?= number_format($totalHoras, 1) ?>h</div>
            <div class="stat-label">Horas totais</div>
        </div>
    </div>

    <?php if ($numProjetos === 0): ?>
        <div class="card empty">
            <i class="fa-solid fa-folder-open"></i>
            <h3>Sem projetos atribuídos</h3>
            <p>Assim que um aluno te escolher como orientador, aparece aqui.</p>
        </div>
    <?php else: ?>

        <!-- GRÁFICOS (lado a lado, compactos) -->
        <div class="grid-half" style="margin-bottom:20px;">

            <!-- DONUT -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-pie"></i> Estados dos projetos</div>
                </div>
                <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;">

                    <!-- Donut -->
                    <div style="position:relative;width:180px;height:180px;flex-shrink:0;">
                        <canvas id="estadosChart"></canvas>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                            <div style="font-size:26px;font-weight:800;letter-spacing:-1px;line-height:1;"><?= $totalProjetos ?></div>
                            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Total</div>
                        </div>
                    </div>

                    <!-- Legenda com percentagens -->
                    <div style="flex:1;min-width:160px;display:flex;flex-direction:column;gap:10px;">
                        <?php
                        $cores = [
                            'Planeamento' => '#8b95a8',
                            'Em curso'    => '#06b6d4',
                            'Concluido'   => '#10b981',
                            'Avaliado'    => '#6366f1',
                        ];
                        $labels = ['Planeamento','Em curso','Concluído','Avaliado'];
                        $i = 0;
                        foreach ($porEstado as $est => $qtd):
                            $pct = $totalProjetos > 0 ? round(($qtd / $totalProjetos) * 100) : 0;
                        ?>
                            <div style="display:flex;align-items:center;gap:10px;font-size:13px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:<?= $cores[$est] ?>;flex-shrink:0;"></span>
                                <span style="flex:1;color:var(--text-soft);"><?= $labels[$i] ?></span>
                                <span style="font-weight:700;"><?= $qtd ?></span>
                                <span style="color:var(--text-muted);font-size:12px;min-width:38px;text-align:right;"><?= $pct ?>%</span>
                            </div>
                        <?php $i++; endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- BARRAS -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-column"></i> Tarefas por projeto</div>
                </div>
                <div style="height:220px;">
                    <canvas id="tarefasChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TABELA DE ALUNOS -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-users"></i> Meus alunos</div>
                <a href="projetos_orientados.php" class="btn btn-ghost btn-sm">Ver todos</a>
            </div>
            <div class="table-wrap" style="border:none;box-shadow:none;background:transparent;">
                <table>
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Tema</th>
                            <th>Estado</th>
                            <th>Progresso</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($meusProjetos as $p): ?>
                        <?php $prog = $progressoPorAluno[$p['id_projeto']] ?? 0; ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--info));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;">
                                        <?= strtoupper(substr($p['aluno'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;"><?= e($p['aluno']) ?></div>
                                        <div style="font-size:12px;color:var(--text-muted);"><?= e($p['aluno_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e(mb_strimwidth($p['tema'], 0, 35, '…')) ?></td>
                            <td>
                                <?php
                                $cls = 'muted';
                                if ($p['estado'] === 'Em curso') $cls = 'info';
                                elseif ($p['estado'] === 'Concluido') $cls = 'success';
                                elseif ($p['estado'] === 'Avaliado') $cls = 'primary';
                                ?>
                                <span class="badge badge-<?= $cls ?>"><?= e($p['estado']) ?></span>
                            </td>
                            <td style="min-width:140px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $prog ?>%"></div></div>
                                    <span style="font-size:12px;font-weight:700;min-width:32px;text-align:right;"><?= $prog ?>%</span>
                                </div>
                            </td>
                            <td>
                                <a href="feedbacks.php?projeto=<?= $p['id_projeto'] ?>" class="btn btn-ghost btn-sm" title="Feedback">
                                    <i class="fa-solid fa-comment"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FEEDBACKS RECENTES -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-comments"></i> Feedbacks recentes</div>
                <a href="feedbacks.php" class="btn btn-ghost btn-sm">Ver todos</a>
            </div>
            <?php if (empty($feedbacksRecentes)): ?>
                <p style="color:var(--text-muted);padding:20px 0;text-align:center;">Ainda não deste feedback.</p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($feedbacksRecentes as $f): ?>
                        <div class="timeline-item">
                            <div class="tm-title">
                                <strong><?= e($f['aluno']) ?></strong>
                                <span class="badge badge-primary" style="margin-left:8px;"><?= e($f['tipo']) ?></span>
                            </div>
                            <div class="tm-date" style="margin-bottom:6px;"><?= date('d/m/Y H:i', strtotime($f['data'])) ?></div>
                            <div style="color:var(--text-soft);font-size:14px;"><?= e(mb_strimwidth($f['texto'], 0, 100, '…')) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- =====================================================
             SCRIPTS DOS GRÁFICOS (reformulados)
             ===================================================== -->
        <script>
        // ================= DONUT =================
        const ctxDonut = document.getElementById('estadosChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Planeamento', 'Em curso', 'Concluído', 'Avaliado'],
                datasets: [{
                    data: [
                        <?= $porEstado['Planeamento'] ?>,
                        <?= $porEstado['Em curso'] ?>,
                        <?= $porEstado['Concluido'] ?>,
                        <?= $porEstado['Avaliado'] ?>
                    ],
                    backgroundColor: ['#8b95a8', '#06b6d4', '#10b981', '#6366f1'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,.95)',
                        padding: 10,
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 13 },
                        displayColors: true,
                        callbacks: {
                            label: function(ctx) {
                                const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                                const pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                                return '  ' + ctx.parsed + ' projeto(s) (' + pct + '%)';
                            }
                        }
                    }
                },
                animation: { animateRotate: true, animateScale: false }
            }
        });

        // ================= BARRAS =================
        const ctxBar = document.getElementById('tarefasChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelsProjetos) ?>,
                datasets: [
                    {
                        label: 'Total',
                        data: <?= json_encode($tarefasPorProjeto) ?>,
                        backgroundColor: 'rgba(99,102,241,.35)',
                        borderColor: 'rgba(99,102,241,.9)',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 42
                    },
                    {
                        label: 'Concluídas',
                        data: <?= json_encode($tarefasConcluidasPorProjeto) ?>,
                        backgroundColor: 'rgba(16,185,129,.75)',
                        borderColor: 'rgba(16,185,129,1)',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 42
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'center',
                        labels: {
                            color: '#8b95a8',
                            padding: 14,
                            font: { size: 12, weight: '500' },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,.95)',
                        padding: 10,
                        callbacks: {
                            label: function(ctx) {
                                return '  ' + ctx.dataset.label + ': ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(128,128,128,.08)', drawBorder: false },
                        ticks: {
                            color: '#8b95a8',
                            stepSize: 1,
                            precision: 0,
                            font: { size: 11 }
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#8b95a8', font: { size: 11 } },
                        border: { display: false }
                    }
                }
            }
        });
        </script>

    <?php endif; ?>

<?php endif; ?>

<?php require 'footer.php'; ?>