<?php

class admin_plugin_move_tree extends DokuWiki_Admin_Plugin {

    const TYPE_PAGES = 1;
    const TYPE_MEDIA = 2;

    public function getMenuText($language) {
        return $this->getLang('treemanager');
    }

    public function handle() {

    }

    public function html() {
        echo '<div id="plugin_move__tree">';

        echo '<div class="tree_root tree_pages">';
        echo '<h3>Pages</h3>'; // FIXME localize
        $this->htmlTree(self::TYPE_PAGES);
        echo '</div>';

        echo '<div class="tree_root tree_media">';
        echo '<h3>Media</h3>'; // FIXME localize
        $this->htmlTree(self::TYPE_MEDIA);
        echo '</div>';

        echo '<div class="controls">';
        echo '<button class="plugin_move_tree_exec">go</button>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * print the HTML tree structure
     *
     * @param int $type
     */
    protected function htmlTree($type = self::TYPE_PAGES) {
        $data = $this->tree($type);

        // wrap a list with the root level around the other namespaces
        array_unshift(
            $data, array(
                     'level' => 0, 'id' => '*', 'type' => 'd',
                     'open'  => 'true', 'label' => $this->getLang('root')
                 )
        );
        echo html_buildlist(
            $data, 'tree_list',
            array($this, 'html_list'),
            array($this, 'html_li')
        );
    }

    /**
     * Build a tree info structure from media or page directories
     *
     * @param int    $type
     * @param string $open The hierarchy to open
     * @param string $base The namespace to start from
     * @return array
     */
    public function tree($type = self::TYPE_PAGES, $open = '', $base = '') {
        global $conf;

        $opendir = utf8_encodeFN(str_replace(':', '/', $open));
        $basedir = utf8_encodeFN(str_replace(':', '/', $base));

        $data = array();
        if($type == self::TYPE_PAGES) {
            search($data, $conf['datadir'], 'search_index', array('ns' => $opendir), $basedir);
        } elseif($type == self::TYPE_MEDIA) {
            search($data, $conf['mediadir'], 'search_index', array('ns' => $opendir), $basedir);
        }

        return $data;
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