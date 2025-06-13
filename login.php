<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Usuario.php';
include_once 'debug.php';

// Configurar o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

// Se já estiver logado, redirecionar para a página principal
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Diretório para salvar os comprovantes
$uploadDir = "uploads/comprovantes/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$usuario = new Usuario();

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $_SESSION['erro_login'] = "Por favor, preencha todos os campos.";
    } else {
        $dadosUsuario = $usuario->login($email, $senha);
        
        if ($dadosUsuario) {
            // Login bem-sucedido
            $_SESSION['user'] = [
                'id' => $dadosUsuario['id'],
                'nome' => $dadosUsuario['nome'],
                'email' => $dadosUsuario['email'],
                'tipo' => $dadosUsuario['tipo'],
                'semestre' => $dadosUsuario['semestre'] ?? '',
                'telefone' => $dadosUsuario['telefone'] ?? '',
                'telefone_raw' => $dadosUsuario['telefone_raw'] ?? '',
                'campus' => $dadosUsuario['campus'] ?? 'IFFar Campus Panambi',
                'cidade' => $dadosUsuario['cidade'] ?? 'Panambi',
                'foto_perfil' => $dadosUsuario['foto_perfil'] ?? null,
                'status' => $dadosUsuario['status'] ?? 'ativo'
            ];
            
            // Carregar configurações do usuário
            $configuracoes = $usuario->buscarConfiguracoes($dadosUsuario['id']);
            if ($configuracoes) {
                $_SESSION['user']['configuracoes'] = [
                    'tema' => $configuracoes['tema'],
                    'tamanho_fonte' => $configuracoes['tamanho_fonte'],
                    'alto_contraste' => $configuracoes['alto_contraste']
                ];
            }
            
            logDebug("Login realizado com sucesso para: " . $email);
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['erro_login'] = "Email ou senha incorretos.";
        }
    }
}

// Processar cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $semestre = $_POST['semestre'] ?? '';
    $campus = $_POST['campus'] ?? 'IFFar Campus Panambi';
    $cidade = $_POST['cidade'] ?? 'Panambi';
    
    // Validações
    $erros = [];
    
    if (empty($nome)) $erros[] = "Nome é obrigatório.";
    if (empty($email)) $erros[] = "Email é obrigatório.";
    if (empty($senha)) $erros[] = "Senha é obrigatória.";
    if (empty($tipo)) $erros[] = "Tipo de usuário é obrigatório.";
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Formato de email inválido.";
    }
    
    if ($senha !== $confirmarSenha) {
        $erros[] = "As senhas não coincidem.";
    }
    
    if (strlen($senha) < 6) {
        $erros[] = "A senha deve ter pelo menos 6 caracteres.";
    }
    
    // Verificar se o email já existe
    if ($usuario->emailExiste($email)) {
        $erros[] = "Este email já está cadastrado.";
    }
    
    // Validar campos específicos por tipo
    if ($tipo === 'aluno' && $semestre !== 'pensando_ingressar' && empty($semestre)) {
        $erros[] = "Semestre é obrigatório para alunos.";
    }
    
    // Processar upload de comprovante
    $comprovanteUpload = null;
    if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
        $tempFile = $_FILES['comprovante']['tmp_name'];
        $fileName = $_FILES['comprovante']['name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileType !== 'pdf') {
            $erros[] = "O comprovante deve ser um arquivo PDF.";
        } else {
            $newFileName = 'comprovante_' . time() . '_' . uniqid() . '.pdf';
            $targetFilePath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($tempFile, $targetFilePath)) {
                $comprovanteUpload = $targetFilePath;
                logDebug("Comprovante enviado: " . $targetFilePath);
            } else {
                $erros[] = "Erro ao fazer upload do comprovante.";
            }
        }
    }
    
    // Verificar se comprovante é obrigatório
    if ($tipo === 'professor' && !$comprovanteUpload) {
        $erros[] = "Comprovante é obrigatório para professores.";
    }
    
    if ($tipo === 'aluno' && $semestre !== 'pensando_ingressar' && !$comprovanteUpload) {
        $erros[] = "Comprovante é obrigatório para alunos matriculados.";
    }
    
    if (empty($erros)) {
        try {
            // Criar novo usuário
            $dadosUsuario = [
                'nome' => $nome,
                'email' => $email,
                'senha' => $senha,
                'tipo' => $tipo,
                'semestre' => $semestre === 'pensando_ingressar' ? 'Pensando em ingressar' : $semestre,
                'telefone' => $telefone,
                'telefone_raw' => preg_replace('/[^0-9]/', '', $telefone),
                'campus' => $campus,
                'cidade' => $cidade,
                'comprovante' => $comprovanteUpload
            ];
            
            $novoId = $usuario->criar($dadosUsuario);
            
            logDebug("Novo usuário cadastrado: " . $email . " (Tipo: " . $tipo . ", ID: " . $novoId . ")");
            
            $_SESSION['sucesso_cadastro'] = "Cadastro realizado com sucesso! Você já pode fazer login.";
            header('Location: login.php');
            exit;
        } catch (Exception $e) {
            $erros[] = "Erro ao cadastrar usuário. Tente novamente.";
            logDebug("Erro no cadastro: " . $e->getMessage());
        }
    }
    
    if (!empty($erros)) {
        $_SESSION['erros_cadastro'] = $erros;
        $_SESSION['dados_form'] = $_POST; // Manter dados do formulário
    }
}

