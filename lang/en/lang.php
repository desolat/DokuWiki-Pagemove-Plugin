<?php
/**
 * english language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gary Owen <>
 */

// settings must be present and set appropriately for the language
$lang['encoding']  = 'utf-8';
$lang['direction'] = 'ltr';

$lang['menu']        = 'Move pages and namespaces';
$lang['inprogress']  = '(move pending)';
$lang['treelink']    = 'Alternatively to this simple form you can manage complex restructuring of your wiki using the <a href="%s">tree-based move manager</a>.';

$lang['notexist']           = 'The page %s does not exist';
$lang['medianotexist']      = 'The media file %s does not exist';
$lang['notwrite']           = 'You do not have sufficient permissions to modify this page';
$lang['badns']              = 'Invalid characters in namespace.';
$lang['badname']            = 'Invalid characters in pagename.';
$lang['nochange']           = 'Page name and namespace are unchanged.';
$lang['nomediachange']      = 'Media file name and namespace are unchanged.';
$lang['existing']           = 'A page called %s already exists in %s';
$lang['mediaexisting']      = 'A media file called %s already exists in %s';
$lang['root']               = '[Root namespace]';
$lang['current']            = '(Current)';
$lang['renamed']            = 'Page name changed from %s to %s';
$lang['moved']              = 'Page moved from %s to %s';
$lang['move_rename']        = 'Page moved and renamed from %s to %s';
$lang['delete']             = 'Deleted by move plugin';
$lang['norights']           = 'You have insufficient permissions to edit %s.';
$lang['nomediarights']      = 'You have insufficient permissions to delete %s.';
$lang['notargetperms']      = 'You don\'t have the permission to create the page %s.';
$lang['nomediatargetperms'] = 'You don\'t have the permission to create the media file %s.';
$lang['filelocked']         = 'The page %s is locked. Try again later.';
$lang['linkchange']         = 'Links adapted because of a move operation';

$lang['nodst'] = 'No new name given';
$lang['noaction'] = 'There were no moves defined';

$lang['intro']   = 'The move operation has not been started, yet!';
$lang['preview'] = 'Preview changes to be executed.';

$lang['ns_move_in_progress'] = 'There is currently a namespace move of %s page and %s media files from namespace %s to namespace %s in progress.';
$lang['ns_move_continue']    = 'Continue the namespace move';
$lang['ns_move_abort']       = 'Abort the namespace move';
$lang['ns_move_continued']   = 'The namespace move from namespace %s to namespace %s was continued, %s items are still remaining.';
$lang['ns_move_started']     = 'A namespace move from namespace %s to namespace %s was started, %s pages and %s media files will be moved.';
$lang['ns_move_error']       = 'An error occurred while continueing the namespace move from %s to %s.';
$lang['ns_move_tryagain']    = 'Try again';
$lang['ns_move_skip']        = 'Skip the current item';

$lang['btn_start']    = 'Start';
$lang['btn_continue'] = 'Continue';
$lang['btn_retry']    = 'Retry item';
$lang['btn_skip']     = 'Skip item';
$lang['btn_abort']    = 'Abort';

// Form labels
$lang['legend'] = 'Move current page or namespace';

$lang['movepage']             = 'Move page';
$lang['movens']               = 'Move namespace';
$lang['dst']                  = 'New name:';
$lang['content_to_move']      = 'Content to move:';
$lang['autoskip']             = 'Ignore errors and skip pages that can\'t be moved';
$lang['autorewrite']          = 'Rewrite links right after the move completed';
$lang['move_pages']           = 'Pages';
$lang['move_media']           = 'Media files';
$lang['move_media_and_pages'] = 'Pages and media files';

// Rename feature
$lang['renamepage']       = 'Rename Page';
$lang['cantrename']       = 'The page can\'t be renamed right now. Please try later.';
$lang['js']['rename']     = 'Rename';
$lang['js']['cancel']     = 'Cancel';
$lang['js']['newname']    = 'New name:';
$lang['js']['inprogress'] = 'renaming page and adjusting links...';
$lang['js']['complete']   = 'Move operation finished.';

// Tree Manager
$lang['pages']          = 'Pages';
$lang['media']          = 'Media';
$lang['noscript']       = 'This feature requires JavaScript';
$lang['moveinprogress'] = 'There is another move operation in progress currently, you can\'t use this tool right now.';
