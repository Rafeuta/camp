<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$pageTitle = 'Calendário';
$id = $_SESSION['id_utilizador'];

/* ============================================================
   ALUNO — vê apenas o seu calendário
   ============================================================ */
if (isAluno()) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.tema
        FROM acomp_tarefas t
        JOIN acomp_projetos p ON p.id_projeto = t.id_projeto
        WHERE p.id_aluno = ?
    ");
    $stmt->execute([$id]);
    $tarefas = $stmt->fetchAll();

    $alunoSelecionado = null;
}

/* ============================================================
   PROFESSOR — escolhe de qual aluno quer ver o calendário
   ============================================================ */
else {
    // Lista de alunos orientados por este professor
    $stmt = $pdo->prepare("
        SELECT p.id_projeto, p.tema, u.id_utilizador, u.nome, u.email
        FROM acomp_projetos p
        JOIN acomp_utilizadores u ON u.id_utilizador = p.id_aluno
        WHERE p.id_professor = ?
        ORDER BY u.nome ASC
    ");
    $stmt->execute([$id]);
    $meusAlunos = $stmt->fetchAll();

    // Qual aluno está selecionado?
    $alunoSelecionado = isset($_GET['aluno']) ? (int)$_GET['aluno'] : null;

    // Se não escolheu nenhum, fica vazio (não mostra tarefas)
    $tarefas = [];
    if ($alunoSelecionado) {
        // Confirmar que este aluno É orientado por este professor
        $valido = false;
        foreach ($meusAlunos as $a) {
            if ($a['id_utilizador'] == $alunoSelecionado) { $valido = true; break; }
        }

        if ($valido) {
            $stmt = $pdo->prepare("
                SELECT t.*, p.tema
                FROM acomp_tarefas t
                JOIN acomp_projetos p ON p.id_projeto = t.id_projeto
                WHERE p.id_aluno = ?
            ");
            $stmt->execute([$alunoSelecionado]);
            $tarefas = $stmt->fetchAll();
        } else {
            $alunoSelecionado = null;
        }
    }
}

/* ============================================================
   Converter tarefas para eventos do FullCalendar
   ============================================================ */
$eventos = [];
foreach ($tarefas as $t) {
    if (!$t['data_fim']) continue;

    $cor = '#8b95a8';
    if ($t['estado'] === 'Em curso') $cor = '#06b6d4';
    if ($t['estado'] === 'Concluida') $cor = '#10b981';
    if ($t['prioridade'] === 'Alta'  && $t['estado'] !== 'Concluida') $cor = '#ef4444';
    if ($t['prioridade'] === 'Media' && $t['estado'] !== 'Concluida') $cor = '#f59e0b';

    $eventos[] = [
        'id'    => $t['id_tarefa'],
        'title' => $t['titulo'],
        'start' => $t['data_fim'],
        'backgroundColor' => $cor,
        'borderColor'     => $cor,
        'extendedProps' => [
            'estado'     => $t['estado'],
            'prioridade' => $t['prioridade'],
            'descricao'  => $t['descricao'],
            'tema'       => $t['tema'],
            'inicio'     => $t['data_inicio'],
        ]
    ];
}

require 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

<!-- =====================================================
     CABEÇALHO
     ===================================================== -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-size:20px;font-weight:800;letter-spacing:-.3px;">Calendário de tarefas</h2>
        <p style="color:var(--text-muted);font-size:14px;margin-top:2px;">
            <?php if (isAluno()): ?>
                Prazos das tarefas do teu projeto.
            <?php else: ?>
                Seleciona um aluno para ver o respetivo calendário.
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <span class="badge" style="background:#fef2f2;color:#ef4444;"><span style="width:6px;height:6px;background:#ef4444;border-radius:50%;"></span> Alta</span>
        <span class="badge" style="background:#fffbeb;color:#b45309;"><span style="width:6px;height:6px;background:#f59e0b;border-radius:50%;"></span> Média</span>
        <span class="badge" style="background:#ecfeff;color:#0891b2;"><span style="width:6px;height:6px;background:#06b6d4;border-radius:50%;"></span> Em curso</span>
        <span class="badge" style="background:#ecfdf5;color:#10b981;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;"></span> Concluída</span>
    </div>
</div>

<?php if (isProfessor()): ?>

    <!-- SELETOR DE ALUNO (só para professor) -->
    <div class="card" style="margin-bottom:20px;padding:18px;">
        <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
            <div class="form-group" style="flex:1;min-width:240px;margin:0;">
                <label style="margin-bottom:6px;display:block;">Ver calendário de:</label>
                <select name="aluno" onchange="this.form.submit();" style="width:100%;">
                    <option value="">— Escolhe um aluno —</option>
                    <?php foreach ($meusAlunos as $a): ?>
                        <option value="<?= $a['id_utilizador'] ?>" <?= $alunoSelecionado == $a['id_utilizador'] ? 'selected' : '' ?>>
                            <?= e($a['nome']) ?> — <?= e(mb_strimwidth($a['tema'], 0, 40, '…')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($alunoSelecionado): ?>
                <a href="calendario.php" class="btn btn-ghost">
                    <i class="fa-solid fa-xmark"></i> Limpar
                </a>
            <?php endif; ?>
        </form>

        <?php if (empty($meusAlunos)): ?>
            <p style="color:var(--text-muted);margin-top:12px;">
                <i class="fa-solid fa-circle-info"></i>
                Ainda não tens alunos atribuídos. Assim que um aluno te escolher como orientador, aparece aqui.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($alunoSelecionado):
        // Procurar dados do aluno escolhido para mostrar no cabeçalho
        $alunoInfo = null;
        foreach ($meusAlunos as $a) {
            if ($a['id_utilizador'] == $alunoSelecionado) { $alunoInfo = $a; break; }
        }
    ?>
        <!-- INFO DO ALUNO SELECIONADO -->
        <div class="card" style="margin-bottom:20px;padding:16px;display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--info));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;">
                <?= strtoupper(substr($alunoInfo['nome'], 0, 1)) ?>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:16px;"><?= e($alunoInfo['nome']) ?></div>
                <div style="font-size:13px;color:var(--text-muted);">
                    <?= e($alunoInfo['email']) ?> · <?= e($alunoInfo['tema']) ?>
                </div>
            </div>
            <span class="badge badge-primary"><?= count($tarefas) ?> tarefa(s)</span>
        </div>
    <?php endif; ?>

<?php endif; ?>

<!-- =====================================================
     CALENDÁRIO
     ===================================================== -->
<?php if (isProfessor() && !$alunoSelecionado): ?>

    <div class="card empty">
        <i class="fa-solid fa-calendar-days"></i>
        <h3>Escolhe um aluno</h3>
        <p>Usa o seletor acima para ver o calendário de um aluno específico.</p>
    </div>

<?php else: ?>

    <div class="card" style="padding:16px;">
        <div id="calendar"></div>
    </div>

    <?php if (empty($eventos)): ?>
        <div class="alert alert-info" style="margin-top:16px;">
            <i class="fa-solid fa-circle-info"></i>
            Ainda não existem tarefas com data de fim. Cria tarefas com prazo para aparecerem no calendário.
        </div>
    <?php endif; ?>

<?php endif; ?>

<!-- MODAL DETALHES -->
<div class="modal-overlay" id="eventoModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:1000;display:none;justify-content:center;align-items:center;padding:20px;">
    <div class="card" style="max-width:480px;width:100%;">
        <div class="card-header">
            <div class="card-title" id="evTitulo">Tarefa</div>
            <button class="icon-btn" onclick="document.getElementById('eventoModal').style.display='none';">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:14px;" id="evBadges"></div>
        <p style="color:var(--text-soft);line-height:1.6;" id="evDesc"></p>
        <p style="color:var(--text-muted);font-size:13px;margin-top:14px;" id="evDatas"></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales-all.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', list: 'Lista' },
        events: <?= json_encode($eventos) ?>,
        eventClick: function(info) {
            const p = info.event.extendedProps;
            document.getElementById('evTitulo').textContent = info.event.title;
            document.getElementById('evDesc').textContent = p.descricao || 'Sem descrição.';

            let html = '';
            if (p.prioridade) {
                const cor = p.prioridade === 'Alta' ? '#ef4444' : (p.prioridade === 'Media' ? '#f59e0b' : '#06b6d4');
                const bg  = p.prioridade === 'Alta' ? '#fef2f2' : (p.prioridade === 'Media' ? '#fffbeb' : '#ecfeff');
                html += `<span class="badge" style="background:${bg};color:${cor};">${p.prioridade}</span>`;
            }
            if (p.estado) {
                const cls = p.estado === 'Concluida' ? 'success' : (p.estado === 'Em curso' ? 'info' : 'muted');
                html += `<span class="badge badge-${cls}">${p.estado}</span>`;
            }
            document.getElementById('evBadges').innerHTML = html;

            let datas = '';
            if (p.inicio) datas += 'Início: ' + p.inicio + ' · ';
            datas += 'Fim: ' + info.event.startStr;
            document.getElementById('evDatas').textContent = datas;

            document.getElementById('eventoModal').style.display = 'flex';
        },
        eventDidMount: function(info) {
            info.el.title = info.event.title + (info.event.extendedProps.descricao ? '\n' + info.event.extendedProps.descricao : '');
        }
    });

    calendar.render();
});
</script>

