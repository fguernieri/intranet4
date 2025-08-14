<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Reservas</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/php-mysql-app/assets/css/reservations.css">
</head>
<body>
    <div class="header">
        <h1>Controle de Reservas</h1>
        <a href="/php-mysql-app/src/views/admin.php" class="nav-link">← Voltar para Senhas</a>
    </div>

    <div class="container">
        <!-- Formulário à esquerda -->
        <div class="form-section">
            <div class="reservation-form">
                <h2>Nova Reserva</h2>
                <?php include ROOT_PATH . '/src/views/reservations/create.php'; ?>
            </div>
        </div>

        <!-- Conteúdo principal à direita -->
        <div class="main-content">
            <?php if (!empty($error)): ?>
                <div class="message error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="message success-message"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Grid de Disponibilidade -->
            <div class="availability-section">
                <h2>Disponibilidade</h2>
                
                <!-- Adicione os botões de filtro -->
                <div class="filter-section">
                    <button class="filter-button <?= empty($_GET['period']) || $_GET['period'] == 'month' ? 'active' : '' ?>" 
                            onclick="window.location.href='?period=month'">
                        Mês Atual
                    </button>
                    <button class="filter-button <?= $_GET['period'] == '60days' ? 'active' : '' ?>" 
                            onclick="window.location.href='?period=60days'">
                        60 Dias
                    </button>
                </div>

                <div class="availability-grid">
                    <?php 
                    $diasSemana = [
                        'Sun' => 'Dom',
                        'Mon' => 'Seg',
                        'Tue' => 'Ter',
                        'Wed' => 'Qua',
                        'Thu' => 'Qui',
                        'Fri' => 'Sex',
                        'Sat' => 'Sáb'
                    ];

                    foreach ($nextDays as $date => $info): 
                        $showDate = true;
                        if (empty($_GET['period']) || $_GET['period'] == 'month') {
                            $showDate = date('m/Y', strtotime($date)) == date('m/Y');
                        }
                        
                        if ($showDate):
                            $dataObj = new DateTime($date);
                            $dia = $dataObj->format('d/m');
                            $diaSemana = $diasSemana[$dataObj->format('D')];
                    ?>
                        <div class="date-card <?= $info['available'] <= 0 ? 'full' : '' ?>">
                            <div class="date-header">
                                <?= $dia ?> (<?= $diaSemana ?>)
                            </div>
                            <div class="date-info">
                                <p>Reservado: <?= $info['reserved'] ?></p>
                                <p>Disponível: <?= $info['available'] ?></p>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" 
                                     style="width: <?= min(100, ($info['reserved'] / $info['max_with_margin']) * 100) ?>%">
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Lista de Reservas -->
            <div class="reservations-list">
                <h2>Reservas Confirmadas</h2>
                <?php if (!empty($nextDays)): ?>
                    <?php foreach ($nextDays as $date => $info): ?>
                        <?php if (!empty($info['reservations'])): ?>
                            <div class="date-reservations">
                                <h3><?= (new DateTime($date))->format('d/m/Y') ?> - <?= $diasSemana[(new DateTime($date))->format('D')] ?></h3>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Pessoas</th>
                                            <th>Telefone</th>
                                            <th>Obs</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($info['reservations'] as $reservation): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($reservation['customer_name']) ?></td>
                                                <td><?= $reservation['number_of_people'] ?></td>
                                                <td><?= htmlspecialchars($reservation['phone']) ?></td>
                                                <td><?= htmlspecialchars($reservation['notes'] ?? '') ?></td>
                                                <td>
                                                    <form method="post" style="display: inline">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="id" value="<?= $reservation['id'] ?>">
                                                        <button type="submit" class="btn btn-danger">Cancelar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Nenhuma reserva encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>