<?php
session_start();

// --- CONFIGURAÇÕES SUPABASE ---
$host     = 'aws-1-us-east-1.pooler.supabase.com'; 
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.dahxpbiljzhkaxwetjza'; 
$password = 'Xl2DbdCmESCLbSG5';
$supabase_url = "https://dahxpbiljzhkaxwetjza.supabase.co";
$supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRhaHhwYmlsanpoa2F4d2V0anphIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzczMzA3MjAsImV4cCI6MjA5MjkwNjcyMH0.ZbXnuBXM3IwQr2LAoH4LDo4YFQy2IPqZMy45Ul7V1TI";

$db_conectado = false;
$erro_db = "";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $db = new PDO($dsn, $user, $password, [PDO::ATTR_TIMEOUT => 5]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_conectado = true;

    // Criar tabelas
    $db->exec("CREATE TABLE IF NOT EXISTS usuarios (id SERIAL PRIMARY KEY, usuario TEXT UNIQUE, senha TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS medicos (id SERIAL PRIMARY KEY, nome TEXT, especialidade TEXT, foto TEXT, cliques INTEGER DEFAULT 0)");
    $db->exec("CREATE TABLE IF NOT EXISTS agenda (id SERIAL PRIMARY KEY, medico_id INTEGER, data_agenda DATE, hora_agenda TEXT, status TEXT DEFAULT 'disponivel')");
    $db->exec("CREATE TABLE IF NOT EXISTS promocoes (id SERIAL PRIMARY KEY, foto TEXT, ativa INTEGER DEFAULT 0)");

    if ($db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() == 0) {
        $hash = password_hash('Radsenha123@', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO usuarios (usuario, senha) VALUES ('admin', ?)")->execute([$hash]);
    }
} catch (PDOException $e) { $db_conectado = false; $erro_db = $e->getMessage(); }

function subirParaSupabase($arquivo, $url, $key) {
    $nome = time() . "_" . $arquivo['name'];
    $ch = curl_init("$url/storage/v1/object/uploads/$nome");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($arquivo['tmp_name']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $key", "Content-Type: ".$arquivo['type']]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code == 200) ? "$url/storage/v1/object/public/uploads/$nome" : null;
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: ./index.php"); exit; }

if (!isset($_SESSION['logado'])) {
    if ($db_conectado && isset($_POST['login'])) {
        $st = $db->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $st->execute([$_POST['user']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($_POST['pass'], $u['senha'])) { $_SESSION['logado'] = true; header("Location: ./index.php"); exit; }
    }
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Login</title><style>body{font-family:sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}.card{background:#fff;padding:40px;border-radius:20px;text-align:center;width:300px}input,button{width:100%;padding:12px;margin-top:10px;border-radius:8px;border:1px solid #ddd;box-sizing:border-box}button{background:#007bff;color:#fff;font-weight:bold;cursor:pointer}</style></head><body><div class="card"><h2>Painel Admin</h2><p style="font-size:10px;color:'.($db_conectado?'green':'red').'">'.($db_conectado?'● BANCO CONECTADO':'● ERRO: '.$erro_db).'</p><form method="POST"><input type="text" name="user" placeholder="Usuário"><input type="password" name="pass" placeholder="Senha"><button type="submit" name="login">Entrar</button></form></div></body></html>';
    exit;
}

if (isset($_GET['del_medico'])) {
    $db->prepare("DELETE FROM agenda WHERE medico_id = ?")->execute([$_GET['del_medico']]);
    $db->prepare("DELETE FROM medicos WHERE id = ?")->execute([$_GET['del_medico']]);
    header("Location: ./index.php"); exit;
}
if (isset($_GET['del_agenda'])) {
    $db->prepare("DELETE FROM agenda WHERE id = ?")->execute([$_GET['del_agenda']]);
    header("Location: ./index.php?medico_id=".$_GET['med_id']."&data=".$_GET['data']); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_medico'])) {
        $foto = "https://cdn-icons-png.flaticon.com/512/3774/3774299.png";
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) { $url = subirParaSupabase($_FILES['foto'], $supabase_url, $supabase_key); if($url) $foto=$url; }
        $db->prepare("INSERT INTO medicos (nome, especialidade, foto) VALUES (?, ?, ?)")->execute([$_POST['nome'], $_POST['especialidade'], $foto]);
    }
    if (isset($_POST['add_agenda'])) {
        $db->prepare("INSERT INTO agenda (medico_id, data_agenda, hora_agenda) VALUES (?, ?, ?)")->execute([$_POST['medico_id'], $_POST['data'], $_POST['hora']]);
    }
    if (isset($_POST['save_promo'])) {
        $ativa = isset($_POST['ativa']) ? 1 : 0;
        $link = $_POST['link_promo'] ?: '';
        if (isset($_FILES['foto_promo']) && $_FILES['foto_promo']['error'] == 0) {
            $url = subirParaSupabase($_FILES['foto_promo'], $supabase_url, $supabase_key);
            if ($url) $link = $url;
        }
        $db->exec("DELETE FROM promocoes");
        $db->prepare("INSERT INTO promocoes (foto, ativa) VALUES (?, ?)")->execute([$link, $ativa]);
        header("Location: ./index.php"); exit;
    }
}

$medicos = $db->query("SELECT * FROM medicos ORDER BY cliques DESC")->fetchAll(PDO::FETCH_ASSOC);
$promo = $db->query("SELECT * FROM promocoes LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$med_id = $_GET['medico_id'] ?? ($medicos[0]['id'] ?? 0);
$data_sel = $_GET['data'] ?? date('Y-m-d');
$horarios = [];
if ($med_id) {
    $st = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? ORDER BY hora_agenda ASC");
    $st->execute([$med_id, $data_sel]);
    $horarios = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Admin</title><style>body{font-family:sans-serif;background:#f4f7f6;display:flex;justify-content:center;padding:20px;gap:20px}.panel{width:450px;background:#fff;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1)}.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:13px}input,select,button{width:100%;padding:10px;margin-top:10px;border-radius:8px;border:1px solid #ddd;box-sizing:border-box}button{background:#007bff;color:#fff;font-weight:bold;cursor:pointer;border:none}.preview{width:350px;height:650px;border:8px solid #333;border-radius:40px;overflow:hidden;position:sticky;top:20px}@media(max-width:900px){.preview{display:none}}</style></head><body>
<div class="panel">
    <div style="display:flex;justify-content:space-between"><h3>📊 Relatório</h3><a href="?logout=1" style="color:red;text-decoration:none">Sair</a></div>
    <?php foreach($medicos as $m): ?><div class="row"><span><?= $m['nome'] ?></span><b><?= $m['cliques'] ?> acessos</b></div><?php endforeach; ?>
    <hr>
    <form method="POST" enctype="multipart/form-data"><small>NOVO MÉDICO</small><input type="text" name="nome" placeholder="Nome"><input type="text" name="especialidade" placeholder="Especialidade"><input type="file" name="foto"><button name="add_medico">Salvar Médico</button></form>
    <hr>
    <form method="POST"><small>AGENDA</small><select name="medico_id" onchange="location.href='?medico_id='+this.value"><?php foreach($medicos as $m): ?><option value="<?= $m['id'] ?>" <?= $m['id']==$med_id?'selected':'' ?>><?= $m['nome'] ?></option><?php endforeach; ?></select><input type="date" name="data" value="<?= $data_sel ?>" onchange="location.href='?medico_id=<?= $med_id ?>&data='+this.value"><input type="time" name="hora"><button name="add_agenda" style="background:#28a745">Adicionar Vaga</button></form>
    <div style="margin-top:10px"><?php foreach($horarios as $h): ?><div class="row"><span><?= $h['hora_agenda'] ?></span><a href="?del_agenda=<?= $h['id'] ?>&med_id=<?= $med_id ?>&data=<?= $data_sel ?>" style="color:red">Remover</a></div><?php endforeach; ?></div>
    <hr>
    <form method="POST" enctype="multipart/form-data"><small>PROMOÇÃO (POPUP)</small><br><label><input type="checkbox" name="ativa" <?= ($promo['ativa']??0)?'checked':'' ?>> Exibir Banner</label><input type="text" name="link_promo" placeholder="Link (Drive/YouTube)" value="<?= $promo['foto']??'' ?>"><input type="file" name="foto_promo"><button name="save_promo" style="background:#ffc107;color:#000">Salvar Promoção</button></form>
</div>
<div class="preview"><iframe src="agenda.php?medico_id=<?= $med_id ?>" style="width:100%;height:100%;border:none"></iframe></div>
</body></html>
