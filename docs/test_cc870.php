<?php
// Tester da API Cloudify CC870 (Cupons de Venda) com campos customizáveis
// - Renderiza um form com todos os filtros da CC870
// - Envia a requisição (JSON) e mostra a resposta na mesma página

ini_set('display_errors', 1);
error_reporting(E_ALL);

$response = null;
$request  = null;
$error    = null;

// Defaults (pode ajustar conforme necessário)
$defaults = [
  'AccKey'     => 'bjJ4FQvmuA6AoHNDjsDU6893bastards',
  'TokenKey'   => 'BHzQaSYPd0dFZPuGQzzR2vnCjwTTfH16wghmEps9HLIHq3Lbnpr6893870e56ec8',
  'ApiName'    => 'CFYCC870',
  'LoginUsr'   => 'integracaoapis@bastards.com',
  'NomeUsr'    => 'INTEGRACAO API BASTARDS',
  'CodEmpresa' => '798',
  'CodFilial'  => '1',
  // Filtros padrão: mês atual
  'DataInicio' => date('Ym01'),
  'DataFim'    => date('Ymd'),
];

function toIntOrNull($v) { $v = trim((string)$v); return ($v === '') ? null : (int)$v; }
function toStrOrNull($v) { $v = trim((string)$v); return ($v === '') ? null : $v; }
function ymdFromHtml($v) {
  $v = trim((string)$v);
  if ($v === '') return null;
  // Aceita já em AAAAMMDD
  if (preg_match('/^\d{8}$/', $v)) return $v;
  // Converte AAAA-MM-DD -> AAAAMMDD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return str_replace('-', '', $v);
  // Tenta normalizar qualquer coisa numérica
  $digits = preg_replace('/\D+/', '', $v);
  return strlen($digits) === 8 ? $digits : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Coleta
  $AccKey     = toStrOrNull($_POST['AccKey']     ?? $defaults['AccKey']);
  $TokenKey   = toStrOrNull($_POST['TokenKey']   ?? $defaults['TokenKey']);
  $ApiName    = toStrOrNull($_POST['ApiName']    ?? $defaults['ApiName']);

  $LoginUsr   = toStrOrNull($_POST['LoginUsr']   ?? $defaults['LoginUsr']);
  $NomeUsr    = toStrOrNull($_POST['NomeUsr']    ?? $defaults['NomeUsr']);
  $CodEmpresa = toIntOrNull($_POST['CodEmpresa'] ?? $defaults['CodEmpresa']);
  $CodFilial  = toIntOrNull($_POST['CodFilial']  ?? $defaults['CodFilial']);

  // Filtros
  $DataInicio = ymdFromHtml($_POST['DataInicio'] ?? $defaults['DataInicio']);
  $DataFim    = ymdFromHtml($_POST['DataFim']    ?? $defaults['DataFim']);
  $NrCupom    = toIntOrNull($_POST['NrCupom']    ?? null);
  $NrCaixa    = toIntOrNull($_POST['NrCaixa']    ?? null);
  $CodRefProduto             = toStrOrNull($_POST['CodRefProduto'] ?? null);
  $CodGrupoProduto           = toIntOrNull($_POST['CodGrupoProduto'] ?? null);
  $CodCliente                = toIntOrNull($_POST['CodCliente'] ?? null);
  $CPFCNPJCliente            = toStrOrNull($_POST['CPFCNPJCliente'] ?? null);
  $CodOperadorCaixa          = toIntOrNull($_POST['CodOperadorCaixa'] ?? null);
  $CodAtendente              = toIntOrNull($_POST['CodAtendente'] ?? null);
  $CodFormaPagamento         = toIntOrNull($_POST['CodFormaPagamento'] ?? null);
  $SituacaoCupom             = toIntOrNull($_POST['SituacaoCupom'] ?? null);
  $IdentifDesconto           = toIntOrNull($_POST['IdentifDesconto'] ?? null);
  $IdentifTaxaServico        = toIntOrNull($_POST['IdentifTaxaServico'] ?? null);
  $IdentifConsultaProd       = toIntOrNull($_POST['IdentifConsultaProd'] ?? 1);
  $IdentifConsultaFormaPagto = toIntOrNull($_POST['IdentifConsultaFormaPagto'] ?? 0);
  $IdentifConsultaProdCancelados = toIntOrNull($_POST['IdentifConsultaProdCancelados'] ?? 0);

  // Monta payload
  $payload = [
    'Parameters' => [
      'AccKey'   => $AccKey,
      'TokenKey' => $TokenKey,
      'ApiName'  => $ApiName,
      'Data'     => [
        'Solicitante' => [
          'LoginUsr'   => $LoginUsr,
          'NomeUsr'    => $NomeUsr,
          'CodEmpresa' => $CodEmpresa,
          'CodFilial'  => $CodFilial,
        ],
        'Filtros' => []
      ],
    ],
  ];

  // Adiciona apenas filtros informados
  $f = &$payload['Parameters']['Data']['Filtros'];
  foreach ([
    'DataInicio' => $DataInicio,
    'DataFim'    => $DataFim,
    'NrCupom'    => $NrCupom,
    'NrCaixa'    => $NrCaixa,
    'CodRefProduto' => $CodRefProduto,
    'CodGrupoProduto' => $CodGrupoProduto,
    'CodCliente'  => $CodCliente,
    'CPFCNPJCliente' => $CPFCNPJCliente,
    'CodOperadorCaixa' => $CodOperadorCaixa,
    'CodAtendente' => $CodAtendente,
    'CodFormaPagamento' => $CodFormaPagamento,
    'SituacaoCupom' => $SituacaoCupom,
    'IdentifDesconto' => $IdentifDesconto,
    'IdentifTaxaServico' => $IdentifTaxaServico,
    'IdentifConsultaProd' => $IdentifConsultaProd,
    'IdentifConsultaFormaPagto' => $IdentifConsultaFormaPagto,
    'IdentifConsultaProdCancelados' => $IdentifConsultaProdCancelados,
  ] as $k => $v) {
    if ($v !== null && $k !== '') { $f[$k] = $v; }
  }

  $request = $payload;

  // Chamada HTTP
  $ch = curl_init('https://api.cloudfy.net.br/ApiCFYCC');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  $raw = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $curlErr  = curl_error($ch);
  curl_close($ch);

  if ($raw === false) {
    $error = 'Erro cURL: ' . $curlErr;
  } else if ($httpCode !== 200) {
    $error = 'HTTP ' . $httpCode . ': ' . $raw;
  } else {
    $json = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $error = 'Erro ao decodificar JSON: ' . json_last_error_msg();
    } else {
      $response = $json;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Teste API Cloudify - CC870</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 16px; background:#0f172a; color:#e5e7eb; }
    h1 { color: #facc15; }
    fieldset { border:1px solid #334155; padding:12px; margin-bottom:12px; }
    legend { color:#fbbf24; padding:0 6px; }
    label { display:block; margin:6px 0; }
    input[type=text], input[type=number], input[type=date] { width: 290px; padding:6px; background:#111827; color:#e5e7eb; border:1px solid #374151; border-radius:4px; }
    .btn { background:#f59e0b; color:#111827; padding:8px 14px; border:none; border-radius:6px; font-weight:bold; cursor:pointer; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:10px; }
    textarea { width:100%; height:220px; background:#0b1220; color:#e5e7eb; border:1px solid #334155; border-radius:6px; padding:8px; }
    .box { background:#0b1220; padding:10px; border-radius:6px; border:1px solid #334155; }
    .error { color:#f87171; font-weight:bold; }
    .muted { color:#94a3b8; font-size:12px; }
  </style>
  <script>
    function toYMD(idSrc, idDst){
      const v = document.getElementById(idSrc).value; // yyyy-mm-dd
      if(!v){ document.getElementById(idDst).value=''; return; }
      document.getElementById(idDst).value = v.replaceAll('-', '');
    }
  </script>
  </head>
<body>
  <h1>Teste API Cloudify — CC870</h1>
  <form method="post">
    <fieldset>
      <legend>Credenciais</legend>
      <div class="grid">
        <label>AccKey<br><input type="text" name="AccKey" value="<?= htmlspecialchars($_POST['AccKey'] ?? $defaults['AccKey']) ?>" required></label>
        <label>TokenKey<br><input type="text" name="TokenKey" value="<?= htmlspecialchars($_POST['TokenKey'] ?? $defaults['TokenKey']) ?>" required></label>
        <label>ApiName<br><input type="text" name="ApiName" value="<?= htmlspecialchars($_POST['ApiName'] ?? $defaults['ApiName']) ?>" required></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Solicitante</legend>
      <div class="grid">
        <label>LoginUsr<br><input type="text" name="LoginUsr" value="<?= htmlspecialchars($_POST['LoginUsr'] ?? $defaults['LoginUsr']) ?>" required></label>
        <label>NomeUsr<br><input type="text" name="NomeUsr" value="<?= htmlspecialchars($_POST['NomeUsr'] ?? $defaults['NomeUsr']) ?>" required></label>
        <label>CodEmpresa<br><input type="number" name="CodEmpresa" value="<?= htmlspecialchars($_POST['CodEmpresa'] ?? $defaults['CodEmpresa']) ?>" required></label>
        <label>CodFilial<br><input type="number" name="CodFilial" value="<?= htmlspecialchars($_POST['CodFilial'] ?? $defaults['CodFilial']) ?>" required></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Filtros</legend>
      <div class="grid">
        <label>Data Início (HTML)
          <input type="date" id="di_html" value="<?= htmlspecialchars(date('Y-m-d', strtotime(($_POST['DataInicio'] ?? $defaults['DataInicio'])))) ?>" onchange="toYMD('di_html','di_ymd')">
          <div class="muted">Converte para AAAAMMDD automaticamente</div>
        </label>
        <label>Data Início (AAAAMMDD)
          <input type="text" id="di_ymd" name="DataInicio" value="<?= htmlspecialchars($_POST['DataInicio'] ?? $defaults['DataInicio']) ?>">
        </label>
        <label>Data Fim (HTML)
          <input type="date" id="df_html" value="<?= htmlspecialchars(date('Y-m-d', strtotime(($_POST['DataFim'] ?? $defaults['DataFim'])))) ?>" onchange="toYMD('df_html','df_ymd')">
        </label>
        <label>Data Fim (AAAAMMDD)
          <input type="text" id="df_ymd" name="DataFim" value="<?= htmlspecialchars($_POST['DataFim'] ?? $defaults['DataFim']) ?>">
        </label>

        <label>NrCupom<br><input type="number" name="NrCupom" value="<?= htmlspecialchars($_POST['NrCupom'] ?? '') ?>"></label>
        <label>NrCaixa<br><input type="number" name="NrCaixa" value="<?= htmlspecialchars($_POST['NrCaixa'] ?? '') ?>"></label>
        <label>CodRefProduto<br><input type="text" name="CodRefProduto" value="<?= htmlspecialchars($_POST['CodRefProduto'] ?? '') ?>"></label>
        <label>CodGrupoProduto<br><input type="number" name="CodGrupoProduto" value="<?= htmlspecialchars($_POST['CodGrupoProduto'] ?? '') ?>"></label>
        <label>CodCliente<br><input type="number" name="CodCliente" value="<?= htmlspecialchars($_POST['CodCliente'] ?? '') ?>"></label>
        <label>CPFCNPJCliente<br><input type="text" name="CPFCNPJCliente" value="<?= htmlspecialchars($_POST['CPFCNPJCliente'] ?? '') ?>"></label>
        <label>CodOperadorCaixa<br><input type="number" name="CodOperadorCaixa" value="<?= htmlspecialchars($_POST['CodOperadorCaixa'] ?? '') ?>"></label>
        <label>CodAtendente<br><input type="number" name="CodAtendente" value="<?= htmlspecialchars($_POST['CodAtendente'] ?? '') ?>"></label>
        <label>CodFormaPagamento<br><input type="number" name="CodFormaPagamento" value="<?= htmlspecialchars($_POST['CodFormaPagamento'] ?? '') ?>"></label>
        <label>SituacaoCupom<br><input type="number" name="SituacaoCupom" value="<?= htmlspecialchars($_POST['SituacaoCupom'] ?? '') ?>"></label>

        <label>IdentifDesconto (0/1)<br><input type="number" name="IdentifDesconto" value="<?= htmlspecialchars($_POST['IdentifDesconto'] ?? '') ?>"></label>
        <label>IdentifTaxaServico (0/1)<br><input type="number" name="IdentifTaxaServico" value="<?= htmlspecialchars($_POST['IdentifTaxaServico'] ?? '') ?>"></label>
        <label>IdentifConsultaProd (0/1)<br><input type="number" name="IdentifConsultaProd" value="<?= htmlspecialchars($_POST['IdentifConsultaProd'] ?? '1') ?>"></label>
        <label>IdentifConsultaFormaPagto (0/1)<br><input type="number" name="IdentifConsultaFormaPagto" value="<?= htmlspecialchars($_POST['IdentifConsultaFormaPagto'] ?? '0') ?>"></label>
        <label>IdentifConsultaProdCancelados (0/1)<br><input type="number" name="IdentifConsultaProdCancelados" value="<?= htmlspecialchars($_POST['IdentifConsultaProdCancelados'] ?? '0') ?>"></label>
      </div>
    </fieldset>

    <button type="submit" class="btn">Enviar consulta</button>
  </form>

  <?php if ($request): ?>
    <h2>Request JSON</h2>
    <div class="box">
      <textarea readonly><?= htmlspecialchars(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($response): ?>
    <h2>Response</h2>
    <div class="box">
      <textarea readonly><?= htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
    </div>
  <?php endif; ?>
</body>
</html>

