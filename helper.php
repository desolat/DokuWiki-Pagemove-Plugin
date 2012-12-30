<?php
/**
 * Helper part of the pagemove plugins.
 *
 * @author Michael Hamann <michael@content-space.de>
 */
class helper_plugin_pagemove extends DokuWiki_Plugin {
    /**
     * Move a namespace according to the given options
     *
     * @author Bastian Wolf
     * @param array $opts      Options for moving the namespace
     * @param bool  $checkonly If only the checks if all pages can be moved shall be executed
     * @return bool if the move was executed
     */
    function move_namespace(&$opts, $checkonly = false) {
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
            if (!$this->move_page($pageOpts, $checkonly)) return false;
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
     * @author  Michael Hamann <michael@content-space.de>
     *
     * @param array $opts
     * @param bool  $checkonly Only execute the checks if the page can be moved
     * @return bool If the move was executed
     */
    public function move_page(&$opts, $checkonly = false) {
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
            msg(sprintf($this->getLang('pm_existing'), $opts['newname'], ($opts['newns'] == '' ? $this->getLang('pm_root') : $opts['newns'])), -1);
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

            $text = $this->rewrite_content($text, $ID, array($ID => $opts['new_id']));

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
    public function moveattic($opts) {
        global $conf;

        $regex = '\.\d+\.txt(?:\.gz|\.bz2)?';
        $this->move_files($conf['olddir'], $opts, $regex);
    }

    /**
     * Move the meta files of the page that is specified in the options.
     *
     * @param array $opts Pagemove options (used here: name, newname, ns, newns)
     */
    public function movemeta($opts) {
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

    /**
     * Rewrite the text of a page according to the recorded moves, the rewritten text is saved
     *
     * @param string      $id   The id of the page that shall be rewritten
     * @param string|null $text Old content of the page. When null is given the content is loaded from disk.
     * @return string The rewritten content
     */
    public function execute_rewrites($id, $text = null) {
        $meta = p_get_metadata($id, 'plugin_pagemove', METADATA_DONT_RENDER);
        if($meta && isset($meta['moves'])) {
            if(is_null($text)) $text = rawWiki($id);

            $text = $this->rewrite_content($text, $id, $meta['moves']);
            $file = wikiFN($id, '', false);
            if(is_writable($file)) {
                saveWikiText($id, $text, $this->getLang('pm_linkchange'));
                unset($meta['moves']);
                p_set_metadata($id, array('plugin_pagemove' => $meta), false, true);
            } else { // FIXME: print error here or fail silently?
                msg('Error: Page '.hsc($id).' needs to be rewritten because of page renames but is not writable.', -1);
            }
        }

        return $text;
    }

    /**
     * Rewrite a text in order to fix the content after the given moves.
     *
     * @param string $text   The wiki text that shall be rewritten
     * @param string $id     The id of the wiki page, if the page itself was moved the old id
     * @param array  $moves  Array of all moves, the keys are the old ids, the values the new ids
     * @return string        The rewritten wiki text
     */
    function rewrite_content($text, $id, $moves) {
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
                if(isset($moves[$new]) && $moves[$new] != $new) {
                    $tmp_moves[$old] = $moves[$new];
                    $changed         = true;
                }
            }
        }

        // manual merge, we can't use array_merge here as ids can be numeric
        foreach($tmp_moves as $old => $new) {
            $moves[$old] = $new;
        }

        $handlers = array();
        $data     = array('id' => $id, 'moves' => &$moves, 'handlers' => &$handlers);

        /*
         * PAGEMOVE_HANDLERS REGISTER event:
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
         * - adaptRelativeId($id): adapts the relative $id according to the moves
         */
        trigger_event('PAGEMOVE_HANDLERS_REGISTER', $data);

        $modes = p_get_parsermodes();

        // Create the parser
        $Parser = new Doku_Parser();

        // Add the Handler
        $Parser->Handler = new helper_plugin_pagemove_handler($id, $moves, $handlers);

        //add modes to parser
        foreach($modes as $mode) {
            $Parser->addMode($mode['mode'], $mode['obj']);
        }

        return $Parser->parse($text);
    }
}

/**
 * Handler class for pagemove. It does the actual rewriting of the content.
 */
class helper_plugin_pagemove_handler {
    public $calls = '';
    public $id;
    public $ns;
    public $new_id;
    public $new_ns;
    public $moves;
    private $handlers;

