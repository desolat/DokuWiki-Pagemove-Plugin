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

require_once(DOKU_INC.'inc/search.php');


class admin_plugin_pagemove extends DokuWiki_Admin_Plugin {

    var $show_form = true;
    var $have_rights = true;
    var $locked_files = array();
    var $errors = array();
    var $opts = array();
    var $text = '';
    var $idsToDelete = array();


    function getMenuSort() { return FIXME; }
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
        return array(
        'author' => 'Gary Owen, Arno Puschmann, Christoph Jähnigen',
        'email'  => 'pagemove@gmail.com',
        'date'   => '2011-08-11',
        'name'   => 'Pagemove',
        'desc'   => $this->lang['desc'],
        'url'    => 'http://www.dokuwiki.org/plugin:pagemove',
        );
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
            ptln( $this->render($this->text) );
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

        $namesp = array( 0 => '' );     //Include root
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

            // check the NS if a recursion is needed
            // @fixme Is this still needed?
            $pagelist = array();
            $needrecursion = false;
            $nsRelPath = utf8_encodeFN(str_replace(':', '/', $this->opts['ns']));
            search($items, $conf['datadir'], 'search_index', '', $nsRelPath);
            foreach ($items as $item) {
                if ($item['type'] == 'd') {
                    $needrecursion = true;
                    break;
                }
            }

            $nsRelPath = utf8_encodeFN(str_replace(':', '/', $this->opts['ns']));
            $this->_pm_move_recursive($nsRelPath, $this->opts);

            $newNsAbsPath = $conf['datadir'].'/'.str_replace(':', '/', $this->opts['newns']);
            $this->_pm_disable_cache($newNsAbsPath);
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
            io_saveFile($conf['cachedir'].'/purgefile', time());
            $ID = $opts['new_id'];
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
     * touch every file which was moved, because of cached backlinks inside of moved namespace
     *
     * @author Arno Puschmann 2010-01-29
     * @param $pathToSearch
     * @return unknown_type
     */
    function _pm_disable_cache($pathToSearch) {
        $files = scandir($pathToSearch);
        if( !empty($files) ) {
	        foreach($files as $file) {
	            if( $file == '.' || $file == '..' ) continue;
	            if( is_dir($pathToSearch.'/'.$file) ) {
	                $this->_pm_disable_cache($pathToSearch.'/'.$file);
	            }
	            else {
	            	if( preg_match('#\.txt$#', $file) ) {
		                touch($pathToSearch.'/'.$file, time()+1);
	            	}
	            }
	        }
        }
    }


    /**
     *
     * @author Bastian Wolf
     * @param $pathToSearch
     * @param $opts
     * @return unknown_type
     */
    function _pm_move_recursive($pathToSearch, $opts) {
        global $ID;
        global $conf;

        $pagelist = array();
        search($pagelist, $conf['datadir'], 'search_index', '', $pathToSearch);

        foreach ($pagelist as $page) {
            if ($page['type'] == 'd') {
                $pathToSearch = utf8_encodeFN(str_replace(':', '/', $page['id']));
                // @fixme shouldn't be necessary as ID already exists
                io_createNamespace($page['id']);
                // NS to move is this one
                $nsOpts = $opts;
                $nsOpts['ns'] = $page['id'];
                // target NS is this folder under the current target NS
                $thisFolder = end(explode(':', $page['id']));
                $nsOpts['newns'] .= ':'.$thisFolder;
                array_push($this->idsToDelete, $page['id']);
                // Recursion
                $this->_pm_move_recursive($pathToSearch, $nsOpts);
            }
            elseif ($page['type'] == 'f') {
                $ID = $page['id'];
                $pageOpts = $opts;
                $pageOpts['ns']   = getNS($ID);
                $pageOpts['name'] = noNS($ID);
                $pageOpts['newname'] = noNS($ID);
                $this->_pm_move_page($pageOpts);
            }
            else {
                $this->errors[] = $this->lang['pm_unknown_file_type'];
                return;
            }
        }
    }


