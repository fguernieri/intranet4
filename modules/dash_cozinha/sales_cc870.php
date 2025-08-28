<?php
// Retorna quantidades vendidas por produto a partir da API Cloudify (CC870 + produtos)
// Parâmetros: GET inicio=YYYY-MM-DD, fim=YYYY-MM-DD, filial=1|2

header('Content-Type: application/json; charset=utf-8');

$inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-01');
$fim    = isset($_GET['fim'])    ? $_GET['fim']    : date('Y-m-d');
$filial = isset($_GET['filial']) ? (int)$_GET['filial'] : 1;

// Credenciais (mover para config se desejar)
$accKey   = 'bjJ4FQvmuA6AoHNDjsDU6893bastards';
$tokenKey = 'BHzQaSYPd0dFZPuGQzzR2vnCjwTTfH16wghmEps9HLIHq3Lbnpr6893870e56ec8';
$loginUsr = 'integracaoapis@bastards.com';
$nomeUsr  = 'INTEGRACAO API BASTARDS';
$codEmp   = 798;

// Monta payload para CFYCC870 consultando itens de produtos
$payload = [
  'Parameters' => [
    'AccKey'   => $accKey,
    'TokenKey' => $tokenKey,
    'ApiName'  => 'CFYCC870',
    'Data'     => [
      'Solicitante' => [
        'LoginUsr'   => $loginUsr,
        'NomeUsr'    => $nomeUsr,
        'CodEmpresa' => $codEmp,
        'CodFilial'  => $filial,
      ],
      'Filtros' => [
        'DataInicio'                    => str_replace('-', '', $inicio),
        'DataFim'                       => str_replace('-', '', $fim),
        'IdentifConsultaProd'           => 1,
        'IdentifConsultaProdCancelados' => 0,
      ],
    ],
  ],
];

$ch = curl_init('https://api.cloudfy.net.br/ApiCFYCC');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$resp = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false) {
  echo json_encode(['error' => 'curl_error', 'message' => $err]);
  exit;
}

$data = json_decode($resp, true);
if (!is_array($data)) {
  echo json_encode(['error' => 'invalid_json']);
  exit;
}

// Navega nas possíveis chaves conforme variações de retorno
$cupons = [];
if (isset($data['Resultado']['CuponsVenda'])) {
  $cupons = $data['Resultado']['CuponsVenda'];
} elseif (isset($data['CuponsVenda'])) {
  $cupons = $data['CuponsVenda'];
}

$agg = [];
foreach ($cupons as $cupom) {
  if (!isset($cupom['Produtos']) || !is_array($cupom['Produtos'])) continue;
  foreach ($cupom['Produtos'] as $p) {
    $codigo = $p['CodRefProduto'] ?? $p['CodRef'] ?? null;
    $nome   = $p['Produto'] ?? ($p['Descricao'] ?? '');
    $qtd    = isset($p['Qtde']) ? (float)$p['Qtde'] : (isset($p['Quantidade']) ? (float)$p['Quantidade'] : 0);
    // Ignora itens explicitamente cancelados quando houver descrição textual
    if (isset($p['DescSituacaoItem']) && stripos($p['DescSituacaoItem'], 'cancel') !== false) continue;
    if ($codigo === null && $nome === '') continue;

    $key = $codigo ? ('c:' . $codigo) : ('n:' . mb_strtolower($nome, 'UTF-8'));
    if (!isset($agg[$key])) {
      $agg[$key] = [
        'codigo' => $codigo,
        'nome'   => $nome,
        'quantidade' => 0.0,
      ];
    }
    $agg[$key]['quantidade'] += $qtd;
  }
}

echo json_encode([
  'params' => ['inicio'=>$inicio,'fim'=>$fim,'filial'=>$filial],
  'vendas' => array_values($agg),
]);
exit;
?>

