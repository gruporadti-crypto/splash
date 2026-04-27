<?php
session_start();
define('USUARIO_ADMIN', 'admin');
define('SENHA_ADMIN', 'Radsenha123@');

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

// --- TELA DE LOGIN ---
if (!isset($_SESSION['logado'])) {
    if (isset($_POST['login']) && $_POST['user'] == USUARIO_ADMIN && $_POST['pass'] == SENHA_ADMIN) {
        $_SESSION['logado'] = true;
    } else {
        $erro_login = isset($_POST['login']) ? "Dados incorretos!" : "";
        echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login Admin</title><style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 320px; text-align: center; }
        input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size:16px; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        </style></head><body><div class="card"><h2>Área Restrita</h2>'.($erro_login ? "<p style='color:red'>$erro_login</p>" : "").'
        <form method="POST"><input type="text" name="user" placeholder="Usuário" required><input type="password" name="pass" placeholder="Senha" required>
        <button type="submit" name="login">Entrar</button></form></div></body></html>';
        exit;
    }
}

// --- CONFIGURAÇÕES DE CONEXÃO SUPABASE (PostgreSQL) ---
$host     = '://xzemserhahccodubenfj.supabase.co'; // Geralmente é o host do seu projeto
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.xzemserhahccodubenfj';
$password = 'oJxh3BlVcVIuRIW1';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $db = new PDO($dsn, $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tabelas (Sintaxe PostgreSQL: SERIAL no lugar de AUTOINCREMENT)
    $db->exec("CREATE TABLE IF NOT EXISTS medicos (id SERIAL PRIMARY KEY, nome TEXT, especialidade TEXT, foto TEXT, cliques INTEGER DEFAULT 0)");
    $db->exec("CREATE TABLE IF NOT EXISTS agenda (id SERIAL PRIMARY KEY, medico_id INTEGER, data_agenda DATE, hora_agenda TEXT, status TEXT DEFAULT 'disponivel')");
    $db->exec("CREATE TABLE IF NOT EXISTS promocoes (id SERIAL PRIMARY KEY, foto TEXT, ativa INTEGER DEFAULT 0)");

} catch (PDOException $e) { 
    die("Erro no banco Supabase: " . $e->getMessage()); 
}

if (!file_exists('uploads')) mkdir('uploads', 0777, true);

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['del_medico'])) {
    $db->prepare("DELETE FROM agenda WHERE medico_id = ?")->execute([$_GET['del_medico']]);
    $db->prepare("DELETE FROM medicos WHERE id = ?")->execute([$_GET['del_medico']]);
    header("Location: index.php"); exit;
}
if (isset($_GET['del_agenda'])) {
    $db->prepare("DELETE FROM agenda WHERE id = ?")->execute([$_GET['del_agenda']]);
    header("Location: index.php?medico_id=".$_GET['med_id']."&data=".$_GET['data']); exit;
}

// --- PROCESSAMENTO DE FORMULÁRIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_medico'])) {
        $foto = "uploads/default.png";
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $foto = 'uploads/'.time().'_'.$_FILES['foto']['name'];
            move_uploaded_file($_FILES['foto']['tmp_name'], $foto);
        }
        $db->prepare("INSERT INTO medicos (nome, especialidade, foto, cliques) VALUES (?, ?, ?, 0)")->execute([$_POST['nome'], $_POST['especialidade'], $foto]);
    }
    if (isset($_POST['add_agenda'])) {
        $db->prepare("INSERT INTO agenda (medico_id, data_agenda, hora_agenda) VALUES (?, ?, ?)")->execute([$_POST['medico_id'], $_POST['data'], $_POST['hora']]);
    }
    if (isset($_POST['save_promo'])) {
        $ativa = isset($_POST['ativa']) ? 1 : 0;
        if (isset($_FILES['foto_promo']) && $_FILES['foto_promo']['error'] === 0) {
            $foto_p = 'uploads/promo_'.time().'_'.$_FILES['foto_promo']['name'];
            move_uploaded_file($_FILES['foto_promo']['tmp_name'], $foto_p);
            $db->exec("DELETE FROM promocoes");
            $db->prepare("INSERT INTO promocoes (foto, ativa) VALUES (?, ?)")->execute([$foto_p, $ativa]);
        } else {
            $db->prepare("UPDATE promocoes SET ativa = ? WHERE id = (SELECT id FROM promocoes LIMIT 1)")->execute([$ativa]);
        }
    }
}

