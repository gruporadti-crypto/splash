<?php
session_start();

// ==========================================
// 1. CONFIGURAÇÕES (SUPABASE)
// ==========================================
$host     = 'aws-1-us-east-1.pooler.supabase.com'; 
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.dahxpbiljzhkaxwetjza'; 
$password = 'Xl2DbdCmESCLbSG5';

// Dados para o Storage
$supabase_url = "https://dahxpbiljzhkaxwetjza.supabase.co";
$supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRhaHhwYmlsanpoa2F4d2V0anphIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzczMzA3MjAsImV4cCI6MjA5MjkwNjcyMH0.ZbXnuBXM3IwQr2LAoH4LDo4YFQy2IPqZMy45Ul7V1TI";

$db_conectado = false;
$erro_db = "";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $db = new PDO($dsn, $user, $password, [PDO::ATTR_TIMEOUT => 5]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_conectado = true;

    // Criar tabelas se não existirem
    $db->exec("CREATE TABLE IF NOT EXISTS usuarios (id SERIAL PRIMARY KEY, usuario TEXT UNIQUE, senha TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS medicos (id SERIAL PRIMARY KEY, nome TEXT, especialidade TEXT, foto TEXT, cliques INTEGER DEFAULT 0)");
    $db->exec("CREATE TABLE IF NOT EXISTS agenda (id SERIAL PRIMARY KEY, medico_id INTEGER, data_agenda DATE, hora_agenda TEXT, status TEXT DEFAULT 'disponivel')");
    $db->exec("CREATE TABLE IF NOT EXISTS promocoes (id SERIAL PRIMARY KEY, foto TEXT, ativa INTEGER DEFAULT 0)");

    // Usuário admin padrão
    $checkUser = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($checkUser == 0) {
        $senhaHash = password_hash('Radsenha123@', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO usuarios (usuario, senha) VALUES (?, ?)")->execute(['admin', $senhaHash]);
    }
} catch (PDOException $e) {
    $db_conectado = false;
    $erro_db = $e->getMessage();
}

// --- FUNÇÃO AUXILIAR PARA UPLOAD SUPABASE STORAGE ---
function subirParaSupabase($arquivo, $url, $key) {
    $nomeFinal = time() . "_" . $arquivo['name'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url/storage/v1/object/uploads/$nomeFinal");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($arquivo['tmp_name']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $key", "Content-Type: " . $arquivo['type']]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code == 200) ? "$url/storage/v1/object/public/uploads/$nomeFinal" : null;
}

// ==========================================
// 2. LÓGICA DE LOGIN / LOGOUT
// ==========================================
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

if (!isset($_SESSION['logado'])) {
    $erro_login = "";
    if ($db_conectado && isset($_POST['login'])) {
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $stmt->execute([$_POST['user']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($_POST['pass'], $u['senha'])) {
            $_SESSION['logado'] = true;
            header("Location: index.php"); exit;
        } else { $erro_login = "Dados incorretos!"; }
    }
    // Tela de login integrada
    include_once('login_ui.php'); // Opcional: ou coloque o HTML de login aqui
    ?>
    <!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Login Admin</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 320px; text-align: center; }
        input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
    </style></head><body><div class="card"><h2>Área Restrita</h2>
    <p style="font-size:10px; color:<?= $db_conectado?'green':'red'?>"><?= $db_conectado?'● Conectado ao Supabase':'● Offline: '.$erro_db ?></p>
    <form method="POST"><?php if($erro_login) echo "<p style='color:red'>$erro_login</p>"; ?>
    <input type="text" name="user" placeholder="Usuário" required><input type="password" name="pass" placeholder="Senha" required><button type="submit" name="login">Entrar</button></form></div></body></html>
    <?php exit;
}

// ==========================================
// 3. PROCESSAMENTO DE AÇÕES (POST/GET)
// ==========================================

// Exclusão
if (isset($_GET['del_medico'])) {
    $db->prepare("DELETE FROM agenda WHERE medico_id = ?")->execute([$_GET['del_medico']]);
    $db->prepare("DELETE FROM medicos WHERE id = ?")->execute([$_GET['del_medico']]);
    header("Location: index.php"); exit;
}
if (isset($_GET['del_agenda'])) {
    $db->prepare("DELETE FROM agenda WHERE id = ?")->execute([$_GET['del_agenda']]);
    header("Location: index.php?medico_id=".$_GET['med_id']."&data=".$_GET['data']); exit;
}

