<?php
/**
 * Plugin : Move
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Helper part of the move plugin.
 */
class helper_plugin_move_general extends DokuWiki_Plugin {

    /**
     * @var string symbol to make move operations easily recognizable in change log
     */
    public $symbol = '↷';

    /**
     * @var array access to the info the last move in execution
     *
     * the same as the data passed to the PLUGIN_MOVE_PAGE_RENAME and PLUGIN_MOVE_MEDIA_RENAME events,
     * used for internal introspection in the move plugin. This is ephemeral data and may be outdated or
     * misleading when not read directly after a page or media move operation
     */
    public $lastmove = array();

    /**
     * Start a namespace move by creating the list of all pages and media files that shall be moved
     *
     * @param array $opts The options for the namespace move
     * @return int The number of items to move
     */
    public function start_namespace_move(&$opts) {
        global $conf;

        // generate and save a list of all pages
        $pagelist = array();
        $pathToSearch = utf8_encodeFN(str_replace(':', '/', $opts['ns']));
        $searchOpts = array('depth' => 0, 'skipacl' => true);
        if (in_array($opts['contenttomove'], array('pages', 'both'))) {
            search($pagelist, $conf['datadir'], 'search_allpages', $searchOpts, $pathToSearch);
        }
        $pages = array();
        foreach ($pagelist as $page) {
            $pages[] = $page['id'];
        }
        unset($pagelist);

        $opts['num_pages'] = count($pages);

        $files = $this->get_namespace_meta_files();
        io_saveFile($files['pagelist'], implode("\n", $pages));
        unset($pages);

        // generate and save a list of all media files
        $medialist = array();
        if (in_array($opts['contenttomove'], array('media,', 'both'))) {
            search($medialist, $conf['mediadir'], 'search_media', $searchOpts, $pathToSearch);
        }

        $media_files = array();
        foreach ($medialist as $media) {
            $media_files[] = $media['id'];
        }
        unset ($medialist);

        $opts['started']   = time(); // remember when this move started
        $opts['affected']  = 0; // will be filled in later
        $opts['num_media'] = count($media_files);

        io_saveFile($files['medialist'], implode("\n", $media_files));

        $opts['remaining'] = $opts['num_media'] + $opts['num_pages'];

        // save the options
        io_saveFile($files['opts'], serialize($opts));

        return $opts['num_pages'] + $opts['num_media'];
    }

