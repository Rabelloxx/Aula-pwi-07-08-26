<?php
// ===== CONFIGURAÇÃO DO BANCO DE DADOS (ajuste conforme seu MySQL local) =====
$dbHost = "localhost";
$dbName = "formulario_db";
$dbUser = "root";
$dbPass = "";

// Inicializa variáveis de feedback
$mensagem = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta e higieniza os dados de entrada
    $nome  = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $idade = filter_input(INPUT_POST, 'idade', FILTER_VALIDATE_INT);
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    // Validação básica dos campos
    if (empty($nome) || empty($email) || $idade === false || $idade === null) {
        $mensagem = "Por favor, preencha todos os campos corretamente.";
        $status = "erro";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "O e-mail informado é inválido.";
        $status = "erro";

    // ===== 1. VALIDAÇÃO DE SENHA E CONFIRMAÇÃO =====
    } elseif ($senha !== $confirmarSenha) {
        $mensagem = "As senhas não coincidem. Digite a mesma senha nos dois campos.";
        $status = "erro";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve possuir no mínimo 6 caracteres.";
        $status = "erro";

    // ===== 2. UPLOAD DE ARQUIVO (CURRÍCULO EM PDF) =====
    } elseif (!isset($_FILES['curriculo']) || $_FILES['curriculo']['error'] === UPLOAD_ERR_NO_FILE) {
        $mensagem = "Por favor, anexe o seu currículo em PDF.";
        $status = "erro";
    } elseif ($_FILES['curriculo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['curriculo']['error'] === UPLOAD_ERR_FORM_SIZE) {
        $mensagem = "O arquivo excede o tamanho máximo permitido (2MB).";
        $status = "erro";
    } elseif ($_FILES['curriculo']['error'] !== UPLOAD_ERR_OK) {
        $mensagem = "Erro ao enviar o arquivo. Tente novamente.";
        $status = "erro";
    } else {
        $arquivo = $_FILES['curriculo'];
        $tamanhoMaximo = 2 * 1024 * 1024; // 2MB

        // Verificações estritas de PDF: extensão + tipo MIME real + assinatura "%PDF"
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($arquivo['tmp_name']);
        $assinatura = file_get_contents($arquivo['tmp_name'], false, null, 0, 4);

        if ($extensao !== 'pdf' || $mime !== 'application/pdf' || $assinatura !== '%PDF') {
            $mensagem = "O arquivo enviado deve ser estritamente um PDF.";
            $status = "erro";
        } elseif ($arquivo['size'] >= $tamanhoMaximo) {
            $mensagem = "O arquivo deve ter menos de 2MB.";
            $status = "erro";
        } else {
            // Cria a pasta uploads/ se não existir
            $pastaUploads = __DIR__ . "/uploads/";
            if (!is_dir($pastaUploads)) {
                mkdir($pastaUploads, 0755, true);
            }

            // Nome único para evitar sobrescrever arquivos
            $nomeArquivo = uniqid('curriculo_', true) . ".pdf";
            $destino = $pastaUploads . $nomeArquivo;

            if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
                $mensagem = "Não foi possível salvar o arquivo no servidor.";
                $status = "erro";
            } else {
                // ===== 3. PERSISTÊNCIA EM BANCO DE DADOS MYSQL (PDO + PREPARED STATEMENTS) =====
                try {
                    $pdo = new PDO(
                        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
                        $dbUser,
                        $dbPass,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]
                    );

                    $sql = "INSERT INTO usuarios (nome, email, idade) VALUES (:nome, :email, :idade)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
                    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                    $stmt->bindValue(':idade', $idade, PDO::PARAM_INT);
                    $stmt->execute();

                    $mensagem = "Cadastro de <strong>" . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . "</strong> realizado com sucesso! Currículo enviado e dados salvos no banco.";
                    $status = "sucesso";
                } catch (PDOException $e) {
                    // Remove o arquivo se o banco falhar, para não deixar lixo no servidor
                    if (file_exists($destino)) {
                        unlink($destino);
                    }
                    $mensagem = "Erro ao salvar no banco de dados: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $status = "erro";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Tutorial de Formulário PHP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f9f9f9; }
        .container { max-width: 400px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="number"], input[type="password"], input[type="file"] { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .alerta-sucesso { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alerta-erro { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Cadastro de Contato</h2>
    <?php if (!empty($mensagem)): ?>
        <div class="<?php echo ($status === 'sucesso') ? 'alerta-sucesso' : 'alerta-erro'; ?>">
            <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label for="nome">Nome Completo:</label>
            <input type="text" id="nome" name="nome" required>
        </div>
        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="idade">Idade:</label>
            <input type="number" id="idade" name="idade" min="1" max="120" required>
        </div>
        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required>
        </div>
        <div class="form-group">
            <label for="confirmar_senha">Confirmar Senha:</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" required>
        </div>
        <div class="form-group">
            <label for="curriculo">Currículo (PDF, máx. 2MB):</label>
            <input type="file" id="curriculo" name="curriculo" accept="application/pdf" required>
        </div>
        <button type="submit">Enviar Dados</button>
    </form>
</div>
</body>
</html>
