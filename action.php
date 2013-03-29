<?php
/**
 * Plugin : Pagemove
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Action part of the pagemove plugin
 */
class action_plugin_pagemove extends DokuWiki_Action_Plugin {
    /**
     * Register event handlers.
     *
     * @param Doku_Event_Handler $controller The plugin controller
     */
    public function register($controller) {
        $controller->register_hook('IO_WIKIPAGE_READ', 'AFTER', $this, 'handle_read', array());
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_cache', array());
        $controller->register_hook('INDEXER_VERSION_GET', 'BEFORE', $this, 'handle_index_version');
        $controller->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, 'index_media_use');
   }

    /**
     * Rewrite pages when they are read and they need to be updated.
     *
     * @param Doku_Event $event The event object
     * @param mixed      $param Optional parameters (not used)
     */
    function handle_read(Doku_Event $event, $param) {
        global $ACT, $conf;
        static $stack = array();
        // handle only reads of the current revision
        if ($event->data[3]) return;

        $id = $event->data[2];
        if ($event->data[1]) $id = $event->data[1].':'.$id;

        if (!$id) {
            // try to reconstruct the id from the filename
            $path = $event->data[0][0];
            if (strpos($path, $conf['datadir']) === 0) {
                $path = substr($path, strlen($conf['datadir'])+1);
                $id = pathID($path);
            }
        }

        if (isset($stack[$id])) return;

        // Don't change the page when the user is currently changing the page content or the page is locked by another user
        if ((isset($ACT) && (in_array($ACT, array('save', 'preview', 'recover', 'revert'))))
            || checklock($id) !== false) return;

        $helper = $this->loadHelper('pagemove', true);
        if(!is_null($helper)) {
            $stack[$id]    = true;
            $event->result = $helper->execute_rewrites($id, $event->result);
            unset($stack[$id]);
        }
    }

    /**
     * Handle the cache events, it looks if a page needs to be rewritten so it can expire the cache of the page
     *
     * @param Doku_Event $event The even object
     * @param mixed      $param Optional parameters (not used)
     */
    function handle_cache(Doku_Event $event, $param) {
        global $conf;
        /** @var $cache cache_parser */
        $cache = $event->data;
        $id = $cache->page;
        if (!$id) {
            // try to reconstruct the id from the filename
            $path = $cache->file;
            if (strpos($path, $conf['datadir']) === 0) {
                $path = substr($path, strlen($conf['datadir'])+1);
                $id = pathID($path);
            }
        }
        if ($id) {
            $meta = p_get_metadata($id, 'plugin_pagemove', METADATA_DONT_RENDER);
            if ($meta && isset($meta['moves'])) {
                $file = wikiFN($id, '', false);
                if (is_writable($file))
                    $cache->depends['purge'] = true;
                else // FIXME: print error here or fail silently?
                    msg('Error: Page '.hsc($id).' needs to be rewritten because of page renames but is not writable.', -1);
            }
        }
    }

    /**
     * Add the pagemove version to theindex version
     *
     * @param Doku_Event $event The event object
     * @param array $param Optional parameters (unused)
     */
    public function handle_index_version(Doku_Event $event, $param) {
        $event->data['plugin_pagemove'] = 0.1;
    }

    /**
     * Index media usage data
     *
     * @param Doku_Event $event The event object
     * @param array $param  Optional parameters (unused)
     */
    public function index_media_use(Doku_Event $event, $param) {
        $id = $event->data['page'];
        $media_references = array();
        $instructions = p_cached_instructions(wikiFn($id), false, $id);
        if (is_array($instructions)) {
            $this->get_media_references_from_instructions($instructions, $media_references, $id);
        }
        $media_references = array_unique($media_references);
        $event->data['metadata']['pagemove_media'] = $media_references;
    }

    /**
     * Helper function for getting all media references from an instruction array
     *
     * @param array $instructions The instructions to scan
     * @param array $media_references The array of media references
     * @param string $id The reference id for resolving media ids
     */
    private function get_media_references_from_instructions($instructions, &$media_references, $id) {
        foreach ($instructions as $ins) {
            if ($ins[0] === 'internalmedia') {
                $src = $ins[1][0];
                list($src,$hash) = explode('#',$src,2);
                resolve_mediaid(getNS($id),$src, $exists);
                $media_references[] = $src;
            } elseif (in_array($ins[0], array('interwikilink', 'windowssharelink', 'externallink', 'emaillink', 'locallink', 'internallink'))) {
                $img = $ins[1][1];
                if (is_array($img) && $img['type'] === 'internalmedia') {
                    list($src,$hash) = explode('#',$img['src'],2);
                    resolve_mediaid(getNS($id), $src, $exists);
                    $media_references[] = $src;
                }
            } elseif ($ins[0] === 'nest') {
                // nested instructions
                $this->get_media_references_from_instructions($ins[1][0], $media_references, $id);
            } elseif ($ins[0] === 'plugin' && $ins[1][0] === 'variants_variants') {
                // the variants plugin has two branches with nested instructions, both need to be rewritten
                $this->get_media_references_from_instructions($ins[1][1][1], $media_references, $id);
                $this->get_media_references_from_instructions($ins[1][1][2], $media_references, $id);
            }
        }
    }
}