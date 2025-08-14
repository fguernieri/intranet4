<?php
// Add this at the top of the file, after the opening PHP tag
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST received in admin.php: ' . print_r($_POST, true));
}
?>
<?php
// Remove these debug lines
// error_log('PDO in view: ' . print_r($pdo, true));
// error_log('POST data: ' . print_r($_POST, true));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Admin - Sistema de Senhas</title>
    <link rel="stylesheet" href="/php-mysql-app/public/assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        .admin-controls {
            background: white;
            padding: 2rem 2rem 2rem 2rem;
            margin: 0; /* Remova a margem inferior */
            border-radius: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-controls-left {
            flex: 1;
        }
        .admin-logo {
            height: 130px;  /* Aumentado de 50px para 80px */
            width: auto;
            position: absolute;
            right: 3rem;
            margin-top: -15px; /* metade da altura para centralizar */
            z-index: 1; /* garante que fique sobre outros elementos */
        }
        .admin-controls form {
            display: flex;
            gap: 1rem;
        }
        .admin-controls input[type="text"] {
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            flex: 1;
            max-width: 200px;
        }
        .btn {
            padding: 0.3rem 0.8rem;   /* um pouco maior */
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;        /* um pouco maior */
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .display-link {
            display: inline-block;
            margin-top: 1rem;
            color: #3498db;
            text-decoration: none;
        }
        .display-link:hover {
            text-decoration: underline;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Cada coluna ocupa metade da tela */
            gap: 2rem;
            padding: 0;
            margin: 0;
            width: 100vw; /* Ocupa toda a largura da tela */
            box-sizing: border-box;
        }
        .orders {
            background: white;
            border-radius: 0; /* Sem arredondamento */
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%; /* Ocupa toda a coluna */
            min-width: 0; /* Permite encolher ao máximo */
            height: calc(100vh - 300px);
            overflow-y: auto;
        }
        .orders h2 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
            font-size: 1.5rem;
        }
        .order-item {
            margin: 0.1rem 0;
            padding: 0.15rem 0.4rem;
            border-radius: 2px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: unset;
        }
        .preparing {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .ready {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .alert {
            background: #ff4444 !important;
            color: white !important;
        }
        .ticket-number {
            font-size: 0.7rem;    /* menor ainda */
            font-weight: 600;
        }
        .time-info {
            font-size: 0.65rem;
            margin-top: 0;
        }
        .actions {
            display: flex;
            gap: 0.1rem;
        }
        .error-message {
            background: #fee;
            color: #c0392b;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #c0392b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistema de Gerenciamento de Senhas</h1>
    </div>
    
    <div class="admin-controls">
        <div class="admin-controls-left">
            <div id="alertMessage" style="display: none; color: #c0392b; background: #fee; padding: 10px; border-radius: 4px; margin-bottom: 10px; border-left: 4px solid #c0392b;"></div>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Agrupe o form e a pesquisa em um flex container -->
            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                <!-- Form de criar senha -->
                <form id="createForm" method="post" style="display: flex; gap: 1rem; margin: 0;">
                    <input type="hidden" name="action" value="create">
                    <input type="text" name="ticket_number" placeholder="Digite a senha" required>
                    <button type="submit" class="btn btn-primary">Inserir</button>
                </form>
                <!-- Caixa de pesquisa movida para cá -->
                <input type="text" id="searchBox" placeholder="Pesquisar senha..." style="width: 200px; padding: 0.7rem; border-radius: 4px; border: 1px solid #ccc;">
            </div>

            <a href="/php-mysql-app/public/display.php" target="_blank" class="display-link">
                Abrir Tela de Exibição ↗
            </a>
            <a href="/php-mysql-app/public/reservations.php" target="_blank" class="display-link" style="margin-left: 20px;">
                Gerenciar Reservas ↗
            </a>
        </div>
        <img src="/php-mysql-app/assets/img/logobdf1.png" alt="Logo BDF" class="admin-logo">
    </div>

    <div class="container" id="ordersContainer">
        <div class="orders">
            <h2>Em Preparo</h2>
            <?php if(!empty($orders)): ?>
                <?php foreach($orders as $order): ?>
                    <?php if($order['status'] === 'preparing'): ?>
                        <div class="order-item preparing">
                            <div>
                                <span class="ticket-number">
                                    Senha: <?= htmlspecialchars($order['ticket_number']) ?>
                                </span>
                                <div class="time-info">
                                    Tempo: <?= $order['preparing_time'] ?> minutos
                                </div>
                            </div>
                            <div class="actions">
                                <!-- Botão de Pronto (em cada item em preparo) -->
                                <form method="post" style="display: inline">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($order['id']) ?>">
                                    <button type="submit" class="btn-status">Pronto</button>
                                </form>
                                <form method="post" style="display: inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="btn btn-danger">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="orders">
            <h2>Pronto</h2>
            <?php if(!empty($orders)): ?>
                <?php foreach($orders as $order): ?>
                    <?php if($order['status'] === 'ready'): ?>
                        <div class="order-item ready <?= $order['ready_time'] >= 5 ? 'alert' : '' ?>">
                            <div>
                                <span class="ticket-number">
                                    Senha: <?= htmlspecialchars($order['ticket_number']) ?>
                                </span>
                                <div class="time-info">
                                    Tempo: <?= $order['ready_time'] ?> minutos
                                </div>
                            </div>
                            <div class="actions">
                                <!-- Botão de Entregue (em cada item pronto) -->
                                <form method="post" style="display: inline">
                                    <input type="hidden" name="action" value="deliver">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($order['id']) ?>">
                                    <button type="submit" class="btn-deliver">Entregue</button>
                                </form>
                                <form method="post" style="display: inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="btn btn-danger">Excluir</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div> <!-- fim do container -->

    <script>
    $(document).ready(function() {
        // Function to refresh orders
        function refreshOrders() {
            $.ajax({
                url: '/php-mysql-app/public/index.php',
                type: 'GET',
                data: { ajax: true },
                success: function(response) {
                    $('#ordersContainer').html(response);
                }
            });
        }

        // Initial load
        refreshOrders();

        // Form submission
        $('#createForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: '/php-mysql-app/public/index.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    if (response.error) {
                        alert(response.error);
                    } else {
                        refreshOrders();
                        $('#createForm')[0].reset();
                    }
                }
            });
        });

        // Button actions (using event delegation)
        $(document).on('click', '.btn-status, .btn-deliver', function(e) {
            e.preventDefault();
            const form = $(this).closest('form');
            $.ajax({
                url: '/php-mysql-app/public/index.php',
                type: 'POST',
                data: form.serialize(),
                success: function() {
                    refreshOrders();
                }
            });
        });

        // Filtro de pesquisa
        $('#searchBox').on('input', function() {
            var search = $(this).val().toLowerCase();
            $('.order-item').each(function() {
                var text = $(this).text().toLowerCase();
                if (text.indexOf(search) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Auto refresh every 5 seconds
        setInterval(refreshOrders, 5000);
    });
    </script>
</body>
</html>