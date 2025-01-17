<?php

namespace Utilities;

require_once "utilities/utilities.php";
require_once("utilities/DBAccess.php");

use DB\DBAccess;

session_start();

$paginaHTML = file_get_contents("template/template-pagina.html");
$content = file_get_contents("template/index.html");

$title = 'Fungo';
$pageId = basename(__FILE__, '.php');
$description = 'Pagina ufficiale del collettivo rap Restraining Stirpe Crew.';
$keywords = 'restraining stirpe, freestyle, freestyle rap, rap, battle, live, dj set, micelio, fungo';
$menu = get_menu($pageId);
$breadcrumbs = get_breadcrumbs($pageId);
$onload = 'init_index()';
$logout = '';
$classList = '';
$logo = get_content_between_markers($paginaHTML, 'logoNoLink');

if (isset($_SESSION["login"])) {
    $logout = get_content_between_markers($paginaHTML, 'logout');
}


$eventoHome = get_content_between_markers($content, 'eventoHome');
$contentEvento = '';

$connection = DBAccess::get_instance();
$connectionOk = $connection->open_DB_connection();
if ($connectionOk) {
    $headingEvento = '';
    $listaEventi  = $connection->get_lista_eventi(date('Y-m-d')); // lista eventi futuri

    if (count($listaEventi) > 0) { // se ci sono eventi futuri
        $headingEvento = get_content_between_markers($eventoHome, 'prossimoEvento');
    } else { // altrimenti prendo i passati
        $listaEventi = $connection->get_lista_eventi(date('Y-m-d'), '', false); // lista eventi passati
        $headingEvento = get_content_between_markers($eventoHome, 'ultimoEvento');
    }
    if (count($listaEventi) > 0) { // se ho ottenuto eventi
        $eventoId = $listaEventi[0]['Id'];
        $evento = $connection->get_evento($eventoId);
        $evento = replace_lang_array($evento);
        [$tipoEvento, $titolo, $descrizione, $data, $ora, $luogo, $locandina] = array_values($evento);

        $contentEvento = multi_replace(replace_content_between_markers($eventoHome, [
            'intestazione' => $headingEvento
        ]), [
            '{id}' => $eventoId,
            '{titolo}' => $titolo,
            '{data}' => date_format(date_create($data), 'Y-m-d'),
            '{dataVisualizzata}' => date_format_ita($data),
            '{ora}' => date_format(date_create($ora), 'H:i'),
            '{luogo}' => $luogo
        ]);
    }
    $connection->close_DB_connection();
}
else {
    header("location: errore500.php");
    exit;
}

$content = replace_content_between_markers($content, [
    'eventoHome' => $contentEvento
]);

echo multi_replace(replace_content_between_markers($paginaHTML, [
    'logo' => $logo,
    'breadcrumbs' => $breadcrumbs,
    'menu' => $menu,
    'logout' => $logout
]), [
    '{title}' => $title,
    '{description}' => $description,
    '{keywords}' => $keywords,
    '{pageId}' => $pageId,
    '{content}' => $content,
    '{onload}' => $onload,
    '{classList}' => $classList
]);