    /**
     * Execute the next steps of the currently running namespace move
     *
     * This function will move up to 10 pages or media files or adjust the links of affected pages.
     * It is repeatedly called via AJAX (or several clicks from the user if JavaScript is missing)
     *
     * @return bool|int False if an error occurred, otherwise the number of remaining moves
     */
    public function continue_namespace_move() {
        global $ID;
        global $conf;

        $files = $this->get_namespace_meta_files();

        if (!@file_exists($files['opts'])) {
            msg('Error: there are no saved options', -1);
            return false;
        }

        $opts = unserialize(file_get_contents($files['opts']));

        // handle page moves
        if (@file_exists($files['pagelist']) && (filesize($files['pagelist']) > 1) ) {
            $pagelist = fopen($files['pagelist'], 'a+');;

            for ($i = 0; $i < 10; ++$i) {
                $ID = $this->get_last_id($pagelist);
                if ($ID === false) {
                    break;
                }
                $newID = $this->getNewID($ID, $opts['ns'], $opts['newns']);
                $pageOpts = $opts;
                $pageOpts['ns']   = getNS($ID);
                $pageOpts['name'] = noNS($ID);
                $pageOpts['newname'] = noNS($ID);
                $pageOpts['newns'] = getNS($newID);
                if (!$this->move_page($pageOpts)) {
                    fclose($pagelist);
                    $this->log($opts['started'], 'P', $ID, $newID, false);

                    // automatically skip this item if wanted
                    if($opts['autoskip']) {
                        return $this->skip_namespace_move_item();
                    }
                    return false;
                }
                $this->log($opts['started'], 'P', $ID, $newID, true);

                // remember affected pages
                io_saveFile($files['affected'], join("\n", $this->lastmove['affected_pages'])."\n", true);

                // update the list of pages and the options after every move
                ftruncate($pagelist, ftell($pagelist));
                $opts['remaining']--;
                io_saveFile($files['opts'], serialize($opts));
            }

            fclose($pagelist);
            return max(1, $opts['remaining']); // force one more call
        }

        // handle media moves
        if (@file_exists($files['medialist']) && (filesize($files['medialist']) > 1) ) {
            $medialist = fopen($files['medialist'], 'a+');

            for ($i = 0; $i < 10; ++$i) {
                $ID = $this->get_last_id($medialist);
                if ($ID === false) {
                    break;
                }
                $newID = $this->getNewID($ID, $opts['ns'], $opts['newns']);
                $pageOpts = $opts;
                $pageOpts['ns']   = getNS($ID);
                $pageOpts['name'] = noNS($ID);
                $pageOpts['newname'] = noNS($ID);
                $pageOpts['newns'] = getNS($newID);
                if (!$this->move_media($pageOpts)) {
                    fclose($medialist);
                    $this->log($opts['started'], 'M', $ID, $newID, false);

                    // automatically skip this item if wanted
                    if($opts['autoskip']) {
                        return $this->skip_namespace_move_item();
                    }
                    return false;
                }
                $this->log($opts['started'], 'M', $ID, $newID, true);

                // remember affected pages
                io_saveFile($files['affected'], join("\n", $this->lastmove['affected_pages'])."\n", true);

                // update the list of media files and the options after every move
                ftruncate($medialist, ftell($medialist));
                $opts['remaining']--;
                io_saveFile($files['opts'], serialize($opts));
            }

            fclose($medialist);
            return max(1, $opts['remaining']); // force one more call
        }

        // update affected pages
        if($opts['autorewrite'] && @file_exists($files['affected']) && (filesize($files['affected']) > 1)) {
            if(!$opts['affected']) {
                // this is the first run, clean up the file
                $affected = io_readFile($files['affected']);
                $affected = explode("\n", $affected);
                $affected = array_unique($affected);
                $affected = array_filter($affected);
                sort($affected);
                if($affected[0] === '') array_shift($affected);
                io_saveFile($files['affected'], join("\n", $affected));

                $opts['affected'] = count($affected);
                $opts['remaining'] = $opts['affected']; // something to do again
                io_saveFile($files['opts'], serialize($opts));

                return max(1, $opts['remaining']); // force one more call
            }

            // handle affected pages
            $affectedlist = fopen($files['affected'], 'a+');
            for ($i = 0; $i < 10; ++$i) {
                $ID = $this->get_last_id($affectedlist);
                if ($ID === false) {
                    break;
                }

                // rewrite it
                $this->execute_rewrites($ID, null);

                // update the list of media files and the options after every move
                ftruncate($affectedlist, ftell($affectedlist));
                $opts['remaining']--;
                io_saveFile($files['opts'], serialize($opts));
            }

            return max(1, $opts['remaining']); // force one more call
        }

        // move all namespace subscriptions
        $this->move_files(
            $conf['metadir'],
            array(
                 'ns' => $opts['ns'],
                 'newns' => $opts['newns'],
                 'name' => '',
                 'newname' => ''
            ),
            '\.mlist'
        );

        // still here? the move is completed
        $this->abort_namespace_move();
        return 0;
    }

    /**
     * Preview all single move operations in a namespace move operation
     */
    public function preview_namespace_move() {
        $files = $this->get_namespace_meta_files();

        if (!@file_exists($files['opts'])) {
            msg('Error: there are no saved options', -1);
            return;
        }
        $opts = unserialize(file_get_contents($files['opts']));

        echo '<ul>';
        if (@file_exists($files['pagelist'])) {
            $pagelist = file($files['pagelist']);
            foreach($pagelist as $old) {
                $new = $this->getNewID($old, $opts['ns'], $opts['newns']);

                echo '<li class="page"><div class="li">';
                echo hsc($old);
                echo '→';
                echo hsc($new);
                echo '</div></li>';
            }
        }
        if (@file_exists($files['medialist'])) {
            $medialist = file($files['medialist']);
            foreach($medialist as $old) {
                $new = $this->getNewID($old, $opts['ns'], $opts['newns']);

                echo '<li class="media"><div class="li">';
                echo hsc($old);
                echo '→';
                echo hsc($new);
                echo '</div></li>';
            }
        }
        echo '</ul>';
    }



    /**
     * Skip the item that would be executed next in the current namespace move
     *
     * @return bool|int False if an error occurred, otherwise the number of remaining moves
     */
    public function skip_namespace_move_item() {
        global $ID;
        $files = $this->get_namespace_meta_files();

        if (!@file_exists($files['opts'])) {
            msg('Error: there are no saved options', -1);
            return false;
        }

        $opts = unserialize(file_get_contents($files['opts']));

        if (@file_exists($files['pagelist'])) {
            $pagelist = fopen($files['pagelist'], 'a+');

            $ID = $this->get_last_id($pagelist);
            // save the list of pages after every move
            if ($ID === false || ftell($pagelist) == 0) {
                fclose($pagelist);
                unlink($files['pagelist']);
            } else {
                ftruncate($pagelist, ftell($pagelist));;
                fclose($pagelist);
            }
        } elseif (@file_exists($files['medialist'])) {
            $medialist = fopen($files['medialist'], 'a+');

            $ID = $this->get_last_id($medialist);;
            // save the list of media files after every move
            if ($ID === false || ftell($medialist) == 0) {
                fclose($medialist);
                unlink($files['medialist']);
                unlink($files['opts']);
            } else {
                ftruncate($medialist, ftell($medialist));
            }
        } else {
            unlink($files['opts']);
        }
        if ($opts['remaining'] == 0) return 0;
        else {
            $opts['remaining']--;
            // save the options
            io_saveFile($files['opts'], serialize($opts));
            return $opts['remaining'];
        }
    }

