<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Usuario.php';
require_once 'classes/Publicacao.php';
require_once 'classes/Resposta.php';
include_once 'debug.php';

// Configurar o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

// Verificar se o usuário está logado, se não, redirecionar para login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$usuario = new Usuario();
$publicacao = new Publicacao();
$resposta = new Resposta();

// Logout
if (isset($_GET['logout'])) {
    logDebug("Logout realizado para o usuário ID: " . $_SESSION['user']['id']);
    session_destroy();
    header('Location: login.php');
    exit;
}

// Formatar data no padrão brasileiro
function formatarData($data = null) {
    if ($data === null) {
        return date('d/m/Y H:i:s');
    }
    return date('d/m/Y H:i:s', strtotime($data));
}

// Obter as iniciais do nome do usuário
function getIniciais($nome) {
    $partes = explode(' ', $nome);
    $iniciais = '';
    
    if (count($partes) >= 2) {
        // Pegar a primeira letra do primeiro e último nome
        $iniciais = mb_substr($partes[0], 0, 1) . mb_substr($partes[count($partes) - 1], 0, 1);
    } else {
        // Se for apenas um nome, pegar as duas primeiras letras
        $iniciais = mb_substr($nome, 0, 2);
    }
    
    return mb_strtoupper($iniciais);
}

// Obter a imagem de perfil do usuário
function getImagemPerfil($usuarioData) {
    if (isset($usuarioData['foto_perfil']) && !empty($usuarioData['foto_perfil']) && file_exists($usuarioData['foto_perfil'])) {
        return '<img src="' . $usuarioData['foto_perfil'] . '" alt="Avatar" class="avatar">';
    } else {
        // Retornar avatar padrão com iniciais
        $iniciais = getIniciais($usuarioData['nome']);
        return '<div class="avatar-default">' . $iniciais . '</div>';
    }
}

// Processar nova publicação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conteudo']) && isset($_SESSION['user'])) {
    // Verificar se há conteúdo de texto ou imagem
    $temConteudo = !empty(trim($_POST['conteudo']));
    $temImagem = isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK;
    
    // Só prosseguir se houver conteúdo de texto ou imagem
    if ($temConteudo || $temImagem) {
        $imagemPath = null;
        
        // Processar upload de imagem
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileName = basename($_FILES["imagem"]["name"]);
            $targetFilePath = $targetDir . time() . '_' . $fileName;
            
            if (move_uploaded_file($_FILES["imagem"]["tmp_name"], $targetFilePath)) {
                $imagemPath = $targetFilePath;
            }
        }
        
        $dadosPublicacao = [
            'usuario_id' => $_SESSION['user']['id'],
            'conteudo' => $_POST['conteudo'],
            'imagem' => $imagemPath
        ];
        
        $publicacao->criar($dadosPublicacao);
        
        header('Location: index.php');
        exit;
    }
}

// Processar nova resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resposta_conteudo']) && isset($_POST['publicacao_id']) && isset($_SESSION['user'])) {
    // Verificar se há conteúdo de texto ou imagem
    $temConteudo = !empty(trim($_POST['resposta_conteudo']));
    $temImagem = isset($_FILES['resposta_imagem']) && $_FILES['resposta_imagem']['error'] === UPLOAD_ERR_OK;
    
    // Só prosseguir se houver conteúdo de texto ou imagem
    if ($temConteudo || $temImagem) {
        $imagemPath = null;
        
        // Processar upload de imagem
        if (isset($_FILES['resposta_imagem']) && $_FILES['resposta_imagem']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileName = basename($_FILES["resposta_imagem"]["name"]);
            $targetFilePath = $targetDir . time() . '_' . $fileName;
            
            if (move_uploaded_file($_FILES["resposta_imagem"]["tmp_name"], $targetFilePath)) {
                $imagemPath = $targetFilePath;
            }
        }
        
        $dadosResposta = [
            'publicacao_id' => $_POST['publicacao_id'],
            'usuario_id' => $_SESSION['user']['id'],
            'conteudo' => $_POST['resposta_conteudo'],
            'imagem' => $imagemPath
        ];
        
        $resposta->criar($dadosResposta);
        
        header('Location: index.php');
        exit;
    }
}

// Processar edição de resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_resposta_id']) && isset($_POST['editar_publicacao_id']) && isset($_SESSION['user'])) {
    $respostaId = $_POST['editar_resposta_id'];
    $novoConteudo = $_POST['editar_resposta_conteudo'];
    $userId = $_SESSION['user']['id'];
    
    $resposta->atualizar($respostaId, $novoConteudo, $userId);
    
    header('Location: index.php');
    exit;
}

