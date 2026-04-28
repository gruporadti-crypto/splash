<?php
// ==========================================
// 1. CONFIGURAÇÕES DE CONEXÃO (SUPABASE)
// ==========================================
$host     = 'aws-1-us-east-1.pooler.supabase.com'; 
$port     = '6543'; 
$dbname   = 'postgres';
$user     = 'postgres.dahxpbiljzhkaxwetjza'; 
$password = 'Xl2DbdCmESCLbSG5';

$db_ok = false;
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

// Configurações do WhatsApp e Traduções
$meu_whatsapp = "559930781040"; 
$dias_pt = ['Sun'=>'Dom', 'Mon'=>'Seg', 'Tue'=>'Ter', 'Wed'=>'Qua', 'Thu'=>'Qui', 'Fri'=>'Sex', 'Sat'=>'Sáb'];

if ($db_ok) {
    // 1. Busca todos os médicos
    $medicos = $db->query("SELECT * FROM medicos ORDER BY cliques DESC")->fetchAll();
    
    // 2. Define qual médico exibir (ou o primeiro da lista)
    $medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : ($medicos[0]['id'] ?? 0);

    // 3. Rastreio de Cliques (Só conta se mudar de médico)
    if (isset($_GET['medico_id'])) {
        $stmt_click = $db->prepare("UPDATE medicos SET cliques = cliques + 1 WHERE id = ?");
        $stmt_click->execute([$medico_id]);
    }

    // 4. Busca Promoção Ativa
    $promo = $db->query("SELECT * FROM promocoes WHERE ativa = 1 LIMIT 1")->fetch();

    // 5. Busca Datas que têm horários disponíveis para este médico
    $hoje = date('Y-m-d');
    $stmt_datas = $db->prepare("SELECT DISTINCT data_agenda FROM agenda WHERE medico_id = ? AND data_agenda >= ? ORDER BY data_agenda ASC");
    $stmt_datas->execute([$medico_id, $hoje]);
    $datas_disponiveis = $stmt_datas->fetchAll(PDO::FETCH_COLUMN);

    // 6. Define data selecionada
    $data_sel = $_GET['data'] ?? ($datas_disponiveis[0] ?? $hoje);

    // 7. Busca horários para o médico e data escolhida
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
    <title>Agenda Clínica Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #f8f9fa; display: flex; justify-content: center; color: #333; }
        .app { width: 100%; max-width: 500px; background: white; min-height: 100vh; position: relative; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
        
        /* Promoção Popup */
        .promo-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .promo-box { width: 100%; max-width: 350px; text-align: center; }
        .promo-box img { width: 100%; border-radius: 20px; border: 3px solid #fff; }
        .btn-close { margin-top: 20px; padding: 15px; width: 100%; background: #007bff; color: white; border: none; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 16px; }

        /* Navegação de Médicos (Carrossel) */
        .doctor-nav { display: flex; overflow-x: auto; padding: 20px 15px; gap: 15px; border-bottom: 1px solid #f0f0f0; scrollbar-width: none; }
        .doctor-nav::-webkit-scrollbar { display: none; }
        .doc { min-width: 80px; text-align: center; text-decoration: none; color: #333; opacity: 0.5; transition: 0.3s; }
        .doc.active { opacity: 1; transform: scale(1.1); }
        .doc img { width: 65px; height: 65px; border-radius: 50%; object-fit: cover; border: 3px solid transparent; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .doc.active img { border-color: #007bff; }
        .doc span { font-size: 11px; font-weight: 600; display: block; margin-top: 8px; }

        /* Calendário de Datas */
        .calendar { display: flex; overflow-x: auto; padding: 15px; gap: 10px; background: #fafafa; scrollbar-width: none; }
        .calendar::-webkit-scrollbar { display: none; }
        .day { min-width: 65px; padding: 15px 5px; background: white; border: 1px solid #eee; border-radius: 18px; text-align: center; text-decoration: none; color: #444; }
        .day.active { background: #007bff; color: white; border-color: #007bff; box-shadow: 0 5px 15px rgba(0,123,255,0.3); }
        .day small { font-size: 10px; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .day strong { font-size: 18px; }

        /* Lista de Horários */
        .slots-container { padding: 20px; }
        .slot { margin-bottom: 12px; padding: 15px 20px; border: 1px solid #f0f0f0; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .slot strong { font-size: 20px; color: #222; }
        .btn-zap { background: #25d366; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 14px; font-weight: bold; }

        .header-dr { text-align: center; padding: 20px 20px 10px; }
        .header-dr h2 { margin: 0; font-size: 22px; color: #000; }
        .header-dr p { color: #007bff; margin: 5px 0 0; font-size: 14px; font-weight: 600; }

        .empty { text-align: center; color: #bbb; margin-top: 50px; font-size: 14px; }
    </style>
</head>
<body>

<?php if ($db_ok): ?>

    <!-- POPUP DE PROMOÇÃO (Se estiver ativa) -->
    <?php if ($promo && !isset($_GET['medico_id'])): ?>
    <div class="promo-overlay" id="pop">
        <div class="promo-box">
            <img src="<?= htmlspecialchars($promo['foto']) ?>">
            <button class="btn-close" onclick="document.getElementById('pop').style.display='none'">VER HORÁRIOS DISPONÍVEIS</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="app">
        <!-- Navegação de Médicos -->
        <div class="doctor-nav">
            <?php foreach($medicos as $m): ?>
                <a href="?medico_id=<?= $m['id'] ?>" class="doc <?= $m['id'] == $medico_id ? 'active' : '' ?>">
                    <img src="<?= htmlspecialchars($m['foto']) ?>">
                    <span><?= htmlspecialchars(explode(' ', $m['nome'])[0]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if($medico_atual): ?>
            <!-- Nome do Médico Selecionado -->
            <div class="header-dr">
                <h2>Dr(a). <?= htmlspecialchars($medico_atual['nome']) ?></h2>
                <p><?= htmlspecialchars($medico_atual['especialidade']) ?></p>
            </div>

            <!-- Calendário de Datas -->
            <div class="calendar">
                <?php foreach($datas_disponiveis as $d): 
                    $active = ($d == $data_sel) ? 'active' : '';
                    $ts = strtotime($d);
                ?>
                    <a href="?medico_id=<?= $medico_id ?>&data=<?= $d ?>" class="day <?= $active ?>">
                        <small><?= $dias_pt[date('D', $ts)] ?></small>
                        <strong><?= date('d', $ts) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Lista de Horários -->
            <div class="slots-container">
                <p style="font-size: 11px; color: #aaa; font-weight: bold; text-transform: uppercase; margin-bottom: 15px;">
                    Horários para <?= date('d/m', strtotime($data_sel)) ?>
                </p>
                
                <?php foreach($horarios as $h): ?>
                    <div class="slot">
                        <strong><?= htmlspecialchars($h['hora_agenda']) ?></strong>
                        <?php 
                            $msg = "Olá! Gostaria de agendar um horário com Dr(a) " . $medico_atual['nome'] . " para o dia " . date('d/m', strtotime($data_sel)) . " às " . $h['hora_agenda'];
                        ?>
                        <a href="https://wa.me/<?= $meu_whatsapp ?>?text=<?= urlencode($msg) ?>" target="_blank" class="btn-zap">AGENDAR</a>
                    </div>
                <?php endforeach; ?>

                <?php if(!$horarios): ?>
                    <div class="empty">Não há horários disponíveis para esta data.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty">Nenhum médico cadastrado no momento.</div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div style="padding: 50px; text-align: center;">
        <h3>⚠️ Sistema em Manutenção</h3>
        <p>Estamos atualizando nossos serviços. Por favor, tente novamente em instantes.</p>
    </div>
<?php endif; ?>

</body>
</html>
