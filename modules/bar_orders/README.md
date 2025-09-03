Bar Orders module
=================

Purpose
-------
Small module to let managers submit bar orders and persist them to a Supabase table.

Files
-----
- `index.php` - select which bar to create an order for
- `order.php` - order form (fetches products from Supabase `insumos` table)
- `save_order.php` - endpoint that writes rows to Supabase `orders` table via REST
- `config.example.php` - example config. Copy to `config.php` and fill credentials

Supabase setup
--------------
Create a table (SQL example) named `orders` in Supabase with these columns:

DATA timestamptz default now()
PRODUTO text
UND text
QTDE numeric
OBSERVACAO text
NUMERO_PEDIDO text
filial text
usuario text

Example SQL (run in SQL editor):

-- create table orders
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

Security
--------
This module uses the Supabase REST API. For server-side inserts use a service_role key or another server-side key with insert privileges. Keep it out of version control.

Deployment
----------
1. Copy `config.example.php` to `modules/bar_orders/config.php` and set `SUPABASE_URL` and `SUPABASE_KEY`.
2. Create the orders table in Supabase as above.
3. Visit `/modules/bar_orders/index.php` on your intranet.
