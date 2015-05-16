<?php
/**
 * swedish language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gary Owen <>
 */

// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';

// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'Flytt/Namnbyte på Sida/Namnrymd...';
$lang['desc'] = 'Plugin för Flytt/Namnbyte på Sida/Namnrymd...';

$lang['notexist']    = 'Sidan %s finns inte';
$lang['medianotexist']    = 'Media filen %s finns inte';
$lang['notwrite']    = 'Du har inte tillräcklig behörighet att ändar på den här sidan';
$lang['badns']       = 'Otillåtet tecken i namnrymd.';
$lang['badname']     = 'Otillåtet tecken i sidnamn.';
$lang['nochange']    = 'Sid- och namnrymdsnamn är inte ändrade';
$lang['nomediachange']    = 'Mediafil- och namnrymdsnamn är inte ändrade';
$lang['existing']    = 'En sida som heter %s finns redan i %s';
$lang['mediaexisting']    = 'En mediafil som heter %s finns redan i %s';
$lang['root']        = '[Rotnamnrymd]';
$lang['current']     = '[Nuvarande]';
$lang['renamed']     = 'Sidnamn ändrat från %s till %s';
$lang['moved']       = 'Sida flyttad från %s till %s';
$lang['move_rename'] = 'Sida flyttad och namnändrad från %s till %s';
$lang['delete']     = 'Borttagen av pluginen';
$lang['norights']    = 'Du har inte tillräcklig behörighet för att ändra %s.';
$lang['nomediarights']    = 'Du har inte tillräcklig behörighet att ta bort %s.';
$lang['notargetperms'] = 'Du har inte tillräcklig behörighet för att skapa sidan %s.';
$lang['nomediatargetperms'] = 'Du har inte tillräcklig behörighet för att skapa mediafilen %s.';
$lang['filelocked']  = 'Sidan %s är låst. Försök senare.';
$lang['linkchange']  = 'Länkar som ändrats på grund av flytt/namnändring';

$lang['ns_move_in_progress'] = 'En namnrymdsflytt eller namnändring av %s sidor och %s mediafiler pågår.';
$lang['ns_move_continue'] = 'Fortsätt flytt av namnrymd';
$lang['ns_move_abort'] = 'Avbryt flytt av namnrymd';
$lang['ns_move_continued'] = 'Namnrymdsflytt från %s till %s pågår, %s åtgärder återstår.';
$lang['ns_move_started'] = 'En namnrymdsflytt från %s till %s har startat, %s sidor och %s mediafiler kommer att flyttas.';
$lang['ns_move_error'] = 'Ett fel uppstod under namnrymdsflytt från %s till %s.';
$lang['ns_move_tryagain'] = 'Försök igen';
$lang['ns_move_skip'] = 'Hoppa över nuvarande åtgärd';
// Form labels
$lang['newname']     = 'Nytt sidnamn:';
$lang['newnsname']   = 'Nytt namn för namnrymden:';
$lang['targetns']    = 'Välj ny namnrymd:';
$lang['newtargetns'] = 'Skapa ny namnrymd:';
$lang['movepage']   = 'Flytta sida';
$lang['movens']     = 'Flytta namnrymd';
$lang['submit']      = 'Genomför';
$lang['content_to_move'] = 'Innehåll att flytta';
$lang['move_pages']  = 'Sidor';
$lang['move_media']  = 'Mediafiler';
$lang['move_media_and_pages'] = 'Sidor och mediafiler';
// JavaScript preview
$lang['js']['previewpage'] = 'OLDPAGE kommer att flyttas till NEWPAGE';
$lang['js']['previewns'] = 'Alla sidor och namnrymder i namnrymden OLDNS kommer att flyttas till namnrymden NEWNS';
