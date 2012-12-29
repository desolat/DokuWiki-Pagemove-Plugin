<?php
/**
 * Plugin : Pagemove
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Gary Owen,
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

/**
 * Admin component of the pagemove plugin. Provides the user interface.
 */
class admin_plugin_pagemove extends DokuWiki_Admin_Plugin {

    var $opts = array();

    /**
     * Get the sort number that defines the position in the admin menu.
     *
     * @return int The sort number
     */
    function getMenuSort() { return 1000; }

    /**
     * If this admin plugin is for admins only
     * @return bool false
     */
    function forAdminOnly() { return false; }

    /**
     * return some info
     */
    function getInfo(){
        $result = parent::getInfo();
        $result['desc'] = $this->getLang('desc');
        return $result;
    }

    /**
     * Only show the menu text for pages we can move or rename.
     */
    function getMenuText() {
        global $INFO;

        if( !$INFO['exists'] )
            return $this->getLang('menu').' ('.$this->getLang('pm_notexist').')';
        elseif( !$INFO['writable'] )
            return $this->getLang('menu').' ('.$this->getLang('pm_notwrite').')';
        else
            return $this->getLang('menu');
    }



    /**
     * output appropriate html
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function html() {
        ptln('<!-- Pagemove Plugin start -->');
        ptln( $this->locale_xhtml('pagemove') );
        $this->_pm_form();
        ptln('<!-- Pagemove Plugin end -->');
    }

    /**
     * show the move and/or rename a page form
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_form() {
        global $ID;

        $ns = getNS($ID);

        $ns_select_data = $this->build_namespace_select_content($ns);

        $form = new Doku_Form(array('action' => wl($ID), 'method' => 'post', 'class' => 'pagemove__form'));
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('id', $ID);
        $form->addHidden('move_type', 'page');
        $form->startFieldset($this->getLang('pm_movepage'));
        $form->addElement(form_makeMenuField('ns_for_page', $ns_select_data, $this->opts['ns_for_page'], $this->getLang('pm_targetns'), '', 'block'));
        $form->addElement(form_makeTextField('newns', $this->opts['newns'], $this->getLang('pm_newtargetns'), '', 'block'));
        $form->addElement(form_makeTextField('newname', $this->opts['newname'], $this->getLang('pm_newname'), '', 'block'));
        $form->addElement(form_makeButton('submit', 'admin', $this->getLang('pm_submit')));
        $form->endFieldset();
        $form->printForm();

        $form = new Doku_Form(array('action' => wl($ID), 'method' => 'post', 'class' => 'pagemove__form'));
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('id', $ID);
        $form->addHidden('move_type', 'namespace');
        $form->startFieldset($this->getLang('pm_movens'));
        $form->addElement(form_makeMenuField('targetns', $ns_select_data, $this->opts['targetns'], $this->getLang('pm_targetns'), '', 'block'));
        $form->addElement(form_makeTextField('newnsname', $this->opts['newnsname'], $this->getLang('pm_newnsname'), '', 'block'));
        $form->addElement(form_makeButton('submit', 'admin', $this->getLang('pm_submit')));
        $form->endFieldset();
        $form->printForm();
    }


    /**
     * create a list of namespaces for the html form
     *
     * @author  Michael Hamann <michael@content-space.de>
     * @author  Gary Owen <gary@isection.co.uk>
     * @author  Arno Puschmann (bin out of _pm_form)
     */
    private function build_namespace_select_content($ns) {
        global $conf;

        $result = array();

        $namesp = array( 0 => array('id' => '') );     //Include root
        search($namesp, $conf['datadir'], 'search_namespaces', array());
        sort($namesp);
        foreach($namesp as $row) {
            if ( auth_quickaclcheck($row['id'].':*') >= AUTH_CREATE || $row['id'] == $ns ) {

                $result[($row['id'] ? $row['id'] : ':')] = ($row['id'] ? $row['id'].':' : ": ".$this->getLang('pm_root')).
                                       ($row['id'] == $ns ? ' '.$this->getLang('pm_current') : '');
            }
        }
        return $result;
    }


