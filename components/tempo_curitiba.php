<?php
$apiKey = '7278bbc757936888cbdb74f41f57eeb4';
$lat = '-25.4284';
$lon = '-49.2733';

$url = "https://api.openweathermap.org/data/3.0/onecall?lat=$lat&lon=$lon&exclude=hourly,minutely,current,alerts&units=metric&lang=pt_br&appid=$apiKey";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo '<pre>Erro na requisição: ' . curl_error($ch) . '</pre>';
    exit;
}

$data = json_decode($response, true);

if (!isset($data['daily'])) {
    echo '<pre>Resposta da API:</pre>';
    echo '<pre>' . print_r($data, true) . '</pre>';
    exit;
}

$data = json_decode($response, true);
$daily = $data['daily'] ?? [];

function formatarDia($timestamp) {
    $formatter = new IntlDateFormatter(
        'pt_BR',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        'America/Sao_Paulo',
        IntlDateFormatter::GREGORIAN,
        'EEEE'
    );
    return ucfirst($formatter->format(new DateTime("@$timestamp")));
}

setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
?>
<div class="rounded-xl p-4 w-min-full mx-auto bg-white/5 border border-white/10 backdrop-blur-sm shadow-sm mb-6">
  <h2 class="text-xl font-bold text-center text-blue-400 mb-4">🌦️ Previsão para Curitiba</h2>
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 text-white">
    <?php foreach (array_slice($daily, 0, 5) as $dia): ?>
      <?php
        $icone = $dia['weather'][0]['icon'];
        $descricao = ucfirst($dia['weather'][0]['description']);
        $diaSemana = ucfirst(formatarDia($dia['dt']));
        $img = "https://openweathermap.org/img/wn/{$icone}@2x.png";      ?>
      <div class="bg-white/10 rounded-lg p-3 text-center border border-white/10 hover:bg-white/20 transition">
        <div class="text-sm font-semibold mb-1 text-blue-300"><?= $diaSemana ?></div>
        <img src="<?= $img ?>" class="w-12 h-12 mx-auto" alt="<?= $descricao ?>">
        <div class="text-sm mt-1 text-gray-200"><?= $descricao ?></div>
        <div class="mt-2 text-lg">
          <span class="text-green-400 font-bold"><?= round($dia['temp']['max']) ?>°C</span> /
          <span class="text-gray-300"><?= round($dia['temp']['min']) ?>°C</span>
        </div>

        <?php if (isset($dia['rain'])): ?>
          <div class="text-sm text-blue-200 mt-1">
            🌧️ <?= $dia['rain'] ?> mm de chuva
          </div>
        <?php endif; ?>      </div>
    <?php endforeach; ?>
  </div>
</div>