    /**
     * Construct the pagemove handler.
     *
     * @param string $id       The id of the text that is passed to the handler
     * @param array  $moves    Moves that shall be considered in the form $old => $new ($old can be $id)
     * @param array  $handlers Handlers for plugin content in the form $plugin_anme => $callback
     */
    public function __construct($id, $moves, $handlers) {
        $this->id = $id;
        $this->ns = getNS($id);
        $this->moves = $moves;
        $this->handlers = $handlers;
        if (isset($moves[$id])) {
            $this->new_id = $moves[$id];
            $this->new_ns = getNS($moves[$id]);
        } else {
            $this->new_id = $id;
            $this->new_ns = $this->ns;
        }
    }

    /**
     * Handle camelcase links
     *
     * @param string $match  The text match
     * @param string $state  The starte of the parser
     * @param int    $pos    The position in the input
     * @return bool If parsing should be continued
     */
    public function camelcaselink($match, $state, $pos) {
        if ($this->ns)
            $old = cleanID($this->ns.':'.$match);
        else
            $old = cleanID($match);
        if (isset($this->moves[$old]) || $this->id != $this->new_id) {
            if (isset($this->moves[$old])) {
                $new = $this->moves[$old];
            } else {
                $new = $old;
            }
            $new_ns = getNS($new);
            // preserve capitalization either in the link or in the title
            if (noNS($new) == noNS($old)) {
                // camelcase link still seems to work
                if ($new_ns == $this->new_ns) {
                    $this->calls .= $match;
                } else { // just the namespace was changed, the camelcase word is a valid id
                    $this->calls .= "[[$new_ns:$match]]";
                }
            } else {
                $this->calls .= "[[$new|$match]]";
            }
        } else {
            $this->calls .= $match;
        }
        return true;
    }

    /**
     * Handle rewriting of internal links
     *
     * @param string $match  The text match
     * @param string $state  The starte of the parser
     * @param int    $pos    The position in the input
     * @return bool If parsing should be continued
     */
    public function internallink($match, $state, $pos) {
        // Strip the opening and closing markup
        $link = preg_replace(array('/^\[\[/','/\]\]$/u'),'',$match);

        // Split title from URL
        $link = explode('|',$link,2);
        if ( !isset($link[1]) ) {
            $link[1] = NULL;
        }
        $link[0] = trim($link[0]);


        //decide which kind of link it is

        if ( preg_match('/^[a-zA-Z0-9\.]+>{1}.*$/u',$link[0]) ) {
            // Interwiki
            $this->calls .= $match;
        }elseif ( preg_match('/^\\\\\\\\[^\\\\]+?\\\\/u',$link[0]) ) {
            // Windows Share
            $this->calls .= $match;
        }elseif ( preg_match('#^([a-z0-9\-\.+]+?)://#i',$link[0]) ) {
            // external link (accepts all protocols)
            $this->calls .= $match;
        }elseif ( preg_match('<'.PREG_PATTERN_VALID_EMAIL.'>',$link[0]) ) {
            // E-Mail (pattern above is defined in inc/mail.php)
            $this->calls .= $match;
        }elseif ( preg_match('!^#.+!',$link[0]) ){
            // local link
            $this->calls .= $match;
        }else{
            $id = $link[0];

            $hash = '';
            $parts = explode('#', $id, 2);
            if (count($parts) === 2) {
                $id = $parts[0];
                $hash = $parts[1];
            }

            $params = '';
            $parts = explode('?', $id, 2);
            if (count($parts) === 2) {
                $id = $parts[0];
                $params = $parts[1];
            }


            $new_id = $this->adaptRelativeId($id);

            if ($id == $new_id) {
                $this->calls .= $match;
            } else {
                if ($params !== '') {
                    $new_id.= '?'.$params;
                }

                if ($hash !== '') {
                    $new_id .= '#'.$hash;
                }

                if ($link[1] != NULL) {
                    $new_id .= '|'.$link[1];
                }

                $this->calls .= '[['.$new_id.']]';
            }

        }

        return true;

    }

    /**
     * Handle rewriting of media links
     *
     * @param string $match  The text match
     * @param string $state  The starte of the parser
     * @param int    $pos    The position in the input
     * @return bool If parsing should be continued
     */
    public function media($match, $state, $pos) {
        $p = Doku_Handler_Parse_Media($match);
        if ($p['type'] == 'internalmedia') {
            $new_src = $this->adaptRelativeId($p['src']);
            if ($new_src == $p['src']) {
                $this->calls .= $match;
            } else {
                // do a simple replace of the first match so really only the id is changed and not e.g. the alignment
                $srcpos = strpos($match, $p['src']);
                $srclen = strlen($p['src']);
                $this->calls .= substr_replace($match, $new_src, $srcpos, $srclen);
            }
        } else { // external media
            $this->calls .= $match;
        }
        return true;
    }

