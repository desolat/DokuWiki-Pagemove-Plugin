<?php
/**
 * Action part of the pagemove plugin
 *
 * @author Michael Hamann <michael@content-space.de>
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
   }

    /**
     * Rewrite pages when they are read and they need to be updated.
     *
     * @param Doku_Event $event The event object
     * @param mixed      $param Optional parameters (not used)
     */
    function handle_read(Doku_Event $event, $param) {
        static $stack = array();
        // handle only reads of the current revision
        if ($event->data[3]) return;

        $id = $event->data[2];
        if ($event->data[1]) $id = $event->data[1].':'.$id;
        if (isset($stack[$id])) return;
        $meta = p_get_metadata($id, 'plugin_pagemove', METADATA_DONT_RENDER);
        if ($meta && isset($meta['moves'])) {
            $stack[$id] = true;
            $helper = $this->loadHelper('pagemove', true);
            if (!is_null($helper)) {
                $event->result = $helper->rewrite_content($event->result, $id, $meta['moves']);
            }
            $file = wikiFN($id, '', false);
            if (is_writable($file)) {
                saveWikiText($id,$event->result,$this->getLang('pm_linkchange'));
                unset($meta['moves']);
                p_set_metadata($id, array('plugin_pagemove' => $meta), false, true);
            } else { // FIXME: print error here or fail silently?
                msg('Error: Page '.hsc($id).' needs to be rewritten because of page renames but is not writable.', -1);
            }
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
        /** @var $cache cache_parser */
        $cache = $event->data;
        $id = $cache->page;
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
}