    /**
     * move page
     *
     * @author  Gary Owen <gary@isection.co.uk>, modified by Kay Roesler
     *
     * @param array $opts
     */
    function _pm_move_page($opts) {

        global $conf;
        global $lang;
        global $ID;
        global $INFO;
        global $ACT;

        // Check we have rights to move this document
        if ( !$INFO['exists']) {
            $this->have_rights = false;
            $this->errors[] = $this->lang['pm_notexist'];
            return;
        }
        if ( $ID == $conf['start']) {
            $this->have_rights = false;
            $this->errors[] = $this->lang['pm_notstart'];
            return;
        }
        if ( auth_quickaclcheck($ID) < AUTH_EDIT ) {
            $this->have_rights = false;
            $this->errors[] = $this->lang['pm_norights'];
            return;
        }

        // Check file is not locked
        if (checklock($ID)) {
        	$this->locked_files[] = $ID;
        }

        // get all backlink information
        $backlinksById = array();
        $this->_pm_search($backlinksById, $conf['datadir'], '_pm_search_backlinks', $opts);

        // Check we have edit rights on the backlinks and they are not locked
        foreach($backlinksById as $backlinkingId=>$backlinks) {
            if (auth_quickaclcheck($backlinkingId) < AUTH_EDIT) {
	            $this->have_rights = false;
            }
            if (checklock($backlinkingId)) {
           		$this->locked_files[] = $backlinkingId;
            }
        }

        // Assemble fill document name and path
        $opts['new_id'] = cleanID($opts['newns'].':'.$opts['newname']);
        $opts['new_path'] = wikiFN($opts['new_id']);

        // Has the document name and/or namespace changed?
        if ( $opts['newns'] == $opts['ns'] && $opts['newname'] == $opts['name'] ) {
            $this->errors[] = $this->lang['pm_nochange'];
            return;
        }
        // Check the page does not already exist
        if ( @file_exists($opts['new_path']) ) {
            $this->errors[] = sprintf($this->lang['pm_existing'], $opts['newname'],
                    ($opts['newns'] == '' ? $this->lang['pm_root'] : $opts['newns']));
            return;
        }

        if ( count($this->errors) != 0 ) {
            return;
        }

        /**
         * End of init (checks)
         */

        // Open the old document and change forward links
        lock($ID);
        $this->text = io_readFile(wikiFN($ID), True);

        // Get an array of forward links from the document
        $forward = $this->_pm_getforwardlinks($ID);

        // Change the forward links
        foreach($forward as $lnk => $lid) {
            // Get namespace of target document
            $tns = getNS($lid);
            $tname = noNS($lid);
            // Form new document ID for the target

            $matches = array();
            if ( $tns == $opts['newns'] ) {
            	// Document is in same namespace as target
                $this->_pm_updatelinks($this->text, array($lnk => $tname));
            }
            elseif ( preg_match('#^'.$opts['newns'].':(.*:)$#', $tns, $matches) ) {
            	// Target is in a sub-namespace
                $this->_pm_updatelinks($this->text, array($lnk => '.:'.$matches[1].':'.$tname));
            }
            elseif ( $tns == "" ) {
            	// Target is in root namespace
                $this->_pm_updatelinks($this->text, array($lnk => $lid ));
            }
            else {
                $this->_pm_updatelinks($this->text, array($lnk => $lid ));
            }
        }

        if ( $opts['ns'] != $opts['newns'] ) {
        	// Change media links when moving between namespaces
            $media = $this->_pm_getmedialinks($ID);
            foreach($media as $lnk => $lid) {
                $tns = getNS($lid);
                $tname = noNS($lid);
                // Form new document id for the target
                $matches = array();
                if ( $tns == $opts['newns'] ) {
                	// Document is in same namespace as target
                    $this->_pm_updatemedialinks($this->text, $lnk, $tname );
                }
                elseif ( preg_match('#^'.$opts['newns'].':(.*:)$#', $tns, $matches) ) {
                	// Target is in a sub-namespace
                    $this->_pm_updatemedialinks($this->text, $lnk, '.:'.$matches[1].':'.$tname );
                }
                elseif ( $tns == "" ) {
                	// Target is in root namespace
                    $this->_pm_updatemedialinks($this->text, $lnk, ':'.$lid );
                }
                else {
                    $this->_pm_updatemedialinks($this->text, $lnk, $lid );
                }
            }
        }

        // Move the Subscriptions & Indexes
        $this->_pm_movemeta('metadir', '/^'.$opts['name'].'\.\w*?$/', $opts);

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
        $summary = sprintf($this->lang[$lang_key], $ID, $opts['new_id']);
        saveWikiText($opts['new_id'], $this->text, $summary);

        // Delete the orginal file
        if (@file_exists(wikiFN($opts['new_id']))) {
        	saveWikiText($ID, '', $this->lang['pm_delete'] );
        }

        // Loop through backlinks
        foreach($backlinksById as $backlinkingId => $backlinks) {
            $this->_pm_updatebacklinks($backlinkingId, $backlinks, $opts, $brackets);
        }

        // Move the old revisions
        $this->_pm_movemeta('olddir', '/^'.$opts['name'].'\.[0-9]{10}\.txt(\.gz)?$/', $opts);

    }