// Processar exclusão de resposta
if (isset($_GET['action']) && $_GET['action'] === 'delete_resposta' && isset($_GET['resp_id']) && isset($_SESSION['user'])) {
    $respostaId = $_GET['resp_id'];
    $userId = $_SESSION['user']['id'];
    
    $resposta->excluir($respostaId, $userId);
    
    header('Location: index.php');
    exit;
}

// Processar likes (toggle)
if (isset($_GET['action']) && $_GET['action'] === 'like' && isset($_GET['id']) && isset($_SESSION['user'])) {
    $publicacaoId = $_GET['id'];
    $userId = $_SESSION['user']['id'];
    
    $publicacao->toggleLike($publicacaoId, $userId);
    
    // Redirecionar de volta para a página com um fragmento para a publicação específica
    header('Location: index.php#pub-' . $publicacaoId);
    exit;
}

// Processar edição de publicação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_id']) && isset($_SESSION['user'])) {
    $publicacaoId = $_POST['editar_id'];
    $novoConteudo = $_POST['editar_conteudo'];
    $userId = $_SESSION['user']['id'];
    
    $publicacao->atualizar($publicacaoId, $novoConteudo, $userId);
    
    header('Location: index.php');
    exit;
}

// Carregar todas as publicações
$publicacoes = $publicacao->listarTodas();

// Para cada publicação, carregar as respostas e verificar se o usuário curtiu
foreach ($publicacoes as &$pub) {
    $pub['respostas'] = $resposta->listarPorPublicacao($pub['id']);
    $pub['usuario_curtiu'] = $publicacao->usuarioCurtiu($pub['id'], $_SESSION['user']['id']);
    $pub['data'] = formatarData($pub['data_criacao']);
    
    // Formatar data das respostas
    foreach ($pub['respostas'] as &$resp) {
        $resp['data'] = formatarData($resp['data_criacao']);
    }
}

// Obter configurações de tema do usuário
$configTema = isset($_SESSION['user']['configuracoes']['tema']) ? $_SESSION['user']['configuracoes']['tema'] : 'claro';
$configTamanhoFonte = isset($_SESSION['user']['configuracoes']['tamanho_fonte']) ? $_SESSION['user']['configuracoes']['tamanho_fonte'] : 'medio';
$configAltoContraste = isset($_SESSION['user']['configuracoes']['alto_contraste']) && $_SESSION['user']['configuracoes']['alto_contraste'];

// Determinar se deve usar tema escuro
$usarTemaEscuro = $configTema === 'escuro' || 
                  ($configTema === 'sistema' && isset($_COOKIE['prefere_escuro']) && $_COOKIE['prefere_escuro'] === 'true');

// Classes CSS para aplicar ao body
$bodyClasses = [];
if ($configTamanhoFonte === 'pequeno') $bodyClasses[] = 'font-small';
if ($configTamanhoFonte === 'grande') $bodyClasses[] = 'font-large';
if ($configTamanhoFonte === 'muito_grande') $bodyClasses[] = 'font-xlarge';
if ($usarTemaEscuro) $bodyClasses[] = 'dark-theme';
if ($configAltoContraste) $bodyClasses[] = 'high-contrast';

$bodyClassString = implode(' ', $bodyClasses);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma de Dúvidas - TSI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/tema.css">
    <script src="js/tema.js"></script>
