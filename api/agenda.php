<?php
// --- CONFIGURAÇÕES DE CONEXÃO SUPABASE ---
$host     = '://xzemserhahccodubenfj.supabase.co';
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.xzemserhahccodubenfj';
$password = 'oJxh3BlVcVIuRIW1';

try {
    // String de conexão para PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { 
    // Em produção, você pode trocar o $e->getMessage() por uma frase genérica
    die("Erro ao conectar ao banco de dados: " . $e->getMessage()); 
}

// --- RASTREIO DE CLIQUES ---
if (isset($_GET['medico_id'])) {
    // No PostgreSQL, garantimos que o ID seja tratado como inteiro
    $stmt_click = $db->prepare("UPDATE medicos SET cliques = cliques + 1 WHERE id = ?");
    $stmt_click->execute([(int)$_GET['medico_id']]);
}

$zap = "559930781040";
$dias_pt = ['Sun'=>'Dom', 'Mon'=>'Seg', 'Tue'=>'Ter', 'Wed'=>'Qua', 'Thu'=>'Qui', 'Fri'=>'Sex', 'Sat'=>'Sáb'];
$meses_pt = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

// Busca Médicos
$medicos = $db->query("SELECT * FROM medicos ORDER BY id ASC")->fetchAll();
$medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : ($medicos[0]['id'] ?? 0);

// Busca Promoção
$promo = $db->query("SELECT * FROM promocoes WHERE ativa = 1 LIMIT 1")->fetch();

// Busca apenas datas que possuem horários livres futuros
$datas_disp = [];
if ($medico_id) {
    $hoje = date('Y-m-d');
    // PostgreSQL usa aspas simples para strings
    $stmt = $db->prepare("SELECT DISTINCT data_agenda FROM agenda WHERE medico_id = ? AND status = 'disponivel' AND data_agenda >= ? ORDER BY data_agenda ASC");
    $stmt->execute([$medico_id, $hoje]);
    $datas_disp = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$data_sel = $_GET['data'] ?? ($datas_disp[0] ?? date('Y-m-d'));
$medico_atual = null;
$horarios = [];

if ($medico_id) {
    foreach($medicos as $m) {
        if($m['id'] == $medico_id) {
            $medico_atual = $m;
            break;
        }
    }
    
    $stmt = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? AND status = 'disponivel' ORDER BY hora_agenda ASC");
    $stmt->execute([$medico_id, $data_sel]);
    $horarios = $stmt->fetchAll();
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
        /* O seu CSS permanece exatamente o mesmo */
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #f8f9fa; display: flex; justify-content: center; }
        .app { width: 100%; max-width: 500px; background: white; min-height: 100vh; }
        .promo-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .promo-box { width: 100%; max-width: 350px; text-align: center; }
        .promo-box img { width: 100%; border-radius: 15px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        .btn-close { margin-top: 20px; padding: 12px; width: 100%; background: #007bff; color: white; border: none; border-radius: 30px; font-weight: bold; cursor: pointer; }
        .doctor-nav { display: flex; overflow-x: auto; padding: 15px; gap: 15px; border-bottom: 1px solid #eee; scrollbar-width: none; }
        .doctor-nav::-webkit-scrollbar { display: none; }
        .doc { min-width: 75px; text-align: center; text-decoration: none; color: #333; opacity: 0.4; transition: 0.3s; }
        .doc.active { opacity: 1; transform: scale(1.05); }
        .doc img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid transparent; }
        .doc.active img { border-color: #007bff; }
        .doc span { font-size: 0.7rem; font-weight: 600; display: block; margin-top: 5px; }
        .calendar { display: flex; overflow-x: auto; padding: 15px; gap: 10px; background: #fafafa; scrollbar-width: none; }
        .calendar::-webkit-scrollbar { display: none; }
        .day { min-width: 60px; padding: 12px 5px; background: white; border: 1px solid #eee; border-radius: 15px; text-align: center; text-decoration: none; color: #333; }
        .day.active { background: #007bff; color: white; border-color: #007bff; box-shadow: 0 5px 12px rgba(0,123,255,0.2); }
        .day small { font-size: 0.6rem; text-transform: uppercase; font-weight: 600; display: block; }
        .slot { margin: 15px; padding: 18px; border: 1px solid #f0f0f0; border-radius: 18px; display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .btn-zap { background: #007bff; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: bold; }
        .header-dr { text-align: center; padding: 20px; }
        .header-dr h2 { margin: 0; font-size: 1.3rem; }
        .header-dr p { color: #888; margin: 0; font-size: 0.85rem; }
    </style>
</head>
<body>

<?php if ($promo && !isset($_GET['nopro'])): ?>
<div class="promo-overlay" id="pop">
    <div class="promo-box">
        <img src="<?= htmlspecialchars($promo['foto']) ?>">
        <button class="btn-close" onclick="document.getElementById('pop').style.display='none'">VER AGENDA COMPLETA</button>
    </div>
</div>
<?php endif; ?>

<div class="app">
    <div class="doctor-nav">
        <?php foreach($medicos as $m): ?>
            <a href="?medico_id=<?= $m['id'] ?>&nopro=1" class="doc <?= $m['id']==$medico_id?'active':'' ?>">
                <img src="<?= htmlspecialchars($m['foto']) ?>">
                <span><?= htmlspecialchars(explode(' ', $m['nome'])[0]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if($medico_atual): ?>
        <div class="header-dr">
            <h2>Dr(a). <?= htmlspecialchars($medico_atual['nome']) ?></h2>
            <p><?= htmlspecialchars($medico_atual['especialidade']) ?></p>
        </div>

        <div class="calendar">
            <?php foreach($datas_disp as $d): 
                $active = ($d == $data_sel) ? 'active' : '';
                $ts = strtotime($d);
            ?>
                <a href="?medico_id=<?= $medico_id ?>&data=<?= $d ?>&nopro=1" class="day <?= $active ?>">
                    <small><?= $dias_pt[date('D', $ts)] ?></small>
                    <strong><?= date('d', $ts) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="padding-bottom: 40px;">
            <p style="padding-left:20px; font-size:0.7rem; color:#aaa; font-weight:bold; text-transform:uppercase;">Horários para <?= date('d/m', strtotime($data_sel)) ?></p>
            <?php foreach($horarios as $h): ?>
                <div class="slot">
                    <strong style="font-size:1.2rem; color:#444"><?= htmlspecialchars($h['hora_agenda']) ?></strong>
                    <a href="https://wa.me/<?= $zap ?>?text=Olá! Gostaria de agendar com <?= urlencode($medico_atual['nome']) ?> dia <?= date('d/m', strtotime($data_sel)) ?> às <?= $h['hora_agenda'] ?>" class="btn-zap">AGENDAR</a>
                </div>
            <?php endforeach; ?>
            <?php if(!$horarios) echo "<p style='text-align:center; color:#ccc; margin-top:50px'>Sem horários livres nesta data.</p>"; ?>
        </div>
    <?php else: ?>
        <p style="text-align:center; padding-top:100px; color:#ccc">Nenhum profissional cadastrado.</p>
    <?php endif; ?>
</div>
</body>
</html>