    /**
     * Modify the links in a backlink.
     *
     * @param id Page ID of the backlinking page
     * @param links Array of page names on this page.
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_updatebacklinks($backlinkingId, $links, $opts, &$brackets) {
        global $ID;

        // Get namespace of document we are editing
        $bns = getNS($backlinkingId);

        // Create a clean version of the new name
        $cleanname = cleanID($opts['newname']);

        // Open backlink
        lock($backlinkingId);
        $text = io_readFile(wikiFN($backlinkingId),True);

        // Form new document ID for this backlink
        $matches = array();
        // new page is in same namespace as backlink
        if ( $bns == $opts['newns'] ) {
            $replacementNamespace = '';
        }
        // new page is in sub-namespace of backlink
        elseif ( preg_match('#^'.$bns.':(.*)$#', $opts['newns'], $matches) ) {
            $replacementNamespace = '.:'.$matches[1].':';
        }
        // not same or sub namespace: use absolute reference
        else {
            $replacementNamespace = $opts['newns'].':';
        }

        // @fixme stupid: for each page get original backlink and its replacement
        $matches = array();
        // get an array of: backlinks => replacement
        $oid = array();
        if ( $bns == $opts['ns'] ) {
        	// old page was in same namespace as backlink
            foreach ( $links as $link ) {
                $oid[$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
                $oid['.:'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
                $oid['.'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
            }
        }
        if ( preg_match('#^'.$bns.':(.*)$#', $opts['ns'], $matches) ) {
        	// old page was in sub namespace of backlink namespace
        	foreach ( $links as $link ) {
                $oid['.:'.$matches[1].':'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
                $oid['.'.$matches[1].':'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
        	}
        }
        if ( preg_match('#^'.$opts['ns'].':(.*)$#', $bns , $matches) && $opts['page_ns'] == 'page' ) {
            // old page was in upper namespace of backlink
            foreach ( $links as $link ) {
                $oid['..:'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
                $oid['..'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
                $oid['.:..:'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
            }
        }
        // replace all other links
        foreach ( $links as $link ) {
            // absolute links
            $oid[$opts['ns'].':'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
            //$oid['.:'.$opts['ns'].':'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);

            // check backwards relative links
            $relLink = $link;
            $relDots = '..';
            $backlinkingNamespaceCount = count(explode(':', $bns));
            $oldNamespaces = explode(':', $opts['ns'], $backlinkingNamespaceCount);
            $oldNamespaceCount = count($oldNamespaces);
            if ($backlinkingNamespaceCount > $oldNamespaceCount) {
                $levelDiff = $backlinkingNamespaceCount - $oldNamespaceCount;
                for ($i = 0; $i < $levelDiff; $i++) {
                    $relDots .= ':..';
                }
            }

            foreach (array_reverse($oldNamespaces) as $nextUpperNs) {
                $relLink = $nextUpperNs.':'.$relLink;
                foreach (array($relDots.$relLink, $relDots.':'.$relLink) as $dottedRelLink) {
                    $absLink=$dottedRelLink;
                    resolve_pageid($bns, $absLink, $exists);
                    if ($absLink == $ID) {
                        $oid[$dottedRelLink] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
                    }
                }
                $relDots = '..:'.$relDots;
            }

            //$oid['..:'.$opts['ns'].':'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
            //$oid['..'.$opts['ns'].':'.$link] = $replacementNamespace.(($cleanname == cleanID($link)) ? $link : $opts['newname']);
        }

        // Make the changes
        $this->_pm_updatelinks($text, $oid);

        // Save backlink and release lock
        saveWikiText($backlinkingId, $text, sprintf($this->lang['pm_linkchange'], $ID, $opts['new_id']));
        unlock($backlinkingId);
    }

    /**
     * modify the links using the pairs in $links
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_updatelinks(&$text, $links) {
        foreach( $links as $old => $new ) {
            $text = preg_replace( '#\[\[:?' . $old . '((\]\])|[\|\#])#i', '[[' . $new . '\1', $text);
        }
    }

    /**
     * modify the medialinks from namepspace $old to namespace $new
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_updatemedialinks(&$text, $old, $new) {
        // Question marks in media links need some extra handling
        $text = preg_replace('#\{\{' . $old . '([\?\|]|(\}\}))#i', '{{' . $new . '\1', $text);
    }

    /**
     * Get forward links in a given page which need to be changed.
     *
     * Not changed: local sections, absolute links
     * Changed need to be
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_getforwardlinks($id) {
        $data = array();
        $text = io_readfile(wikiFN($id));

        // match all links
        // FIXME may be incorrect because of code blocks
        // TODO CamelCase isn't supported, too
        preg_match_all('#\[\[(.+?)\]\]#si', $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match) {
            // ignore local headings [[#some_heading]]
            if ( preg_match('/^#/', $match[1])) continue;

            // get ID from link and discard most non wikilinks
            list($mid) = split('[\|#]', $match[1], 2);
            // ignore links with URL schema prefix ([[prefix://]])
            if(preg_match('#^\w+://#', $mid)) continue;
            //          if(preg_match('#^(https?|telnet|gopher|file|wais|ftp|ed2k|irc)://#',$mid)) continue;
            // inter-wiki link
            if(preg_match('#\w+>#', $mid)) continue;
            // baselink ([[/some_link]])
            if(preg_match('#^/#', $mid)) continue;
            // email addresses
            if(strpos($mid, '@') !== FALSE) continue;
            // ignore absolute links
            if( strpos($mid, ':') === 0 ) continue;

            $absoluteMatchId = $mid;
            $exists = FALSE;
            resolve_pageid(getNS($id), $absoluteMatchId, $exists);
            if($absoluteMatchId != FALSE) {
                $data[$mid] = $absoluteMatchId;
            }
        }
        return $data;
    }

    /**
     * Get media links in a given page
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_getmedialinks($id) {
        $data = array();
        $text = io_readfile(wikiFN($id));
        // match all links
        // FIXME may be incorrect because of code blocks
        // TODO CamelCase isn't supported, too
        preg_match_all('#{{(.[^>]+?)}}#si', $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match) {
            // get ID from link and discard most non wikilinks
            list($mid) = split('(\?|\|)', $match[1], 2);
            $mns = getNS($mid);
            $lnk = $mid;

            // namespace starting with "." - prepend current namespace
            if(strpos($mns, '.')===0) {
                $mid = getNS($id).':'.substr($mid, 1);
            }
            elseif($mns === FALSE){
                // no namespace in link? add current
                $mid = getNS($id) . ':' . $mid;
            }
            $data[$lnk] = preg_replace('#:+#', ':', $mid);
        }
        return $data;
    }

    /**
     * move meta files (Old Revs, Subscriptions, Meta, etc)
     *
     * This function meta files between directories
     *
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_movemeta($dir, $regex, $opts) {
        global $conf;

        $old_path = $conf[$dir].'/'.str_replace(':','/',$opts['ns']).'/';
        $new_path = $conf[$dir].'/'.str_replace(':','/',$opts['newns']).'/';
        $dh = @opendir($old_path);
        if($dh) {
            while(($file = readdir($dh)) !== false) {
            	// skip hidden files and upper dirs
                if(preg_match('/^\./',$file)) continue;
                if(is_file($old_path.$file) and preg_match($regex,$file)) {
                    io_mkdir_p($new_path);
                    io_rename($old_path.$file,$new_path.str_replace($opts['name'], $opts['newname'], $file));
                    continue;
                }
            }
            closedir($dh);
        }
    }


    /**
     * recurse directory
     *
     * This function recurses into a given base directory
     * and calls the supplied function for each file and directory
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param array $data Found data is collected
     * @param string $base Directory to be searched in
     * @param string $func Name of real search function
     * @param array $opts Options to the search functions
     * @param string $dir Current relative directory
     * @param integer $lvl Level of recursion
     */
    function _pm_search(&$data, $base, $func, $opts, $dir='' ,$lvl=1) {
        $dirs   = array();
        $files  = array();

        // read in directories and files
        $dh = @opendir($base.'/'.$dir);
        if(!$dh) return;
        while(($file = readdir($dh)) !== false) {
        	// skip hidden files and upper dirs
            if(preg_match('/^\./',$file)) continue;
            if(is_dir($base.'/'.$dir.'/'.$file)) {
                $dirs[] = $dir.'/'.$file;
                continue;
            }
            $files[] = $dir.'/'.$file;
        }
        closedir($dh);
        sort($files);
        sort($dirs);

        // give directories to userfunction then recurse
        foreach($dirs as $dir) {
            if ($this->$func($data, $base, $dir, 'd', $lvl, $opts)) {
                $this->_pm_search($data, $base, $func, $opts, $dir, $lvl+1);
            }
        }
        // now handle the files
        foreach($files as $file) {
            $this->$func($data, $base, $file, 'f', $lvl, $opts);
        }
    }

