-- SQL to create the orders table used by this module
create table if not exists orders (
  id bigserial primary key,
  data timestamp default now(),
  produto text,
  und text,
  qtde numeric,
  observacao text,
  numero_pedido text,
  filial text,
  usuario text
);
