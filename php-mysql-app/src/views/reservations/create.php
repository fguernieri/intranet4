<form method="post" class="reservation-form">
    <input type="hidden" name="action" value="create">
    
    <div class="form-group">
        <label for="date">Data da Reserva</label>
        <input type="date" 
               id="date" 
               name="date" 
               required 
               min="<?= date('Y-m-d') ?>"
               class="form-control">
    </div>

    <div class="form-group">
        <label for="customer_name">Nome do Cliente</label>
        <input type="text" 
               id="customer_name" 
               name="customer_name" 
               required 
               class="form-control">
    </div>

    <div class="form-group">
        <label for="number_of_people">Número de Pessoas</label>
        <input type="number" 
               id="number_of_people" 
               name="number_of_people" 
               required 
               min="1" 
               class="form-control">
    </div>

    <div class="form-group">
        <label for="phone">Telefone</label>
        <input type="tel" 
               id="phone" 
               name="phone" 
               required 
               class="form-control">
    </div>

    <div class="form-group">
        <label for="notes">Observações</label>
        <textarea id="notes" 
                  name="notes" 
                  class="form-control" 
                  rows="3"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Criar Reserva</button>
</form>