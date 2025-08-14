<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Display - Sistema de Senhas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            height: 100vh;
            display: grid;
            grid-template-columns: 50% 50%;
            overflow: hidden;
        }

        .column {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 1rem;
        }

        .preparing { background: #2c3e50; color: white; }
        .ready { background: #27ae60; color: white; }

        .title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            width: 98%;
            justify-items: center;
        }

        .number-container {
            text-align: center;
            padding: 0.3rem;
        }

        .number {
            font-size: 2.2rem;
            font-weight: bold;
            padding: 0.3rem;
        }

        .timer {
            font-size: 0.8rem;
            margin-top: 0.1rem;
            opacity: 0.8;
        }

        .alert {
            animation: blink 1s infinite;
            background-color: #e74c3c;
            border-radius: 4px;
        }

        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="column preparing">
        <h1 class="title">EM PRODUÇÃO</h1>
        <div class="numbers-grid">
            <?php if (!array_filter($orders, fn($o) => $o['status'] === 'preparing')): ?>
                <div class="empty-message"></div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php if ($order['status'] === 'preparing'): ?>
                        <div class="number-container">
                            <div class="number"><?= htmlspecialchars($order['ticket_number']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="column ready">
        <h1 class="title">RANGO PRONTO</h1>
        <div class="numbers-grid">
            <?php if (!array_filter($orders, fn($o) => $o['status'] === 'ready')): ?>
                <div class="empty-message"></div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php if ($order['status'] === 'ready'): ?>
                        <?php 
                            $readyTime = (int)$order['ready_time'];
                            $alertClass = $readyTime >= 5 ? 'alert' : '';
                        ?>
                        <div class="number-container">
                            <div class="number <?= $alertClass ?>"><?= htmlspecialchars($order['ticket_number']) ?></div>
                            <div class="timer"><?= $readyTime ?> min</div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function refreshDisplay() {
            $.get(window.location.href + '?ajax=true', function(response) {
                document.body.innerHTML = response;
            });
        }
        setInterval(refreshDisplay, 5000);
    </script>
</body>
</html>