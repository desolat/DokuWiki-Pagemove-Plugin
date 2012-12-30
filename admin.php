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
            return $this->getLang('menu').' ('.sprintf($this->getLang('pm_notexist'), $INFO['id']).')';
        elseif( !$INFO['writable'] )
            return $this->getLang('menu').' ('.$this->getLang('pm_notwrite').')';
        else
            return $this->getLang('menu');
    }



    /**
     * output appropriate html
     *
     * @author  Michael Hamann <michael@content-space.de>
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function html() {
        ptln('<!-- Pagemove Plugin start -->');
        ptln( $this->locale_xhtml('pagemove') );
        $this->printForm();
        ptln('<!-- Pagemove Plugin end -->');
    }

    /**
     * show the move and/or rename a page form
     *
     * @author  Michael Hamann <michael@content-space.de>
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function printForm() {
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

        /** @var helper_plugin_pagemove $helper */
        $helper = $this->loadHelper('pagemove', true);
        if (!$helper) return;

        // check the input for completeness
        if( $this->opts['move_type'] == 'namespace' ) {
            if ($this->opts['targetns'] == '') {
                $this->opts['newns'] = $this->opts['newnsname'];
            } else {
                $this->opts['newns'] = $this->opts['targetns'].':'.$this->opts['newnsname'];
            }

            if ($helper->move_namespace($this->opts, true) &&
                $helper->move_namespace($this->opts)) {
                $ID = $helper->getNewID($INFO['id'], $this->opts['ns'], $this->opts['newns']);
                $ACT = 'show';
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

            if ($helper->move_page($this->opts)) {
                // Set things up to display the new page.
                $ID = $this->opts['new_id'];
                $ACT = 'show'; // this triggers a redirect to the page
            }
        }
    }
}