</head>
<body class="<?= $bodyClassString ?>">
    <header class="bg-teal text-white py-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <img src="img/tsi-logo.png" alt="Logo TSI" class="logo me-3">
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <h1 class="mb-0">Plataforma de Dúvidas</h1>
                </div>
                <div class="col-md-4 text-end">
                    <img src="img/if-logo.png" alt="Logo Instituto Federal" class="logo">
                </div>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Esquerda -->
            <div class="col-md-2 sidebar-left py-3">
                <nav>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#"><i class="fas fa-home"></i> Página Inicial</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="modulos/perfil/perfil.php"><i class="fas fa-user"></i> Perfil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-laptop"></i> Portal do Aluno</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-download"></i> Baixar PPC</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-info-circle"></i> Descrição do TSI</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-question-circle"></i> Dúvidas Frequentes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="modulos/configuracoes/configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-universal-access"></i> Acessibilidade</a>
                        </li>
                        <?php if (isset($_SESSION['user'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>

            <!-- Conteúdo Principal -->
            <div class="col-md-7 main-content py-3">
                <!-- Área de Criação de Publicação -->
                <div class="card mb-4">
                    <div class="card-header bg-teal text-white">
                        Criar nova Publicação
                    </div>
                    <div class="card-body">
                        <form action="index.php" method="post" enctype="multipart/form-data" id="form-publicacao" onsubmit="return validarPublicacao()">
                            <div class="d-flex mb-3">
                                <div class="avatar-container me-3">
                                    <?= getImagemPerfil($_SESSION['user']) ?>
                                </div>
                                <textarea class="form-control" name="conteudo" id="conteudo-publicacao" rows="3" placeholder="Compartilhe ideias..."></textarea>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div class="d-flex align-items-center">
                                    <label class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-image"></i> Anexar imagem
                                        <input type="file" name="imagem" id="imagem-publicacao" style="display: none;" accept="image/*">
                                    </label>
                                    <span id="file-selected" class="ms-2 small"></span>
                                </div>
                                <button type="submit" class="btn btn-teal">Publicar</button>
                            </div>
                            <div id="erro-publicacao" class="text-danger mt-2" style="display: none;">
                                Por favor, escreva algo ou anexe uma imagem antes de publicar.
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Feed de Publicações -->
                <?php foreach ($publicacoes as $pub): ?>
                <div id="pub-<?= $pub['id'] ?>" class="card mb-3 publicacao">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex">
                                <div class="avatar-container me-3">
                                    <?= getImagemPerfil($pub) ?>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?= htmlspecialchars($pub['nome']) ?></h5>
                                    <p class="text-muted small mb-0"><?= htmlspecialchars($pub['semestre'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="text-muted small d-flex align-items-center">
                                <?= $pub['data'] ?>
                                <?php if (isset($_SESSION['user']) && ($_SESSION['user']['id'] == $pub['usuario_id'] || $_SESSION['user']['tipo'] === 'professor')): ?>
                                <div class="dropdown ms-2">
                                    <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item" type="button" onclick="editarPublicacao(<?= $pub['id'] ?>, '<?= addslashes(htmlspecialchars($pub['conteudo'])) ?>')"><i class="fas fa-edit me-2"></i>Editar</button></li>
                                        <li><button class="dropdown-item" type="button" onclick="abrirModalExclusao(<?= $pub['id'] ?>)"><i class="fas fa-trash-alt me-2"></i>Excluir</button></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                <?php if ($pub['editado']): ?>
                                <span class="badge bg-secondary ms-2" title="Publicação editada">Editado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p><?= nl2br(htmlspecialchars($pub['conteudo'])) ?></p>
                        
                        <?php if ($pub['imagem']): ?>
                        <div class="post-image">
                            <img src="<?= htmlspecialchars($pub['imagem']) ?>" alt="Imagem da publicação" class="img-fluid">
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleFormResposta(<?= $pub['id'] ?>)">
                                <i class="fas fa-reply"></i> Responder
                            </button>
                            
                            <?php 
                                $btnClass = $pub['usuario_curtiu'] ? 'btn-primary' : 'btn-outline-primary';
                            ?>
                            <a href="?action=like&id=<?= $pub['id'] ?>" class="btn btn-sm <?= $btnClass ?>" onclick="curtirViaAjax(<?= $pub['id'] ?>)">
                                <i class="fas fa-thumbs-up"></i> <?= $pub['likes'] ?>
                            </a>
                        </div>
                        
                        <!-- Formulário de Resposta -->
                        <div id="form-resposta-<?= $pub['id'] ?>" class="form-resposta">
                            <form action="index.php" method="post" enctype="multipart/form-data" onsubmit="return validarResposta(<?= $pub['id'] ?>)">
                                <input type="hidden" name="publicacao_id" value="<?= $pub['id'] ?>">
                                <div class="d-flex mb-3">
                                    <div class="avatar-container me-2">
                                        <?= getImagemPerfil($_SESSION['user']) ?>
                                    </div>
                                    <textarea class="form-control" name="resposta_conteudo" id="resposta-conteudo-<?= $pub['id'] ?>" rows="2" placeholder="Escreva sua resposta..."></textarea>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <label class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-image"></i> Anexar imagem
                                            <input type="file" name="resposta_imagem" id="resposta-imagem-<?= $pub['id'] ?>" style="display: none;" accept="image/*">
                                        </label>
                                        <span id="resposta-file-selected-<?= $pub['id'] ?>" class="ms-2 small"></span>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-secondary me-2" onclick="toggleFormResposta(<?= $pub['id'] ?>)">Cancelar</button>
                                        <button type="submit" class="btn btn-sm btn-teal">Responder</button>
                                    </div>
                                </div>
                                <div id="erro-resposta-<?= $pub['id'] ?>" class="text-danger mt-2" style="display: none;">
                                    Por favor, escreva algo ou anexe uma imagem antes de responder.
                                </div>
                            </form>
                        </div>
                        
                        <!-- Respostas -->
                        <?php if (isset($pub['respostas']) && count($pub['respostas']) > 0): ?>
                        <div class="respostas-container">
                            <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Respostas (<?= count($pub['respostas']) ?>)</h6>
                            
                            <?php 
                            // Inverter a ordem das respostas para mostrar as mais recentes primeiro
                            $respostasOrdenadas = array_reverse($pub['respostas']);
                            foreach ($respostasOrdenadas as $resposta): 
                            ?>
                            <div class="resposta">
                                <div class="resposta-header">
                                    <div class="d-flex">
                                        <div class="avatar-container me-2">
                                            <?php 
                                            // Determinar como exibir o avatar para a resposta
                                            if (isset($resposta['foto_perfil']) && !empty($resposta['foto_perfil']) && file_exists($resposta['foto_perfil'])) {
                                                echo '<img src="' . $resposta['foto_perfil'] . '" alt="Avatar" class="avatar">';
                                            } else {
                                                // Avatar padrão com iniciais
                                                $iniciais = getIniciais($resposta['nome']);
                                                echo '<div class="avatar-default">' . $iniciais . '</div>';
                                            }
                                            ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($resposta['nome']) ?></h6>
                                            <p class="text-muted small mb-0">
                                                <?= $resposta['data'] ?>
                                                <?php if (isset($resposta['editado']) && $resposta['editado']): ?>
                                                <span class="badge bg-secondary ms-1" title="Resposta editada">Editado</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($_SESSION['user']) && ($_SESSION['user']['id'] == $resposta['usuario_id'] || $_SESSION['user']['tipo'] === 'professor')): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><button class="dropdown-item" type="button" onclick="editarResposta(<?= $pub['id'] ?>, <?= $resposta['id'] ?>, '<?= addslashes(htmlspecialchars($resposta['conteudo'])) ?>')"><i class="fas fa-edit me-2"></i>Editar</button></li>
                                            <li><button class="dropdown-item" type="button" onclick="abrirModalExclusaoResposta(<?= $pub['id'] ?>, <?= $resposta['id'] ?>)"><i class="fas fa-trash-alt me-2"></i>Excluir</button></li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="resposta-content">
                                    <p class="mb-2"><?= nl2br(htmlspecialchars($resposta['conteudo'])) ?></p>
                                    
                                    <?php if (isset($resposta['imagem'])): ?>
                                    <div class="post-image">
                                        <img src="<?= htmlspecialchars($resposta['imagem']) ?>" alt="Imagem da resposta" class="img-fluid">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Se não houver publicações, mostrar mensagem -->
                <?php if (empty($publicacoes)): ?>
                <div class="alert alert-info">
                    Ainda não há publicações. Seja o primeiro a compartilhar algo!
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar Direita -->
            <div class="col-md-3 sidebar-right py-3">
                <div class="accordion" id="accordionSidebar">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRanking">
                                <i class="fas fa-trophy me-2"></i> Ranking da Semana
                            </button>
                        </h2>
                        <div id="collapseRanking" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        João Silva
                                        <span class="badge bg-primary rounded-pill">10 interações</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Maria Oliveira
                                        <span class="badge bg-primary rounded-pill">8 interações</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Carlos Souza
                                        <span class="badge bg-primary rounded-pill">5 interações</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEstatisticas">
                                <i class="fas fa-chart-line me-2"></i> Estatísticas do Fórum
                            </button>
                        </h2>
                        <div id="collapseEstatisticas" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Publicações
                                        <span class="badge bg-secondary rounded-pill"><?= count($publicacoes) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Respostas
                                        <span class="badge bg-secondary rounded-pill">
                                            <?php
                                                $totalRespostas = 0;
                                                foreach ($publicacoes as $pub) {
                                                    if (isset($pub['respostas'])) {
                                                        $totalRespostas += count($pub['respostas']);
                                                    }
                                                }
                                                echo $totalRespostas;
                                            ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Usuários Ativos
                                        <span class="badge bg-secondary rounded-pill">34</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEventos">
                                <i class="fas fa-calendar-alt me-2"></i> Próximos Eventos
                            </button>
                        </h2>
                        <div id="collapseEventos" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>10/12 - Início do Período de Férias</strong>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>10/12 - Palestra: "Mercado de TI"</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExplore">
                                <i class="fas fa-compass me-2"></i> Explore Mais
                            </button>
                        </h2>
                        <div id="collapseExplore" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">Participe do Curso de Extensão</li>
                                    <li class="list-group-item">Conheça os projetos de pesquisa</li>
                                    <li class="list-group-item">Contribua com o projeto de iniciação</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNoticias">
                                <i class="fas fa-newspaper me-2"></i> Notícias do IFFar
                            </button>
                        </h2>
                        <div id="collapseNoticias" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">Novos laboratórios inaugurados no campus</li>
                                    <li class="list-group-item">Inscrições para monitoria abertas</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDicas">
                                <i class="fas fa-lightbulb me-2"></i> Dicas de Uso
                            </button>
                        </h2>
                        <div id="collapseDicas" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                Dicas para melhor aproveitamento da plataforma.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLinks">
                                <i class="fas fa-link me-2"></i> Links Úteis
                            </button>
                        </h2>
                        <div id="collapseLinks" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                Links para recursos importantes.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAvisos">
                                <i class="fas fa-exclamation-circle me-2"></i> Avisos Importantes
                            </button>
                        </h2>
                        <div id="collapseAvisos" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                            <div class="accordion-body">
                                Avisos e comunicados importantes.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div class="modal fade" id="editarModal" tabindex="-1" aria-labelledby="editarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-teal text-white">
                    <h5 class="modal-title" id="editarModalLabel">Editar Publicação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form action="index.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="editar_id" id="editar_id">
                        <div class="mb-3">
                            <label for="editar_conteudo" class="form-label">Conteúdo</label>
                            <textarea class="form-control" name="editar_conteudo" id="editar_conteudo" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Edição de Resposta -->
    <div class="modal fade" id="editarRespostaModal" tabindex="-1" aria-labelledby="editarRespostaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-teal text-white">
                    <h5 class="modal-title" id="editarRespostaModalLabel">Editar Resposta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form action="index.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="editar_publicacao_id" id="editar_publicacao_id">
                        <input type="hidden" name="editar_resposta_id" id="editar_resposta_id">
                        <div class="mb-3">
                            <label for="editar_resposta_conteudo" class="form-label">Conteúdo</label>
                            <textarea class="form-control" name="editar_resposta_conteudo" id="editar_resposta_conteudo" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="excluirModal" tabindex="-1" aria-labelledby="excluirModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="excluirModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir esta publicação?</p>
                    <p class="text-danger"><strong>Atenção:</strong> Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="btn-confirmar-exclusao" class="btn btn-danger">Excluir Publicação</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão de Resposta -->
    <div class="modal fade" id="excluirRespostaModal" tabindex="-1" aria-labelledby="excluirRespostaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="excluirRespostaModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir esta resposta?</p>
                    <p class="text-danger"><strong>Atenção:</strong> Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="btn-confirmar-exclusao-resposta" class="btn btn-danger">Excluir Resposta</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar nome do arquivo selecionado
        document.addEventListener("DOMContentLoaded", function() {
            const fileInput = document.querySelector('input[type="file"]');
            const fileSelected = document.getElementById("file-selected");

            if (fileInput && fileSelected) {
                fileInput.addEventListener("change", function() {
                    if (this.files && this.files.length > 0) {
                        fileSelected.textContent = this.files[0].name;
                    } else {
                        fileSelected.textContent = "";
                    }
                });
            }
            
            // Adicionar event listeners para os inputs de imagem das respostas
            document.querySelectorAll('input[name="resposta_imagem"]').forEach(function(input) {
                const id = input.id.split('-')[2];
                const fileSelectedSpan = document.getElementById(`resposta-file-selected-${id}`);
                
                input.addEventListener("change", function() {
                    if (this.files && this.files.length > 0) {
                        fileSelectedSpan.textContent = this.files[0].name;
                    } else {
                        fileSelectedSpan.textContent = "";
                    }
                });
            });
        });

        // Função para editar publicação
        function editarPublicacao(id, conteudo) {
            document.getElementById('editar_id').value = id;
            document.getElementById('editar_conteudo').value = conteudo.replace(/\\'/g, "'");
            
            const editarModal = new bootstrap.Modal(document.getElementById('editarModal'));
            editarModal.show();
        }
        
        // Função para editar resposta
        function editarResposta(pubId, respId, conteudo) {
            document.getElementById('editar_publicacao_id').value = pubId;
            document.getElementById('editar_resposta_id').value = respId;
            document.getElementById('editar_resposta_conteudo').value = conteudo.replace(/\\'/g, "'");
            
            const editarRespostaModal = new bootstrap.Modal(document.getElementById('editarRespostaModal'));
            editarRespostaModal.show();
        }

        // Função para abrir o modal de confirmação de exclusão
        function abrirModalExclusao(id) {
            // Definir o link de exclusão com o ID correto
            document.getElementById('btn-confirmar-exclusao').href = 'excluir.php?id=' + id;
            
            // Abrir o modal
            const excluirModal = new bootstrap.Modal(document.getElementById('excluirModal'));
            excluirModal.show();
        }
        
        // Função para abrir o modal de confirmação de exclusão de resposta
        function abrirModalExclusaoResposta(pubId, respId) {
            // Definir o link de exclusão com os IDs corretos
            document.getElementById('btn-confirmar-exclusao-resposta').href = `index.php?action=delete_resposta&pub_id=${pubId}&resp_id=${respId}`;
            
            // Abrir o modal
            const excluirRespostaModal = new bootstrap.Modal(document.getElementById('excluirRespostaModal'));
            excluirRespostaModal.show();
        }

        // Função para validar o formulário de publicação
        function validarPublicacao() {
            const conteudo = document.getElementById('conteudo-publicacao').value.trim();
            const imagem = document.getElementById('imagem-publicacao').files;
            const erroMsg = document.getElementById('erro-publicacao');
            
            // Verificar se há conteúdo de texto ou imagem
            if (conteudo === '' && (!imagem || imagem.length === 0)) {
                erroMsg.style.display = 'block';
                return false; // Impedir o envio do formulário
            }
            
            erroMsg.style.display = 'none';
            return true; // Permitir o envio do formulário
        }
        
        // Função para validar o formulário de resposta
        function validarResposta(id) {
            const conteudo = document.getElementById(`resposta-conteudo-${id}`).value.trim();
            const imagem = document.getElementById(`resposta-imagem-${id}`).files;
            const erroMsg = document.getElementById(`erro-resposta-${id}`);
            
            // Verificar se há conteúdo de texto ou imagem
            if (conteudo === '' && (!imagem || imagem.length === 0)) {
                erroMsg.style.display = 'block';
                return false; // Impedir o envio do formulário
            }
            
            erroMsg.style.display = 'none';
            return true; // Permitir o envio do formulário
        }
        
        // Função para mostrar/esconder o formulário de resposta
        function toggleFormResposta(id) {
            const formResposta = document.getElementById(`form-resposta-${id}`);
            
            if (formResposta.style.display === 'none' || formResposta.style.display === '') {
                formResposta.style.display = 'block';
                // Focar no textarea
                document.getElementById(`resposta-conteudo-${id}`).focus();
            } else {
                formResposta.style.display = 'none';
            }
        }

        // Função para curtir via AJAX
        function curtirViaAjax(id) {
            // Impedir o comportamento padrão do link
            event.preventDefault();
            
            // Obter o botão que foi clicado
            const botao = event.currentTarget;
            
            // Criar um objeto XMLHttpRequest
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `?action=like&id=${id}`, true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    // Recarregar apenas a seção de curtidas
                    const ehCurtido = botao.classList.contains('btn-primary');
                    
                    // Toggle da classe do botão
                    if (ehCurtido) {
                        botao.classList.remove('btn-primary');
                        botao.classList.add('btn-outline-primary');
                        
                        // Decrementar o contador
                        const contador = botao.querySelector('i').nextSibling;
                        contador.nodeValue = ' ' + (parseInt(contador.nodeValue) - 1);
                    } else {
                        botao.classList.remove('btn-outline-primary');
                        botao.classList.add('btn-primary');
                        
                        // Incrementar o contador
                        const contador = botao.querySelector('i').nextSibling;
                        contador.nodeValue = ' ' + (parseInt(contador.nodeValue) + 1);
                    }
                }
            };
            
            xhr.send();
        }
    </script>
</body>
</html>
