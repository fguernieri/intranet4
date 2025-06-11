<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/sidebar.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard iFood</title>
    <style>
        .btn-acao {
  min-width: 90px;
  height: 33px;
  padding: 0 12px;
  border-radius: 0.25rem;
  font-weight: bold;
  text-align: center;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: #eab308; 
  color: #111827;            
  transition: background-color 0.2s;
}

.btn-acao:hover {
  background-color: #ca8a04;
}

.btn-acao-vermelho {
  min-width: 90px;
  height: 33px;
  padding: 0 12px;
  border-radius: 0.25rem;
  font-weight: bold;
  text-align: center;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: #dc2626; /* bg-red-600 */
  color: #ffffff;            /* white */
  transition: background-color 0.2s;
}

.btn-acao-vermelho:hover {
  background-color: #b91c1c; /* bg-red-700 */
}

.divider_yellow {
  margin-top: 1.5rem;
  margin-bottom: 1.5rem;
  border: none;
  border-top: 1px solid #eab308; /* cor semelhante ao yellow-500 do Tailwind */
}

.card1 {
  background-color: rgba(255, 255, 255, 0.05);
  border-radius: 0.75rem; /* rounded-xl */
  padding: 1rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: transform 0.1s ease-in-out;
}
.card1:hover {
  transform: scale(1.1);
}
.card1 p:first-child {
  font-size: 0.875rem; /* text-sm */
  color: #d1d5db;      /* gray-300 */
}
.card1 p:last-child {
  font-size: 1.5rem;   /* text-2xl */
  font-weight: bold;
}

card1.no-hover:hover {
  transform: none !important;
}

main {
  overflow-y: auto;
}


/* 1. Container do switch */
.custom-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}

/* 2. Checkbox “invisível” (sr-only) */
.custom-switch-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* 3. Track do switch */
.custom-switch-slider {
  width: 44px;              /* w-11 */
  height: 24px;             /* h-6 */
  background-color: #e5e7eb;/* bg-gray-200 */
  border-radius: 9999px;    /* rounded-full */
  position: relative;
  transition: background-color 0.2s;
}

/* 4. Thumb (a “bolinha”) */
.custom-switch-slider::after {
  content: "";
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;              /* h-5/w-5 */
  height: 20px;
  background-color: #ffffff;/* bg-white */
  border-radius: 9999px;    /* rounded-full */
  transition: transform 0.2s;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* 5. Estado ligado (checked) */
.custom-switch-input:checked + .custom-switch-slider {
  background-color: #2563eb;/* bg-blue-600 */
}
.custom-switch-input:checked + .custom-switch-slider::after {
  transform: translateX(20px);
}

/* 6. Foco (análogo ao ring) */
.custom-switch-input:focus + .custom-switch-slider {
  box-shadow: 0 0 0 4px rgba(147, 197, 253, 0.5); /* ring-blue-300 */
}

/* 7. Label de texto ao lado */
.custom-switch-label {
  margin-left: 0.75rem;     /* ml-3 */
  font-size: 0.875rem;      /* text-sm */
  font-weight: 500;         /* font-medium */
  color: #e5e7eb;           /* text-gray-200 */
}

/* 8. Modo escuro (prefers-color-scheme) */

@media (prefers-color-scheme: dark) {
  .custom-switch-slider {
    background-color: #374151;/* bg-gray-700 */
  }
  .custom-switch-input:checked + .custom-switch-slider {
    background-color: #2563eb;/* manter blue-600 */
  }
}

    .grafico-container {
        margin: 15px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 20px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 15px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 20px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 15px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 20px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 15px auto;
        padding: 15px;
    }
    
    .grafico-container {
        margin: 20px auto;
        padding: 15px;
    }
    
.grafico-container {
    padding-bottom: 40px !important;  /* Extra padding for legend */
}

/* Base table styles */
#matriz-ifood {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 20px 0;
    color: #000000;
    background: #ffffff;
    font-size: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

#matriz-ifood th {
    background-color: #f1f1f1;
    padding: 8px;
    text-align: left;
    font-weight: bold;
    border-bottom: 2px solid #ddd;
    border-right: 1px solid #ddd;
    font-size: 12px;
}

#matriz-ifood td {
    padding: 6px;
    border-bottom: 1px solid #ddd;
    border-right: 1px solid #ddd;
    font-size: 12px;
}

/* Remove right border from last column */
#matriz-ifood th:last-child,
#matriz-ifood td:last-child {
    border-right: none;
}

/* Promoções Loja */
#matriz-ifood td:nth-child(4), /* Valor Promoções */
#matriz-ifood td:nth-child(5), /* % Promoções */
#matriz-ifood th:nth-child(4),
#matriz-ifood th:nth-child(5) {
    background-color: #ef9a9a; /* Vermelho claro sólido (Promoções) */
}

/* Entrega Loja */
#matriz-ifood td:nth-child(6), /* Valor Entrega */
#matriz-ifood td:nth-child(7), /* % Entrega */
#matriz-ifood th:nth-child(6),
#matriz-ifood th:nth-child(7) { 
    background-color: #ffab91; /* Laranja claro sólido (Entrega) */
}

/* Comissões iFood */
#matriz-ifood td:nth-child(8), /* Valor Comissões */
#matriz-ifood td:nth-child(9), /* % Comissões */
#matriz-ifood th:nth-child(8),
#matriz-ifood th:nth-child(9) { 
    background-color: #ffb3ba; /* Rosa claro/suave sólido (Comissões) */
}

/* Total Custos iFood - bold only in header */
#matriz-ifood th:nth-child(10),  /* Valor Total Custos */
#matriz-ifood th:nth-child(11) { /* % Total Custos */ 
    background-color: #e57373; /* Vermelho médio sólido (Total Custos) */
    font-weight: bold;
}
#matriz-ifood td:nth-child(10),
#matriz-ifood td:nth-child(11) {
    background-color: #e57373; /* Vermelho médio sólido (Total Custos) */
    font-weight: normal;
}

/* Incentivos iFood - (newly styled pair, e.g. light blue) */
#matriz-ifood td:nth-child(12), /* Valor Incentivos */
#matriz-ifood td:nth-child(13), /* % Incentivos */
#matriz-ifood th:nth-child(12),
#matriz-ifood th:nth-child(13) { /* Changed to very light green */
    background-color: #e6ffe6; /* Original Faturamento Líquido green */
}

/* Faturamento Líquido - green background and bold (now correctly targeting Faturamento Líquido) */
#matriz-ifood td:nth-child(14), /* Valor Faturamento Líquido */
#matriz-ifood td:nth-child(15), /* % Faturamento Líquido */
#matriz-ifood th:nth-child(14),
#matriz-ifood th:nth-child(15) {
    background-color: #c8e6c9;  /* More vivid green - e.g., Material Design Green 100 */
    font-weight: bold;
}

/* Remove spacing between value and percentage pairs */
#matriz-ifood td:nth-child(4), /* Promoções Loja */
#matriz-ifood td:nth-child(6), /* Entrega Loja */
#matriz-ifood td:nth-child(8), /* Comissões iFood */
#matriz-ifood td:nth-child(10), /* Total Custos iFood */
#matriz-ifood td:nth-child(12), /* Incentivos iFood */
#matriz-ifood td:nth-child(14) { /* Faturamento Líquido */
    padding-right: 0;
    border-right: none;
}