    /**
     * Log result of an operation
     *
     * @param int $optime
     * @param string $type
     * @param string $from
     * @param string $to
     * @param bool $success
     * @author Andreas Gohr <gohr@cosmocode.de>
     */
    private function log($optime, $type, $from, $to, $success){
        global $conf;
        global $MSG;


        $file = $conf['cachedir'].'/move-'.$optime.'.log';
        $now  = time();
        $date = date('Y-m-d H:i:s', $now); // for human readability

        if($success) {
            $ok  = 'success';
            $msg = '';
        }else {
            $ok  = 'failed';
            $msg = $MSG[count($MSG)-1]['msg']; // get detail from message array
        }

        $log  = "$now\t$date\t$type\t$from\t$to\t$ok\t$msg\n";
        io_saveFile($file, $log, true);
    }

    /**
     * Get last file id from the list that is stored in the file that is referenced by the handle
     * The handle is set to the newline before the file id
     *
     * @param resource $handle The file handle to read from
     * @return string|bool the last id from the list or false if there is none
     */
    private function get_last_id($handle) {
        // begin the seek at the end of the file
        fseek($handle, 0, SEEK_END);
        $id = '';

        // seek one backwards as long as it's possible
        while (fseek($handle, -1, SEEK_CUR) >= 0) {
            $c = fgetc($handle);
            fseek($handle, -1, SEEK_CUR); // reset the position to the character that was read

            if ($c == "\n") {
                break;
            }
            if ($c === false) return false; // EOF, i.e. the file is empty
            $id = $c.$id;
        }

        if ($id === '') return false; // nothing was read i.e. the file is empty
        else return $id;
    }

