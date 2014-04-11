<?php
/**
 * Move Plugin Operation Execution
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Gary Owen <gary@isection.co.uk>
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_move_op extends DokuWiki_Plugin {

    /**
     * @var string symbol to make move operations easily recognizable in change log
     */
    public $symbol = '↷';

    /**
     * @var array stores the affected pages of the last operation
     */
    protected $affectedPages = array();

    /**
     * Check if the given page can be moved to the given destination
     *
     * @param $src
     * @param $dst
     * @return bool
     */
    public function checkPage($src, $dst) {
        // Check we have rights to move this document
        if(!page_exists($src)) {
            msg(sprintf($this->getLang('notexist'), hsc($src)), -1);
            return false;
        }
        if(auth_quickaclcheck($src) < AUTH_EDIT) {
            msg(sprintf($this->getLang('norights'), hsc($src)), -1);
            return false;
        }

        // Check file is not locked
        // checklock checks if the page lock hasn't expired and the page hasn't been locked by another user
        // the file exists check checks if the page is reported unlocked if a lock exists which means that
        // the page is locked by the current user
        if(checklock($src) !== false || @file_exists(wikiLockFN($src))) {
            msg(sprintf($this->getLang('filelocked'), hsc($src)), -1);
            return false;
        }

        // Has the document name and/or namespace changed?
        if($src == $dst) {
            msg($this->getLang('nochange'), -1);
            return false;
        }

        // Check the page does not already exist
        if(page_exists($dst)) {
            msg(sprintf($this->getLang('existing'), $dst), -1); // FIXME adjust string
            return false;
        }

        // Check if the current user can create the new page
        if(auth_quickaclcheck($dst) < AUTH_CREATE) {
            msg(sprintf($this->getLang('notargetperms'), $dst), -1);
            return false;
        }

        return true;
    }

    /**
     * Check if the given media file can be moved to the given destination
     *
     * @param $src
     * @param $dst
     * @return bool
     */
    public function checkMedia($src, $dst) {
        // Check we have rights to move this document
        if(!file_exists(mediaFN($src))) {
            msg(sprintf($this->getLang('medianotexist'), hsc($src)), -1);
            return false;
        }
        if(auth_quickaclcheck($src) < AUTH_DELETE) {
            msg(sprintf($this->getLang('nomediarights'), hsc($src)), -1);
            return false;
        }

        // Has the document name and/or namespace changed?
        if($src == $dst) {
            msg($this->getLang('nomediachange'), -1);
            return false;
        }

        // Check the page does not already exist
        if(@file_exists(mediaFN($dst))) {
            msg(sprintf($this->getLang('mediaexisting'), $dst), -1); // FIXME adjust string
            return false;
        }

        // Check if the current user can create the new page
        if(auth_quickaclcheck($dst) < AUTH_UPLOAD) {
            msg(sprintf($this->getLang('nomediatargetperms'), $dst), -1);
            return false;
        }

        return true;
    }

    /**
     * Execute a page move/rename
     *
     * @param string $src original ID
     * @param string $dst new ID
     * @return bool
     */
    public function movePage($src, $dst) {
        if(!$this->checkPage($src, $dst)) return false;

        /** @var helper_plugin_move_rewrite $Rewriter */
        $Rewriter = plugin_load('helper', 'move_rewrite');

        // remember what this page was called before the move in meta data
        $page_meta = $Rewriter->getMoveMeta($src);
        if(!$page_meta) $page_meta = array();
        if(!isset($page_meta['old_ids'])) $page_meta['old_ids'] = array();
        $page_meta['old_ids'][$src] = time();

        // ft_backlinks() is not used here, as it does a hidden page and acl check but we really need all pages
        $affected_pages = idx_get_indexer()->lookupKey('relation_references', $src);

        $src_ns   = getNS($src);
        $src_name = noNS($src);
        $dst_ns   = getNS($dst);
        $dst_name = noNS($dst);

        // pass this info on to other plugins
        $eventdata = array(
            // this is for compatibility to old plugin
            'opts'           => array(
                'ns'      => $src_ns,
                'name'    => $src_name,
                'newns'   => $dst_ns,
                'newname' => $dst_name,
            ),
            'old_ids'        => $page_meta['old_ids'],
            'affected_pages' => &$affected_pages,
            'src_id'         => $src,
            'dst_id'         => $dst,
        );

        // give plugins the option to add their own meta files to the list of files that need to be moved
        // to the oldfiles/newfiles array or to adjust their own metadata, database, ...
        // and to add other pages to the affected pages
        // note that old_ids is in the form 'id' => timestamp of move
        $event = new Doku_Event('PLUGIN_MOVE_PAGE_RENAME', $eventdata);
        if($event->advise_before()) {
            lock($src);

            /** @var helper_plugin_move_file $FileMover */
            $FileMover = plugin_load('helper', 'move_file');

            // Open the old document and change forward links
            $text = rawWiki($src);
            $text = $Rewriter->rewrite_content($text, $src, array($src => $dst));

            // Move the Subscriptions & Indexes (new feature since Spring 2013 release)
            $Indexer = idx_get_indexer();
            if(($idx_msg = $Indexer->renamePage($src, $dst)) !== true
                || ($idx_msg = $Indexer->renameMetaValue('relation_references', $src, $dst)) !== true
            ) {
                msg('Error while updating the search index ' . $idx_msg, -1);
                return false;
            }
            if(!$FileMover->movePageMeta($src_ns, $src_name, $dst_ns, $dst_name)) {
                msg('The meta files of page ' . $src . ' couldn\'t be moved', -1);
                return false;
            }

            // prepare the summary for the changelog entry
            if($src_ns == $dst_ns) {
                $lang_key = 'renamed';
            } elseif($src_name == $dst_name) {
                $lang_key = 'moved';
            } else {
                $lang_key = 'move_rename';
            }
            $summary = $this->symbol . ' ' . sprintf($this->getLang($lang_key), $src, $dst);

            // Wait a second when the page has just been rewritten
            $oldRev = filemtime(wikiFN($src));
            if($oldRev == time()) sleep(1);

            // Save the updated document in its new location
            saveWikiText($dst, $text, $summary);

            // Delete the orginal file
            if(@file_exists(wikiFN($dst))) {
                saveWikiText($src, '', $summary);
            }

            // Move the old revisions
            if(!$FileMover->movePageAttic($src_ns, $src_name, $dst_ns, $dst_name)) {
                // it's too late to stop the move, so just display a message.
                msg('The attic files of page ' . $src . ' couldn\'t be moved. Please move them manually.', -1);
            }

            // Add meta data to all affected pages, so links get updated later
            foreach($affected_pages as $id) {
                if(!page_exists($id, '', false) || $id == $src || $id == $dst) continue;
                // we are only interested in persistent metadata, so no need to render anything.
                $meta = $Rewriter->getMoveMeta($id);
                if(!$meta) $meta = array('moves' => array());
                if(!isset($meta['moves'])) $meta['moves'] = array();
                $meta['moves']       = $Rewriter->resolve_moves($meta['moves'], $id);
                $meta['moves'][$src] = $dst;
                p_set_metadata($id, array('plugin_move' => $meta), false, true);
            }
            p_set_metadata($dst, array('plugin_move' => $page_meta), false, true);

            unlock($src);
        }
        $event->advise_after();

        // store this for later use
        $this->affectedPages = $affected_pages;

        return true;
    }

    /**
     * Execute a media file move/rename
     *
     * @param string $src original ID
     * @param string $dst new ID
     * @return bool true if the move was successfully executed
     */
    public function moveMedia($src, $dst) {
        if(!$this->checkMedia($src, $dst)) return false;

        // get all pages using this media
        $affected_pages = idx_get_indexer()->lookupKey('relation_media', $src);

        $src_ns   = getNS($src);
        $src_name = noNS($src);
        $dst_ns   = getNS($dst);
        $dst_name = noNS($dst);

        // pass this info on to other plugins
        $eventdata = array(
            // this is for compatibility to old plugin
            'opts'           => array(
                'ns'      => $src_ns,
                'name'    => $src_name,
                'newns'   => $dst_ns,
                'newname' => $dst_name,
            ),
            'affected_pages' => &$affected_pages,
            'src_id'         => $src,
            'dst_id'         => $dst,
        );

        // give plugins the option to add their own meta files to the list of files that need to be moved
        // to the oldfiles/newfiles array or to adjust their own metadata, database, ...
        // and to add other pages to the affected pages
        $event = new Doku_Event('PLUGIN_MOVE_MEDIA_RENAME', $eventdata);
        if($event->advise_before()) {
            /** @var helper_plugin_move_file $FileMover */
            $FileMover = plugin_load('helper', 'move_file');
            /** @var helper_plugin_move_rewrite $Rewriter */
            $Rewriter = plugin_load('helper', 'move_rewrite');

            // Move the Subscriptions & Indexes (new feature since Spring 2013 release)
            $Indexer = idx_get_indexer();
            if(($idx_msg = $Indexer->renameMetaValue('relation_media', $src, $dst)) !== true) {
                msg('Error while updating the search index ' . $idx_msg, -1);
                return false;
            }
            if(!$FileMover->moveMediaMeta($src_ns, $src_name, $dst_ns, $dst_name)) {
                msg('The meta files of the media file ' . $src . ' couldn\'t be moved', -1);
                return false;
            }

            // prepare directory
            io_createNamespace($dst, 'media');

            // move it FIXME this does not create a changelog entry!
            if(!io_rename(mediaFN($src), mediaFN($dst))) {
                msg('Moving the media file ' . $src . ' failed', -1);
                return false;
            }

            // clean up old ns
            io_sweepNS($src, 'mediadir');

            // Move the old revisions
            if(!$FileMover->moveMediaAttic($src_ns, $src_name, $dst_ns, $dst_name)) {
                // it's too late to stop the move, so just display a message.
                msg('The attic files of media file ' . $src . ' couldn\'t be moved. Please move them manually.', -1);
            }

            // Add meta data to all affected pages, so links get updated later
            foreach($affected_pages as $id) {
                if(!page_exists($id, '', false)) continue;
                $meta = $Rewriter->getMoveMeta($id);
                if(!$meta) $meta = array('media_moves' => array());
                if(!isset($meta['media_moves'])) $meta['media_moves'] = array();
                $meta['media_moves']       = $Rewriter->resolve_moves($meta['media_moves'], '__');
                $meta['media_moves'][$src] = $dst;
                p_set_metadata($id, array('plugin_move' => $meta), false, true);
            }
        }
        $event->advise_after();

        // store this for later use
        $this->affectedPages = $affected_pages;

        return true;
    }

    /**
     * Get a list of pages that where affected by the last successful move operation
     *
     * @return array
     */
    public function getAffectedPages() {
        return $this->affectedPages;
    }
}