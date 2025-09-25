<?php
require_once '../../config/db.php';
session_start();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function compressImage($sourcePath, $destinationPath, $maxFileSize = 512000) {
    $info = getimagesize($sourcePath);
    $mime = $info['mime'];
    $quality = 85;

    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $image = imagecreatefromjpeg($sourcePath);
        do {
            ob_start();
            imagejpeg($image, null, $quality);
            $data = ob_get_clean();
            $quality -= 5;
        } while (strlen($data) > $maxFileSize && $quality > 10);
        file_put_contents($destinationPath, $data);
        imagedestroy($image);
    } elseif ($mime === 'image/png') {
        $image = imagecreatefrompng($sourcePath);
        $width = imagesx($image);
        $height = imagesy($image);
        $resized = imagescale($image, $width * 0.9, $height * 0.9);
        imagepng($resized, $destinationPath, 9);
        imagedestroy($image);
        imagedestroy($resized);
    }
}

function logHistorico($pdo, $id, $usuario_logado, $antigo, $novo) {
    foreach ($novo as $campo => $valor_novo) {
        $valor_antigo = $antigo[$campo] ?? null;
        if (trim((string)$valor_antigo) !== trim((string)$valor_novo)) {
            $stmt = $pdo->prepare("INSERT INTO historico (ficha_id, campo_alterado, valor_antigo, valor_novo, usuario)
                                   VALUES (:ficha_id, :campo, :antigo, :novo, :usuario)");
            $stmt->execute([
                ':ficha_id' => $id,
                ':campo'    => $campo,
                ':antigo'   => $valor_antigo,
                ':novo'     => $valor_novo,
                ':usuario'  => $usuario_logado
            ]);
        }
    }
}

try {
    $id         = $_POST['id'];
    $nome       = $_POST['nome_prato'];
    $rendimento = $_POST['rendimento'];
    $modo       = $_POST['modo_preparo'];
    $responsavel = $_POST['usuario']; // <-- campo do formulário
    $usuario_logado = $_SESSION['usuario_nome'] ?? 'sistema'; // <-- nome do usuário logado
    $cloudify   = $_POST['codigo_cloudify'] ?? '';
    $base_origem = strtoupper($_POST['base_origem'] ?? 'WAB');

    if (!in_array($base_origem, ['WAB', 'BDF'], true)) {
        throw new Exception('Base de origem inválida.');
    }

    $descricao  = $_POST['descricao'];
    $quantidade = $_POST['quantidade'];
    $unidade    = $_POST['unidade'];
    $codigo     = $_POST['codigo'];
    $ingred_ids = $_POST['ingrediente_id'];
    $excluir    = $_POST['excluir'];
    $ativo_wab = isset($_POST['ativo_wab']) ? 1 : 0;
    $ativo_bdf_almoco = isset($_POST['ativo_bdf_almoco']) ? 1 : 0;
    $ativo_bdf_almoco_fds = isset($_POST['ativo_bdf_almoco_fds']) ? 1 : 0;
    $ativo_bdf_noite = isset($_POST['ativo_bdf_noite']) ? 1 : 0;
    $remover_imagem = isset($_POST['remover_imagem']) && $_POST['remover_imagem'] === '1';


    $pdo->beginTransaction();

    // Buscar ficha antiga
    $stmt = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $antigo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$antigo) throw new Exception("Ficha técnica não encontrada.");

    // Upload e remoção de imagem
    $imagem_nome = $antigo['imagem'];
    $caminhoImagemAntiga = $imagem_nome ? __DIR__ . '/uploads/' . $imagem_nome : null;
    $novoUpload = isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK;

    if ($novoUpload) {
        $tmp_name = $_FILES['imagem']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $mime_type = mime_content_type($tmp_name);

        if ($mime_type === 'image/heic' || $ext === 'heic') {
            throw new Exception('Formato HEIC não suportado.');
        }

        $imagem_nome = uniqid('prato_') . '.' . $ext;
        $destinoDir = __DIR__ . '/uploads';

        if (!is_dir($destinoDir)) {
            if (!mkdir($destinoDir, 0755, true) && !is_dir($destinoDir)) {
                throw new Exception('Não foi possível criar a pasta de uploads.');
            }
        }

        $destino = $destinoDir . '/' . $imagem_nome;

        if (!move_uploaded_file($tmp_name, $destino)) {
            throw new Exception('Erro ao salvar imagem.');
        }

        compressImage($destino, $destino);

        if ($caminhoImagemAntiga && is_file($caminhoImagemAntiga)) {
            unlink($caminhoImagemAntiga);
        }
    } elseif ($remover_imagem) {
        if ($caminhoImagemAntiga && is_file($caminhoImagemAntiga)) {
            unlink($caminhoImagemAntiga);
        }
        $imagem_nome = null;
    }

    // Atualizar ficha técnica
    $stmt = $pdo->prepare("UPDATE ficha_tecnica SET
        nome_prato = :nome, rendimento = :rendimento, modo_preparo = :modo,
        imagem = :imagem, usuario = :responsavel, codigo_cloudify = :codigo, base_origem = :base_origem,
        ativo_wab = :ativo_wab, ativo_bdf_noite = :ativo_bdf_noite, ativo_bdf_almoco = :ativo_bdf_almoco,
        ativo_bdf_almoco_fds = :ativo_bdf_almoco_fds
        WHERE id = :id");
        
    $stmt->execute([
        ':nome'       => $nome,
        ':rendimento' => $rendimento,
        ':modo'       => $modo,
        ':imagem'     => $imagem_nome,
        ':responsavel'=> $responsavel,
        ':codigo'     => $cloudify,
        ':base_origem'=> $base_origem,
        ':ativo_wab'  => $ativo_wab,
        ':ativo_bdf_almoco'     => $ativo_bdf_almoco,
        ':ativo_bdf_almoco_fds' => $ativo_bdf_almoco_fds,
        ':ativo_bdf_noite'      => $ativo_bdf_noite,
        ':id'         => $id
    ]);

    // Log de alterações
    $novo = [
        'nome_prato'      => $nome,
        'rendimento'      => $rendimento,
        'modo_preparo'    => $modo,
        'usuario'         => $responsavel, // aqui continua como "usuario" pois é o nome do campo na tabela
        'codigo_cloudify' => $cloudify,
        'base_origem'     => $base_origem,
        'ativo_wab'       => $ativo_wab,
        'ativo_bdf_almoco'     => $ativo_bdf_almoco,
        'ativo_bdf_almoco_fds' => $ativo_bdf_almoco_fds,
        'ativo_bdf_noite'      => $ativo_bdf_noite
        
    ];
    logHistorico($pdo, $id, $usuario_logado, $antigo, $novo);

    // Atualizar ingredientes
    foreach ($descricao as $i => $desc) {
        $remover = $excluir[$i] ?? '0';
        $ing_id  = $ingred_ids[$i];

        if ($remover === '1' && $ing_id) {
            $stmt = $pdo->prepare("DELETE FROM ingredientes WHERE id = :id");
            $stmt->execute([':id' => $ing_id]);
            continue;
        }

        if (!trim($desc) || !trim($quantidade[$i]) || !trim($unidade[$i])) continue;

        if ($ing_id) {
            $stmt = $pdo->prepare("UPDATE ingredientes SET 
                codigo = :codigo, descricao = :descricao, quantidade = :quantidade, unidade = :unidade 
                WHERE id = :id");
            $stmt->execute([
                ':codigo'     => $codigo[$i],
                ':descricao'  => $desc,
                ':quantidade' => $quantidade[$i],
                ':unidade'    => $unidade[$i],
                ':id'         => $ing_id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ingredientes 
                (ficha_id, codigo, descricao, quantidade, unidade) 
                VALUES (:ficha_id, :codigo, :descricao, :quantidade, :unidade)");
            $stmt->execute([
                ':ficha_id'   => $id,
                ':codigo'     => $codigo[$i],
                ':descricao'  => $desc,
                ':quantidade' => $quantidade[$i],
                ':unidade'    => $unidade[$i]
            ]);
        }
    }

    $pdo->commit();
    header("Location: visualizar_ficha.php?id=$id&sucesso=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("❌ Erro ao salvar edição: " . $e->getMessage());
    echo "Erro: " . $e->getMessage();
}
