<?php
require_once 'config.php';
require_once 'functions.php';
requireAluno();

$pageTitle = 'O meu projeto';
$id = $_SESSION['id_utilizador'];

// Buscar projeto existente
$stmt = $pdo->prepare("SELECT * FROM acomp_projetos WHERE id_aluno = ?");
$stmt->execute([$id]);
$projeto = $stmt->fetch();

// Lista de professores para escolher orientador
$professores = $pdo->query("
    SELECT id_utilizador, nome FROM acomp_utilizadores
    WHERE tipo = 'professor' ORDER BY nome
")->fetchAll();

// Guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tema        = trim($_POST['tema'] ?? '');
    $titulo      = trim($_POST['titulo'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $dataInicio  = $_POST['data_inicio'] ?: null;
    $dataFim     = $_POST['data_fim_prevista'] ?: null;
    $estado      = $_POST['estado'] ?? 'Planeamento';
    $idProfessor = $_POST['id_professor'] ?: null;

    if ($tema === '') {
        setFlash("O tema é obrigatório.", 'error');
    } else {
        if ($projeto) {
            $stmt = $pdo->prepare("
                UPDATE acomp_projetos
                SET tema=?, titulo=?, descricao=?, data_inicio=?,
                    data_fim_prevista=?, estado=?, id_professor=?
                WHERE id_projeto=?
            ");
            $stmt->execute([$tema, $titulo, $descricao, $dataInicio,
                            $dataFim, $estado, $idProfessor,
                            $projeto['id_projeto']]);
            setFlash("Projeto atualizado com sucesso.");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO acomp_projetos
                (tema, titulo, descricao, data_inicio, data_fim_prevista, estado, id_aluno, id_professor)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tema, $titulo, $descricao, $dataInicio,
                            $dataFim, $estado, $id, $idProfessor]);
            setFlash("Projeto criado com sucesso.");
        }
        header("Location: projeto.php");
        exit;
    }
}

require 'header.php';
?>

<h1><?= $projeto ? 'Editar' : 'Criar' ?> projeto</h1>

<div class="card">
    <form method="POST">
        <div>
            <label>Tema *</label>
            <input type="text" name="tema" required value="<?= e($projeto['tema'] ?? '') ?>">
        </div>
        <div>
            <label>Título</label>
            <input type="text" name="titulo" value="<?= e($projeto['titulo'] ?? '') ?>">
        </div>
        <div>
            <label>Descrição</label>
            <textarea name="descricao"><?= e($projeto['descricao'] ?? '') ?></textarea>
        </div>
        <div>
            <label>Data de início</label>
            <input type="date" name="data_inicio" value="<?= e($projeto['data_inicio'] ?? '') ?>">
        </div>
        <div>
            <label>Data prevista de fim</label>
            <input type="date" name="data_fim_prevista" value="<?= e($projeto['data_fim_prevista'] ?? '') ?>">
        </div>
        <div>
            <label>Orientador</label>
            <select name="id_professor">
                <option value="">— Sem orientador —</option>
                <?php foreach ($professores as $p): ?>
                    <option value="<?= $p['id_utilizador'] ?>"
                        <?= ($projeto['id_professor'] ?? '') == $p['id_utilizador'] ? 'selected' : '' ?>>
                        <?= e($p['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Estado</label>
            <select name="estado">
                <?php foreach (['Planeamento','Em curso','Concluido','Avaliado'] as $est): ?>
                    <option value="<?= $est ?>" <?= ($projeto['estado'] ?? '') === $est ? 'selected' : '' ?>>
                        <?= $est ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">
            <?= $projeto ? '💾 Guardar alterações' : '➕ Criar projeto' ?>
        </button>
    </form>
</div>

<?php require 'footer.php'; ?>