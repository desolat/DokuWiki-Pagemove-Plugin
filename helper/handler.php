<?php
/**
 * Move Plugin Rewriting Handler
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Handler class for move. It does the actual rewriting of the content.
 *
 * Note: This is not actually a valid DokuWiki Helper plugin and can not be loaded via plugin_load()
 */
class helper_plugin_move_handler {
    public $calls = '';
    public $id;
    public $ns;
    public $new_id;
    public $new_ns;
    public $moves;
    public $media_moves;
    private $handlers;

    /**
     * Construct the move handler.
     *
     * @param string $id       The id of the text that is passed to the handler
     * @param array $moves    Moves that shall be considered in the form $old => $new ($old can be $id)
     * @param array $media_moves Moves of media files that shall be considered in the form $old => $new
     * @param array $handlers Handlers for plugin content in the form $plugin_anme => $callback
     */
    public function __construct($id, $moves, $media_moves, $handlers) {
        $this->id = $id;
        $this->ns = getNS($id);
        $this->moves = $moves;
        $this->media_moves = $media_moves;
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
        } else if ( preg_match('/^\{\{[^\}]+\}\}$/',$link[1]) ) {
            // If the title is an image, rewrite it
            $old_title = $link[1];
            $link[1] = $this->rewrite_media($link[1]);
            // do a simple replace of the first match so really only the id is changed and not e.g. the alignment
            $oldpos = strpos($match, $old_title);
            $oldlen = strlen($old_title);
            $match  = substr_replace($match, $link[1], $oldpos, $oldlen);
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
        $this->calls .= $this->rewrite_media($match);
        return true;
    }

    /**
     * Rewrite a media syntax
     *
     * @param string $match The text match of the media syntax
     * @return string The rewritten syntax
     */
    protected function rewrite_media($match) {
        $p = Doku_Handler_Parse_Media($match);
        if ($p['type'] == 'internalmedia') { // else: external media
            $new_src = $this->adaptRelativeId($p['src'], true);
            if ($new_src !== $p['src']) {
                // do a simple replace of the first match so really only the id is changed and not e.g. the alignment
                $srcpos = strpos($match, $p['src']);
                $srclen = strlen($p['src']);
                return substr_replace($match, $new_src, $srcpos, $srclen);
            }
        }
        return $match;
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
     * @param bool $media If the id is a media id
     * @return string The relative id, adapted according to the new/old id and the moves
     */
    public function adaptRelativeId($id, $media = false) {
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

        if (($media ? isset($this->media_moves[$clean_id]) : isset($this->moves[$clean_id])) || $this->ns !== $this->new_ns) {
            if (!$media && isset($this->moves[$clean_id])) {
                $new = $this->moves[$clean_id];
            } elseif ($media && isset($this->media_moves[$clean_id])) {
                $new = $this->media_moves[$clean_id];
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
