<?php
/**
 * english language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gary Owen <>
 */
 
// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';
 
// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'Page/Namespace Move/Rename...';
$lang['desc'] = 'Page/Namespace Move/Rename Plugin';

$lang['pm_notexist']    = 'The page %s does not exist';
$lang['pm_medianotexist']    = 'The media file %s does not exist';
$lang['pm_notwrite']    = 'You do not have sufficient permissions to modify this page';
$lang['pm_badns']       = 'Invalid characters in namespace.';
$lang['pm_badname']     = 'Invalid characters in pagename.';
$lang['pm_nochange']    = 'Page name and namespace are unchanged.';
$lang['pm_nomediachange']    = 'Media file name and namespace are unchanged.';
$lang['pm_existing']    = 'A page called %s already exists in %s';
$lang['pm_mediaexisting']    = 'A media file called %s already exists in %s';
$lang['pm_root']        = '[Root namespace]';
$lang['pm_current']     = '(Current)';
$lang['pm_renamed']     = 'Page name changed from %s to %s';
$lang['pm_moved']       = 'Page moved from %s to %s';
$lang['pm_move_rename'] = 'Page moved and renamed from %s to %s';
$lang['pm_delete']		= 'Deleted by move plugin';
$lang['pm_norights']    = 'You have insufficient permissions to edit %s.';
$lang['pm_nomediarights']    = 'You have insufficient permissions to delete %s.';
$lang['pm_notargetperms'] = 'You don\'t have the permission to create the page %s.';
$lang['pm_nomediatargetperms'] = 'You don\'t have the permission to create the media file %s.';
$lang['pm_filelocked']  = 'The page %s is locked. Try again later.';
$lang['pm_linkchange']  = 'Links adapted because of a move operation';

$lang['pm_ns_move_in_progress'] = 'There is currently a namespace move of %s page and %s media files from namespace %s to namespace %s in progress.';
$lang['pm_ns_move_continue'] = 'Continue the namespace move';
$lang['pm_ns_move_abort'] = 'Abort the namespace move';
$lang['pm_ns_move_continued'] = 'The namespace move from namespace %s to namespace %s was continued, %s items are still remaining.';
$lang['pm_ns_move_started'] = 'A namespace move from namespace %s to namespace %s was started, %s pages and %s media files will be moved.';
$lang['pm_ns_move_error'] = 'An error occurred while continueing the namespace move from %s to %s.';
$lang['pm_ns_move_tryagain'] = 'Try again';
$lang['pm_ns_move_skip'] = 'Skip the current item';
// Form labels
$lang['pm_newname']     = 'New page name:';
$lang['pm_newnsname']   = 'New namespace name:';
$lang['pm_targetns']    = 'Select new namespace:';
$lang['pm_newtargetns'] = 'Create a new namespace:';
$lang['pm_movepage']	= 'Move page';
$lang['pm_movens']		= 'Move namespace';
$lang['pm_submit']      = 'Submit';
$lang['pm_content_to_move'] = 'Content to move';
$lang['pm_move_pages']  = 'Pages';
$lang['pm_move_media']  = 'Media files';
$lang['pm_move_media_and_pages'] = 'Pages and media files';
// JavaScript preview
$lang['js']['pm_previewpage'] = 'OLDPAGE will be moved to NEWPAGE';
$lang['js']['pm_previewns'] = 'All pages and namespaces in the namespace OLDNS will be moved in the namespace NEWNS';