    /**
     * Search for backlinks to a given page
     *
     * $opts['ns']    namespace of the page
     * $opts['name']  name of the page without namespace
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @author  Gary Owen <gary@isection.co.uk>
     */
    function _pm_search_backlinks(&$data, $base, $file, $type, $lvl, $opts) {
        // we do nothing with directories
        if($type == 'd') return true;
        // only search txt files
        if(!preg_match('#\.txt$#', $file)) return true;

        $text = io_readfile($base.'/'.$file);
        // absolute search ID
//         $absSearchedId = cleanID($opts['ns'].':'.$opts['name']);
        $absSearchedId = $opts['name'];
        resolve_pageid($opts['ns'], $absSearchedId, $exists);

        // construct current namespace
        $cid = pathID($file);
        $cns = getNS($cid);

        // match all links
        // FIXME may be incorrect because of code blocks
        // FIXME CamelCase isn't supported, too
        preg_match_all('#\[\[(.+?)\]\]#si', $text, $matches, PREG_SET_ORDER);
        foreach($matches as $match) {
            // get ID from link and discard most non wikilinks
            list($matchLink) = split('[\|#]', $match[1], 2);
            // all URLs with a scheme
            if(preg_match('#^\w+://#', $matchLink)) continue;
//            if(preg_match('#^(https?|telnet|gopher|file|wais|ftp|ed2k|irc)://#',$matchLink)) continue;
            // baselinks
            if(preg_match('#^/#', $matchLink)) continue;
            // inter-wiki links
            if(preg_match('#\w+>#', $matchLink)) continue;
            // email addresses
            if(strpos($matchLink, '@') !== FALSE) continue;

            // get the ID the link refers to by cleaning and resolving it
            $matchId = cleanID($matchLink);
            resolve_pageid($cns, $matchId, $exists);
            $matchPagename = ltrim(noNS($matchId), '.:');

            // only collect IDs not in collected $data already
            if ($matchId == $absSearchedId                 // matching link refers to the searched ID
                && (! array_key_exists($cid, $data)        // not in $data already
                    || empty($data[$cid])
                    || ! in_array($matchPagename, $data[$cid]))) {
                // @fixme return original link and its replacement
                $data[$cid][] = $matchPagename;
            }
        }
    }
}