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

$lang['pm_notexist']    = 'Die Seite %s existiert nicht.';
$lang['pm_medianotexist']    = 'Die Mediendatei %s existiert nicht';
$lang['pm_notwrite']	= 'Sie haben unzureichende Rechte um diese Seite zu ändern';
$lang['pm_badns']		= 'Ungültige Zeichen in der Namensraum-Bezeichnung.';
$lang['pm_badname']		= 'Ungültiges Zeichen im Seitennamen.';
$lang['pm_nochange']	= 'Name und Namensraum der Seite sind unverändert.';
$lang['pm_nomediachange']    = 'Name und Namensraum der Mediendatei sind unverändert.';
$lang['pm_existing']	= 'Eine Seite mit der Bezeichnung %s existiert bereits in %s';
$lang['pm_mediaexisting']    = 'Eine Mediendatei mit der Bezeichnung %s existiert bereits in %s';
$lang['pm_root']		= '[Wurzel des Namensraumes / Root namespace]';
$lang['pm_current']		= '(Aktueller)';
$lang['pm_renamed']     = 'Seitename wurde von %s auf %s geändert';
$lang['pm_moved']       = 'Seite von %s nach %s verschoben';
$lang['pm_move_rename'] = 'Seite von %s nach %s verschoben und umbenannt';
$lang['pm_delete']      = 'Gelöscht durch das move Plugin';
$lang['pm_norights']    = 'Sie haben unzureichende Rechte um %s zu bearbeiten.';
$lang['pm_nomediarights']    = 'Sie haben unzureichende Rechte, um die Mediendatei %s zu löschen.';
$lang['pm_notargetperms'] = 'Sie haben nicht die Berechtigung, die Seite %s anzulegen.';
$lang['pm_nomediatargetperms'] = 'Sie haben nicht die Berechtigung, die Mediendatei %s anzulegen.';
$lang['pm_filelocked']  = 'Die Seite %s ist gesperrt - versuchen Sie es später noch einmal.';
$lang['pm_linkchange']  = 'Links angepasst weil Seiten im Wiki verschoben wurden';

$lang['pm_ns_move_in_progress'] = 'Das Verschieben von %s Seiten und %s Mediendateien vom Namensraum %s nach %s ist derzeit im Gange.';
$lang['pm_ns_move_continue'] = 'Das Verschieben des Namensraumes fortsetzen';
$lang['pm_ns_move_abort'] = 'Das Verschieben des Namensraumes abbrechen';
$lang['pm_ns_move_continued'] = 'Das Verschieben des Namensraumes %s nach %s wurde fortgesetzt, %s Einträge müssen noch verschoben werden.';
$lang['pm_ns_move_started'] = 'Das Verschieben des Namensraumes %s nach %s wurde begonnen, %s Seiten und %s Mediendateien werden verschoben werden.';
$lang['pm_ns_move_error'] = 'Ein Fehler ist beim Verschieben des Namensraumes %s nach %s aufgetreten.';
$lang['pm_ns_move_tryagain'] = 'Nochmal versuchen';
$lang['pm_ns_move_skip'] = 'Den aktuellen Eintrag überspringen';
// Form labels
$lang['pm_newname']		= 'Neuer Seitenname:';
$lang['pm_newnsname']   = 'Neuer Name für Namensraum:';
$lang['pm_targetns']	= 'Wählen Sie einen neuen Namensraum: ';
$lang['pm_newtargetns'] = 'Erstellen Sie einen neuen Namensraum';
$lang['pm_movepage']	= 'Seite verschieben';
$lang['pm_movens']		= 'Namensraum verschieben';
$lang['pm_submit']      = 'Übernehmen';
// JavaScript preview
$lang['js']['pm_previewpage'] = 'OLDPAGE wird in NEWPAGE umbenannt';
$lang['js']['pm_previewns']	= 'Alle Seiten und Namensräume im Namensraum OLDNS werden in den Namensraum NEWNS verschoben';
