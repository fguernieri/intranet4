$file = "c:\xampp\htdocs\modules\financeiro_bar\index2.php"
$content = Get-Content $file -Raw -Encoding UTF8

# Contador de substituições
$count = 0

# Padrão mais específico: buscar todas as ocorrências de <div> seguido por <div class="font-medium"
while ($content -match '(<div class="flex items-start justify-between">\s*)<div>\s*\n\s*<div class="font-medium text-sm text-gray-100">\<\?= htmlspecialchars\(\$detalhe\[''descricao''\] \?\? ''SEM DESCRIÇÃO''\) \?\></div>') {
    $oldPattern = $matches[0]
    $prefix = $matches[1]
    
    $newPattern = $prefix + @'
<div class="flex-1">
                                                                <div class="flex items-center gap-2 mb-1">
                                                                    <div class="font-medium text-sm text-gray-100"><?= htmlspecialchars($detalhe['descricao'] ?? 'SEM DESCRIÇÃO') ?></div>
                                                                    <?= renderStatusBadge($detalhe) ?>
                                                                </div>
'@
    
    $content = $content.Replace($oldPattern, $newPattern)
    $count++
    
    Write-Host "Substituição $count realizada"
    
    # Prevenir loop infinito
    if ($count -gt 20) { break }
}

# Salvar o arquivo
$content | Set-Content $file -NoNewline -Encoding UTF8

Write-Host "Total de substituições: $count"
