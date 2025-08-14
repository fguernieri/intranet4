<style>
    #ordersContainer {
        display: flex;
        gap: 2rem;
        padding: 1rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .orders {
        flex: 1;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .orders h2 {
        color: #333;
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #dee2e6;
    }

    .order-item {
        background: white;
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .ticket-number {
        font-size: 1.25rem;
        font-weight: bold;
        color: #495057;
    }

    .time {
        color: #6c757d;
        margin: 0 1rem;
    }

    .btn-status, .btn-deliver {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-status {
        background-color: #28a745;
        color: white;
    }

    .btn-deliver {
        background-color: #007bff;
        color: white;
    }

    .btn-status:hover {
        background-color: #218838;
        transform: translateY(-1px);
    }

    .btn-deliver:hover {
        background-color: #0056b3;
        transform: translateY(-1px);
    }

    .empty-message {
        text-align: center;
        color: #6c757d;
        padding: 1rem;
        background: #e9ecef;
        border-radius: 4px;
        margin-top: 1rem;
    }

    @media (max-width: 768px) {
        #ordersContainer {
            flex-direction: column;
        }
        
        .orders {
            margin-bottom: 1rem;
        }
    }
</style>

<div id="ordersContainer">
    <div class="orders preparing">
        <h2>Em Preparo</h2>
        <div class="orders-list">
            <?php if (empty($orders) || !array_filter($orders, fn($o) => $o['status'] === 'preparing')): ?>
                <div class="empty-message">Nenhum pedido em preparo</div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php if ($order['status'] === 'preparing'): ?>
                        <div class="order-item">
                            <span class="ticket-number"><?= htmlspecialchars($order['ticket_number']) ?></span>
                            <span class="time"><?= $order['preparing_time'] ?>min</span>
                            <div class="actions">
                                <form method="post" class="action-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="btn btn-success">Pronto</button>
                                </form>
                                <form method="post" class="action-form">
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
    </div>

    <div class="orders ready">
        <h2>Pronto</h2>
        <div class="orders-list">
            <?php if (empty($orders) || !array_filter($orders, fn($o) => $o['status'] === 'ready')): ?>
                <div class="empty-message">Nenhum pedido pronto</div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php if ($order['status'] === 'ready'): ?>
                        <div class="order-item">
                            <span class="ticket-number"><?= htmlspecialchars($order['ticket_number']) ?></span>
                            <span class="time"><?= $order['ready_time'] ?>min</span>
                            <form method="post">
                                <input type="hidden" name="action" value="deliver">
                                <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn-deliver">Entregue</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>