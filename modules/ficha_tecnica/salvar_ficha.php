<?php
require_once '../../config/db.php';
session_start();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- Compressão de imagem ---
function compressImage($sourcePath, $destinationPath, $maxFileSize = 512000) {
    $info = getimagesize($sourcePath);
    $mime = $info['mime'];
    $quality = 85;

    if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
        $image = imagecreatefromjpeg($sourcePath);
        do {
            ob_start();
            imagejpeg($image, null, $quality);
            $data = ob_get_clean();
            $quality -= 5;
        } while (strlen($data) > $maxFileSize && $quality > 10);
        file_put_contents($destinationPath, $data);
        imagedestroy($image);
    } elseif ($mime == 'image/png') {
        $image = imagecreatefrompng($sourcePath);
        $width = imagesx($image);
        $height = imagesy($image);
        $resized = imagescale($image, $width * 0.9, $height * 0.9);
        imagepng($resized, $destinationPath, 9);
        imagedestroy($image);
        imagedestroy($resized);
    }
}
// --- Fim da compressão ---

try {
    $nome            = $_POST['nome_prato'];
    $rendimento      = $_POST['rendimento'];
    $modo_preparo    = $_POST['modo_preparo'];
    $responsavel     = $_POST['usuario']; // este vai para ficha
    $codigo_cloudify = $_POST['codigo_cloudify'] ?? null;
    $base_origem     = strtoupper($_POST['base_origem'] ?? 'WAB');
    $usuario_logado  = $_SESSION['usuario_nome'] ?? 'sistema'; // este vai para histórico

    if (!in_array($base_origem, ['WAB', 'BDF'], true)) {
        throw new Exception('Base de origem inválida. Selecione WAB ou BDF.');
    }

    $ingredientes = $_POST['descricao'] ?? [];
    $codigos      = $_POST['codigo'] ?? [];
    $quantidades  = $_POST['quantidade'] ?? [];
    $unidades     = $_POST['unidade'] ?? [];

    if (count($ingredientes) === 0 || empty($ingredientes[0])) {
        throw new Exception('É necessário adicionar pelo menos um ingrediente.');
    }

    // 📷 Upload da imagem
    $imagem_nome = null;

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $tmp_name  = $_FILES['imagem']['tmp_name'];
        $mime_type = mime_content_type($tmp_name);
        $ext       = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));

        if ($mime_type === 'image/heic' || $ext === 'heic') {
            throw new Exception('Imagens HEIC não são suportadas. Envie JPG ou PNG.');
        }

        $imagem_nome = uniqid('prato_') . '.' . $ext;
        $destino     = 'uploads/' . $imagem_nome;

        if (!move_uploaded_file($tmp_name, $destino)) {
            throw new Exception('Erro ao mover a imagem para a pasta uploads.');
        }

        compressImage($destino, $destino);
    }

    // 📝 Inserir ficha técnica
    $stmt = $pdo->prepare("INSERT INTO ficha_tecnica
        (nome_prato, rendimento, modo_preparo, imagem, usuario, codigo_cloudify, base_origem)
        VALUES (:nome, :rendimento, :modo, :imagem, :usuario, :cloudify, :base)");
    $stmt->execute([
        ':nome'      => $nome,
        ':rendimento'=> $rendimento,
        ':modo'      => $modo_preparo,
        ':imagem'    => $imagem_nome,
        ':usuario'   => $responsavel,
        ':cloudify'  => $codigo_cloudify,
        ':base'      => $base_origem
    ]);

    $ficha_id = $pdo->lastInsertId();

    // 🧠 Histórico de criação
    $stmt = $pdo->prepare("INSERT INTO historico 
        (ficha_id, campo_alterado, valor_antigo, valor_novo, usuario)
        VALUES (:ficha_id, 'criação', '', 'Ficha técnica criada', :usuario)");
    $stmt->execute([
        ':ficha_id' => $ficha_id,
        ':usuario'  => $usuario_logado
    ]);

    // ➕ Ingredientes
    for ($i = 0; $i < count($ingredientes); $i++) {
        if (!empty($ingredientes[$i]) && !empty($quantidades[$i]) && !empty($unidades[$i])) {
            $stmt = $pdo->prepare("INSERT INTO ingredientes 
                (ficha_id, codigo, descricao, quantidade, unidade)
                VALUES (:ficha_id, :codigo, :descricao, :quantidade, :unidade)");
            $stmt->execute([
                ':ficha_id'   => $ficha_id,
                ':codigo'     => $codigos[$i],
                ':descricao'  => $ingredientes[$i],
                ':quantidade' => $quantidades[$i],
                ':unidade'    => $unidades[$i]
            ]);
        }
    }

    // 🔁 Redireciona para visualização
    header("Location: visualizar_ficha.php?id=$ficha_id&sucesso=1");
    exit;

} catch (Exception $e) {
    echo "Erro ao cadastrar ficha: " . $e->getMessage();
}
