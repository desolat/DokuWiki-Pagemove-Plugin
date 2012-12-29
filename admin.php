<?php
/**
 * Plugin : Pagemove
 * Version : 0.10 (2010-06-17)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gary Owen,
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'admin.php';

class admin_plugin_pagemove extends DokuWiki_Admin_Plugin {

    var $show_form = true;
    var $have_rights = true;
    var $locked_files = array();
    var $errors = array();
    var $opts = array();
    var $idsToDelete = array();


    function getMenuSort() { return 1000; }
    function forAdminOnly() { return false; }

    /**
     * function constructor
     */
    function admin_plugin_pagemove(){
        // enable direct access to language strings
        $this->setupLocale();
    }

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
        global $ID;
        global $conf;

        if( !$INFO['exists'] )
            return $this->lang['menu'].' ('.$this->lang['pm_notexist'].')';
        elseif( $ID == $conf['start'] )
            return $this->lang['menu'].' ('.$this->lang['pm_notstart'].')';
        elseif( !$INFO['writable'] )
            return $this->lang['menu'].' ('.$this->lang['pm_notwrite'].')';
        else
            return $this->lang['menu'];
    }



    /**
     * output appropriate html
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function html() {
        global $ID;
        global $lang;

        ptln('<!-- Pagemove Plugin start -->');
        if( $this->show_form ) {
            ptln( $this->locale_xhtml('pagemove') );
            //We didn't get here from submit.
            if( $this->have_rights && count($this->locked_files) == 0 ) {
                $this->_pm_form();
            }
            else {
                ptln( '<p><strong>' );
                if ( !$this->have_rights ) {
                    ptln( $this->errors[0].'<br>' );
                }
                $c = count($this->locked_files);
                if ( $c == 1 ) {
                    ptln( $this->lang['pm_filelocked'].$this->locked_files[0].'<br>'.$this->lang['pm_tryagain'] );
                }
                elseif ( $c > 1 ) {
                    ptln( $this->lang['pm_fileslocked'] );
                    for ( $i = 0 ; $i < $c ; $i++ ) {
                    	ptln ( ($i > 0 ? ', ' : '').$this->locked_files[$i] );
                    }
                    ptln( '<br>'.$this->lang['pm_tryagain'] );
                }
                ptln ( '</strong></p>' );
            }
        }
        else {
            // display the moved/renamed page
            p_wiki_xhtml($ID);
        }
        ptln('<!-- Pagemove Plugin end -->');
    }

    /**
     * show the move and/or rename a page form
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_form() {
        global $ID;
        global $lang;
        global $conf;

        $ns = getNS($ID);
        $name = noNS($ID);

        ptln('  <div align="center">');
        ptln('  <script language="Javascript">');
        ptln('      function setradio( group, choice ) {');
        ptln('        for ( i = 0 ; i < group.length ; i++ ) {');
        ptln('          if ( group[i].value == choice )');
        ptln('            group[i].checked = true;');
        ptln('        }');
        ptln('      }');
        ptln('  </script>');
        ptln('  <form name="frm" action="'.wl($ID).'" method="post">');
        // output hidden values to ensure dokuwiki will return back to this plugin
        ptln('    <input type="hidden" name="do"   value="admin" />');
        ptln('    <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
        ptln('    <input type="hidden" name="id" value="'.$ID.'" />');
        ptln('  <fieldset id="fieldset_page">');
        ptln('  <legend><input type="radio" name="page_ns" id="page_ns_0" value="page" CHECKED> '. $this->lang['pm_movepage'] .'</legend>');
        ptln('    <table border="0" id="table_page">');

        //Show any errors
        if (count($this->errors) > 0) {
            ptln ('<tr><td bgcolor="red" colspan="3">');
            foreach($this->errors as $error) {
	            ptln ($error.'<br>');
            }
            ptln ('</td></tr>');
        }
        //create a list of namespaces
       	ptln( '      <tr><td align="right" nowrap><label><span>'.$this->lang['pm_targetns'].'</span></label></td>');
        ptln( '        <td width="25"><input type="radio" name="nsr" id="nsr_0" value="<old>" '.($_REQUEST['nsr'] != '<new>' ? 'CHECKED' : '').'></td>');
        ptln( '        <td><select name="ns_for_page" id="nsr_select" onChange="setradio(document.frm.nsr, \'<old>\');setradio(document.frm.page_ns, \'page\');">');
        $this->_pm_form_create_list_ns($ns);

        ptln( "        </select></td>\n      </tr><tr>");

        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['pm_newtargetns'].'</span></label></td>');
        ptln( '        <td width="25"><input type="radio" name="nsr" id="nsr_1" value="<new>" '.($_REQUEST['nsr'] == '<new>' ? 'CHECKED' : '').'></td>');
        ptln( '        <td align="left" nowrap><input type="text" name="newns" id="newns" value="'.formtext($this->opts['newns']).'" class="edit" onClick="setradio(document.frm.nsr, \'<new>\');setradio(document.frm.page_ns, \'page\');" /></td>');
        ptln( '      </tr>');
        ptln( '      <tr>');
        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['pm_newname'].'</span></label></td>');
        ptln('		   <td width="25"></td>'); //<input type="radio" name="pageradio" value="<page>" '.($_REQUEST['pageradio']!= '<namespace>' ? 'CHECKED' : '').'>
        ptln( '        <td align="left" nowrap><input type="text" name="pagename" id="pagename" value="'.formtext(isset($this->opts['newname']) ? $this->opts['newname'] : $name).'" class="edit" onClick="setradio(document.frm.page_ns, \'page\');" /></td>');
        ptln( '      </tr>');
        ptln( '      </tr>');
        ptln( '      </tr>');
        ptln( '    </table>');
        ptln( '  </fieldset>');

        ptln('  <br>');
        ptln('  <fieldset id="fieldset_ns" >');
        ptln('  <legend><input type="radio" name="page_ns" id="page_ns_1" value="ns"> '. $this->lang['pm_movens'] .'</legend>');
        ptln('    <table border="0" id="table_ns">');
        ptln( '      <tr><td align="right" nowrap><label><span>'.$this->lang['pm_targetns'].'</span></label></td>');
        ptln( '        <td><select name="ns" id="ns_select" onChange="setradio(document.frm.page_ns, \'ns\');">');
        $this->_pm_form_create_list_ns($ns);
        ptln( "        </select></td>\n      </tr>");
        ptln( '      <tr>');
        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['pm_newnsname'].'</span></label></td>');
        ptln( '        <td align="left" nowrap><input type="text" name="namespacename" id="namespacename" value="'.formtext(isset($this->opts['newnsname']) ? $this->opts['newnsname'] : $this->opts['nsname']).'" class="edit" onClick="setradio(document.frm.page_ns, \'ns\');" /></td>');
        ptln( '      </tr>');
        ptln( '    </table>');
        ptln('  </fieldset>');
        ptln( '<br><center><input type="submit" value="'.formtext($this->lang['pm_submit']).'" class="button" /><input type="button" value="'.$this->lang['pm_preview'].'" class="button" onClick="Javascript:preview();"/></center>');
        ptln( '</form>');

        ptln('<font id="preview_output"></font>');

        ptln('  <script language="Javascript">');
        ptln(" table_page_width = document.getElementById('table_page').offsetWidth;");
        ptln(" table_ns_width = document.getElementById('table_ns').offsetWidth;");
        ptln(" max_width = Math.max(table_page_width,table_ns_width)+'px';");
        ptln(" document.getElementById('fieldset_page').style.width = max_width;");
        ptln(" document.getElementById('fieldset_ns').style.width   = max_width;");

        ptln("function preview(){");
        ptln("if(document.getElementById('page_ns_0').checked == true)");
        ptln("{");
        ptln("	if(document.getElementById('nsr_0').checked == true)");
        ptln("	{");
        ptln("		preview_text = \"".$ID . $this->lang['pm_previewpage']. "  \" + document.getElementById('nsr_select').value +  (document.getElementById('nsr_select').value==':'? '' : ':') + document.getElementById('pagename').value;");
        ptln("	}");
        ptln("	else");
        ptln("	{");
        ptln("		preview_text = \"".$ID . $this->lang['pm_previewpage']. "  \" + document.getElementById('newns').value + ':' + document.getElementById('pagename').value;");
        ptln("	}");
        ptln("}");
        ptln("else{");
        ptln("	preview_text = \"". sprintf($this->lang['pm_previewns'], $ns). "  \" + document.getElementById('ns_select').value + (document.getElementById('ns_select').value==':'? '' : ':') + document.getElementById('namespacename').value;");
        ptln("}");
        ptln("document.getElementById('preview_output').innerHTML = preview_text;");
        ptln("");
        ptln("}");
        ptln("  </script>");

        ptln( '</div>');
    }


    /**
     * create a list of namespaces for the html form
     *
     * @author  Gary Owen <gary@isection.co.uk>
     * @author  Arno Puschmann (bin out of _pm_form)
     */
    function _pm_form_create_list_ns($ns) {
        global $conf;

        $namesp = array( 0 => array('id' => '') );     //Include root
        search($namesp, $conf['datadir'], 'search_namespaces', array());
        sort($namesp);
        foreach($namesp as $row) {
            if ( auth_quickaclcheck($row['id'].':*') >= AUTH_CREATE || $row['id'] == $ns ) {
                ptln ( '          <option value="'.
                ($row['id'] ? $row['id'] : ':').
                ($_REQUEST['ns'] ?
                (($row['id'] ? $row['id'] : ":") == $_REQUEST['ns'] ? '" SELECTED>' : '">') :
                ($row['id'] == $ns ? '" SELECTED>' : '">') ).
                ($row['id'] ? $row['id'].':' : ": ".$this->lang['pm_root']).
                ($row['id'] == $ns ? ' '.$this->lang['pm_current'] : '').
                    "</option>" );
            }
        }
    }


    /**
     * handle user request
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function handle() {

        global $conf;
        global $lang;
        global $ID;
        global $INFO;
        global $ACT;

        // check we have rights to move this document
        if( !$INFO['exists'] ) {
            $this->have_rights = false;
            $this->errors[] = $this->lang['pm_notexist'];
            return;
        }
        // do not move start page
        if( $ID == $conf['start'] ) {
            $this->have_rights = false;
            $this->errors[] = $this->lang['pm_notstart'];
            return;
        }

        // was a form send?
        if (! array_key_exists('page_ns', $_REQUEST)) {
            // @fixme do something more intelligent like showing in message
            return;
        }

        // extract namespace and document name from ID
        $this->opts['ns']   = getNS($ID);
        $this->opts['name'] = noNS($ID);
        $this->opts['page_ns'] = $_REQUEST['page_ns'];

        // check the input for completeness
        if( $this->opts['page_ns'] == 'ns' ) {
            // @todo Target namespace needn't be new (check pages for overwrite!)
            if( $_REQUEST['namespacename'] == '' ) {
                $this->errors[] = $this->lang['pm_emptynamespace'];
                return;
            }
            $this->opts['newnsname'] = $_REQUEST['namespacename'];
            if ( cleanID($this->opts['newnsname']) == '' ) {
                $this->errors[] = $this->lang['pm_badns'];
                return;
            }
            if ($_REQUEST['ns'] == ':') {
                $this->opts['newns'] = $this->opts['newnsname'];
            }
            else {
                $this->opts['newns'] = $_REQUEST['ns'].':'.$this->opts['newnsname'];
            }

            $nsRelPath = utf8_encodeFN(str_replace(':', '/', $this->opts['ns']));
            $this->_pm_move_recursive($nsRelPath, $this->opts);
        }
        elseif( $this->opts['page_ns'] == 'page' ) {
            if( $_REQUEST['pagename'] == '' ) {
                $this->errors[] = $this->lang['pm_emptypagename'];
                return;
            }
            $this->opts['newname'] = $_REQUEST['pagename'];
            // check that the pagename is valid
            if ( cleanID($this->opts['newname']) == '' ) {
                $this->errors[] = $this->lang['pm_badname'];
                return;
            }

            if ($_REQUEST['nsr'] == '<old>') {
                $this->opts['newns'] = ($_REQUEST['ns_for_page'] == ':' ? '' : $_REQUEST['ns_for_page']);
            }
            elseif ($_REQUEST['nsr'] =='<new>') {
                // if a new namespace was requested, check and use it
                if ($_REQUEST['newns'] != '') {
                    $this->opts['newns'] = $_REQUEST['newns'];
                    // check that the new namespace is valid
                    if ( cleanID($this->opts['newns']) == '' ) {
                        $this->errors[] = $this->lang['pm_badns'];
                        return;
                    }
                }
                else {
                    $this->errors[] = $this->lang['pm_badns'];
                    return;
                }
            }
            else {
                $this->errors[] = $this->lang['pm_fatal'];
                return;
            }

            $this->_pm_move_page($this->opts);

            // @todo if the namespace is now empty, delete it

            // Set things up to display the new page.
            $ID = $this->opts['new_id'];
            $ACT = 'show';
            $INFO = pageinfo();
            $this->show_form = false;
        }
        else {
            $this->errors[] = $this->lang['pm_fatal'];
            return;
        }


        // only go on if no errors occured and inputs are not empty
        if (count($this->errors) != 0 ) {
            return;
        }
        // delete empty namespaces if possible
        // @fixme does not work like that
        foreach ($this->idsToDelete as $idToDelete) {
            io_sweepNS($idToDelete);
        }

    }


    /**
     *
     * @author Bastian Wolf
     * @param $pathToSearch
     * @param array $opts      Options for moving the page
     * @param bool  $checkonly If only the checks if all pages can be moved shall be executed
     * @return bool if the move was executed
     */
    function _pm_move_recursive($pathToSearch, &$opts, $checkonly = false) {
        global $ID;
        global $conf;

        $pagelist = array();
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