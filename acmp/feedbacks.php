<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$pageTitle = 'Feedbacks';
$id = $_SESSION['id_utilizador'];

$erro = '';

/* ============================================================
   1. PROCESSA AÇÕES
   ============================================================ */

// ---------- NOVO FEEDBACK / RESPOSTA (usa só a tabela acomp_feedbacks) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'enviar') {
    $texto = trim($_POST['texto'] ?? '');
    $tipo  = $_POST['tipo'] ?? 'Comentario';
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);

    if ($texto === '' || !$idProjeto) {
        setFlash("Escreve uma mensagem antes de enviar.", 'error');
    } else {
        // Confirmar que o utilizador tem acesso ao projeto
        $temAcesso = false;
        if (isAluno()) {
            $stmt = $pdo->prepare("SELECT id_projeto FROM acomp_projetos WHERE id_projeto=? AND id_aluno=?");
            $stmt->execute([$idProjeto, $id]);
            $temAcesso = (bool)$stmt->fetch();
        } else {
            $stmt = $pdo->prepare("SELECT id_projeto FROM acomp_projetos WHERE id_projeto=? AND id_professor=?");
            $stmt->execute([$idProjeto, $id]);
            $temAcesso = (bool)$stmt->fetch();
        }

        if ($temAcesso) {
            // Se for aluno → grava o id do aluno no campo id_professor (não é o ideal mas é o que temos)
            // Se for professor → grava o próprio id
            $autorId = $id;

            $stmt = $pdo->prepare("
                INSERT INTO acomp_feedbacks (texto, tipo, id_projeto, id_professor)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$texto, $tipo, $idProjeto, $autorId]);
            setFlash("Mensagem enviada.");
        } else {
            setFlash("Não tens permissão para publicar neste projeto.", 'error');
        }
    }
    header("Location: feedbacks.php");
    exit;
}


/* ============================================================
   2. CARREGA DADOS
   ============================================================ */