#matriz-ifood td:nth-child(5),
#matriz-ifood td:nth-child(7),
#matriz-ifood td:nth-child(9),
#matriz-ifood td:nth-child(11),
#matriz-ifood td:nth-child(13) { /* After Incentivos % and Faturamento Líquido % */
    padding-left: 0;
}

/* Add vertical separation between groups */
#matriz-ifood td:nth-child(3),  /* After Qtd Pedidos */
#matriz-ifood td:nth-child(5),  /* After Promoções % */
#matriz-ifood td:nth-child(7),  /* After Entrega % */
#matriz-ifood td:nth-child(9),  /* After Comissões % */
#matriz-ifood td:nth-child(11), /* After Total Custos % */
#matriz-ifood td:nth-child(13)  /* After Incentivos % */ {
    border-right: 2px solid #fff;
}

/* Center specific columns */
#matriz-ifood td:nth-child(2),  /* Qtd Pedidos */
#matriz-ifood td:nth-child(3),  /* Fat. Bruto */
#matriz-ifood td:nth-child(4),  /* Promoções Valor */
#matriz-ifood td:nth-child(5),  /* Promoções % */
#matriz-ifood td:nth-child(6),  /* Entrega Valor */
#matriz-ifood td:nth-child(7),  /* Entrega % */
#matriz-ifood td:nth-child(8),  /* Comissões Valor */
#matriz-ifood td:nth-child(9),  /* Comissões % */
#matriz-ifood td:nth-child(10), /* Total Custos Valor */
#matriz-ifood td:nth-child(11), /* Total Custos % */
#matriz-ifood td:nth-child(12), /* Incentivos Valor */
#matriz-ifood td:nth-child(13), /* Incentivos % */
#matriz-ifood td:nth-child(14), /* Faturamento Líquido Valor */
#matriz-ifood td:nth-child(15)  /* Faturamento Líquido % */ {
    text-align: center;
}

/* Keep first column (Ano/Mês) left aligned */
#matriz-ifood td:nth-child(1) {
    text-align: left;
}

/* Center headers except first one */
#matriz-ifood th:not(:first-child) {
    text-align: center;
}

.matriz-container {
    margin: 30px auto;
    padding: 20px;
    background: #f8f8f8;  /* Mudado para cinza claro */
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

#matriz-ifood {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    color: #000000;
    background: #ffffff;  /* Fundo branco para tabela */
}

#matriz-ifood th {
    background-color: #f1f1f1;
    padding: 12px;
    text-align: left;
    font-weight: bold;
    border-bottom: 2px solid #ddd;
}

#matriz-ifood td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    color: #000000;
}

/* Estilo para as colunas de desconto - incluindo background */
#matriz-ifood td:nth-child(4), /* Promoções Loja - Valor */
#matriz-ifood td:nth-child(5), /* Promoções Loja - % */
#matriz-ifood th:nth-child(4),
#matriz-ifood th:nth-child(5) {
    background-color: #ef9a9a; /* Vermelho claro sólido (Promoções) */
    color: #000000;  /* Texto preto */
    padding-right: 2px;
    padding-left: 2px;
}

#matriz-ifood tr:hover td {
    background-color: #f5f5f5;
}

/* Mantém o background das colunas de custo mesmo no hover, escurecendo um pouco */
#matriz-ifood tr:hover td:nth-child(4),
#matriz-ifood tr:hover td:nth-child(5) { /* Promoções Hover */
    background-color: #e57373; /* Vermelho claro sólido mais escuro no hover */
}
#matriz-ifood tr:hover td:nth-child(6), /* Entrega Hover */
#matriz-ifood tr:hover td:nth-child(7) {
    background-color: #ff8a65; /* Laranja claro sólido mais escuro no hover */
}
#matriz-ifood tr:hover td:nth-child(8), /* Comissões Hover */
#matriz-ifood tr:hover td:nth-child(9) {
    background-color: #ef9a9a; /* Rosa claro/suave sólido mais escuro no hover */
}
#matriz-ifood tr:hover td:nth-child(10), /* Total Custos Hover */
#matriz-ifood tr:hover td:nth-child(11) {
    background-color: #d32f2f; /* Vermelho médio sólido mais escuro no hover */
}