    /**
     * Abort the currently running namespace move
     */
    public function abort_namespace_move() {
        $files = $this->get_namespace_meta_files();
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Get the options for the namespace move that is currently in progress if there is any
     *
     * @return bool|array False if there is no namespace move in progress, otherwise the array of options
     */
    public function get_namespace_move_opts() {
        $files = $this->get_namespace_meta_files();

        if (!@file_exists($files['opts'])) {
            return false;
        }

        $opts = unserialize(file_get_contents($files['opts']));

        return $opts;
    }

    /**
     * Get the filenames for the metadata of the move plugin
     *
     * @return array The file names for opts, pagelist and medialist
     * @moved
     */
    protected function get_namespace_meta_files() {
        global $conf;
        return array(
            'opts' => $conf['metadir'].'/__move_opts',
            'pagelist' => $conf['metadir'].'/__move_pagelist',
            'medialist' => $conf['metadir'].'/__move_medialist',
            'affected' => $conf['metadir'].'/__move_affected',
        );
    }

    /**
     * Get the id of a page after a namespace move
     *
     * @param string $oldid The old id of the page
     * @param string $oldns The old namespace. $oldid needs to be inside $oldns
     * @param string $newns The new namespace
     * @return string The new id
     */
    function getNewID($oldid, $oldns, $newns) {
        $newid = $oldid;
        if ($oldns != '') {
            $newid = substr($oldid, strlen($oldns)+1);
        }

        if ($newns != '') {
            $newid = $newns.':'.$newid;
        }

        return $newid;
    }

    /**
     * move page
     *
     * @author  Gary Owen <gary@isection.co.uk>, modified by Kay Roesler
     * @author  Michael Hamann <michael@content-space.de>
     *
     * @param array $opts
     * @param bool  $checkonly Only execute the checks if the page can be moved
     * @return bool If the move was executed
     */
    public function move_page(&$opts, $checkonly = false) {
        global $ID;

        // Check we have rights to move this document
        if ( !page_exists($ID)) {
            msg(sprintf($this->getLang('notexist'), $ID), -1);
            return false;
        }
        if ( auth_quickaclcheck($ID) < AUTH_EDIT ) {
            msg(sprintf($this->getLang('norights'), hsc($ID)), -1);
            return false;
        }

        // Check file is not locked
        // checklock checks if the page lock hasn't expired and the page hasn't been locked by another user
        // the file exists check checks if the page is reported unlocked if a lock exists which means that
        // the page is locked by the current user
        if (checklock($ID) !== false || @file_exists(wikiLockFN($ID))) {
            msg( sprintf($this->getLang('filelocked'), hsc($ID)), -1);
            return false;
        }

        // Assemble fill document name and path
        $opts['new_id'] = cleanID($opts['newns'].':'.$opts['newname']);
        $opts['new_path'] = wikiFN($opts['new_id']);

        // Has the document name and/or namespace changed?
        if ( $opts['newns'] == $opts['ns'] && $opts['newname'] == $opts['name'] ) {
            msg($this->getLang('nochange'), -1);
            return false;
        }
        // Check the page does not already exist
        if ( @file_exists($opts['new_path']) ) {
            msg(sprintf($this->getLang('existing'), $opts['newname'], ($opts['newns'] == '' ? $this->getLang('root') : $opts['newns'])), -1);
            return false;
        }

        // Check if the current user can create the new page
        if (auth_quickaclcheck($opts['new_id']) < AUTH_CREATE) {
            msg(sprintf($this->getLang('notargetperms'), $opts['new_id']), -1);
            return false;
        }

        if ($checkonly) return true;

        /**
         * End of init (checks)
         */

        $page_meta  = $this->getMoveMeta($ID);
        if (!$page_meta) $page_meta = array();
        if (!isset($page_meta['old_ids'])) $page_meta['old_ids'] = array();
        $page_meta['old_ids'][$ID] = time();

        // ft_backlinks() is not used here, as it does a hidden page and acl check but we really need all pages
        $affected_pages = idx_get_indexer()->lookupKey('relation_references', $ID);

        $this->lastmove = array('opts' => &$opts, 'old_ids' => $page_meta['old_ids'], 'affected_pages' => &$affected_pages);
        // give plugins the option to add their own meta files to the list of files that need to be moved
        // to the oldfiles/newfiles array or to adjust their own metadata, database, ...
        // and to add other pages to the affected pages
        // note that old_ids is in the form 'id' => timestamp of move
        $event = new Doku_Event('PLUGIN_MOVE_PAGE_RENAME', $this->lastmove);
        if ($event->advise_before()) {
            // Open the old document and change forward links
            lock($ID);
            $text = rawWiki($ID);

            $text   = $this->rewrite_content($text, $ID, array($ID => $opts['new_id']));
            $oldRev = getRevisions($ID, -1, 1, 1024); // from changelog

            // Move the Subscriptions & Indexes
            if (method_exists('Doku_Indexer', 'renamePage')) { // new feature since Spring 2013 release
                $Indexer = idx_get_indexer();
            } else {
                $Indexer = new helper_plugin_move_indexer(); // copy of the new code
            }
            if (($idx_msg = $Indexer->renamePage($ID, $opts['new_id'])) !== true
                || ($idx_msg = $Indexer->renameMetaValue('relation_references', $ID, $opts['new_id'])) !== true) {
                msg('Error while updating the search index '.$idx_msg, -1);
                return false;
            }
            if (!$this->movemeta($opts)) {
                msg('The meta files of page '.$ID.' couldn\'t be moved', -1);
                return false;
            }

            // Save the updated document in its new location
            if ($opts['ns'] == $opts['newns']) {
                $lang_key = 'renamed';
            }
            elseif ( $opts['name'] == $opts['newname'] ) {
                $lang_key = 'moved';
            }
            else {
                $lang_key = 'move_rename';
            }

            // Wait a second when the page has just been rewritten
            if ($oldRev == time()) sleep(1);

            $summary = sprintf($this->getLang($lang_key), $ID, $opts['new_id']);
            saveWikiText($opts['new_id'], $text, $this->symbol.' '.$summary);

            // Delete the orginal file
            if (@file_exists(wikiFN($opts['new_id']))) {
                saveWikiText($ID, '', $this->symbol.' '.$summary);
            }

            // Move the old revisions
            if (!$this->moveattic($opts)) {
                // it's too late to stop the move, so just display a message.
                msg('The attic files of page '.$ID.' couldn\'t be moved. Please move them manually.', -1);
            }

            foreach ($affected_pages as $id) {
                if (!page_exists($id, '', false) || $id == $ID || $id == $opts['new_id']) continue;
                // we are only interested in persistent metadata, so no need to render anything.
                $meta = $this->getMoveMeta($id);
                if (!$meta) $meta = array('moves' => array());
                if (!isset($meta['moves'])) $meta['moves'] = array();
                $meta['moves'] = $this->resolve_moves($meta['moves'], $id);
                $meta['moves'][$ID] = $opts['new_id'];
                //if (empty($meta['moves'])) unset($meta['moves']);
                p_set_metadata($id, array('plugin_move' => $meta), false, true);
            }

            p_set_metadata($opts['new_id'], array('plugin_move' => $page_meta), false, true);

            unlock($ID);
        }

        $event->advise_after();
        return true;
    }

    /**
     * Move media file
     *
     * @author  Michael Hamann <michael@content-space.de>
     *
     * @param array $opts
     * @param bool  $checkonly Only execute the checks if the media file can be moved
     * @return bool If the move was executed
     */
    public function move_media(&$opts, $checkonly = false) {
        $opts['id'] = cleanID($opts['ns'].':'.$opts['name']);
        $opts['path'] = mediaFN($opts['id']);

        // Check we have rights to move this document
        if ( !file_exists(mediaFN($opts['id']))) {
            msg(sprintf($this->getLang('medianotexist'), hsc($opts['id'])), -1);
            return false;
        }

        if ( auth_quickaclcheck($opts['ns'].':*') < AUTH_DELETE ) {
            msg(sprintf($this->getLang('nomediarights'), hsc($opts['id'])), -1);
            return false;
        }

        // Assemble media name and path
        $opts['new_id'] = cleanID($opts['newns'].':'.$opts['newname']);
        $opts['new_path'] = mediaFN($opts['new_id']);

        // Has the document name and/or namespace changed?
        if ( $opts['newns'] == $opts['ns'] && $opts['newname'] == $opts['name'] ) {
            msg($this->getLang('nomediachange'), -1);
            return false;
        }
        // Check the page does not already exist
        if ( @file_exists($opts['new_path']) ) {
            msg(sprintf($this->getLang('mediaexisting'), $opts['newname'], ($opts['newns'] == '' ? $this->getLang('root') : $opts['newns'])), -1);
            return false;
        }

        // Check if the current user can create the new page
        if (auth_quickaclcheck($opts['new_ns'].':*') < AUTH_UPLOAD) {
            msg(sprintf($this->getLang('nomediatargetperms'), $opts['new_id']), -1);
            return false;
        }

        if ($checkonly) return true;

        /**
         * End of init (checks)
         */

        $affected_pages = idx_get_indexer()->lookupKey('relation_media', $opts['id']);

        $this->lastmove = array('opts' => &$opts, 'affected_pages' => &$affected_pages);
        // give plugins the option to add their own meta files to the list of files that need to be moved
        // to the oldfiles/newfiles array or to adjust their own metadata, database, ...
        // and to add other pages to the affected pages
        $event = new Doku_Event('PLUGIN_MOVE_MEDIA_RENAME', $this->lastmove);
        if ($event->advise_before()) {
            // Move the Subscriptions & Indexes
            if (method_exists('Doku_Indexer', 'renamePage')) { // new feature since Spring 2013 release
                $Indexer = idx_get_indexer();
            } else {
                $Indexer = new helper_plugin_move_indexer(); // copy of the new code
            }
            if (($idx_msg = $Indexer->renameMetaValue('relation_media', $opts['id'], $opts['new_id'])) !== true) {
                msg('Error while updating the search index '.$idx_msg, -1);
                return false;
            }
            if (!$this->movemediameta($opts)) {
                msg('The meta files of the media file '.$opts['id'].' couldn\'t be moved', -1);
                return false;
            }

            // prepare directory
            io_createNamespace($opts['new_id'], 'media');

            if (!io_rename($opts['path'], $opts['new_path'])) {
                msg('Moving the media file '.$opts['id'].' failed', -1);
                return false;
            }

            io_sweepNS($opts['id'], 'mediadir');

            // Move the old revisions
            if (!$this->movemediaattic($opts)) {
                // it's too late to stop the move, so just display a message.
                msg('The attic files of media file '.$opts['id'].' couldn\'t be moved. Please move them manually.', -1);
            }

            foreach ($affected_pages as $id) {
                if (!page_exists($id, '', false)) continue;
                $meta = $this->getMoveMeta($id);
                if (!$meta) $meta = array('media_moves' => array());
                if (!isset($meta['media_moves'])) $meta['media_moves'] = array();
                $meta['media_moves'] = $this->resolve_moves($meta['media_moves'], '__');
                $meta['media_moves'][$opts['id']] = $opts['new_id'];
                //if (empty($meta['moves'])) unset($meta['moves']);
                p_set_metadata($id, array('plugin_move' => $meta), false, true);
            }
        }

        $event->advise_after();
        return true;
    }

    /**
     * Move the old revisions of the media file that is specified in the options
     *
     * @param array $opts Move options (used here: name, newname, ns, newns)
     * @return bool If the attic files were moved successfully
     */
    public function movemediaattic($opts) {
        global $conf;

        $ext = mimetype($opts['name']);
        if ($ext[0] !== false) {
            $name = substr($opts['name'],0, -1*strlen($ext[0])-1);
        } else {
            $name = $opts['name'];
        }
        $newext = mimetype($opts['newname']);
        if ($ext[0] !== false) {
            $newname = substr($opts['newname'],0, -1*strlen($ext[0])-1);
        } else {
            $newname = $opts['newname'];
        }
        $regex = '\.\d+\.'.preg_quote((string)$ext[0], '/');

        return $this->move_files($conf['mediaolddir'], array(
            'ns' => $opts['ns'],
            'newns' => $opts['newns'],
            'name' => $name,
            'newname' => $newname
        ), $regex);
    }

    /**
     * Move the meta files of the page that is specified in the options.
     *
     * @param array $opts Move options (used here: name, newname, ns, newns)
     * @return bool If the meta files were moved successfully
     */
    public function movemediameta($opts) {
        global $conf;

        $regex = '\.[^.]+';
        return $this->move_files($conf['mediametadir'], $opts, $regex);
    }

    /**
     * Move the old revisions of the page that is specified in the options.
     *
     * @param array $opts Move options (used here: name, newname, ns, newns)
     * @return bool If the attic files were moved successfully
     */
    public function moveattic($opts) {
        global $conf;

        $regex = '\.\d+\.txt(?:\.gz|\.bz2)?';
        return $this->move_files($conf['olddir'], $opts, $regex);
    }

    /**
     * Move the meta files of the page that is specified in the options.
     *
     * @param array $opts Move options (used here: name, newname, ns, newns)
     * @return bool If the meta files were moved successfully
     */
    public function movemeta($opts) {
        global $conf;

        $regex = '\.[^.]+';
        return $this->move_files($conf['metadir'], $opts, $regex);
    }

    /**
     * Internal function for moving and renaming meta/attic files between namespaces
     *
     * @param string $dir   The root path of the files (e.g. $conf['metadir'] or $conf['olddir']
     * @param array  $opts  Move options (used here: ns, newns, name, newname)
     * @param string $extregex Regular expression for matching the extension of the file that shall be moved
     * @return bool If the files were moved successfully
     */
    private function move_files($dir, $opts, $extregex) {
        $old_path = $dir;
        if ($opts['ns'] != '') $old_path .= '/'.utf8_encodeFN(str_replace(':', '/', $opts['ns']));
        $new_path = $dir;
        if ($opts['newns'] != '') $new_path .= '/'.utf8_encodeFN(str_replace(':', '/', $opts['newns']));
        $regex = '/^'.preg_quote(utf8_encodeFN($opts['name'])).'('.$extregex.')$/u';

        if (!is_dir($old_path)) return true; // no media files found

        $dh = @opendir($old_path);
        if($dh) {
            while(($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..') continue;
                $match = array();
                if (is_file($old_path.'/'.$file) && preg_match($regex, $file, $match)) {
                    if (!is_dir($new_path)) {
                        if (!io_mkdir_p($new_path)) {
                            msg('Creating directory '.hsc($new_path).' failed.', -1);
                            return false;
                        }
                    }
                    if (!io_rename($old_path.'/'.$file, $new_path.'/'.utf8_encodeFN($opts['newname'].$match[1]))) {
                        msg('Moving '.hsc($old_path.'/'.$file).' to '.hsc($new_path.'/'.utf8_encodeFN($opts['newname'].$match[1])).' failed.', -1);
                        return false;
                    }
                }
            }
            closedir($dh);
        } else {
            msg('Directory '.hsc($old_path).' couldn\'t be opened.', -1);
            return false;
        }
        return true;
    }

    /**
     * Rewrite the text of a page according to the recorded moves, the rewritten text is saved
     *
     * @param string      $id   The id of the page that shall be rewritten
     * @param string|null $text Old content of the page. When null is given the content is loaded from disk.
     * @return string|bool The rewritten content, false on error
     */
    public function execute_rewrites($id, $text = null) {
        $meta = $this->getMoveMeta($id);
        if($meta && (isset($meta['moves']) || isset($meta['media_moves']))) {
            if(is_null($text)) $text = rawWiki($id);
            $moves = isset($meta['moves']) ? $meta['moves'] : array();
            $media_moves = isset($meta['media_moves']) ? $meta['media_moves'] : array();

            $old_text = $text;
            $text = $this->rewrite_content($text, $id, $moves, $media_moves);
            $changed = ($old_text != $text);
            $file = wikiFN($id, '', false);
            if(is_writable($file) || !$changed) {
                if ($changed) {
                    // Wait a second if page has just been saved
                    $oldRev = getRevisions($id, -1, 1, 1024); // from changelog
                    if ($oldRev == time()) sleep(1);
                    saveWikiText($id, $text, $this->symbol.' '.$this->getLang('linkchange'), $this->getConf('minor'));
                }
                unset($meta['moves']);
                unset($meta['media_moves']);
                p_set_metadata($id, array('plugin_move' => $meta), false, true);
            } else { // FIXME: print error here or fail silently?
                msg('Error: Page '.hsc($id).' needs to be rewritten because of page renames but is not writable.', -1);
            }
        }

        return $text;
    }

    /**
     * Rewrite a text in order to fix the content after the given moves.
     *
     * @param string $text   The wiki text that shall be rewritten
     * @param string $id     The id of the wiki page, if the page itself was moved the old id
     * @param array $moves  Array of all page moves, the keys are the old ids, the values the new ids
     * @param array $media_moves Array of all media moves.
     * @return string        The rewritten wiki text
     */
    function rewrite_content($text, $id, $moves, $media_moves = array()) {
        $moves = $this->resolve_moves($moves, $id);
        $media_moves = $this->resolve_moves($media_moves, $id);

        $handlers = array();
        $data     = array('id' => $id, 'moves' => &$moves, 'media_moves' => &$media_moves, 'handlers' => &$handlers);

        /*
         * PLUGIN_MOVE_HANDLERS REGISTER event:
         *
         * Plugin handlers can be registered in the $handlers array, the key is the plugin name as it is given to the handler
         * The handler needs to be a valid callback, it will get the following parameters:
         * $match, $state, $pos, $pluginname, $handler. The first three parameters are equivalent to the parameters
         * of the handle()-function of syntax plugins, the $pluginname is just the plugin name again so handler functions
         * that handle multiple plugins can distinguish for which the match is. The last parameter is the handler object.
         * It has the following properties and functions that can be used:
         * - id, ns: id and namespace of the old page
         * - new_id, new_ns: new id and namespace (can be identical to id and ns)
         * - moves: array of moves, the same as $moves in the event
         * - media_moves: array of media moves, same as $media_moves in the event
         * - adaptRelativeId($id): adapts the relative $id according to the moves
         */
        trigger_event('PLUGIN_MOVE_HANDLERS_REGISTER', $data);

        $modes = p_get_parsermodes();

        // Create the parser
        $Parser = new Doku_Parser();

        // Add the Handler
        $Parser->Handler = new helper_plugin_move_handler($id, $moves, $media_moves, $handlers);

        //add modes to parser
        foreach($modes as $mode) {
            $Parser->addMode($mode['mode'], $mode['obj']);
        }

        return $Parser->parse($text);
    }

    /**
     * Resolves the provided moves, i.e. it calculates for each page the final page it was moved to.
     *
     * @param array $moves The moves
     * @param string $id
     * @return array The resolved moves
     */
    protected function resolve_moves($moves, $id) {
        // resolve moves of pages that were moved more than once
        $tmp_moves = array();
        foreach($moves as $old => $new) {
            if($old != $id && isset($moves[$new]) && $moves[$new] != $new) {
                // write to temp array in order to correctly handle rename circles
                $tmp_moves[$old] = $moves[$new];
            }
        }

        $changed = !empty($tmp_moves);

        // this correctly resolves rename circles by moving forward one step a time
        while($changed) {
            $changed = false;
            foreach($tmp_moves as $old => $new) {
                if($old != $new && isset($moves[$new]) && $moves[$new] != $new && $tmp_moves[$new] != $new) {
                    $tmp_moves[$old] = $moves[$new];
                    $changed         = true;
                }
            }
        }

        // manual merge, we can't use array_merge here as ids can be numeric
        foreach($tmp_moves as $old => $new) {
            if($old == $new) unset($moves[$old]);
            else $moves[$old] = $new;
        }
        return $moves;
    }

    /**
     * Get the HTML code of a namespace move button
     * @param string $action The desired action of the button (continue, tryagain, skip, abort)
     * @param string|null $id The id of the target page, null if $ID shall be used
     * @return bool|string The HTML of code of the form or false if an invalid action was supplied
     */
    public function getNSMoveButton($action, $id = NULL) {
        if ($id === NULL) {
            global $ID;
            $id = $ID;
        }

        $class = 'move__nsform';
        switch ($action) {
            case 'continue':
            case 'tryagain':
                $class .= ' move__nscontinue';
                break;
            case 'skip':
                $class .= ' move__nsskip';
                break;
        }

        $form = new Doku_Form(array('action' => wl($id), 'method' => 'post', 'class' => $class));
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('id', $id);
        switch ($action) {
            case 'continue':
            case 'tryagain':
                $form->addHidden('continue_namespace_move', true);
                if ($action == 'tryagain') {
                    $form->addElement(form_makeButton('submit', 'admin', $this->getLang('ns_move_tryagain')));
                } else {
                    $form->addElement(form_makeButton('submit', 'admin', $this->getLang('ns_move_continue')));
                }
                break;
            case 'skip':
                $form->addHidden('skip_continue_namespace_move', true);
                $form->addElement(form_makeButton('submit', 'admin', $this->getLang('ns_move_skip')));
                break;
            case 'abort':
                $form->addHidden('abort_namespace_move', true);
                $form->addElement(form_makeButton('submit', 'admin', $this->getLang('ns_move_abort')));
                break;
            default:
                return false;
        }
        return $form->getForm();
    }

    /**
     * This function loads and returns the persistent metadata for the move plugin. If there is metadata for the
     * pagemove plugin (not the old one but the version that immediately preceeded the move plugin) it will be migrated.
     *
     * @param string $id The id of the page the metadata shall be loaded for
     * @return array|null The metadata of the page
     */
    public function getMoveMeta($id) {
        $all_meta = p_get_metadata($id, '', METADATA_DONT_RENDER);
        // migrate old metadata from the pagemove plugin
        if (isset($all_meta['plugin_pagemove']) && !is_null($all_meta['plugin_pagemove'])) {
            if (isset($all_meta['plugin_move'])) {
                $all_meta['plugin_move'] = array_merge_recursive($all_meta['plugin_pagemove'], $all_meta['plugin_move']);
            } else {
                $all_meta['plugin_move'] = $all_meta['plugin_pagemove'];
            }
            p_set_metadata($id, array('plugin_move' => $all_meta['plugin_move'], 'plugin_pagemove' => null), false, true);
        }
        return isset($all_meta['plugin_move']) ? $all_meta['plugin_move'] : null;
    }

    /**
     * Determines if it would be okay to show a rename page button for the given page and current user
     *
     * @param $id
     * @return bool
     */
    public function renameOkay($id) {
        global $ACT;
        global $USERINFO;
        if ( !($ACT == 'show' || empty($ACT)) ) return false;
        if (!page_exists($id)) return false;
        if (auth_quickaclcheck($id) < AUTH_EDIT ) return false;
        if (checklock($id) !== false || @file_exists(wikiLockFN($id))) return false;
        if(!auth_isMember($this->getConf('allowrename'), $_SERVER['REMOTE_USER'], $USERINFO['grps'])) return false;

        return true;
    }

    /**
     * Use this in your template to add a simple "move this page" link
     *
     * Alternatively give anything the class "plugin_move_page" - it will automatically be hidden and shown and
     * trigger the page move dialog.
     */
    public function tpl() {
        echo '<a href="" class="plugin_move_page">';
        echo $this->getLang('renamepage');
        echo '</a>';
    }
}

/**
 * Indexer class extended by move features, only needed and used in releases older than Spring 2013
 */
class helper_plugin_move_indexer extends Doku_Indexer {
    /**
     * Rename a page in the search index without changing the indexed content
     *
     * @param string $oldpage The old page name
     * @param string $newpage The new page name
     * @return string|bool If the page was successfully renamed, can be a message in the case of an error
     */
    public function renamePage($oldpage, $newpage) {
        if (!$this->lock()) return 'locked';

        $pages = $this->getPages();

        $id = array_search($oldpage, $pages);
        if ($id === false) {
            $this->unlock();
            return 'page is not in index';
        }

        $new_id = array_search($newpage, $pages);
        if ($new_id !== false) {
            $this->unlock();
            // make sure the page is not in the index anymore
            $this->deletePage($newpage);
            if (!$this->lock()) return 'locked';

            $pages[$new_id] = 'deleted:'.time().rand(0, 9999);
        }

        $pages[$id] = $newpage;

        // update index
        if (!$this->saveIndex('page', '', $pages)) {
            $this->unlock();
            return false;
        }

        $this->unlock();
        return true;
    }

    /**
     * Renames a meta value in the index. This doesn't change the meta value in the pages, it assumes that all pages
     * will be updated.
     *
     * @param string $key       The metadata key of which a value shall be changed
     * @param string $oldvalue  The old value that shall be renamed
     * @param string $newvalue  The new value to which the old value shall be renamed, can exist (then values will be merged)
     * @return bool|string      If renaming the value has been successful, false or error message on error.
     */
    public function renameMetaValue($key, $oldvalue, $newvalue) {
        if (!$this->lock()) return 'locked';

        // change the relation references index
        $metavalues = $this->getIndex($key, '_w');
        $oldid = array_search($oldvalue, $metavalues);
        if ($oldid !== false) {
            $newid = array_search($newvalue, $metavalues);
            if ($newid !== false) {
                // free memory
                unset ($metavalues);

                // okay, now we have two entries for the same value. we need to merge them.
                $indexline = $this->getIndexKey($key, '_i', $oldid);
                if ($indexline != '') {
                    $newindexline = $this->getIndexKey($key, '_i', $newid);
                    $pagekeys     = $this->getIndex($key, '_p');
                    $parts = explode(':', $indexline);
                    foreach ($parts as $part) {
                        list($id, $count) = explode('*', $part);
                        $newindexline =  $this->updateTuple($newindexline, $id, $count);

                        $keyline = explode(':', $pagekeys[$id]);
                        // remove old meta value
                        $keyline = array_diff($keyline, array($oldid));
                        // add new meta value when not already present
                        if (!in_array($newid, $keyline)) {
                            array_push($keyline, $newid);
                        }
                        $pagekeys[$id] = implode(':', $keyline);
                    }
                    $this->saveIndex($key, '_p', $pagekeys);
                    unset($pagekeys);
                    $this->saveIndexKey($key, '_i', $oldid, '');
                    $this->saveIndexKey($key, '_i', $newid, $newindexline);
                }
            } else {
                $metavalues[$oldid] = $newvalue;
                if (!$this->saveIndex($key, '_w', $metavalues)) {
                    $this->unlock();
                    return false;
                }
            }
        }

        $this->unlock();
        return true;
    }
}


