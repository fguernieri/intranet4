<div class="reservations-list">
    <?php foreach ($nextDays as $date => $info): ?>
        <?php if (!empty($info['reservations'])): ?>
            <div class="date-reservations">
                <h3><?= date('d/m/Y (D)', strtotime($date)) ?></h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Pessoas</th>
                            <th>Telefone</th>
                            <th>Observações</th>
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
                                        <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>