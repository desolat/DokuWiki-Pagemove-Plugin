<?php
/**
 * Move Plugin Operation Planner
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
     * Check if the given page can be moved to the given destination
     *
     * @param $src
     * @param $dst
     * @return bool
     */
    public function checkPage($src, $dst) {
        // Check we have rights to move this document
        if(!page_exists($src)) {
            msg(sprintf($this->getLang('notexist'), $src), -1);
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
            msg(sprintf($this->getLang('existing'), $dst), -1);
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
     * Execute a page rename
     *
     * @param string $src
     * @param string $dst
     * @return bool
     */
    public function movePage($src, $dst) {
        if(!$this->checkPage($src, $dst)) return false;

        // remember what this page was called before the move in meta data
        $page_meta = $this->getMoveMeta($src);
        if(!$page_meta) $page_meta = array();
        if(!isset($page_meta['old_ids'])) $page_meta['old_ids'] = array();
        $page_meta['old_ids'][$src] = time();

        // ft_backlinks() is not used here, as it does a hidden page and acl check but we really need all pages
        $affected_pages = idx_get_indexer()->lookupKey('relation_references', $src);

        // pass this info on to other plugins
        $eventdata = array(
            // this is for compatibility to old plugin
            'opts' => array(
                'ns' => getNS($src),
                'name' => noNS($src),
                'newns' => getNS($dst),
                'newname' => noNS($dst),
            ),
            'old_ids' => $page_meta['old_ids'],
            'affected_pages' => &$affected_pages,
            'src_id' => $src,
            'dst_id' => $dst,
        );

        // give plugins the option to add their own meta files to the list of files that need to be moved
        // to the oldfiles/newfiles array or to adjust their own metadata, database, ...
        // and to add other pages to the affected pages
        // note that old_ids is in the form 'id' => timestamp of move
        $event = new Doku_Event('PLUGIN_MOVE_PAGE_RENAME', $eventdata);
        if($event->advise_before()) {
            // Open the old document and change forward links
            lock($src);
            $text = rawWiki($src);

            $text = $this->rewrite_content($text, $src, array($src => $dst));
            $oldRev = getRevisions($src, -1, 1, 1024); // from changelog

            // Move the Subscriptions & Indexes
            if(method_exists('Doku_Indexer', 'renamePage')) { // new feature since Spring 2013 release
                $Indexer = idx_get_indexer();
            } else {
                $Indexer = new helper_plugin_move_indexer(); // copy of the new code
            }
            if(($idx_msg = $Indexer->renamePage($src, $dst)) !== true
                || ($idx_msg = $Indexer->renameMetaValue('relation_references', $src, $dst)) !== true
            ) {
                msg('Error while updating the search index ' . $idx_msg, -1);
                return false;
            }
            if(!$this->movemeta($opts)) {
                msg('The meta files of page ' . $src . ' couldn\'t be moved', -1);
                return false;
            }

            // Save the updated document in its new location
            if($opts['ns'] == $opts['newns']) {
                $lang_key = 'renamed';
            } elseif($opts['name'] == $opts['newname']) {
                $lang_key = 'moved';
            } else {
                $lang_key = 'move_rename';
            }

            // Wait a second when the page has just been rewritten
            if($oldRev == time()) sleep(1);

            $summary = sprintf($this->getLang($lang_key), $src, $opts['new_id']);
            saveWikiText($opts['new_id'], $text, $this->symbol . ' ' . $summary);

            // Delete the orginal file
            if(@file_exists(wikiFN($opts['new_id']))) {
                saveWikiText($src, '', $this->symbol . ' ' . $summary);
            }

            // Move the old revisions
            if(!$this->moveattic($opts)) {
                // it's too late to stop the move, so just display a message.
                msg('The attic files of page ' . $src . ' couldn\'t be moved. Please move them manually.', -1);
            }

            foreach($affected_pages as $id) {
                if(!page_exists($id, '', false) || $id == $src || $id == $opts['new_id']) continue;
                // we are only interested in persistent metadata, so no need to render anything.
                $meta = $this->getMoveMeta($id);
                if(!$meta) $meta = array('moves' => array());
                if(!isset($meta['moves'])) $meta['moves'] = array();
                $meta['moves'] = $this->resolve_moves($meta['moves'], $id);
                $meta['moves'][$src] = $opts['new_id'];
                //if (empty($meta['moves'])) unset($meta['moves']);
                p_set_metadata($id, array('plugin_move' => $meta), false, true);
            }

            p_set_metadata($opts['new_id'], array('plugin_move' => $page_meta), false, true);

            unlock($src);
        }

        $event->advise_after();
        return true;
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
        if(isset($all_meta['plugin_pagemove']) && !is_null($all_meta['plugin_pagemove'])) {
            if(isset($all_meta['plugin_move'])) {
                $all_meta['plugin_move'] = array_merge_recursive($all_meta['plugin_pagemove'], $all_meta['plugin_move']);
            } else {
                $all_meta['plugin_move'] = $all_meta['plugin_pagemove'];
            }
            p_set_metadata($id, array('plugin_move' => $all_meta['plugin_move'], 'plugin_pagemove' => null), false, true);
        }
        return isset($all_meta['plugin_move']) ? $all_meta['plugin_move'] : null;
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
    protected function rewrite_content($text, $id, $moves, $media_moves = array()) {
        $moves = $this->resolve_moves($moves, $id);
        $media_moves = $this->resolve_moves($media_moves, $id);

        $handlers = array();
        $data = array('id' => $id, 'moves' => &$moves, 'media_moves' => &$media_moves, 'handlers' => &$handlers);

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
                    $changed = true;
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

}