<?php
session_start();
include_once 'debug.php';

// Configurar o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

// Verificar se o usuário está logado e se o ID da publicação foi fornecido
if (!isset($_SESSION['user']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];
$userId = $_SESSION['user']['id'];
$userTipo = $_SESSION['user']['tipo'];

logDebug("Tentativa de exclusão da publicação ID: $id pelo usuário ID: $userId");

// Carregar publicações
if (file_exists('publicacoes.json')) {
    $json = file_get_contents('publicacoes.json');
    $publicacoes = json_decode($json, true) ?: [];
} else {
    logDebug("Arquivo publicacoes.json não encontrado");
    header('Location: index.php');
    exit;
}

// Array para armazenar as publicações que serão mantidas
$novasPublicacoes = [];
$excluido = false;

// Percorrer todas as publicações
foreach ($publicacoes as $pub) {
    // Se não for a publicação a ser excluída, ou se o usuário não tem permissão, mantém
    if ($pub['id'] != $id) {
        $novasPublicacoes[] = $pub;
    } else if ($pub['usuario_id'] != $userId && $userTipo !== 'professor') {
        $novasPublicacoes[] = $pub;
        logDebug("Usuário $userId não tem permissão para excluir a publicação $id");
    } else {
        $excluido = true;
        logDebug("Publicação ID: $id excluída pelo usuário ID: $userId");
    }
}

// Se alguma publicação foi excluída, salva o novo array
if ($excluido) {
    $jsonNovo = json_encode($novasPublicacoes, JSON_PRETTY_PRINT);
    $resultado = file_put_contents('publicacoes.json', $jsonNovo);
    
    if ($resultado === false) {
        logDebug("ERRO: Não foi possível escrever no arquivo publicacoes.json");
    } else {
        logDebug("Arquivo publicacoes.json atualizado com sucesso. Bytes escritos: $resultado");
    }
} else {
    logDebug("Nenhuma publicação foi excluída");
}

// Redireciona de volta para a página principal
header('Location: index.php');
exit;
?>