    /**
     * handle user request
     *
     * @author  Michael Hamann <michael@content-space.de>
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function handle() {
        global $ID;
        global $ACT;
        global $INFO;

        // populate options with default values
        $this->opts['ns']          = getNS($ID);
        $this->opts['name']        = noNS($ID);
        $this->opts['ns_for_page'] = $INFO['namespace'];
        $this->opts['newns']       = '';
        $this->opts['newname']     = noNS($ID);
        $this->opts['targetns']    = getNS($ID);
        $this->opts['newnsname']   = '';
        $this->opts['move_type']   = 'page';

        // Only continue when the form was submitted
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            return;
        }

        // Store the form data in the options and clean the submitted data.
        if (isset($_POST['ns_for_page'])) $this->opts['ns_for_page'] = cleanID((string)$_POST['ns_for_page']);
        if (isset($_POST['newns'])) $this->opts['newns'] = cleanID((string)$_POST['newns']);
        if (isset($_POST['newname'])) $this->opts['newname'] = cleanID((string)$_POST['newname']);
        if (isset($_POST['targetns'])) $this->opts['targetns'] = cleanID((string)$_POST['targetns']);
        if (isset($_POST['newnsname'])) $this->opts['newnsname'] = cleanID((string)$_POST['newnsname']);
        if (isset($_POST['move_type'])) $this->opts['move_type'] = (string)$_POST['move_type'];

        // check the input for completeness
        if( $this->opts['move_type'] == 'namespace' ) {
            if ($this->opts['targetns'] == '') {
                $this->opts['newns'] = $this->opts['newnsname'];
            } else {
                $this->opts['newns'] = $this->opts['targetns'].':'.$this->opts['newnsname'];
            }

            if ($this->_pm_move_recursive($this->opts, true) &&
                $this->_pm_move_recursive($this->opts)) {
                $ID = $this->getNewID($INFO['id'], $this->opts['ns'], $this->opts['newns']);
                $ACT = 'show';
            } else {
                return;
            }
        } else {
            // check that the pagename is valid
            if ($this->opts['newname'] == '' ) {
                msg($this->getLang('pm_badname'), -1);
                return;
            }

            if ($this->opts['newns'] === '') {
                $this->opts['newns'] = $this->opts['ns_for_page'];
            }

            if ($this->_pm_move_page($this->opts)) {
                // @todo if the namespace is now empty, delete it

                // Set things up to display the new page.
                $ID = $this->opts['new_id'];
                $ACT = 'show'; // this triggers a redirect to the page
            } else {
                return;
            }
        }
    }


    /**
     *
     * @author Bastian Wolf
     * @param array $opts      Options for moving the page
     * @param bool  $checkonly If only the checks if all pages can be moved shall be executed
     * @return bool if the move was executed
     */
    function _pm_move_recursive(&$opts, $checkonly = false) {
        global $ID;
        global $conf;

        $pagelist = array();
        $pathToSearch = utf8_encodeFN(str_replace(':', '/', $opts['ns']));
        $searchOpts = array('depth' => 0, 'skipacl' => true);
        search($pagelist, $conf['datadir'], 'search_allpages', $searchOpts, $pathToSearch);

        // FIXME: either use ajax for executing the queue and/or store the queue so it can be resumed when the execution
        // is aborted.
        foreach ($pagelist as $page) {
            $ID = $page['id'];
            $newID = $this->getNewID($ID, $opts['ns'], $opts['newns']);
            $pageOpts = $opts;
            $pageOpts['ns']   = getNS($ID);
            $pageOpts['name'] = noNS($ID);
            $pageOpts['newname'] = noNS($ID);
            $pageOpts['newns'] = getNS($newID);
            if (!$this->_pm_move_page($pageOpts, $checkonly)) return false;
        }
        return true;
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
     *
     * @param array $opts
     * @param bool  $checkonly Only execute the checks if the page can be moved
     * @return bool If the move was executed
     */
    function _pm_move_page(&$opts, $checkonly = false) {
        global $ID;

        // Check we have rights to move this document
        if ( !page_exists($ID)) {
            msg($this->getLang('pm_notexist'), -1);
            return false;
        }
        if ( auth_quickaclcheck($ID) < AUTH_EDIT ) {
            msg(sprintf($this->getLang('pm_norights'), hsc($ID)), -1);
            return false;
        }

        // Check file is not locked
        if (checklock($ID) !== false) {
            msg( sprintf($this->getLang('pm_filelocked'), hsc($ID)), -1);
            return false;
        }

        // Assemble fill document name and path
        $opts['new_id'] = cleanID($opts['newns'].':'.$opts['newname']);
        $opts['new_path'] = wikiFN($opts['new_id']);

        // Has the document name and/or namespace changed?
        if ( $opts['newns'] == $opts['ns'] && $opts['newname'] == $opts['name'] ) {
            msg($this->getLang('pm_nochange'), -1);
            return false;
        }
        // Check the page does not already exist
        if ( @file_exists($opts['new_path']) ) {
            msg(sprintf($this->getLang('pm_existing'), $opts['newname'],
                    ($opts['newns'] == '' ? $this->getLang('pm_root') : $opts['newns'])), -1);
            return false;
        }

        // Check if the current user can create the new page
        if (auth_quickaclcheck($opts['new_id']) < AUTH_CREATE) {
            msg(sprintf($this->getLang('pm_notargetperms'), $opts['new_id']), -1);
            return false;
        }

        if ($checkonly) return true;

        /**
         * End of init (checks)
         */

        $page_meta  = p_get_metadata($ID, 'plugin_pagemove', METADATA_DONT_RENDER);
        if (!$page_meta) $page_meta = array();
        if (!isset($page_meta['old_ids'])) $page_meta['old_ids'] = array();
        $page_meta['old_ids'][$ID] = time();

        // ft_backlinks() is not used here, as it does a hidden page and acl check but we really need all pages
        $affected_pages = idx_get_indexer()->lookupKey('relation_references', array_keys($page_meta['old_ids']));

        $data = array('opts' => &$opts, 'old_ids' => $page_meta['old_ids'], 'affected_pages' => &$affected_pages);
        // give plugins the option to add their own meta files to the list of files that need to be moved
        // to the oldfiles/newfiles array or to adjust their own metadata, database, ...
        // and to add other pages to the affected pages
        // note that old_ids is in the form 'id' => timestamp of move and affected_pages is indexed by these ids
        $event = new Doku_Event('PAGEMOVE_PAGE_RENAME', $data);
        if ($event->advise_before()) {
            // Open the old document and change forward links
            lock($ID);
            $text = rawWiki($ID);

            /** @var helper_plugin_pagemove $helper */
            $helper = $this->loadHelper('pagemove', true);
            if (is_null($helper)) return false;

            $text = $helper->rewrite_content($text, $ID, array($ID => $opts['new_id']));

            // Move the Subscriptions & Indexes
            $this->movemeta($opts);

            // Save the updated document in its new location
            if ($opts['ns'] == $opts['newns']) {
                $lang_key = 'pm_renamed';
            }
            elseif ( $opts['name'] == $opts['newname'] ) {
                $lang_key = 'pm_moved';
            }
            else {
                $lang_key = 'pm_move_rename';
            }
            $summary = sprintf($this->getLang($lang_key), $ID, $opts['new_id']);
            saveWikiText($opts['new_id'], $text, $summary);

            // Delete the orginal file
            if (@file_exists(wikiFN($opts['new_id']))) {
                saveWikiText($ID, '', $this->getLang('pm_delete') );
            }

            // Move the old revisions
            $this->moveattic($opts);

            asort($page_meta['old_ids']);

            // additional pages that should be considered because they were affected by moves from previous names
            // if the page has been rendered in the meantime and but the new links aren't in the index yet the
            // page might need information about a more recent rename even though it is not listed for this more recent link
            $additional_pages = array();
            foreach ($page_meta['old_ids'] as $page_id => $time) {
                if (!isset($affected_pages[$page_id])) {
                    $affected_pages[$page_id] = $additional_pages;
                } else {
                    $affected_pages[$page_id] = array_unique(array_merge($affected_pages[$page_id], $additional_pages));
                }
                foreach ($affected_pages[$page_id] as $id) {
                    if (!page_exists($id, '', false) || $id == $page_id || $id == $opts['new_id']) continue;
                    // if the page has been modified since the rename of the old page, the link in the new page is most
                    // probably intentionally to the old page and shouldn't be changed
                    if (filemtime(wikiFN($id, '', false)) > $time) continue;
                    $additional_pages[] = $id;
                    // we are only interested in persistent metadata, so no need to render anything.
                    $meta = p_get_metadata($id, 'plugin_pagemove', METADATA_DONT_RENDER);
                    if (!$meta) $meta = array('moves' => array());
                    if (!isset($meta['moves'])) $meta['moves'] = array();
                    $meta['moves'][$page_id] = $opts['new_id'];
                    // remove redundant moves (can happen when a page is moved back to its old id)
                    if ($page_id == $opts['new_id']) unset($meta['moves'][$page_id]);
                    if (empty($meta['moves'])) unset($meta['moves']);
                    p_set_metadata($id, array('plugin_pagemove' => $meta), false, true);
                }
            }

            p_set_metadata($opts['new_id'], array('plugin_pagemove' => $page_meta), false, true);
        }

        $event->advise_after();
        return true;
    }

    /**
     * Move the old revisions of the page that is specified in the options.
     *
     * @param array $opts Pagemove options (used here: name, newname, ns, newns)
     */
    function moveattic($opts) {
        global $conf;

        $regex = '\.\d+\.txt(?:\.gz|\.bz2)?';
        $this->move_files($conf['olddir'], $opts, $regex);
    }

    /**
     * Move the meta files of the page that is specified in the options.
     *
     * @param array $opts Pagemove options (used here: name, newname, ns, newns)
     */
    function movemeta($opts) {
        global $conf;

        $regex = '\.[^.]+';
        $this->move_files($conf['metadir'], $opts, $regex);
    }

    /**
     * Internal function for moving and renaming meta/attic files between namespaces
     *
     * @param string $dir   The root path of the files (e.g. $conf['metadir'] or $conf['olddir']
     * @param array  $opts  Pagemove options (used here: ns, newns, name, newname)
     * @param string $extregex Regular expression for matching the extension of the file that shall be moved
     */
    private function move_files($dir, $opts, $extregex) {
        $old_path = $dir.'/'.utf8_encodeFN(str_replace(':', '/', $opts['ns'])).'/';
        $new_path = $dir.'/'.utf8_encodeFN(str_replace(':', '/', $opts['newns'])).'/';
        $regex = '/^'.preg_quote(utf8_encodeFN($opts['name'])).'('.$extregex.')$/u';

        $dh = @opendir($old_path);
        if($dh) {
            while(($file = readdir($dh)) !== false) {
                if (substr($file, 0, 1) == '.') continue;
                $match = array();
                if (is_file($old_path.$file) && preg_match($regex, $file, $match)) {
                    if (!is_dir($new_path)) io_mkdir_p($new_path);
                    io_rename($old_path.$file, $new_path.utf8_encodeFN($opts['newname'].$match[1]));
                }
            }
            closedir($dh);
        }
    }
}