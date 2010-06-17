<?php
/**
 * german language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     picsar <>
 */
 
// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';
 
// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'Seite/Namespace verschieben/umbenennen...';
$lang['desc'] = 'Seite/Namespace verschieben/umbenennen... Plugin';

$lang['pm_notexist']	= 'Dieses Thema existiert noch nicht';
$lang['pm_notstart']	= 'Die Startseite kann nicht verschoben oder umbenannt werden';
$lang['pm_notwrite']	= 'Sie haben unzureichende Rechte um diese Seite zu ändern';
$lang['pm_badns']		= 'Ungültige Zeichen in der Namensraum-Bezeichnung.';
$lang['pm_badname']		= 'Ungültiges Zeichen im Seitennamen.';
$lang['pm_nochange']	= 'Name und Namensraum der Seite sind unverändert.';
$lang['pm_existing']	= 'Eine Seite mit der Bezeichnung %s existiert bereits in %s';
$lang['pm_root']		= '[Wurzel des Namensraumes / Root namespace]';
$lang['pm_current']		= '(Aktueller)';
$lang['pm_movedfrom']	= 'Seite verschoben von ';
$lang['pm_movedto']		= 'Seite verschoben nach ';
$lang['pm_renamed']     = 'Seitename wurde geändert von %s auf %s';
$lang['pm_moved']       = 'Seite verschoben von %s nach %s';
$lang['pm_move_rename'] = 'Seite verschoben und umbenannt von %s nach %s';
$lang['pm_norights']	= 'Sie haben unzureichende Rechte, einen oder mehrere Rückverweise mit diesem Dokument zu verändern.';
$lang['pm_tryagain']	= 'Versuchen Sie es später nochmal.';
$lang['pm_filelocked']	= 'Diese Datei ist gesperrt - ';
$lang['pm_fileslocked']	= 'Diese Dateien sind gesperrt - ';
$lang['pm_linkchange']	= 'Link mit %s geändert zu %s';
$lang['pm_newname']		= 'Neuer Seitenname:';
$lang['pm_newnsname']   = 'Neuen Namen für Namensraum verwenden:';
$lang['pm_targetns']	= 'Wählen Sie einen neuen Namensraum: ';
$lang['pm_newtargetns'] = 'Erstellen Sie einen neuen Namensraum';
$lang['pm_movepage']	= 'Seite verschieben';
$lang['pm_movens']		= 'Namensraum verschieben';
$lang['pm_previewpage']	= ' wird verschoben nach ';
$lang['pm_previewns']	= 'Alle Seiten und Namensräume im Namensraum %s: werden verschoben in den Namensraum';
$lang['pm_preview']		= 'Vorschau';
$lang['pm_delete']		= 'Gelöscht durch pagemove Plugin';
$lang['pm_submit']      = 'Übernehmen';
?>
