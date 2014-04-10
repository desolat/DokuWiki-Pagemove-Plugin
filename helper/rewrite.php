<?php
/**
 * Move Plugin Page Rewriter
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Gary Owen <gary@isection.co.uk>
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// load required handler class
require_once(__DIR__ . '/handler.php');

/**
 * Class helper_plugin_move_rewrite
 *
 * This class handles the rewriting of wiki text to update the links
 */
class helper_plugin_move_rewrite extends DokuWiki_Plugin {

    /**
     * @var string symbol to make move operations easily recognizable in change log
     */
    public $symbol = '↷';

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
    public function rewrite_content($text, $id, $moves, $media_moves = array()) {
        $moves       = $this->resolve_moves($moves, $id);
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
    public function resolve_moves($moves, $id) {
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
                    // Wait a second when the page has just been rewritten
                    $oldRev = filemtime(wikiFN($id));
                    if($oldRev == time()) sleep(1);

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


}