// Lista de campi
$campi = [
    'IFFar Campus Panambi',
    'IFFar Campus Santa Rosa',
    'IFFar Campus Santo Augusto',
    'IFFar Campus São Borja',
    'IFFar Campus Júlio de Castilhos',
    'IFFar Campus Alegrete',
    'IFFar Campus Frederico Westphalen'
];

// Lista de cidades
$cidades = [
    'Panambi',
    'Santa Rosa',
    'Santo Augusto',
    'São Borja',
    'Júlio de Castilhos',
    'Alegrete',
    'Frederico Westphalen',
    'Ijuí',
    'Cruz Alta',
    'Passo Fundo'
];

// Lista de semestres
$semestres = [
    '1º Semestre',
    '2º Semestre',
    '3º Semestre',
    '4º Semestre',
    '5º Semestre',
    '6º Semestre'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Plataforma de Dúvidas TSI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a6e6e 0%, #2a8e8e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            margin: 20px;
        }
        
        .login-header {
            background: var(--teal);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header img {
            height: 60px;
            margin-bottom: 1rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: none;
            border: none;
            color: #6c757d;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-tab.active {
            color: var(--teal);
            border-bottom: 3px solid var(--teal);
        }
        
        .form-content {
            display: none;
        }
        
        .form-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 0.2rem rgba(26, 110, 110, 0.25);
        }
        
        .btn-login {
            background: var(--teal);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--teal-dark);
            color: white;
        }
        
        .tipo-usuario-cards {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .tipo-card {
            flex: 1;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tipo-card:hover {
            border-color: var(--teal-light);
        }
        
        .tipo-card.selected {
            border-color: var(--teal);
            background: rgba(26, 110, 110, 0.1);
        }
        
        .tipo-card i {
            font-size: 2rem;
            color: var(--teal);
            margin-bottom: 0.5rem;
        }
        
        .campos-condicionais {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .campos-condicionais.show {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .upload-area {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--teal);
        }
        
        .upload-area.dragover {
            border-color: var(--teal);
            background: rgba(26, 110, 110, 0.1);
        }
        
        .file-info {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 10px;
            }
            
            .login-body {
                padding: 1.5rem;
            }
            
            .tipo-usuario-cards {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="d-flex justify-content-center align-items-center gap-3">
                <img src="img/tsi-logo.png" alt="Logo TSI">
                <img src="img/if-logo.png" alt="Logo Instituto Federal">
            </div>
            <h2 class="mb-0">Plataforma de Dúvidas TSI</h2>
            <p class="mb-0 mt-2">Conecte-se com a comunidade acadêmica</p>
        </div>
        
        <div class="login-body">
            <!-- Abas de Login e Cadastro -->
            <div class="form-tabs">
                <button class="form-tab active" onclick="showTab('login')">
                    <i class="fas fa-sign-in-alt me-2"></i>Entrar
                </button>
                <button class="form-tab" onclick="showTab('register')">
                    <i class="fas fa-user-plus me-2"></i>Cadastrar
                </button>
            </div>
            
            <!-- Mensagens de Sucesso/Erro -->
            <?php if (isset($_SESSION['sucesso_cadastro'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['sucesso_cadastro'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['sucesso_cadastro']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['erro_login'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['erro_login'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['erro_login']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['erros_cadastro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($_SESSION['erros_cadastro'] as $erro): ?>
                        <li><?= $erro ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['erros_cadastro']); ?>
            <?php endif; ?>
            
            <!-- Formulário de Login -->
            <div id="login-form" class="form-content active">
                <form action="login.php" method="post">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="login-email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="login-email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="login-senha" class="form-label">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="login-senha" name="senha" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('login-senha', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Entrar
                    </button>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Não tem uma conta? <a href="#" onclick="showTab('register')" class="text-decoration-none">Cadastre-se aqui</a>
                        </small>
                    </div>
                </form>
            </div>
            
            <!-- Formulário de Cadastro -->
            <div id="register-form" class="form-content">
                <form action="login.php" method="post" enctype="multipart/form-data" id="cadastro-form">
                    <input type="hidden" name="action" value="register">
                    
                    <!-- Informações Básicas -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nome" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="nome" name="nome" 
                                       value="<?= htmlspecialchars($_SESSION['dados_form']['nome'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_SESSION['dados_form']['email'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="senha" class="form-label">Senha *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="senha" name="senha" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('senha', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Mínimo 6 caracteres</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirmar_senha" class="form-label">Confirmar Senha *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmar_senha', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" 
                               placeholder="(+55) 55 99999-9999" value="<?= htmlspecialchars($_SESSION['dados_form']['telefone'] ?? '') ?>">
                    </div>
                    
                    <!-- Seleção de Tipo de Usuário -->
                    <div class="form-group">
                        <label class="form-label">Tipo de Usuário *</label>
                        <div class="tipo-usuario-cards">
                            <div class="tipo-card" onclick="selectTipo('professor')">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h6>Professor</h6>
                                <small class="text-muted">Docente da instituição</small>
                                <input type="radio" name="tipo" value="professor" style="display: none;">
                            </div>
                            <div class="tipo-card" onclick="selectTipo('aluno')">
                                <i class="fas fa-graduation-cap"></i>
                                <h6>Aluno</h6>
                                <small class="text-muted">Estudante matriculado</small>
                                <input type="radio" name="tipo" value="aluno" style="display: none;">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos para Professor -->
                    <div id="campos-professor" class="campos-condicionais">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Professores:</strong> É necessário enviar um comprovante em PDF que confirme seu vínculo com a instituição.
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Comprovante de Vínculo (PDF) *</label>
                            <div class="upload-area" onclick="document.getElementById('comprovante-professor').click()">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                <p class="mb-0">Clique aqui ou arraste seu arquivo PDF</p>
                                <small class="text-muted">Máximo 5MB</small>
                            </div>
                            <input type="file" id="comprovante-professor" name="comprovante" accept=".pdf" style="display: none;">
                            <div id="file-info-professor" class="file-info">
                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                <span class="file-name"></span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile('professor')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos para Aluno -->
                    <div id="campos-aluno" class="campos-condicionais">
                        <div class="form-group">
                            <label for="semestre" class="form-label">Status Acadêmico *</label>
                            <select class="form-select" id="semestre" name="semestre" onchange="toggleComprovanteAluno()">
                                <option value="">Selecione...</option>
                                <?php foreach ($semestres as $sem): ?>
                                    <option value="<?= $sem ?>" <?= (($_SESSION['dados_form']['semestre'] ?? '') === $sem) ? 'selected' : '' ?>><?= $sem ?></option>
                                <?php endforeach; ?>
                                <option value="pensando_ingressar" <?= (($_SESSION['dados_form']['semestre'] ?? '') === 'pensando_ingressar') ? 'selected' : '' ?>>Pensando em ingressar</option>
                            </select>
                        </div>
                        
                        <div id="comprovante-aluno-container" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Alunos matriculados:</strong> É necessário enviar um comprovante de matrícula em PDF.
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Comprovante de Matrícula (PDF) *</label>
                                <div class="upload-area" onclick="document.getElementById('comprovante-aluno').click()">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="mb-0">Clique aqui ou arraste seu arquivo PDF</p>
                                    <small class="text-muted">Máximo 5MB</small>
                                </div>
                                <input type="file" id="comprovante-aluno" name="comprovante" accept=".pdf" style="display: none;">
                                <div id="file-info-aluno" class="file-info">
                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile('aluno')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações Adicionais -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="campus" class="form-label">Campus</label>
                                <select class="form-select" id="campus" name="campus">
                                    <?php foreach ($campi as $camp): ?>
                                        <option value="<?= $camp ?>" <?= (($_SESSION['dados_form']['campus'] ?? 'IFFar Campus Panambi') === $camp) ? 'selected' : '' ?>><?= $camp ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cidade" class="form-label">Cidade</label>
                                <select class="form-select" id="cidade" name="cidade">
                                    <?php foreach ($cidades as $cid): ?>
                                        <option value="<?= $cid ?>" <?= (($_SESSION['dados_form']['cidade'] ?? 'Panambi') === $cid) ? 'selected' : '' ?>><?= $cid ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-user-plus me-2"></i>Cadastrar
                    </button>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Já tem uma conta? <a href="#" onclick="showTab('login')" class="text-decoration-none">Faça login aqui</a>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Alternar entre abas de login e cadastro
        function showTab(tab) {
            // Remover classe active de todas as abas e conteúdos
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-content').forEach(c => c.classList.remove('active'));
            
            // Adicionar classe active na aba e conteúdo selecionados
            event.target.classList.add('active');
            document.getElementById(tab + '-form').classList.add('active');
        }
        
        // Selecionar tipo de usuário
        function selectTipo(tipo) {
            // Remover seleção anterior
            document.querySelectorAll('.tipo-card').forEach(card => card.classList.remove('selected'));
            document.querySelectorAll('.campos-condicionais').forEach(campo => campo.classList.remove('show'));
            
            // Selecionar novo tipo
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
            
            // Mostrar campos específicos
            document.getElementById('campos-' + tipo).classList.add('show');
            
            // Se for aluno, verificar se precisa mostrar comprovante
            if (tipo === 'aluno') {
                toggleComprovanteAluno();
            }
        }
        
        // Toggle comprovante para aluno
        function toggleComprovanteAluno() {
            const semestre = document.getElementById('semestre').value;
            const comprovanteContainer = document.getElementById('comprovante-aluno-container');
            
            if (semestre && semestre !== 'pensando_ingressar') {
                comprovanteContainer.style.display = 'block';
            } else {
                comprovanteContainer.style.display = 'none';
            }
        }
        
        // Toggle visibilidade da senha
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Configurar upload de arquivos
        document.addEventListener('DOMContentLoaded', function() {
            setupFileUpload('professor');
            setupFileUpload('aluno');
        });
        
        function setupFileUpload(tipo) {
            const input = document.getElementById('comprovante-' + tipo);
            const uploadArea = input.parentElement.querySelector('.upload-area');
            const fileInfo = document.getElementById('file-info-' + tipo);
            
            // Click no upload area
            uploadArea.addEventListener('click', () => input.click());
            
            // Drag and drop
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type === 'application/pdf') {
                    input.files = files;
                    showFileInfo(tipo, files[0]);
                }
            });
            
            // Mudança no input
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    showFileInfo(tipo, this.files[0]);
                }
            });
        }
        
        function showFileInfo(tipo, file) {
            const fileInfo = document.getElementById('file-info-' + tipo);
            const fileName = fileInfo.querySelector('.file-name');
            
            fileName.textContent = file.name;
            fileInfo.style.display = 'block';
        }
        
        function removeFile(tipo) {
            const input = document.getElementById('comprovante-' + tipo);
            const fileInfo = document.getElementById('file-info-' + tipo);
            
            input.value = '';
            fileInfo.style.display = 'none';
        }
        
        // Validação do formulário
        document.getElementById('cadastro-form').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (senha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length >= 13) {
                value = value.replace(/(\d{2})(\d{2})(\d{5})(\d{4})/, '(+$1) $2 $3-$4');
            } else if (value.length >= 11) {
                value = value.replace(/(\d{2})(\d{2})(\d{4})(\d{4})/, '(+$1) $2 $3-$4');
            } else if (value.length >= 7) {
                value = value.replace(/(\d{2})(\d{2})(\d{4})/, '(+$1) $2 $3');
            } else if (value.length >= 4) {
                value = value.replace(/(\d{2})(\d{2})/, '(+$1) $2');
            } else if (value.length >= 2) {
                value = value.replace(/(\d{2})/, '(+$1)');
            }
            
            e.target.value = value;
        });
    </script>
    
    <?php
    // Limpar dados do formulário da sessão
    if (isset($_SESSION['dados_form'])) {
        unset($_SESSION['dados_form']);
    }
    ?>
</body>
</html>