<style>
.fc { font-family: var(--font) !important; }
.fc .fc-toolbar-title { font-size: 18px !important; font-weight: 700 !important; color: var(--text) !important; }
.fc .fc-button {
    background: var(--bg-soft) !important;
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    text-transform: capitalize !important;
    font-weight: 500 !important;
    box-shadow: none !important;
    padding: 6px 12px !important;
    border-radius: 8px !important;
}
.fc .fc-button:hover { background: var(--bg-alt) !important; }
.fc .fc-button-active, .fc .fc-button-primary:not(:disabled).fc-button-active {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: #fff !important;
}
.fc .fc-daygrid-day-number,
.fc .fc-col-header-cell-cushion { color: var(--text) !important; text-decoration: none !important; }
.fc .fc-day-today { background: var(--primary-soft) !important; }
.fc .fc-daygrid-day { border-color: var(--border) !important; }
.fc .fc-scrollgrid { border-color: var(--border) !important; }
.fc th { background: var(--bg-alt) !important; padding: 8px 0 !important; font-size: 12px !important; color: var(--text-muted) !important; text-transform: uppercase !important; }
.fc .fc-event { border: none !important; padding: 2px 6px !important; border-radius: 6px !important; cursor: pointer; font-size: 12px !important; font-weight: 500 !important; }
.fc .fc-list-day-cushion { background: var(--bg-alt) !important; color: var(--text) !important; }
.fc .fc-list-event:hover td { background: var(--bg-alt) !important; }
.fc .fc-list-event-title a { color: var(--text) !important; }
</style>

<?php require 'footer.php'; ?>