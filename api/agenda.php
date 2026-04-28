<?php
// ARQUIVO: agenda.php (PÚBLICO PARA CLIENTES)

$host     = 'aws-1-us-east-1.pooler.supabase.com'; 
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.dahxpbiljzhkaxwetjza'; 
$password = 'Xl2DbdCmESCLbSG5';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $db_ok = true;
} catch (PDOException $e) { 
    $db_ok = false;
}

$zap = "559930781040";
$dias_pt = ['Sun'=>'Dom', 'Mon'=>'Seg', 'Tue'=>'Ter', 'Wed'=>'Qua', 'Thu'=>'Qui', 'Fri'=>'Sex', 'Sat'=>'Sáb'];

if ($db_ok) {
    // 1. Contador de cliques (Rastreio)
    if (isset($_GET['medico_id'])) {
        $stmt_click = $db->prepare("UPDATE medicos SET cliques = cliques + 1 WHERE id = ?");
        $stmt_click->execute([(int)$_GET['medico_id']]);
    }

    // 2. Busca Médicos
    $medicos = $db->query("SELECT * FROM medicos ORDER BY cliques DESC")->fetchAll();
    $medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : ($medicos[0]['id'] ?? 0);

    // 3. Busca Promoção
    $promo = $db->query("SELECT * FROM promocoes WHERE ativa = 1 LIMIT 1")->fetch();

    // 4. Busca Datas Disponíveis
    $hoje = date('Y-m-d');
    $stmt_d = $db->prepare("SELECT DISTINCT data_agenda FROM agenda WHERE medico_id = ? AND data_agenda >= ? ORDER BY data_agenda ASC");
    $stmt_d->execute([$medico_id, $hoje]);
    $datas_disp = $stmt_d->fetchAll(PDO::FETCH_COLUMN);

    $data_sel = $_GET['data'] ?? ($datas_disp[0] ?? $hoje);
    
    // 5. Busca Médico Atual e Horários
    $medico_atual = null;
    foreach($medicos as $m) { if($m['id'] == $medico_id) { $medico_atual = $m; break; } }

    $horarios = [];
    if ($medico_id) {
        $stmt_h = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? ORDER BY hora_agenda ASC");
        $stmt_h->execute([$medico_id, $data_sel]);
        $horarios = $stmt_h->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Agenda Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #f8f9fa; display: flex; justify-content: center; }
        .app { width: 100%; max-width: 500px; background: white; min-height: 100vh; position: relative; }
        .promo-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .promo-box { width: 100%; max-width: 350px; text-align: center; }
        .promo-box img { width: 100%; border-radius: 15px; }
        .btn-close { margin-top: 20px; padding: 12px; width: 100%; background: #007bff; color: white; border: none; border-radius: 30px; font-weight: bold; cursor: pointer; }
        .doctor-nav { display: flex; overflow-x: auto; padding: 15px; gap: 15px; border-bottom: 1px solid #eee; }
        .doc { min-width: 75px; text-align: center; text-decoration: none; color: #333; opacity: 0.4; }
        .doc.active { opacity: 1; }
        .doc img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
        .doc.active img { border: 3px solid #007bff; }
        .doc span { font-size: 10px; display: block; margin-top: 5px; font-weight: bold; }
        .calendar { display: flex; overflow-x: auto; padding: 15px; gap: 10px; background: #fafafa; }
        .day { min-width: 60px; padding: 12px 5px; background: white; border: 1px solid #eee; border-radius: 15px; text-align: center; text-decoration: none; color: #333; }
        .day.active { background: #007bff; color: white; }
        .day small { font-size: 10px; text-transform: uppercase; display: block; }
        .slot { margin: 15px; padding: 15px; border: 1px solid #f0f0f0; border-radius: 18px; display: flex; justify-content: space-between; align-items: center; }
        .btn-zap { background: #25d366; color: white; padding: 8px 15px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 13px; }
        .db-status { position: fixed; bottom: 10px; right: 10px; font-size: 10px; padding: 4px 8px; border-radius: 10px; z-index: 999; }
        .db-online { background: #28a745; color: white; opacity: 0.7; }
    </style>
</head>
<body>
<?php if ($db_ok): ?>
    <div class="db-status db-online">● Online</div>

    <!-- O BANNER SÓ ABRE SE NÃO TIVER UM MÉDICO CLICADO NA URL -->
    <?php if ($promo && !isset($_GET['medico_id'])): ?>
    <div class="promo-overlay" id="pop">
        <div class="promo-box">
            <img src="<?= htmlspecialchars($promo['foto']) ?>">
            <button class="btn-close" onclick="document.getElementById('pop').style.display='none'">VER AGENDA</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="app">
        <div class="doctor-nav">
            <?php foreach($medicos as $m): ?>
                <a href="?medico_id=<?= $m['id'] ?>" class="doc <?= $m['id']==$medico_id?'active':'' ?>">
                    <img src="<?= htmlspecialchars($m['foto']) ?>">
                    <span><?= htmlspecialchars(explode(' ', $m['nome'])[0]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if($medico_atual): ?>
            <div style="text-align:center; padding:15px;">
                <h2 style="margin:0; font-size:18px;">Dr(a). <?= htmlspecialchars($medico_atual['nome']) ?></h2>
                <small style="color:#007bff; font-weight:bold;"><?= htmlspecialchars($medico_atual['especialidade']) ?></small>
            </div>

            <div class="calendar">
                <?php foreach($datas_disp as $d): 
                    $active = ($d == $data_sel) ? 'active' : '';
                    $ts = strtotime($d);
                ?>
                    <a href="?medico_id=<?= $medico_id ?>&data=<?= $d ?>" class="day <?= $active ?>">
                        <small><?= $dias_pt[date('D', $ts)] ?></small>
                        <strong><?= date('d', $ts) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>

            <div style="padding-bottom: 30px;">
                <?php foreach($horarios as $h): ?>
                    <div class="slot">
                        <strong><?= htmlspecialchars($h['hora_agenda']) ?></strong>
                        <a href="https://wa.me/<?= $zap ?>?text=Agendamento: <?= urlencode($medico_atual['nome']) ?> - <?= date('d/m', strtotime($data_sel)) ?> às <?= $h['hora_agenda'] ?>" target="_blank" class="btn-zap">AGENDAR</a>
                    </div>
                <?php endforeach; ?>
                <?php if(!$horarios) echo "<p style='text-align:center; color:#ccc; margin-top:50px'>Sem horários nesta data.</p>"; ?>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="text-align:center; padding:50px; color:red;">
        <h3>Sistema Offline</h3>
    </div>
<?php endif; ?>
</body>
</html>