// Cadastro de Médico
if (isset($_POST['add_medico'])) {
    $foto = "https://cdn-icons-png.flaticon.com/512/3774/3774299.png";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $urlUpload = subirParaSupabase($_FILES['foto'], $supabase_url, $supabase_key);
        if ($urlUpload) $foto = $urlUpload;
    }
    $db->prepare("INSERT INTO medicos (nome, especialidade, foto, cliques) VALUES (?, ?, ?, 0)")->execute([$_POST['nome'], $_POST['especialidade'], $foto]);
}

// Adicionar Agenda
if (isset($_POST['add_agenda'])) {
    $db->prepare("INSERT INTO agenda (medico_id, data_agenda, hora_agenda) VALUES (?, ?, ?)")->execute([$_POST['medico_id'], $_POST['data'], $_POST['hora']]);
}

// Salvar Promoção (Popup)
if (isset($_POST['save_promo'])) {
    $ativa = isset($_POST['ativa']) ? 1 : 0;
    if (isset($_FILES['foto_promo']) && $_FILES['foto_promo']['error'] === 0) {
        $foto_p = subirParaSupabase($_FILES['foto_promo'], $supabase_url, $supabase_key);
        if ($foto_p) {
            $db->exec("DELETE FROM promocoes");
            $db->prepare("INSERT INTO promocoes (foto, ativa) VALUES (?, ?)")->execute([$foto_p, $ativa]);
        }
    } else {
        $db->prepare("UPDATE promocoes SET ativa = ? WHERE id = (SELECT id FROM promocoes LIMIT 1)")->execute([$ativa]);
    }
}

// ==========================================
// 4. BUSCA DE DADOS PARA A TELA
// ==========================================
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
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Painel Admin Clínica</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; display: flex; flex-wrap: wrap; padding: 20px; gap: 20px; justify-content: center; }
        .panel { width: 100%; max-width: 450px; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow-y: auto; max-height: 95vh; }
        .preview { width: 350px; height: 650px; border: 8px solid #333; border-radius: 40px; overflow: hidden; position: sticky; top: 20px; background: #fff; }
        @media (max-width: 900px) { .preview { display: none; } }
        input, select, button { width: 100%; padding: 10px; margin-top: 10px; border-radius: 8px; border: 1px solid #ddd; box-sizing: border-box; }
        button { background: #007bff; color: white; font-weight: bold; cursor: pointer; border: none; }
        .report { background: #eef2f7; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .row { display: flex; justify-content: space-between; font-size: 0.85rem; padding: 8px 0; border-bottom: 1px solid #eee; align-items: center; }
        hr { margin: 25px 0; border: 0; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>📊 Relatório de Cliques</h3>
            <a href="?logout=1" style="color:red; font-size:0.8rem; text-decoration:none">Sair do Painel</a>
        </div>
        
        <div class="report">
            <?php foreach($medicos as $m): ?>
                <div class="row"><span><?= htmlspecialchars($m['nome']) ?></span> <b><?= $m['cliques'] ?> acessos</b></div>
            <?php endforeach; if(!$medicos) echo "<small>Nenhum dado.</small>"; ?>
        </div>

        <hr>
        <form method="POST" enctype="multipart/form-data">
            <small><b>CADASTRAR NOVO MÉDICO</b></small>
            <input type="text" name="nome" placeholder="Nome do Médico" required>
            <input type="text" name="especialidade" placeholder="Especialidade" required>
            <input type="file" name="foto" accept="image/*">
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
            <small>Horários configurados:</small>
            <?php foreach($horarios_admin as $ha): ?>
                <div class="row"><span><?= $ha['hora_agenda'] ?></span> <a href="?del_agenda=<?= $ha['id'] ?>&med_id=<?= $medico_sel_id ?>&data=<?= $data_sel ?>" style="color:red; text-decoration:none">Remover</a></div>
            <?php endforeach; if(!$horarios_admin) echo "<br><small>Nenhum horário para este dia.</small>"; ?>
        </div>

        <hr>
        <form method="POST" enctype="multipart/form-data">
            <small><b>PROMOÇÃO (POPUP)</b></small><br>
            <label style="font-size:0.85rem"><input type="checkbox" name="ativa" <?= ($promo['ativa'] ?? 0) ? 'checked' : '' ?>> Exibir Banner Promocional</label>
            <input type="file" name="foto_promo" accept="image/*">
            <button type="submit" name="save_promo" style="background:#ffc107; color:black">Salvar Promoção</button>
        </form>
    </div>

    <!-- PREVIEW LATERAL (ESTILO CELULAR) -->
    <div class="preview">
        <iframe src="agenda.php?medico_id=<?= $medico_sel_id ?>&data=<?= $data_sel ?>" style="width:100%; height:100%; border:none"></iframe>
    </div>
</body>
</html>
