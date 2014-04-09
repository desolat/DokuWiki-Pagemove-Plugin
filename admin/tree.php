<?php

class admin_plugin_move_tree extends DokuWiki_Admin_Plugin {

    public function getMenuText($language) {
        return $this->getLang('treemanager');
    }

    public function handle() {

    }

    public function html() {

        $data = $this->tree();

        // wrap a list with the root level around the other namespaces
        array_unshift(
            $data, array(
                        'level' => 0, 'id' => '*', 'type' => 'd',
                        'open' => 'true', 'label' => $this->getLang('root')
                   )
        );

        echo html_buildlist(
            $data, 'plugin_move_tree',
            array($this, 'html_list'),
            array($this, 'html_li')
        );


        echo '<button class="plugin_move_tree_exec">go</button>';
    }

    /**
     * Build a tree info structure from media and page directories
     *
     * We reuse some code from the ACL plugin here
     *
     * @param string $open The hierarchy to open
     * @param string $base The namespace to start from
     * @return array
     */
    function tree($open = '', $base = '') {
        $opendir = utf8_encodeFN(str_replace(':', '/', $open));
        $basedir = utf8_encodeFN(str_replace(':', '/', $base));

        /** @var admin_plugin_acl $aclplugin */
        $aclplugin = plugin_load('admin', 'acl');

        return $aclplugin->_get_tree($opendir, $basedir);
    }

    /**
     * Item formatter for the tree view
     *
     * User function for html_buildlist()
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function html_list($item) {
        $ret = '';
        // what to display
        if(!empty($item['label'])) {
            $base = $item['label'];
        } else {
            $base = ':' . $item['id'];
            $base = substr($base, strrpos($base, ':') + 1);
        }

        if($item['id'] == '*') $item['id'] = '';

        // namespace or page?
        if($item['type'] == 'd') {
            $ret .= '<a href="' . $item['id'] . '" class="idx_dir">';
            $ret .= $base;
            $ret .= '</a>';
        } else {
            $ret .= '<a class="wikilink1">';
            $ret .= noNS($item['id']);
            $ret .= '</a>';
        }
        return $ret;
    }

    /**
     * print the opening LI for a list item
     *
     * @param array $item
     * @return string
     */
    function html_li($item) {
        if($item['id'] == '*') $item['id'] = '';

        $params = array();
        $params['class'] = ($item['open'] ? 'open' : 'closed') . ' type-' . $item['type'];
        $params['data-parent'] = getNS($item['id']);
        $params['data-name'] = noNS($item['id']);
        $params['data-id'] = $item['id'];
        $attr = buildAttributes($params);
        return "<li $attr>";
    }

}