if (isAluno()) {
    // Aluno: vê o seu projeto
    $stmt = $pdo->prepare("SELECT id_projeto, tema FROM acomp_projetos WHERE id_aluno=?");
    $stmt->execute([$id]);
    $projeto = $stmt->fetch();

    if (!$projeto) {
        setFlash("Cria primeiro o teu projeto.", 'error');
        header("Location: projeto.php");
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT f.*, u.nome AS autor_nome, u.tipo AS autor_tipo, u.id_utilizador AS autor_id
        FROM acomp_feedbacks f
        JOIN acomp_utilizadores u ON u.id_utilizador = f.id_professor
        WHERE f.id_projeto = ?
        ORDER BY f.data ASC
    ");
    $stmt->execute([$projeto['id_projeto']]);
    $mensagens = $stmt->fetchAll();

} else {
    // Professor: lista de projetos + escolher qual ver
    $stmt = $pdo->prepare("
        SELECT p.id_projeto, p.tema, u.nome AS aluno
        FROM acomp_projetos p
        JOIN acomp_utilizadores u ON u.id_utilizador = p.id_aluno
        WHERE p.id_professor = ?
        ORDER BY p.id_projeto DESC
    ");
    $stmt->execute([$id]);
    $meusProjetos = $stmt->fetchAll();

    // Projeto selecionado?
    $projetoSel = isset($_GET['projeto']) ? (int)$_GET['projeto'] : null;

    // Validar que o projeto é dele
    $projetoSelInfo = null;
    foreach ($meusProjetos as $p) {
        if ($p['id_projeto'] == $projetoSel) { $projetoSelInfo = $p; break; }
    }

    $mensagens = [];
    if ($projetoSelInfo) {
        $stmt = $pdo->prepare("
            SELECT f.*, u.nome AS autor_nome, u.tipo AS autor_tipo, u.id_utilizador AS autor_id
            FROM acomp_feedbacks f
            JOIN acomp_utilizadores u ON u.id_utilizador = f.id_professor
            WHERE f.id_projeto = ?
            ORDER BY f.data ASC
        ");
        $stmt->execute([$projetoSelInfo['id_projeto']]);
        $mensagens = $stmt->fetchAll();
    }
}

require 'header.php';
?>

<h2 style="font-size:20px;font-weight:800;letter-spacing:-.3px;margin-bottom:20px;">
    💬 Feedbacks
</h2>

<?php if (isProfessor()): ?>

    <!-- ================================
         PROFESSOR: escolher projeto
         ================================ -->
    <?php if (empty($meusProjetos)): ?>
        <div class="card empty">
            <i class="fa-solid fa-folder-open"></i>
            <h3>Sem alunos atribuídos</h3>
            <p>Assim que um aluno te escolher como orientador, aparece aqui.</p>
        </div>
    <?php else: ?>
        <div class="card" style="margin-bottom:20px;padding:18px;">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:240px;margin:0;">
                    <label style="margin-bottom:6px;display:block;">Conversa com:</label>
                    <select name="projeto" onchange="this.form.submit();" style="width:100%;">
                        <option value="">— Escolhe um aluno —</option>
                        <?php foreach ($meusProjetos as $p): ?>
                            <option value="<?= $p['id_projeto'] ?>" <?= $projetoSel == $p['id_projeto'] ? 'selected' : '' ?>>
                                <?= e($p['aluno']) ?> — <?= e(mb_strimwidth($p['tema'], 0, 40, '…')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($projetoSelInfo): ?>
                    <a href="feedbacks.php" class="btn btn-ghost">
                        <i class="fa-solid fa-xmark"></i> Limpar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$projetoSelInfo): ?>
            <div class="card empty">
                <i class="fa-solid fa-comments"></i>
                <h3>Escolhe um aluno</h3>
                <p>Usa o seletor acima para veres a conversa com um aluno.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>

<?php if (isAluno() || (isProfessor() && $projetoSelInfo)): ?>

    <!-- ================================
         THREAD DE MENSAGENS
         ================================ -->
    <div class="card" style="padding:0;overflow:hidden;">

        <?php if (isProfessor() && $projetoSelInfo): ?>
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:var(--bg-alt);">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--info));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">
                    <?= strtoupper(substr($projetoSelInfo['aluno'], 0, 1)) ?>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;"><?= e($projetoSelInfo['aluno']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= e($projetoSelInfo['tema']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div style="padding:20px;display:flex;flex-direction:column;gap:14px;max-height:600px;overflow-y:auto;">

            <?php if (empty($mensagens)): ?>
                <div class="empty" style="padding:40px 20px;">
                    <i class="fa-regular fa-comments"></i>
                    <h3>Sem mensagens</h3>
                    <p><?= isProfessor() ? 'Começa a conversa enviando um feedback.' : 'Ainda não há feedbacks do teu orientador.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($mensagens as $m):
                    $souEu = $m['autor_id'] == $id;
                    $cor = $m['autor_tipo'] === 'professor' ? 'var(--primary)' : 'var(--info)';
                ?>
                    <div style="display:flex;gap:10px;<?= $souEu ? 'flex-direction:row-reverse;' : '' ?>">
                        <div style="width:38px;height:38px;border-radius:50%;background:<?= $cor ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">
                            <?= strtoupper(substr($m['autor_nome'], 0, 1)) ?>
                        </div>
                        <div style="flex:1;max-width:75%;background:<?= $souEu ? 'var(--primary)' : 'var(--bg-alt)' ?>;color:<?= $souEu ? '#fff' : 'var(--text)' ?>;border-radius:14px;padding:10px 14px;<?= $souEu ? 'border-top-right-radius:3px;' : 'border-top-left-radius:3px;' ?>">
                            <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;font-size:12px;">
                                <strong><?= $souEu ? 'Tu' : e($m['autor_nome']) ?></strong>
                                <span style="padding:1px 7px;border-radius:10px;font-size:10px;background:<?= $souEu ? 'rgba(255,255,255,.2)' : 'rgba(99,102,241,.15)' ?>;color:<?= $souEu ? '#fff' : 'var(--primary)' ?>;">
                                    <?= e(ucfirst($m['autor_tipo'])) ?>
                                </span>
                                <span style="margin-left:auto;opacity:.75;font-size:11px;"><?= date('d/m H:i', strtotime($m['data'])) ?></span>
                            </div>
                            <div style="font-size:14px;line-height:1.55;white-space:pre-wrap;"><?= e($m['texto']) ?></div>
                            <?php if ($m['tipo'] !== 'Comentario'): ?>
                                <div style="margin-top:6px;font-size:11px;opacity:.75;">
                                    <i class="fa-solid fa-tag"></i> <?= e($m['tipo']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- FORM DE ENVIO -->
        <?php if (isAluno() || $projetoSelInfo): ?>
            <form method="POST" style="padding:14px 20px;border-top:1px solid var(--border);background:var(--bg-alt);">
                <input type="hidden" name="acao" value="enviar">
                <input type="hidden" name="id_projeto" value="<?= isAluno() ? $projeto['id_projeto'] : $projetoSelInfo['id_projeto'] ?>">

                <?php if (isProfessor()): ?>
                    <div style="margin-bottom:10px;">
                        <select name="tipo" style="width:auto;padding:6px 10px;font-size:12px;border-radius:8px;">
                            <option value="Comentario">Comentário</option>
                            <option value="Sugestao">Sugestão</option>
                            <option value="Correcao">Correção</option>
                            <option value="Avaliacao">Avaliação</option>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="tipo" value="Comentario">
                <?php endif; ?>

                <div style="display:flex;gap:10px;align-items:end;">
                    <textarea name="texto" required placeholder="<?= isProfessor() ? 'Escreve um feedback...' : 'Responde ao teu orientador...' ?>"
                        style="flex:1;min-height:44px;max-height:120px;padding:10px 14px;border-radius:22px;"></textarea>
                    <button type="submit" class="btn" style="border-radius:22px;padding:10px 18px;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require 'footer.php'; ?>