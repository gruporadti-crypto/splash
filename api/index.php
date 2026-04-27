<?php
session_start();

// ==========================================
// PARTE 1: CONEXÃO COM SUPABASE (POOLER 6543)
// ==========================================
$host     = 'xzemserhahccodubenfj.supabase.co'; 
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.xzemserhahccodubenfj'; 
$password = 'oJxh3BlVcVIuRIW1';

$db_conectado = false;
$erro_db = "";

try {
    // Tentativa de conexão
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $db = new PDO($dsn, $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_conectado = true;

    // Criar tabelas automaticamente se não existirem
    $db->exec("CREATE TABLE IF NOT EXISTS usuarios (id SERIAL PRIMARY KEY, usuario TEXT UNIQUE, senha TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS medicos (id SERIAL PRIMARY KEY, nome TEXT, especialidade TEXT, foto TEXT, cliques INTEGER DEFAULT 0)");
    $db->exec("CREATE TABLE IF NOT EXISTS agenda (id SERIAL PRIMARY KEY, medico_id INTEGER, data_agenda DATE, hora_agenda TEXT, status TEXT DEFAULT 'disponivel')");
    $db->exec("CREATE TABLE IF NOT EXISTS promocoes (id SERIAL PRIMARY KEY, foto TEXT, ativa INTEGER DEFAULT 0)");

    // Criar usuário admin padrão (admin / Radsenha123@)
    $checkUser = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($checkUser == 0) {
        $senhaHash = password_hash('Radsenha123@', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO usuarios (usuario, senha) VALUES (?, ?)")->execute(['admin', $senhaHash]);
    }
} catch (PDOException $e) {
    $db_conectado = false;
    $erro_db = $e->getMessage();
}

// ==========================================
// PARTE 2: LOGOUT E TELA DE LOGIN
// ==========================================
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

if (!isset($_SESSION['logado'])) {
    $erro_login = "";
    if ($db_conectado && isset($_POST['login'])) {
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $stmt->execute([$_POST['user']]);
        $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario_db && password_verify($_POST['pass'], $usuario_db['senha'])) {
            $_SESSION['logado'] = true;
            header("Location: index.php"); exit;
        } else { $erro_login = "Usuário ou senha incorretos!"; }
    }
    ?>
    <!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login Admin</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 320px; text-align: center; }
        input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .status { font-size: 11px; margin-bottom: 20px; padding: 5px 10px; border-radius: 20px; display: inline-block; }
        .online { background: #e6ffed; color: #28a745; border: 1px solid #28a745; }
        .offline { background: #ffeef0; color: #d73a49; border: 1px solid #d73a49; }
    </style></head><body>
    <div class="card">
        <h2>Painel Clínica</h2>
        <?php if($db_conectado): ?>
            <span class="status online">● Supabase Conectado</span>
            <form method="POST">
                <?php if($erro_login) echo "<p style='color:red; font-size:13px'>$erro_login</p>"; ?>
                <input type="text" name="user" placeholder="Usuário" required>
                <input type="password" name="pass" placeholder="Senha" required>
                <button type="submit" name="login">Entrar</button>
            </form>
        <?php else: ?>
            <span class="status offline">● Erro na Conexão</span>
            <p style="color:red; font-size:12px; text-align:left;"><b>Erro:</b> <?= $erro_db ?></p>
            <button onclick="window.location.reload()">Tentar Novamente</button>
        <?php endif; ?>
    </div></body></html>
    <?php exit;
}

// ==========================================
// PARTE 3: AÇÕES DO PAINEL (SÓ SE ESTIVER LOGADO)
// ==========================================

// Exclusão de Médico ou Agenda
if (isset($_GET['del_medico'])) {
    $db->prepare("DELETE FROM agenda WHERE medico_id = ?")->execute([$_GET['del_medico']]);
    $db->prepare("DELETE FROM medicos WHERE id = ?")->execute([$_GET['del_medico']]);
    header("Location: index.php"); exit;
}
if (isset($_GET['del_agenda'])) {
    $db->prepare("DELETE FROM agenda WHERE id = ?")->execute([$_GET['del_agenda']]);
    header("Location: index.php?medico_id=".$_GET['med_id']."&data=".$_GET['data']); exit;
}

// Salvar novos dados (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!file_exists('uploads')) mkdir('uploads', 0777, true);

    if (isset($_POST['add_medico'])) {
        $foto = "uploads/default.png";
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $foto = 'uploads/'.time().'_'.$_FILES['foto']['name'];
            move_uploaded_file($_FILES['foto']['tmp_name'], $foto);
        }
        $db->prepare("INSERT INTO medicos (nome, especialidade, foto) VALUES (?, ?, ?)")->execute([$_POST['nome'], $_POST['especialidade'], $foto]);
    }

    if (isset($_POST['add_agenda'])) {
        $db->prepare("INSERT INTO agenda (medico_id, data_agenda, hora_agenda) VALUES (?, ?, ?)")->execute([$_POST['medico_id'], $_POST['data'], $_POST['hora']]);
    }
}

// Buscar dados para o visual
$medicos = $db->query("SELECT * FROM medicos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$medico_sel_id = $_GET['medico_id'] ?? ($medicos[0]['id'] ?? 0);
$data_sel = $_GET['data'] ?? date('Y-m-d');
$horarios = [];
if ($medico_sel_id) {
    $st = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? ORDER BY hora_agenda ASC");
    $st->execute([$medico_sel_id, $data_sel]);
    $horarios = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- ==========================================
     PARTE 4: O VISUAL DO PAINEL (HTML)
     ========================================== -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel Administrativo</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; display: flex; justify-content: center; padding: 20px; gap: 20px; }
        .panel { width: 450px; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        input, select, button { width: 100%; padding: 10px; margin-top: 10px; border-radius: 8px; border: 1px solid #ddd; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; font-weight: bold; cursor: pointer; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; align-items: center; font-size: 14px; }
        h3 { margin-top: 0; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="panel">
        <h3>
            <span>📊 Administração</span>
            <a href="?logout=1" style="color:red; font-size:12px; text-decoration:none;">Sair</a>
        </h3>

        <hr>
        <form method="POST" enctype="multipart/form-data">
            <small><b>CADASTRAR MÉDICO</b></small>
            <input type="text" name="nome" placeholder="Nome do Médico" required>
            <input type="text" name="especialidade" placeholder="Especialidade" required>
            <input type="file" name="foto">
            <button type="submit" name="add_medico">Salvar Médico</button>
        </form>

        <hr style="margin:20px 0">

        <form method="POST">
            <small><b>GERENCIAR AGENDA</b></small>
            <select name="medico_id" onchange="window.location.href='index.php?medico_id='+this.value">
                <option value="">Selecione o Médico...</option>
                <?php foreach($medicos as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id']==$medico_sel_id?'selected':'' ?>><?= $m['nome'] ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="data" value="<?= $data_sel ?>" onchange="window.location.href='index.php?medico_id=<?= $medico_sel_id ?>&data='+this.value">
            <input type="time" name="hora" required>
            <button type="submit" name="add_agenda" style="background:#28a745">Adicionar Horário</button>
        </form>

        <div style="margin-top:15px; background: #f9f9f9; padding: 10px; border-radius: 8px;">
            <small>Horários marcados:</small>
            <?php foreach($horarios as $h): ?>
                <div class="row">
                    <span><?= $h['hora_agenda'] ?></span>
                    <a href="?del_agenda=<?= $h['id'] ?>&med_id=<?= $medico_sel_id ?>&data=<?= $data_sel ?>" style="color:red; text-decoration:none;">Excluir</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