/* Garante que outras colunas no hover voltem para o cinza claro do hover geral, se não forem as de custo */
#matriz-ifood tr:hover td:not(:nth-child(4)):not(:nth-child(5)):not(:nth-child(6)):not(:nth-child(7)):not(:nth-child(8)):not(:nth-child(9)):not(:nth-child(10)):not(:nth-child(11)):not(:nth-child(12)):not(:nth-child(13)):not(:nth-child(14)):not(:nth-child(15)) {
    background-color: #f5f5f5 !important; /* Hover padrão para colunas não especificamente coloridas */
}

        .grafico-container {
            margin: 20px auto;
            padding: 15px;
            background: #f8f8f8;  /* Light gray background */
            border: 1px solid #ddd;
            border-radius: 10px;  /* Increased from 5px to 10px */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .grafico-title {
            font-size: 14pt;
            font-weight: bold;  /* Added bold */
            color: #333;
            margin: 0 0 15px 0;
            padding: 0;
            text-align: center;
        }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-row">
    <!-- Conteúdo Principal -->
    <main class="flex-1 p-6">
        <h1 class="text-2xl font-bold text-yellow-400 mb-4">Dashboard iFood</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class='grafico-container'>
                <h2 class="grafico-title">Promoções Custeada pela Loja</h2>
                <!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Gráfico - Promoções Custeada pela Loja</title>
    <style>
      html, body {
        box-sizing: border-box;
        display: flow-root;
        height: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
    <script type="text/javascript" src="https://cdn.bokeh.org/bokeh/release/bokeh-3.7.2.min.js"></script>
    <script type="text/javascript">
        Bokeh.set_log_level("info");
    </script>
  </head>
  <body>
    <div id="a609109d-cb21-45e6-ac50-f125d377a646" data-root-id="p1004" style="display: contents;"></div>
  
    <script type="application/json" id="f1550c64-1d4f-42fb-b0c2-8a50d73cf164">
      {"8c823ad4-dbd0-4e3e-9328-989189f1fde8":{"version":"3.7.2","title":"Bokeh Application","roots":[{"type":"object","name":"Figure","id":"p1004","attributes":{"name":"graph_promocoes","height":300,"margin":[10,10,10,10],"x_range":{"type":"object","name":"DataRange1d","id":"p1005"},"y_range":{"type":"object","name":"DataRange1d","id":"p1006"},"x_scale":{"type":"object","name":"LinearScale","id":"p1013"},"y_scale":{"type":"object","name":"LinearScale","id":"p1014"},"extra_y_ranges":{"type":"map","entries":[["pedidos_range",{"type":"object","name":"Range1d","id":"p1039","attributes":{"end":1045.0}}]]},"title":{"type":"object","name":"Title","id":"p1011"},"renderers":[{"type":"object","name":"GlyphRenderer","id":"p1050","attributes":{"data_source":{"type":"object","name":"ColumnDataSource","id":"p1001","attributes":{"selected":{"type":"object","name":"Selection","id":"p1002","attributes":{"indices":[],"line_indices":[]}},"selection_policy":{"type":"object","name":"UnionRenderers","id":"p1003"},"data":{"type":"map","entries":[["x",{"type":"ndarray","array":{"type":"bytes","data":"AAAAWVAueUIAAIBF+Dd5QgAAwJfyQXlCAAAA6uxLeUIAAAAL8FR5QgAAQF3qXnlCAADASZJoeUI="},"shape":[7],"dtype":"float64","order":"little"}],["y",{"type":"ndarray","array":{"type":"bytes","data":"KVyPwlXKxUApXI/CFQLJQFK4HoWbO9JAFK5H4cpz0EAK16NwrYzQQK5H4XqkGtJAAAAAAEA1zUA="},"shape":[7],"dtype":"float64","order":"little"}],["valor",["R$ 11.156,67","R$ 12.804,17","R$ 18.670,43","R$ 16.847,17","R$ 16.946,71","R$ 18.538,57","R$ 14.954,50"]],["pedidos",{"type":"ndarray","array":{"type":"bytes","data":"fQIAAIoCAACYAwAAlQMAALYDAACuAwAAAQMAAA=="},"shape":[7],"dtype":"int32","order":"little"}]]}}},"view":{"type":"object","name":"CDSView","id":"p1051","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1052"}}},"glyph":{"type":"object","name":"Line","id":"p1047","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1048","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1049","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1061","attributes":{"data_source":{"id":"p1001"},"view":{"type":"object","name":"CDSView","id":"p1062","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1063"}}},"glyph":{"type":"object","name":"Scatter","id":"p1058","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"fill_color":{"type":"value","value":"#D0021B"},"hatch_color":{"type":"value","value":"#D0021B"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1059","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1060","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.2}}}}},{"type":"object","name":"GlyphRenderer","id":"p1070","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1001"},"view":{"type":"object","name":"CDSView","id":"p1071","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1072"}}},"glyph":{"type":"object","name":"Line","id":"p1067","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1068","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1069","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1080","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1001"},"view":{"type":"object","name":"CDSView","id":"p1081","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1082"}}},"glyph":{"type":"object","name":"Scatter","id":"p1077","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"fill_color":{"type":"value","value":"#000000"},"hatch_color":{"type":"value","value":"#000000"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1078","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1079","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.2}}}}}],"toolbar":{"type":"object","name":"Toolbar","id":"p1012","attributes":{"tools":[{"type":"object","name":"HoverTool","id":"p1083","attributes":{"renderers":"auto","tooltips":[["M\u00eas/Ano","@x{%Y/%m}"],["Promo\u00e7\u00f5es Custeada pela Loja","@valor"],["Qtd Pedidos","@pedidos"]],"formatters":{"type":"map","entries":[["@x","datetime"]]},"mode":"vline"}}]}},"toolbar_location":null,"left":[{"type":"object","name":"LinearAxis","id":"p1034","attributes":{"ticker":{"type":"object","name":"BasicTicker","id":"p1035","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1036"},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1037"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"right":[{"type":"object","name":"LinearAxis","id":"p1040","attributes":{"y_range_name":"pedidos_range","ticker":{"type":"object","name":"BasicTicker","id":"p1041","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1042"},"axis_label":"Qtd Pedidos","major_label_policy":{"type":"object","name":"AllLabels","id":"p1043"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"below":[{"type":"object","name":"DatetimeAxis","id":"p1015","attributes":{"ticker":{"type":"object","name":"DatetimeTicker","id":"p1016","attributes":{"num_minor_ticks":5,"tickers":[{"type":"object","name":"AdaptiveTicker","id":"p1017","attributes":{"num_minor_ticks":0,"mantissas":[1,2,5],"max_interval":500.0}},{"type":"object","name":"AdaptiveTicker","id":"p1018","attributes":{"num_minor_ticks":0,"base":60,"mantissas":[1,2,5,10,15,20,30],"min_interval":1000.0,"max_interval":1800000.0}},{"type":"object","name":"AdaptiveTicker","id":"p1019","attributes":{"num_minor_ticks":0,"base":24,"mantissas":[1,2,4,6,8,12],"min_interval":3600000.0,"max_interval":43200000.0}},{"type":"object","name":"DaysTicker","id":"p1020","attributes":{"days":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31]}},{"type":"object","name":"DaysTicker","id":"p1021","attributes":{"days":[1,4,7,10,13,16,19,22,25,28]}},{"type":"object","name":"DaysTicker","id":"p1022","attributes":{"days":[1,8,15,22]}},{"type":"object","name":"DaysTicker","id":"p1023","attributes":{"days":[1,15]}},{"type":"object","name":"MonthsTicker","id":"p1024","attributes":{"months":[0,1,2,3,4,5,6,7,8,9,10,11]}},{"type":"object","name":"MonthsTicker","id":"p1025","attributes":{"months":[0,2,4,6,8,10]}},{"type":"object","name":"MonthsTicker","id":"p1026","attributes":{"months":[0,4,8]}},{"type":"object","name":"MonthsTicker","id":"p1027","attributes":{"months":[0,6]}},{"type":"object","name":"YearsTicker","id":"p1028"}]}},"formatter":{"type":"object","name":"DatetimeTickFormatter","id":"p1031","attributes":{"seconds":"%T","minsec":"%T","minutes":"%H:%M","hours":"%H:%M","days":"%b %d","months":"%b %Y","strip_leading_zeros":["microseconds","milliseconds","seconds"],"boundary_scaling":false,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1030","attributes":{"microseconds":"%T","milliseconds":"%T","seconds":"%b %d, %Y","minsec":"%b %d, %Y","minutes":"%b %d, %Y","hourmin":"%b %d, %Y","hours":"%b %d, %Y","days":"%Y","months":"","years":"","boundary_scaling":false,"hide_repeats":true,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1029","attributes":{"microseconds":"%b %d, %Y","milliseconds":"%b %d, %Y","seconds":"","minsec":"","minutes":"","hourmin":"","hours":"","days":"","months":"","years":"","boundary_scaling":false,"hide_repeats":true}},"context_which":"all"}},"context_which":"all"}},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1032"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"center":[{"type":"object","name":"Grid","id":"p1033","attributes":{"axis":{"id":"p1015"},"grid_line_color":null}},{"type":"object","name":"Grid","id":"p1038","attributes":{"dimension":1,"axis":{"id":"p1034"},"grid_line_color":null}},{"type":"object","name":"Legend","id":"p1053","attributes":{"location":"bottom_center","orientation":"horizontal","border_line_color":null,"background_fill_color":null,"click_policy":"hide","label_text_font_size":"10pt","margin":0,"padding":5,"spacing":20,"items":[{"type":"object","name":"LegendItem","id":"p1054","attributes":{"label":{"type":"value","value":"Promo\u00e7\u00f5es Custeada pela Loja"},"renderers":[{"id":"p1050"},{"id":"p1061"}]}},{"type":"object","name":"LegendItem","id":"p1073","attributes":{"label":{"type":"value","value":"Qtd Pedidos"},"renderers":[{"id":"p1070"},{"id":"p1080"}]}}]}}]}}]}}
    </script>
    <script type="text/javascript">
      (function() {
        const fn = function() {
          Bokeh.safely(function() {
            (function(root) {
              function embed_document(root) {
              const docs_json = document.getElementById('f1550c64-1d4f-42fb-b0c2-8a50d73cf164').textContent;
              const render_items = [{"docid":"8c823ad4-dbd0-4e3e-9328-989189f1fde8","roots":{"p1004":"a609109d-cb21-45e6-ac50-f125d377a646"},"root_ids":["p1004"]}];
              root.Bokeh.embed.embed_items(docs_json, render_items);
              }
              if (root.Bokeh !== undefined) {
                embed_document(root);
              } else {
                let attempts = 0;
                const timer = setInterval(function(root) {
                  if (root.Bokeh !== undefined) {
                    clearInterval(timer);
                    embed_document(root);
                  } else {
                    attempts++;
                    if (attempts > 100) {
                      clearInterval(timer);
                      console.log("Bokeh: ERROR: Unable to run BokehJS code because BokehJS library is missing");
                    }
                  }
                }, 10, root)
              }
            })(window);
          });
        };
        if (document.readyState != "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
      })();
    </script>
  </body>
</html>
            </div><div class='grafico-container'>
                <h2 class="grafico-title">Entrega Custeada pela Loja</h2>
                <!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Gráfico - Entrega Custeada pela Loja</title>
    <style>
      html, body {
        box-sizing: border-box;
        display: flow-root;
        height: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
    <script type="text/javascript" src="https://cdn.bokeh.org/bokeh/release/bokeh-3.7.2.min.js"></script>
    <script type="text/javascript">
        Bokeh.set_log_level("info");
    </script>
  </head>
  <body>
    <div id="ccea2f3e-d0cb-458a-aa43-614f242b25d6" data-root-id="p1090" style="display: contents;"></div>
  
    <script type="application/json" id="b45ce35e-403a-424d-a9e6-834ef0bcd16b">
      {"32d0a6f7-2f5f-42a5-a5b7-c19211badbad":{"version":"3.7.2","title":"Bokeh Application","roots":[{"type":"object","name":"Figure","id":"p1090","attributes":{"name":"graph_entrega","height":300,"margin":[10,10,10,10],"x_range":{"type":"object","name":"DataRange1d","id":"p1091"},"y_range":{"type":"object","name":"DataRange1d","id":"p1092"},"x_scale":{"type":"object","name":"LinearScale","id":"p1099"},"y_scale":{"type":"object","name":"LinearScale","id":"p1100"},"extra_y_ranges":{"type":"map","entries":[["pedidos_range",{"type":"object","name":"Range1d","id":"p1125","attributes":{"end":1045.0}}]]},"title":{"type":"object","name":"Title","id":"p1097"},"renderers":[{"type":"object","name":"GlyphRenderer","id":"p1136","attributes":{"data_source":{"type":"object","name":"ColumnDataSource","id":"p1087","attributes":{"selected":{"type":"object","name":"Selection","id":"p1088","attributes":{"indices":[],"line_indices":[]}},"selection_policy":{"type":"object","name":"UnionRenderers","id":"p1089"},"data":{"type":"map","entries":[["x",{"type":"ndarray","array":{"type":"bytes","data":"AAAAWVAueUIAAIBF+Dd5QgAAwJfyQXlCAAAA6uxLeUIAAAAL8FR5QgAAQF3qXnlCAADASZJoeUI="},"shape":[7],"dtype":"float64","order":"little"}],["y",{"type":"ndarray","array":{"type":"bytes","data":"CtejcL0luUDD9Shcj+e7QFK4HoVrTcNAZmZmZuYBwUAK16NwXVbBQDMzMzPTusJAw/UoXE+bv0A="},"shape":[7],"dtype":"float64","order":"little"}],["valor",["R$ 6.437,74","R$ 7.143,56","R$ 9.882,84","R$ 8.707,80","R$ 8.876,73","R$ 9.589,65","R$ 8.091,31"]],["pedidos",{"type":"ndarray","array":{"type":"bytes","data":"fQIAAIoCAACYAwAAlQMAALYDAACuAwAAAQMAAA=="},"shape":[7],"dtype":"int32","order":"little"}]]}}},"view":{"type":"object","name":"CDSView","id":"p1137","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1138"}}},"glyph":{"type":"object","name":"Line","id":"p1133","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1134","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1135","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1147","attributes":{"data_source":{"id":"p1087"},"view":{"type":"object","name":"CDSView","id":"p1148","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1149"}}},"glyph":{"type":"object","name":"Scatter","id":"p1144","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"fill_color":{"type":"value","value":"#D0021B"},"hatch_color":{"type":"value","value":"#D0021B"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1145","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1146","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.2}}}}},{"type":"object","name":"GlyphRenderer","id":"p1156","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1087"},"view":{"type":"object","name":"CDSView","id":"p1157","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1158"}}},"glyph":{"type":"object","name":"Line","id":"p1153","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1154","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1155","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1166","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1087"},"view":{"type":"object","name":"CDSView","id":"p1167","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1168"}}},"glyph":{"type":"object","name":"Scatter","id":"p1163","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"fill_color":{"type":"value","value":"#000000"},"hatch_color":{"type":"value","value":"#000000"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1164","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1165","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.2}}}}}],"toolbar":{"type":"object","name":"Toolbar","id":"p1098","attributes":{"tools":[{"type":"object","name":"HoverTool","id":"p1169","attributes":{"renderers":"auto","tooltips":[["M\u00eas/Ano","@x{%Y/%m}"],["Entrega Custeada pela Loja","@valor"],["Qtd Pedidos","@pedidos"]],"formatters":{"type":"map","entries":[["@x","datetime"]]},"mode":"vline"}}]}},"toolbar_location":null,"left":[{"type":"object","name":"LinearAxis","id":"p1120","attributes":{"ticker":{"type":"object","name":"BasicTicker","id":"p1121","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1122"},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1123"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"right":[{"type":"object","name":"LinearAxis","id":"p1126","attributes":{"y_range_name":"pedidos_range","ticker":{"type":"object","name":"BasicTicker","id":"p1127","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1128"},"axis_label":"Qtd Pedidos","major_label_policy":{"type":"object","name":"AllLabels","id":"p1129"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"below":[{"type":"object","name":"DatetimeAxis","id":"p1101","attributes":{"ticker":{"type":"object","name":"DatetimeTicker","id":"p1102","attributes":{"num_minor_ticks":5,"tickers":[{"type":"object","name":"AdaptiveTicker","id":"p1103","attributes":{"num_minor_ticks":0,"mantissas":[1,2,5],"max_interval":500.0}},{"type":"object","name":"AdaptiveTicker","id":"p1104","attributes":{"num_minor_ticks":0,"base":60,"mantissas":[1,2,5,10,15,20,30],"min_interval":1000.0,"max_interval":1800000.0}},{"type":"object","name":"AdaptiveTicker","id":"p1105","attributes":{"num_minor_ticks":0,"base":24,"mantissas":[1,2,4,6,8,12],"min_interval":3600000.0,"max_interval":43200000.0}},{"type":"object","name":"DaysTicker","id":"p1106","attributes":{"days":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31]}},{"type":"object","name":"DaysTicker","id":"p1107","attributes":{"days":[1,4,7,10,13,16,19,22,25,28]}},{"type":"object","name":"DaysTicker","id":"p1108","attributes":{"days":[1,8,15,22]}},{"type":"object","name":"DaysTicker","id":"p1109","attributes":{"days":[1,15]}},{"type":"object","name":"MonthsTicker","id":"p1110","attributes":{"months":[0,1,2,3,4,5,6,7,8,9,10,11]}},{"type":"object","name":"MonthsTicker","id":"p1111","attributes":{"months":[0,2,4,6,8,10]}},{"type":"object","name":"MonthsTicker","id":"p1112","attributes":{"months":[0,4,8]}},{"type":"object","name":"MonthsTicker","id":"p1113","attributes":{"months":[0,6]}},{"type":"object","name":"YearsTicker","id":"p1114"}]}},"formatter":{"type":"object","name":"DatetimeTickFormatter","id":"p1117","attributes":{"seconds":"%T","minsec":"%T","minutes":"%H:%M","hours":"%H:%M","days":"%b %d","months":"%b %Y","strip_leading_zeros":["microseconds","milliseconds","seconds"],"boundary_scaling":false,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1116","attributes":{"microseconds":"%T","milliseconds":"%T","seconds":"%b %d, %Y","minsec":"%b %d, %Y","minutes":"%b %d, %Y","hourmin":"%b %d, %Y","hours":"%b %d, %Y","days":"%Y","months":"","years":"","boundary_scaling":false,"hide_repeats":true,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1115","attributes":{"microseconds":"%b %d, %Y","milliseconds":"%b %d, %Y","seconds":"","minsec":"","minutes":"","hourmin":"","hours":"","days":"","months":"","years":"","boundary_scaling":false,"hide_repeats":true}},"context_which":"all"}},"context_which":"all"}},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1118"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"center":[{"type":"object","name":"Grid","id":"p1119","attributes":{"axis":{"id":"p1101"},"grid_line_color":null}},{"type":"object","name":"Grid","id":"p1124","attributes":{"dimension":1,"axis":{"id":"p1120"},"grid_line_color":null}},{"type":"object","name":"Legend","id":"p1139","attributes":{"location":"bottom_center","orientation":"horizontal","border_line_color":null,"background_fill_color":null,"click_policy":"hide","label_text_font_size":"10pt","margin":0,"padding":5,"spacing":20,"items":[{"type":"object","name":"LegendItem","id":"p1140","attributes":{"label":{"type":"value","value":"Entrega Custeada pela Loja"},"renderers":[{"id":"p1136"},{"id":"p1147"}]}},{"type":"object","name":"LegendItem","id":"p1159","attributes":{"label":{"type":"value","value":"Qtd Pedidos"},"renderers":[{"id":"p1156"},{"id":"p1166"}]}}]}}]}}]}}
    </script>
    <script type="text/javascript">
      (function() {
        const fn = function() {
          Bokeh.safely(function() {
            (function(root) {
              function embed_document(root) {
              const docs_json = document.getElementById('b45ce35e-403a-424d-a9e6-834ef0bcd16b').textContent;
              const render_items = [{"docid":"32d0a6f7-2f5f-42a5-a5b7-c19211badbad","roots":{"p1090":"ccea2f3e-d0cb-458a-aa43-614f242b25d6"},"root_ids":["p1090"]}];
              root.Bokeh.embed.embed_items(docs_json, render_items);
              }
              if (root.Bokeh !== undefined) {
                embed_document(root);
              } else {
                let attempts = 0;
                const timer = setInterval(function(root) {
                  if (root.Bokeh !== undefined) {
                    clearInterval(timer);
                    embed_document(root);
                  } else {
                    attempts++;
                    if (attempts > 100) {
                      clearInterval(timer);
                      console.log("Bokeh: ERROR: Unable to run BokehJS code because BokehJS library is missing");
                    }
                  }
                }, 10, root)
              }
            })(window);
          });
        };
        if (document.readyState != "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
      })();
    </script>
  </body>
</html>
            </div><div class='grafico-container'>
                <h2 class="grafico-title">Comissão iFood</h2>
                <!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Gráfico - Comissão iFood</title>
    <style>
      html, body {
        box-sizing: border-box;
        display: flow-root;
        height: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
    <script type="text/javascript" src="https://cdn.bokeh.org/bokeh/release/bokeh-3.7.2.min.js"></script>
    <script type="text/javascript">
        Bokeh.set_log_level("info");
    </script>
  </head>
  <body>
    <div id="cf59daf3-7175-49d6-b363-a269cdd26e12" data-root-id="p1176" style="display: contents;"></div>
  
    <script type="application/json" id="fd118bc1-3684-4456-93f9-7f5bb07400c6">
      {"a518f941-9a81-4b9d-b82f-a311059f870b":{"version":"3.7.2","title":"Bokeh Application","roots":[{"type":"object","name":"Figure","id":"p1176","attributes":{"name":"graph_comissoes_ifood","height":300,"margin":[10,10,10,10],"x_range":{"type":"object","name":"DataRange1d","id":"p1177"},"y_range":{"type":"object","name":"DataRange1d","id":"p1178"},"x_scale":{"type":"object","name":"LinearScale","id":"p1185"},"y_scale":{"type":"object","name":"LinearScale","id":"p1186"},"extra_y_ranges":{"type":"map","entries":[["pedidos_range",{"type":"object","name":"Range1d","id":"p1211","attributes":{"end":1045.0}}]]},"title":{"type":"object","name":"Title","id":"p1183"},"renderers":[{"type":"object","name":"GlyphRenderer","id":"p1222","attributes":{"data_source":{"type":"object","name":"ColumnDataSource","id":"p1173","attributes":{"selected":{"type":"object","name":"Selection","id":"p1174","attributes":{"indices":[],"line_indices":[]}},"selection_policy":{"type":"object","name":"UnionRenderers","id":"p1175"},"data":{"type":"map","entries":[["x",{"type":"ndarray","array":{"type":"bytes","data":"AAAAWVAueUIAAIBF+Dd5QgAAwJfyQXlCAAAA6uxLeUIAAAAL8FR5QgAAQF3qXnlCAADASZJoeUI="},"shape":[7],"dtype":"float64","order":"little"}],["y",{"type":"ndarray","array":{"type":"bytes","data":"7FG4HkWsuUBxPQrX40S7QI/C9Sj8G8NAhetRuP6XwUD2KFyPwmzBQLgehetRjcJAuB6F69Fvv0A="},"shape":[7],"dtype":"float64","order":"little"}],["valor",["R$ 6.572,27","R$ 6.980,89","R$ 9.783,97","R$ 9.007,99","R$ 8.921,52","R$ 9.498,64","R$ 8.047,82"]],["pedidos",{"type":"ndarray","array":{"type":"bytes","data":"fQIAAIoCAACYAwAAlQMAALYDAACuAwAAAQMAAA=="},"shape":[7],"dtype":"int32","order":"little"}]]}}},"view":{"type":"object","name":"CDSView","id":"p1223","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1224"}}},"glyph":{"type":"object","name":"Line","id":"p1219","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1220","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1221","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1233","attributes":{"data_source":{"id":"p1173"},"view":{"type":"object","name":"CDSView","id":"p1234","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1235"}}},"glyph":{"type":"object","name":"Scatter","id":"p1230","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"fill_color":{"type":"value","value":"#D0021B"},"hatch_color":{"type":"value","value":"#D0021B"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1231","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1232","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.2}}}}},{"type":"object","name":"GlyphRenderer","id":"p1242","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1173"},"view":{"type":"object","name":"CDSView","id":"p1243","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1244"}}},"glyph":{"type":"object","name":"Line","id":"p1239","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1240","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1241","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1252","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1173"},"view":{"type":"object","name":"CDSView","id":"p1253","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1254"}}},"glyph":{"type":"object","name":"Scatter","id":"p1249","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"fill_color":{"type":"value","value":"#000000"},"hatch_color":{"type":"value","value":"#000000"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1250","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1251","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.2}}}}}],"toolbar":{"type":"object","name":"Toolbar","id":"p1184","attributes":{"tools":[{"type":"object","name":"HoverTool","id":"p1255","attributes":{"renderers":"auto","tooltips":[["M\u00eas/Ano","@x{%Y/%m}"],["Comiss\u00e3o iFood","@valor"],["Qtd Pedidos","@pedidos"]],"formatters":{"type":"map","entries":[["@x","datetime"]]},"mode":"vline"}}]}},"toolbar_location":null,"left":[{"type":"object","name":"LinearAxis","id":"p1206","attributes":{"ticker":{"type":"object","name":"BasicTicker","id":"p1207","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1208"},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1209"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"right":[{"type":"object","name":"LinearAxis","id":"p1212","attributes":{"y_range_name":"pedidos_range","ticker":{"type":"object","name":"BasicTicker","id":"p1213","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1214"},"axis_label":"Qtd Pedidos","major_label_policy":{"type":"object","name":"AllLabels","id":"p1215"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"below":[{"type":"object","name":"DatetimeAxis","id":"p1187","attributes":{"ticker":{"type":"object","name":"DatetimeTicker","id":"p1188","attributes":{"num_minor_ticks":5,"tickers":[{"type":"object","name":"AdaptiveTicker","id":"p1189","attributes":{"num_minor_ticks":0,"mantissas":[1,2,5],"max_interval":500.0}},{"type":"object","name":"AdaptiveTicker","id":"p1190","attributes":{"num_minor_ticks":0,"base":60,"mantissas":[1,2,5,10,15,20,30],"min_interval":1000.0,"max_interval":1800000.0}},{"type":"object","name":"AdaptiveTicker","id":"p1191","attributes":{"num_minor_ticks":0,"base":24,"mantissas":[1,2,4,6,8,12],"min_interval":3600000.0,"max_interval":43200000.0}},{"type":"object","name":"DaysTicker","id":"p1192","attributes":{"days":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31]}},{"type":"object","name":"DaysTicker","id":"p1193","attributes":{"days":[1,4,7,10,13,16,19,22,25,28]}},{"type":"object","name":"DaysTicker","id":"p1194","attributes":{"days":[1,8,15,22]}},{"type":"object","name":"DaysTicker","id":"p1195","attributes":{"days":[1,15]}},{"type":"object","name":"MonthsTicker","id":"p1196","attributes":{"months":[0,1,2,3,4,5,6,7,8,9,10,11]}},{"type":"object","name":"MonthsTicker","id":"p1197","attributes":{"months":[0,2,4,6,8,10]}},{"type":"object","name":"MonthsTicker","id":"p1198","attributes":{"months":[0,4,8]}},{"type":"object","name":"MonthsTicker","id":"p1199","attributes":{"months":[0,6]}},{"type":"object","name":"YearsTicker","id":"p1200"}]}},"formatter":{"type":"object","name":"DatetimeTickFormatter","id":"p1203","attributes":{"seconds":"%T","minsec":"%T","minutes":"%H:%M","hours":"%H:%M","days":"%b %d","months":"%b %Y","strip_leading_zeros":["microseconds","milliseconds","seconds"],"boundary_scaling":false,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1202","attributes":{"microseconds":"%T","milliseconds":"%T","seconds":"%b %d, %Y","minsec":"%b %d, %Y","minutes":"%b %d, %Y","hourmin":"%b %d, %Y","hours":"%b %d, %Y","days":"%Y","months":"","years":"","boundary_scaling":false,"hide_repeats":true,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1201","attributes":{"microseconds":"%b %d, %Y","milliseconds":"%b %d, %Y","seconds":"","minsec":"","minutes":"","hourmin":"","hours":"","days":"","months":"","years":"","boundary_scaling":false,"hide_repeats":true}},"context_which":"all"}},"context_which":"all"}},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1204"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"center":[{"type":"object","name":"Grid","id":"p1205","attributes":{"axis":{"id":"p1187"},"grid_line_color":null}},{"type":"object","name":"Grid","id":"p1210","attributes":{"dimension":1,"axis":{"id":"p1206"},"grid_line_color":null}},{"type":"object","name":"Legend","id":"p1225","attributes":{"location":"bottom_center","orientation":"horizontal","border_line_color":null,"background_fill_color":null,"click_policy":"hide","label_text_font_size":"10pt","margin":0,"padding":5,"spacing":20,"items":[{"type":"object","name":"LegendItem","id":"p1226","attributes":{"label":{"type":"value","value":"Comiss\u00e3o iFood"},"renderers":[{"id":"p1222"},{"id":"p1233"}]}},{"type":"object","name":"LegendItem","id":"p1245","attributes":{"label":{"type":"value","value":"Qtd Pedidos"},"renderers":[{"id":"p1242"},{"id":"p1252"}]}}]}}]}}]}}
    </script>
    <script type="text/javascript">
      (function() {
        const fn = function() {
          Bokeh.safely(function() {
            (function(root) {
              function embed_document(root) {
              const docs_json = document.getElementById('fd118bc1-3684-4456-93f9-7f5bb07400c6').textContent;
              const render_items = [{"docid":"a518f941-9a81-4b9d-b82f-a311059f870b","roots":{"p1176":"cf59daf3-7175-49d6-b363-a269cdd26e12"},"root_ids":["p1176"]}];
              root.Bokeh.embed.embed_items(docs_json, render_items);
              }
              if (root.Bokeh !== undefined) {
                embed_document(root);
              } else {
                let attempts = 0;
                const timer = setInterval(function(root) {
                  if (root.Bokeh !== undefined) {
                    clearInterval(timer);
                    embed_document(root);
                  } else {
                    attempts++;
                    if (attempts > 100) {
                      clearInterval(timer);
                      console.log("Bokeh: ERROR: Unable to run BokehJS code because BokehJS library is missing");
                    }
                  }
                }, 10, root)
              }
            })(window);
          });
        };
        if (document.readyState != "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
      })();
    </script>
  </body>
</html>
            </div><div class='grafico-container'>
                <h2 class="grafico-title">Comissão Pagamento</h2>
                <!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Gráfico - Comissão Pagamento</title>
    <style>
      html, body {
        box-sizing: border-box;
        display: flow-root;
        height: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
    <script type="text/javascript" src="https://cdn.bokeh.org/bokeh/release/bokeh-3.7.2.min.js"></script>
    <script type="text/javascript">
        Bokeh.set_log_level("info");
    </script>
  </head>
  <body>
    <div id="c9b84e21-3c0a-4758-b5bd-9566c4a2f9e4" data-root-id="p1262" style="display: contents;"></div>
  
    <script type="application/json" id="b37e6ec9-90bc-44d4-8796-a2a3ba8670c3">
      {"6a4919b1-e6b2-48ec-bd0a-0d8a2957893c":{"version":"3.7.2","title":"Bokeh Application","roots":[{"type":"object","name":"Figure","id":"p1262","attributes":{"name":"graph_comissoes_pagamento","height":300,"margin":[10,10,10,10],"x_range":{"type":"object","name":"DataRange1d","id":"p1263"},"y_range":{"type":"object","name":"DataRange1d","id":"p1264"},"x_scale":{"type":"object","name":"LinearScale","id":"p1271"},"y_scale":{"type":"object","name":"LinearScale","id":"p1272"},"extra_y_ranges":{"type":"map","entries":[["pedidos_range",{"type":"object","name":"Range1d","id":"p1297","attributes":{"end":1045.0}}]]},"title":{"type":"object","name":"Title","id":"p1269"},"renderers":[{"type":"object","name":"GlyphRenderer","id":"p1308","attributes":{"data_source":{"type":"object","name":"ColumnDataSource","id":"p1259","attributes":{"selected":{"type":"object","name":"Selection","id":"p1260","attributes":{"indices":[],"line_indices":[]}},"selection_policy":{"type":"object","name":"UnionRenderers","id":"p1261"},"data":{"type":"map","entries":[["x",{"type":"ndarray","array":{"type":"bytes","data":"AAAAWVAueUIAAIBF+Dd5QgAAwJfyQXlCAAAA6uxLeUIAAAAL8FR5QgAAQF3qXnlCAADASZJoeUI="},"shape":[7],"dtype":"float64","order":"little"}],["y",{"type":"ndarray","array":{"type":"bytes","data":"7FG4HoW3iUDD9Shcj6SLQFyPwvUol5NApHA9CtcLkkCamZmZmaKRQIXrUbge7pJAAAAAAAA3kEA="},"shape":[7],"dtype":"float64","order":"little"}],["valor",["R$ 822,94","R$ 884,57","R$ 1.253,79","R$ 1.154,96","R$ 1.128,65","R$ 1.211,53","R$ 1.037,75"]],["pedidos",{"type":"ndarray","array":{"type":"bytes","data":"fQIAAIoCAACYAwAAlQMAALYDAACuAwAAAQMAAA=="},"shape":[7],"dtype":"int32","order":"little"}]]}}},"view":{"type":"object","name":"CDSView","id":"p1309","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1310"}}},"glyph":{"type":"object","name":"Line","id":"p1305","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1306","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1307","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"line_color":"#D0021B","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1319","attributes":{"data_source":{"id":"p1259"},"view":{"type":"object","name":"CDSView","id":"p1320","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1321"}}},"glyph":{"type":"object","name":"Scatter","id":"p1316","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"fill_color":{"type":"value","value":"#D0021B"},"hatch_color":{"type":"value","value":"#D0021B"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1317","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1318","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"y"},"size":{"type":"value","value":6},"line_color":{"type":"value","value":"#D0021B"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#D0021B"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#D0021B"},"hatch_alpha":{"type":"value","value":0.2}}}}},{"type":"object","name":"GlyphRenderer","id":"p1328","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1259"},"view":{"type":"object","name":"CDSView","id":"p1329","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1330"}}},"glyph":{"type":"object","name":"Line","id":"p1325","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_width":2}},"nonselection_glyph":{"type":"object","name":"Line","id":"p1326","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.1,"line_width":2}},"muted_glyph":{"type":"object","name":"Line","id":"p1327","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":"#000000","line_alpha":0.2,"line_width":2}}}},{"type":"object","name":"GlyphRenderer","id":"p1338","attributes":{"y_range_name":"pedidos_range","data_source":{"id":"p1259"},"view":{"type":"object","name":"CDSView","id":"p1339","attributes":{"filter":{"type":"object","name":"AllIndices","id":"p1340"}}},"glyph":{"type":"object","name":"Scatter","id":"p1335","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"fill_color":{"type":"value","value":"#000000"},"hatch_color":{"type":"value","value":"#000000"}}},"nonselection_glyph":{"type":"object","name":"Scatter","id":"p1336","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.1},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.1},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.1}}},"muted_glyph":{"type":"object","name":"Scatter","id":"p1337","attributes":{"x":{"type":"field","field":"x"},"y":{"type":"field","field":"pedidos"},"line_color":{"type":"value","value":"#000000"},"line_alpha":{"type":"value","value":0.2},"fill_color":{"type":"value","value":"#000000"},"fill_alpha":{"type":"value","value":0.2},"hatch_color":{"type":"value","value":"#000000"},"hatch_alpha":{"type":"value","value":0.2}}}}}],"toolbar":{"type":"object","name":"Toolbar","id":"p1270","attributes":{"tools":[{"type":"object","name":"HoverTool","id":"p1341","attributes":{"renderers":"auto","tooltips":[["M\u00eas/Ano","@x{%Y/%m}"],["Comiss\u00e3o Pagamento","@valor"],["Qtd Pedidos","@pedidos"]],"formatters":{"type":"map","entries":[["@x","datetime"]]},"mode":"vline"}}]}},"toolbar_location":null,"left":[{"type":"object","name":"LinearAxis","id":"p1292","attributes":{"ticker":{"type":"object","name":"BasicTicker","id":"p1293","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1294"},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1295"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"right":[{"type":"object","name":"LinearAxis","id":"p1298","attributes":{"y_range_name":"pedidos_range","ticker":{"type":"object","name":"BasicTicker","id":"p1299","attributes":{"mantissas":[1,2,5]}},"formatter":{"type":"object","name":"BasicTickFormatter","id":"p1300"},"axis_label":"Qtd Pedidos","major_label_policy":{"type":"object","name":"AllLabels","id":"p1301"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"below":[{"type":"object","name":"DatetimeAxis","id":"p1273","attributes":{"ticker":{"type":"object","name":"DatetimeTicker","id":"p1274","attributes":{"num_minor_ticks":5,"tickers":[{"type":"object","name":"AdaptiveTicker","id":"p1275","attributes":{"num_minor_ticks":0,"mantissas":[1,2,5],"max_interval":500.0}},{"type":"object","name":"AdaptiveTicker","id":"p1276","attributes":{"num_minor_ticks":0,"base":60,"mantissas":[1,2,5,10,15,20,30],"min_interval":1000.0,"max_interval":1800000.0}},{"type":"object","name":"AdaptiveTicker","id":"p1277","attributes":{"num_minor_ticks":0,"base":24,"mantissas":[1,2,4,6,8,12],"min_interval":3600000.0,"max_interval":43200000.0}},{"type":"object","name":"DaysTicker","id":"p1278","attributes":{"days":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31]}},{"type":"object","name":"DaysTicker","id":"p1279","attributes":{"days":[1,4,7,10,13,16,19,22,25,28]}},{"type":"object","name":"DaysTicker","id":"p1280","attributes":{"days":[1,8,15,22]}},{"type":"object","name":"DaysTicker","id":"p1281","attributes":{"days":[1,15]}},{"type":"object","name":"MonthsTicker","id":"p1282","attributes":{"months":[0,1,2,3,4,5,6,7,8,9,10,11]}},{"type":"object","name":"MonthsTicker","id":"p1283","attributes":{"months":[0,2,4,6,8,10]}},{"type":"object","name":"MonthsTicker","id":"p1284","attributes":{"months":[0,4,8]}},{"type":"object","name":"MonthsTicker","id":"p1285","attributes":{"months":[0,6]}},{"type":"object","name":"YearsTicker","id":"p1286"}]}},"formatter":{"type":"object","name":"DatetimeTickFormatter","id":"p1289","attributes":{"seconds":"%T","minsec":"%T","minutes":"%H:%M","hours":"%H:%M","days":"%b %d","months":"%b %Y","strip_leading_zeros":["microseconds","milliseconds","seconds"],"boundary_scaling":false,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1288","attributes":{"microseconds":"%T","milliseconds":"%T","seconds":"%b %d, %Y","minsec":"%b %d, %Y","minutes":"%b %d, %Y","hourmin":"%b %d, %Y","hours":"%b %d, %Y","days":"%Y","months":"","years":"","boundary_scaling":false,"hide_repeats":true,"context":{"type":"object","name":"DatetimeTickFormatter","id":"p1287","attributes":{"microseconds":"%b %d, %Y","milliseconds":"%b %d, %Y","seconds":"","minsec":"","minutes":"","hourmin":"","hours":"","days":"","months":"","years":"","boundary_scaling":false,"hide_repeats":true}},"context_which":"all"}},"context_which":"all"}},"major_label_policy":{"type":"object","name":"AllLabels","id":"p1290"},"major_label_text_color":"#666666","major_label_text_font_size":"10pt","axis_line_color":null,"major_tick_line_color":null,"minor_tick_line_color":null}}],"center":[{"type":"object","name":"Grid","id":"p1291","attributes":{"axis":{"id":"p1273"},"grid_line_color":null}},{"type":"object","name":"Grid","id":"p1296","attributes":{"dimension":1,"axis":{"id":"p1292"},"grid_line_color":null}},{"type":"object","name":"Legend","id":"p1311","attributes":{"location":"bottom_center","orientation":"horizontal","border_line_color":null,"background_fill_color":null,"click_policy":"hide","label_text_font_size":"10pt","margin":0,"padding":5,"spacing":20,"items":[{"type":"object","name":"LegendItem","id":"p1312","attributes":{"label":{"type":"value","value":"Comiss\u00e3o Pagamento"},"renderers":[{"id":"p1308"},{"id":"p1319"}]}},{"type":"object","name":"LegendItem","id":"p1331","attributes":{"label":{"type":"value","value":"Qtd Pedidos"},"renderers":[{"id":"p1328"},{"id":"p1338"}]}}]}}]}}]}}
    </script>
    <script type="text/javascript">
      (function() {
        const fn = function() {
          Bokeh.safely(function() {
            (function(root) {
              function embed_document(root) {
              const docs_json = document.getElementById('b37e6ec9-90bc-44d4-8796-a2a3ba8670c3').textContent;
              const render_items = [{"docid":"6a4919b1-e6b2-48ec-bd0a-0d8a2957893c","roots":{"p1262":"c9b84e21-3c0a-4758-b5bd-9566c4a2f9e4"},"root_ids":["p1262"]}];
              root.Bokeh.embed.embed_items(docs_json, render_items);
              }
              if (root.Bokeh !== undefined) {
                embed_document(root);
              } else {
                let attempts = 0;
                const timer = setInterval(function(root) {
                  if (root.Bokeh !== undefined) {
                    clearInterval(timer);
                    embed_document(root);
                  } else {
                    attempts++;
                    if (attempts > 100) {
                      clearInterval(timer);
                      console.log("Bokeh: ERROR: Unable to run BokehJS code because BokehJS library is missing");
                    }
                  }
                }, 10, root)
              }
            })(window);
          });
        };
        if (document.readyState != "loading") fn();
        else document.addEventListener("DOMContentLoaded", fn);
      })();
    </script>
  </body>
</html>
            </div>
        </div>
        
        <div class="matriz-container">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Análise Mensal de Pedidos e Descontos</h2>
            <table border="1" class="dataframe min-w-full bg-white rounded-lg overflow-hidden shadow-lg" id="matriz-ifood">
  <thead>
    <tr style="text-align: right;">
      <th>Ano/Mês</th>
      <th>Qtd Pedidos</th>
      <th>Fat. Bruto</th>
      <th>Promoções Loja</th>
      <th>%</th>
      <th>Entrega Loja</th>
      <th>%</th>
      <th>Comissões iFood</th>
      <th>%</th>
      <th>Total Custos iFood</th>
      <th>%</th>
      <th>Incentivos iFood</th>
      <th>%</th>
      <th>Faturamento Líquido</th>
      <th>%</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>2024-11-01</td>
      <td>637</td>
      <td>R$ 40.130,61</td>
      <td>R$ -11.156,67</td>
      <td>27%</td>
      <td>R$ -6.437,74</td>
      <td>16%</td>
      <td>R$ -7.609,05</td>
      <td>18%</td>
      <td>R$ -25.203,46</td>
      <td>62%</td>
      <td>R$ 1.397,41</td>
      <td>3%</td>
      <td>R$ 12.553,10</td>
      <td>31%</td>
    </tr>
    <tr>
      <td>2024-12-01</td>
      <td>650</td>
      <td>R$ 43.520,69</td>
      <td>R$ -12.804,17</td>
      <td>29%</td>
      <td>R$ -7.143,56</td>
      <td>16%</td>
      <td>R$ -8.027,29</td>
      <td>18%</td>
      <td>R$ -27.975,02</td>
      <td>64%</td>
      <td>R$ 953,37</td>
      <td>2%</td>
      <td>R$ 13.254,98</td>
      <td>30%</td>
    </tr>
    <tr>
      <td>2025-01-01</td>
      <td>920</td>
      <td>R$ 61.052,83</td>
      <td>R$ -18.670,43</td>
      <td>30%</td>
      <td>R$ -9.882,84</td>
      <td>16%</td>
      <td>R$ -11.257,51</td>
      <td>18%</td>
      <td>R$ -39.810,78</td>
      <td>65%</td>
      <td>R$ 1.511,50</td>
      <td>2%</td>
      <td>R$ 18.881,87</td>
      <td>30%</td>
    </tr>
    <tr>
      <td>2025-02-01</td>
      <td>917</td>
      <td>R$ 55.491,41</td>
      <td>R$ -16.847,17</td>
      <td>30%</td>
      <td>R$ -8.707,80</td>
      <td>15%</td>
      <td>R$ -10.786,65</td>
      <td>19%</td>
      <td>R$ -36.341,62</td>
      <td>65%</td>
      <td>R$ 2.226,01</td>
      <td>4%</td>
      <td>R$ 18.002,33</td>
      <td>32%</td>
    </tr>
    <tr>
      <td>2025-03-01</td>
      <td>950</td>
      <td>R$ 55.761,30</td>
      <td>R$ -16.946,71</td>
      <td>30%</td>
      <td>R$ -8.876,73</td>
      <td>15%</td>
      <td>R$ -10.924,34</td>
      <td>19%</td>
      <td>R$ -36.747,78</td>
      <td>65%</td>
      <td>R$ 2.785,89</td>
      <td>5%</td>
      <td>R$ 17.082,08</td>
      <td>30%</td>
    </tr>
    <tr>
      <td>2025-04-01</td>
      <td>942</td>
      <td>R$ 60.398,95</td>
      <td>R$ -18.538,57</td>
      <td>30%</td>
      <td>R$ -9.589,65</td>
      <td>15%</td>
      <td>R$ -11.597,21</td>
      <td>19%</td>
      <td>R$ -39.725,43</td>
      <td>65%</td>
      <td>R$ 1.852,04</td>
      <td>3%</td>
      <td>R$ 17.983,90</td>
      <td>29%</td>
    </tr>
    <tr>
      <td>2025-05-01</td>
      <td>769</td>
      <td>R$ 49.750,15</td>
      <td>R$ -14.954,50</td>
      <td>30%</td>
      <td>R$ -8.091,31</td>
      <td>16%</td>
      <td>R$ -9.793,42</td>
      <td>19%</td>
      <td>R$ -32.839,23</td>
      <td>66%</td>
      <td>R$ 1.736,57</td>
      <td>3%</td>
      <td>R$ 15.802,82</td>
      <td>31%</td>
    </tr>
  </tbody>
</table>
        </div>
    </main>
</body>
</html>
