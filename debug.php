<?php
// Configurar o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

// Arquivo para debug
function logDebug($message) {
    $logFile = 'debug.log';
    $timestamp = date('d/m/Y H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Função para verificar o conteúdo atual do arquivo de publicações
function verificarPublicacoes() {
    if (file_exists('publicacoes.json')) {
        $conteudo = file_get_contents('publicacoes.json');
        logDebug("Conteúdo atual de publicacoes.json: " . $conteudo);
        return true;
    } else {
        logDebug("Arquivo publicacoes.json não encontrado!");
        return false;
    }
}

// Verificar permissões do arquivo
function verificarPermissoes() {
    $arquivo = 'publicacoes.json';
    if (file_exists($arquivo)) {
        $permissoes = fileperms($arquivo);
        $permissoesOctal = substr(sprintf('%o', $permissoes), -4);
        logDebug("Permissões do arquivo $arquivo: $permissoesOctal");
        
        if (is_writable($arquivo)) {
            logDebug("O arquivo $arquivo é gravável pelo PHP.");
        } else {
            logDebug("O arquivo $arquivo NÃO é gravável pelo PHP!");
        }
    } else {
        logDebug("Arquivo $arquivo não existe para verificar permissões.");
    }
}

// Executar verificações
if (isset($_GET['debug'])) {
    logDebug("=== Iniciando verificação de debug ===");
    verificarPublicacoes();
    verificarPermissoes();
    logDebug("=== Fim da verificação de debug ===");

    echo "<h1>Debug concluído</h1>";
    echo "<p>Verifique o arquivo debug.log para mais informações.</p>";
    echo "<p><a href='index.php'>Voltar para o fórum</a></p>";
    exit;
}
?>
