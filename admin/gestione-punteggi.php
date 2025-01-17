<?php

namespace Utilities;

require_once("../utilities/utilities.php");
require_once("../utilities/DBAccess.php");

use DB\DBAccess;

session_start();

$paginaHTML = file_get_contents("../template/admin/template-admin.html");
$content = file_get_contents("../template/admin/gestione-punteggi.html");

$title = 'Gestione Punteggi &minus; Admin &minus; Fungo';
$pageId = 'admin/' . basename(__FILE__, '.php');
$description = 'pagina di amministrazione per la gestione dei punteggi delle classifiche';
$keywords = 'Fungo, amministrazione, punteggi';
$menu = get_admin_menu($pageId);
$breadcrumbs = get_breadcrumbs($pageId);
$onload = '';
$classList = 'fullMenu';
$logo = get_content_between_markers($paginaHTML, 'logoLink');

if (!isset($_SESSION["login"])) {
    header("location: ../login.php");
    exit;
}

$connection = DBAccess::get_instance();
$connectionOk = $connection->open_DB_connection();

if ($connectionOk) {
    $messaggiForm = '';
    $messaggiFormHTML = get_content_between_markers($content, 'messaggiForm');
    $messaggioForm = get_content_between_markers($messaggiFormHTML, 'messaggioForm');
    $righeTabella = '';
    $validIdEvento = isset($_POST['idEvento']) ? validate_input($_POST['idEvento']) : (isset($_GET['idEvento']) ? validate_input($_GET['idEvento']) : '');
    $provenienza = isset($_GET['provenienza']) ? validate_input($_GET['provenienza']) : '';
    $eventoSelezionato = $validIdEvento != '';
    $validTitolo = $eventoSelezionato ? $connection->get_evento($validIdEvento)['Titolo'] : '';
    $validData = $eventoSelezionato ? date_format(date_create($connection->get_evento($validIdEvento)['Data']), 'd/m/y') : '';
    $validRappersPoints = [];
    $count_punteggi = 0;
    if (isset($_POST['username'])) {
        $count = 0;
        foreach ($_POST['username'] as $rapper) {
            $rapperUsername = validate_input($rapper);
            $validPoints = validate_input($_POST['punti'][$count]);
            $rapperPoints = $_POST['punti'][$count];
            if ($_POST['punti'][$count] != "") {
                $count_punteggi++;
            }
            if ($rapperUsername != "" && $rapperPoints  != "") {
                $validRappersPoints[$rapperUsername] = $rapperPoints;
            }
            $count++;
        }
    }
    if (((isset($_POST['idEvento']) && $_POST['idEvento'] != "") && $validIdEvento == "") ||
        ((isset($_GET['provenienza']) && $_GET['provenienza'] != "") && $provenienza == "") ||
        ($count_punteggi != count($validRappersPoints))) {
        if ($provenienza == 'classifiche') {
            header("location: classifiche.php?errore=invalid");
        } elseif ($provenienza == 'dashboard-punteggi-mancanti') {
            header("location: index.php?punteggi-errore=invalid#messaggi");
        } else {
            header("location: eventi.php?errore=invalid");
        }
        exit;
    }
  
    if (isset($_POST['elimina'])) {
        if ($eventoSelezionato) {
            $connection->delete_punteggi_evento($validIdEvento);
            $messaggiForm .= multi_replace($messaggioForm, [
                '{tipoMessaggio}' => 'successMessage',
                '{messaggio}' => 'Punteggi eliminati con successo'
            ]);

            if ($provenienza == 'classifiche') {
                header("location: classifiche.php?punteggi-eliminati=true");
            } elseif ($provenienza == 'dashboard-punteggi-mancanti') {
                header("location: index.php?punteggi-eliminati=true#messaggi");
            } else {
                header("location: eventi.php?punteggi-eliminati=true");
            }
            exit;
        } else {
            $messaggiForm .= multi_replace($messaggioForm, [
                '{tipoMessaggio}' => 'inputError',
                '{messaggio}' => 'Errore imprevisto, nessun evento selezionato'
            ]);
        }
    } elseif (isset($_POST['conferma'])) {
        if ($eventoSelezionato) {
            // controllo che tutti i punteggi siano effettivamente degli interi
            $errore = false;
            foreach ($validRappersPoints as $points) {
                if (!is_numeric($points) || !ctype_digit($points)) {
                    $errore = true;
                    break;
                }
            }
            if ($errore) {
                $messaggiForm .= multi_replace($messaggioForm, [
                    '{tipoMessaggio}' => 'inputError',
                    '{messaggio}' => 'Punteggi non validi'
                ]);
            } else {
                $connection->update_punteggi_evento($validIdEvento, $validRappersPoints);
                $messaggiForm .= multi_replace($messaggioForm, [
                    '{tipoMessaggio}' => 'successMessage',
                    '{messaggio}' => 'Punteggi aggiornati con successo'
                ]);

                if ($provenienza == 'classifiche') {
                    header("location: classifiche.php?punteggi-modificati=true");
                } elseif ($provenienza == 'dashboard-punteggi-mancanti') {
                    header("location: index.php?punteggi-modificati=true#messaggi");
                } else {
                    header("location: eventi.php?punteggi-modificati=true");
                }
                exit;
            }
        } else {
            $messaggiForm .= multi_replace($messaggioForm, [
                '{tipoMessaggio}' => 'inputError',
                '{messaggio}' => 'Nessun evento selezionato'
            ]);
        }
    }

    // creo l'elenco dei rappers
    $rappers = $connection->get_utenti_base();
    $rigaTabella = get_content_between_markers($content, 'rigaTabella');
    $punteggio = $eventoSelezionato ? $connection->get_punteggi_evento($validIdEvento) : [];
    foreach ($rappers as $rapper) {
        $righeTabella .= multi_replace($rigaTabella, [
            '{rapper}' => $rapper['Username'],
            '{idRapper}' => multi_replace($rapper['Username'], [' ' => '_']),
            '{valuePunti}' => isset($punteggio[$rapper['Username']]) ? " value=\"" . $punteggio[$rapper['Username']] . "\"" : ''
        ]);
    }

    $messaggiFormHTML = $messaggiForm == '' ? '' : replace_content_between_markers($messaggiFormHTML, ['messaggioForm' => $messaggiForm]);

    $content = replace_content_between_markers($content, [
        'messaggiForm' => $messaggiFormHTML,
        'rigaTabella' => $righeTabella
    ]);
    $content = multi_replace($content, [
        '{idEvento}' => $validIdEvento,
        '{provenienza}' => $provenienza
    ]);

    $connection->close_DB_connection();
} else {
    header("location: ../errore500.php");
    exit;
}

echo multi_replace(replace_content_between_markers($paginaHTML, [
    'logo' => $logo,
    'breadcrumbs' => $breadcrumbs,
    'menu' => $menu
]), [
    '{title}' => $title,
    '{description}' => $description,
    '{keywords}' => $keywords,
    '{pageId}' => $pageId,
    '{content}' => $content,
    '{onload}' => $onload,
    '{evento}' => $eventoSelezionato ? $validTitolo . ' ' . $validData : '',
    '{classList}' => $classList
]);
