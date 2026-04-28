<?php
session_start();
$host     = 'aws-1-us-east-1.pooler.supabase.com'; 
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.dahxpbiljzhkaxwetjza'; 
$password = 'Xl2DbdCmESCLbSG5';

try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db_ok = true;
} catch (PDOException $e) { $db_ok = false; }

function converterLink($url) {
    if (strpos($url, 'drive.google.com') !== false) {
        preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $m);
        return isset($m[1]) ? "https://lh3.googleusercontent.com/d/" . $m[1] : $url;
    }
    return $url;
}

if ($db_ok) {
    $medicos = $db->query("SELECT * FROM medicos ORDER BY cliques DESC")->fetchAll(PDO::FETCH_ASSOC);
    $med_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : ($medicos[0]['id'] ?? 0);
    if (isset($_GET['medico_id'])) { $db->prepare("UPDATE medicos SET cliques = cliques + 1 WHERE id = ?")->execute([$med_id]); }
    $promo = $db->query("SELECT * FROM promocoes WHERE ativa = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $hoje = date('Y-m-d');
    $st_d = $db->prepare("SELECT DISTINCT data_agenda FROM agenda WHERE medico_id = ? AND data_agenda >= ? ORDER BY data_agenda ASC");
    $st_d->execute([$med_id, $hoje]);
    $datas = $st_d->fetchAll(PDO::FETCH_COLUMN);
    $data_sel = $_GET['data'] ?? ($datas[0] ?? $hoje);
    $med_atual = null;
    foreach($medicos as $m) { if($m['id'] == $med_id) $med_atual = $m; }
    $horarios = [];
    if ($med_id) {
        $st_h = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? ORDER BY hora_agenda ASC");
        $st_h->execute([$med_id, $data_sel]);
        $horarios = $st_h->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Agenda Online</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
    body{font-family:'Poppins',sans-serif;margin:0;background:#f8f9fa;display:flex;justify-content:center}
    .app{width:100%;max-width:500px;background:#fff;min-height:100vh;position:relative}
    .promo-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px}
    .promo-box{width:100%;max-width:350px;text-align:center}
    .promo-box img{width:100%;border-radius:20px;border:3px solid #fff}
    .btn-close{margin-top:20px;padding:15px;width:100%;background:#007bff;color:#fff;border:none;border-radius:50px;font-weight:bold;cursor:pointer}
    .doctor-nav{display:flex;overflow-x:auto;padding:20px 15px;gap:15px;border-bottom:1px solid #f0f0f0}
    .doc{min-width:70px;text-align:center;text-decoration:none;color:#333;opacity:0.5}
    .doc.active{opacity:1;transform:scale(1.1)}
    .doc img{width:60px;height:60px;border-radius:50%;object-fit:cover}
    .calendar{display:flex;overflow-x:auto;padding:15px;gap:10px;background:#fafafa}
    .day{min-width:60px;padding:10px;background:#fff;border:1px solid #eee;border-radius:15px;text-align:center;text-decoration:none;color:#444}
    .day.active{background:#007bff;color:#fff}
    .slot{margin:15px;padding:15px;border:1px solid #f0f0f0;border-radius:20px;display:flex;justify-content:space-between;align-items:center}
    .btn-zap{background:#25d366;color:#fff;padding:10px 20px;border-radius:12px;text-decoration:none;font-weight:bold}
</style></head><body>
<?php if ($promo && !isset($_SESSION['fechou_promo'])): ?>
<div class="promo-overlay" id="pop">
    <div class="promo-box">
        <?php $url = converterLink($promo['foto']); if(strpos($url, 'youtube') !== false): ?>
        <iframe src="<?= str_replace('watch?v=', 'embed/', $url) ?>" style="width:100%;height:200px;border-radius:15px;border:none"></iframe>
        <?php else: ?><img src="<?= $url ?>"><?php endif; ?>
        <button class="btn-close" onclick="fechar()">FECHAR E VER AGENDA</button>
    </div>
</div>
<script>function fechar(){document.getElementById('pop').style.display='none';<?php $_SESSION['fechou_promo']=true; ?>}</script>
<?php endif; ?>
<div class="app">
    <div class="doctor-nav"><?php foreach($medicos as $m): ?><a href="?medico_id=<?= $m['id'] ?>" class="doc <?= $m['id']==$med_id?'active':'' ?>"><img src="<?= $m['foto'] ?>"><span><?= explode(' ',$m['nome'])[0] ?></span></a><?php endforeach; ?></div>
    <?php if($med_atual): ?>
    <div style="text-align:center;padding:20px"><h2>Dr(a). <?= $med_atual['nome'] ?></h2><p style="color:#007bff"><?= $med_atual['especialidade'] ?></p></div>
    <div class="calendar"><?php foreach($datas as $d): $ts=strtotime($d); ?><a href="?medico_id=<?= $med_id ?>&data=<?= $d ?>" class="day <?= $d==$data_sel?'active':'' ?>"><small><?= date('D',$ts) ?></small><br><strong><?= date('d',$ts) ?></strong></a><?php endforeach; ?></div>
    <div style="padding:20px"><?php foreach($horarios as $h): ?>
    <div class="slot"><strong><?= $h['hora_agenda'] ?></strong><a href="https://wa.me/559930781040?text=Agendar com <?= $med_atual['nome'] ?> às <?= $h['hora_agenda'] ?>" target="_blank" class="btn-zap">AGENDAR</a></div>
    <?php endforeach; if(!$horarios) echo "<p style='text-align:center;color:#999'>Sem horários.</p>"; ?></div>
    <?php endif; ?>
</div>
</body></html>
