<?php
/**
 * Move Plugin Tree Loading Functionality
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class action_plugin_move_rewrite
 */
class action_plugin_move_tree extends DokuWiki_Action_Plugin {

    /**
     * Register event handlers.
     *
     * @param Doku_Event_Handler $controller The plugin controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call');
    }

    /**
     * Render a subtree
     *
     * @param Doku_Event $event
     * @param            $params
     */
    public function handle_ajax_call(Doku_Event $event, $params) {
        if($event->data != 'plugin_move_tree') return;
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;
        global $USERINFO;

        if(!auth_ismanager($_SERVER['REMOTE_USER'], $USERINFO['grps'])) {
            http_status(403);
            exit;
        }

        /** @var admin_plugin_move_tree $plugin */
        $plugin = plugin_load('admin', 'move_tree');

        $ns = cleanID($INPUT->str('ns'));
        if($INPUT->bool('is_media')) {
            $type = admin_plugin_move_tree::TYPE_MEDIA;
        } else {
            $type = admin_plugin_move_tree::TYPE_PAGES;
        }

        $data = $plugin->tree($type, $ns, $ns);

        echo html_buildlist(
            $data, 'tree_list',
            array($plugin, 'html_list'),
            array($plugin, 'html_li')
        );
    }

}