// BUSCA DADOS PARA EXIBIÇÃO
$medicos = $db->query("SELECT * FROM medicos ORDER BY cliques DESC")->fetchAll(PDO::FETCH_ASSOC);
$promo = $db->query("SELECT * FROM promocoes LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$medico_sel_id = $_GET['medico_id'] ?? ($medicos[0]['id'] ?? 0);
$data_sel = $_GET['data'] ?? date('Y-m-d');
$horarios_admin = [];
if ($medico_sel_id) {
    $st = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? ORDER BY hora_agenda ASC");
    $st->execute([$medico_sel_id, $data_sel]);
    $horarios_admin = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!-- O RESTO DO HTML PERMANECE O MESMO -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Painel Admin Clínica</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; display: flex; flex-wrap: wrap; padding: 20px; gap: 20px; justify-content: center; }
        .panel { width: 100%; max-width: 450px; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow-y: auto; max-height: 90vh; }
        .preview { width: 350px; height: 650px; border: 8px solid #333; border-radius: 40px; overflow: hidden; position: sticky; top: 20px; display: none; }
        @media (min-width: 900px) { .preview { display: block; } }
        input, select, button { width: 100%; padding: 10px; margin-top: 10px; border-radius: 8px; border: 1px solid #ddd; box-sizing: border-box; }
        button { background: #007bff; color: white; font-weight: bold; cursor: pointer; border: none; }
        .report { background: #eef2f7; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .row { display: flex; justify-content: space-between; font-size: 0.85rem; padding: 8px 0; border-bottom: 1px solid #ddd; align-items: center; }
        hr { margin: 25px 0; border: 0; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>📊 Relatório Supabase</h3>
            <a href="?logout=1" style="color:red; font-size:0.8rem; text-decoration:none">Sair do Painel</a>
        </div>
        
        <div class="report">
            <?php foreach($medicos as $m): ?>
                <div class="row"><span><?= $m['nome'] ?></span> <b><?= $m['cliques'] ?> acessos</b></div>
            <?php endforeach; if(!$medicos) echo "<small>Nenhum dado.</small>"; ?>
        </div>

        <hr>
        <form method="POST" enctype="multipart/form-data">
            <small><b>CADASTRAR NOVO MÉDICO</b></small>
            <input type="text" name="nome" placeholder="Nome do Médico" required>
            <input type="text" name="especialidade" placeholder="Especialidade" required>
            <input type="file" name="foto">
            <button type="submit" name="add_medico">Salvar Médico</button>
        </form>
        <div style="margin-top:15px">
            <?php foreach($medicos as $m): ?>
                <div class="row"><span>Dr(a). <?= $m['nome'] ?></span> <a href="?del_medico=<?= $m['id'] ?>" onclick="return confirm('Excluir médico e toda sua agenda?')" style="color:red; text-decoration:none">Excluir</a></div>
            <?php endforeach; ?>
        </div>

        <hr>
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
            <button type="submit" name="add_agenda" style="background:#28a745">Adicionar Vaga</button>
        </form>
        <div style="margin-top:15px; background: #fafafa; padding: 10px; border-radius: 8px;">
            <small>Horários de hoje:</small>
            <?php foreach($horarios_admin as $ha): ?>
                <div class="row"><span><?= $ha['hora_agenda'] ?></span> <a href="?del_agenda=<?= $ha['id'] ?>&med_id=<?= $medico_sel_id ?>&data=<?= $data_sel ?>" style="color:red; text-decoration:none">Remover</a></div>
            <?php endforeach; ?>
        </div>

        <hr>
        <form method="POST" enctype="multipart/form-data">
            <small><b>PROMOÇÃO (POPUP)</b></small><br>
            <label style="font-size:0.85rem"><input type="checkbox" name="ativa" <?= ($promo['ativa'] ?? 0) ? 'checked' : '' ?>> Exibir Banner Promocional</label>
            <input type="file" name="foto_promo">
            <button type="submit" name="save_promo" style="background:#ffc107; color:black">Salvar Promoção</button>
        </form>
    </div>

    <div class="preview">
        <iframe src="agenda.php?medico_id=<?= $medico_sel_id ?>&data=<?= $data_sel ?>" style="width:100%; height:100%; border:none"></iframe>
    </div>
</body>
</html>
