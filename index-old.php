<?php
// 1. CONFIGURAÇÃO DO BANCO DE DADOS (SQLite)
$db = new PDO('sqlite:agenda_medica.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Criar pasta de uploads se não existir
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// Criar tabelas
$db->exec("CREATE TABLE IF NOT EXISTS medicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT,
    especialidade TEXT,
    foto TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS agenda (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    medico_id INTEGER,
    data_agenda DATE,
    hora_agenda TEXT,
    status TEXT DEFAULT 'disponivel'
)");

// 2. LÓGICA DO ADMINISTRADOR
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // CADASTRAR MÉDICO COM FOTO
    if (isset($_POST['add_medico'])) {
        $nome = $_POST['nome'];
        $especialidade = $_POST['especialidade'];
        $foto_nome = "default.png"; // Foto padrão caso não envie nada

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $novo_nome = time() . '.' . $extensao; // Nome único para não sobrescrever
            $destino = 'uploads/' . $novo_nome;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $foto_nome = $destino;
            }
        }

        $stmt = $db->prepare("INSERT INTO medicos (nome, especialidade, foto) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $especialidade, $foto_nome]);
    }

    // ADICIONAR HORÁRIO NA AGENDA
    if (isset($_POST['add_agenda'])) {
        $stmt = $db->prepare("INSERT INTO agenda (medico_id, data_agenda, hora_agenda) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['medico_id'], $_POST['data'], $_POST['hora']]);
    }
}

// 3. BUSCAR DADOS
$medicos = $db->query("SELECT * FROM medicos")->fetchAll(PDO::FETCH_ASSOC);
$medico_selecionado_id = $_GET['medico_id'] ?? ($medicos[0]['id'] ?? 0);
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

$medico_atual = null;
$agenda = [];

if ($medico_selecionado_id) {
    foreach ($medicos as $m) {
        if ($m['id'] == $medico_selecionado_id) { $medico_atual = $m; break; }
    }
    $stmt = $db->prepare("SELECT * FROM agenda WHERE medico_id = ? AND data_agenda = ? ORDER BY hora_agenda ASC");
    $stmt->execute([$medico_selecionado_id, $data_selecionada]);
    $agenda = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Médica Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --blue: #007bff; --bg: #f0f2f5; --white: #ffffff; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); margin: 0; display: flex; flex-wrap: wrap; justify-content: center; padding: 20px; gap: 40px; }
        
        /* ESTILO CELULAR */
        .mobile-view { width: 350px; height: 650px; background: var(--white); border-radius: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); overflow: hidden; display: flex; flex-direction: column; border: 10px solid #333; position: sticky; top: 20px; }
        .header { padding: 30px 20px; text-align: center; border-bottom: 1px solid #eee; }
        .profile-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid var(--blue); margin-bottom: 10px; background: #eee; }
        
        .calendar-nav { display: flex; overflow-x: auto; padding: 15px; gap: 10px; background: #fff; scrollbar-width: none; }
        .day-card { min-width: 55px; padding: 12px 5px; text-align: center; border-radius: 15px; background: #f8f9fa; cursor: pointer; text-decoration: none; color: #333; transition: 0.3s; }
        .day-card.active { background: var(--blue); color: white; box-shadow: 0 5px 15px rgba(0,123,255,0.3); }

        .slots { flex: 1; padding: 20px; overflow-y: auto; background: #fafafa; }
        .slot { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: white; margin-bottom: 12px; border-radius: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 5px solid var(--blue); }
        
        /* ESTILO PAINEL ADM */
        .admin-panel { width: 450px; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); height: fit-content; }
        h3 { margin-top: 0; color: #333; border-bottom: 2px solid var(--blue); padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: #666; }
        input, select, button { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        input[type="file"] { padding: 8px; background: #f9f9f9; }
        button { background: var(--blue); color: white; border: none; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.2s; }
        button:hover { background: #0056b3; }
        .btn-green { background: #28a745; }
        .btn-green:hover { background: #218838; }
        hr { border: 0; border-top: 1px solid #eee; margin: 25px 0; }
    </style>
</head>
<body>

    <!-- VISÃO DO CELULAR -->
    <div class="mobile-view">
        <?php if ($medico_atual): ?>
            <div class="header">
                <img src="<?= $medico_atual['foto'] ?>" class="profile-img" alt="Foto">
                <h2 style="margin:0; font-size: 1.2rem;">Dra. <?= htmlspecialchars($medico_atual['nome']) ?></h2>
                <p style="color:gray; margin:5px 0 0 0; font-size: 0.9rem;"><?= htmlspecialchars($medico_atual['especialidade']) ?></p>
            </div>

            <div class="calendar-nav">
                <?php for($i=0; $i<7; $i++): 
                    $d = date('Y-m-d', strtotime("+$i days"));
                    $active = ($d == $data_selecionada) ? 'active' : '';
                ?>
                    <a href="?medico_id=<?= $medico_selecionado_id ?>&data=<?= $d ?>" class="day-card <?= $active ?>">
                        <span style="font-size: 0.7rem; opacity: 0.8;"><?= date('D', strtotime($d)) ?></span><br>
                        <strong><?= date('d', strtotime($d)) ?></strong>
                    </a>
                <?php endfor; ?>
            </div>

            <div class="slots">
                <p style="font-size: 0.8rem; font-weight: bold; color: #999; margin-bottom: 15px;">DISPONÍVEL EM <?= date('d/m/Y', strtotime($data_selecionada)) ?></p>
                <?php if ($agenda): foreach($agenda as $h): ?>
                    <div class="slot">
                        <strong style="font-size: 1.1rem; color: #444;"><?= $h['hora_agenda'] ?></strong>
                        <span style="color: var(--blue); font-size: 0.8rem; font-weight: 600;">Agendar</span>
                    </div>
                <?php endforeach; else: ?>
                    <div style="text-align:center; margin-top: 40px;">
                        <p style="color:#ccc; font-size: 0.9rem;">Sem horários para hoje.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="padding:100px 20px; text-align:center; color: #ccc;">
                <h3>Bem-vindo!</h3>
                <p>Cadastre um médico no painel ao lado para começar.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- PAINEL ADMINISTRADOR -->
    <div class="admin-panel">
        <h3>⚙️ Gestão da Clínica</h3>
        
        <!-- Formulário de Médico -->
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>NOVO MÉDICO</label>
                <input type="text" name="nome" placeholder="Nome Completo" required>
                <input type="text" name="especialidade" placeholder="Ex: Pediatra, Cardiologista" required style="margin-top:10px">
                <label style="margin-top:10px">Foto do Médico:</label>
                <input type="file" name="foto" accept="image/*">
                <button type="submit" name="add_medico">Cadastrar Médico</button>
            </div>
        </form>

        <hr>

        <!-- Formulário de Agenda -->
        <form method="POST">
            <div class="form-group">
                <label>ADICIONAR HORÁRIO NA AGENDA</label>
                <select name="medico_id" required>
                    <option value="">Selecione o Médico...</option>
                    <?php foreach($medicos as $med): ?>
                        <option value="<?= $med['id'] ?>"><?= $med['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="display:flex; gap:10px; margin-top:10px">
                    <input type="date" name="data" required value="<?= date('Y-m-d') ?>">
                    <input type="time" name="hora" required>
                </div>
                <button type="submit" name="add_agenda" class="btn-green">Abrir Horário</button>
            </div>
        </form>

        <hr>

        <!-- Filtro de Visualização -->
        <div class="form-group">
            <label>VISUALIZAR AGENDA NO CELULAR:</label>
            <form method="GET">
                <select name="medico_id" onchange="this.form.submit()">
                    <?php foreach($medicos as $med): ?>
                        <option value="<?= $med['id'] ?>" <?= $med['id'] == $medico_selecionado_id ? 'selected' : '' ?>>
                            <?= $med['nome'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

</body>
</html>