    /**
     * Handle rewriting of plugin syntax, calls the registered handlers
     *
     * @param string $match  The text match
     * @param string $state  The starte of the parser
     * @param int    $pos    The position in the input
     * @param string $pluginname The name of the plugin
     * @return bool If parsing should be continued
     */
    public function plugin($match, $state, $pos, $pluginname) {
        if (isset($this->handlers[$pluginname])) {
            $this->calls .= call_user_func($this->handlers[$pluginname], $match, $state, $pos, $pluginname, $this);
        } else {
            $this->calls .= $match;
        }
        return true;
    }

    /**
     * Catchall handler for the remaining syntax
     *
     * @param string $name Function name that was called
     * @param array  $params Original parameters
     * @return bool If parsing should be continue
     */
    public function __call($name, $params) {
        if (count($params) == 3) {
            $this->calls .= $params[0];
            return true;
        } else {
            trigger_error('Error, handler function '.hsc($name).' with '.count($params).' parameters called which isn\'t implemented', E_USER_ERROR);
            return false;
        }
    }

    public function _finalize() {
        // remove padding that is added by the parser in parse()
        $this->calls = substr($this->calls, 1, -1);
    }

    /**
     * Adapts a link respecting all moves and making it a relative link according to the new id
     *
     * @param string $id A relative id
     * @return string The relative id, adapted according to the new/old id and the moves
     */
    public function adaptRelativeId($id) {
        global $conf;

        if ($id === '') {
            return $id;
        }

        $abs_id = str_replace('/', ':', $id);
        $abs_id = resolve_id($this->ns, $abs_id, false);
        if (substr($abs_id, -1) === ':')
            $abs_id .= $conf['start'];
        $clean_id = cleanID($abs_id);
        // FIXME this simply assumes that the link pointed to :$conf['start'], but it could also point to another page
        // resolve_pageid does a lot more here, but we can't really assume this as the original pages might have been
        // deleted already
        if (substr($clean_id, -1) === ':')
            $clean_id .= $conf['start'];

        if (isset($this->moves[$clean_id]) || $this->ns !== $this->new_ns) {
            if (isset($this->moves[$clean_id])) {
                $new = $this->moves[$clean_id];
            } else {
                $new = $clean_id;

                // only the namespace was changed so if the link still resolves to the same absolute id, we can skip the rest
                $new_abs_id = str_replace('/', ':', $id);
                $new_abs_id = resolve_id($this->new_ns, $new_abs_id, false);
                if (substr($new_abs_id, -1) === ':')
                    $new_abs_id .= $conf['start'];
                if ($new_abs_id == $abs_id) return $id;
            }
            $new_link = $new;
            $new_ns = getNS($new);
            // try to keep original pagename
            if ($this->noNS($new) == $this->noNS($clean_id)) {
                if ($new_ns == $this->new_ns) {
                    $new_link = $this->noNS($id);
                    if ($new_link === false) $new_link = $this->noNS($new);
                    if ($id == ':')
                        $new_link = ':';
                    else if ($id == '/')
                        $new_link = '/';
                } else if ($new_ns != false) {
                    $new_link = $new_ns.':'.$this->noNS($id);
                } else {
                    $new_link = $this->noNS($id);
                    if ($new_link === false) $new_link = $new;
                }
            } else if ($new_ns == $this->new_ns) {
                $new_link = $this->noNS($new_link);
            } else if (strpos($new_ns, $this->ns.':') === 0) {
                $new_link = '.:'.substr($new_link, strlen($this->ns)+1);
            }

            if ($this->new_ns != '' && $new_ns == false) {
                $new_link = ':'.$new_link;
            }

            return $new_link;
        } else {
            return $id;
        }
    }

    /**
     * Remove the namespace from the given id like noNS(), but handles '/' as namespace separator
     * @param string $id the id
     * @return string the id without the namespace
     */
    private function noNS($id) {
        $pos = strrpos($id, ':');
        $spos = strrpos($id, '/');
        if ($pos === false) $pos = $spos;
        if ($spos === false) $spos = $pos;
        $pos = max($pos, $spos);
        if ($pos!==false) {
            return substr($id, $pos+1);
        } else {
            return $id;
        }
    }
}

