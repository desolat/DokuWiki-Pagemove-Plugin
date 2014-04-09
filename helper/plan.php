<?php
/**
 * Move Plugin Operation Planner
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

define('PLUGIN_MOVE_TYPE_PAGES', 1);
define('PLUGIN_MOVE_TYPE_MEDIA', 2);
define('PLUGIN_MOVE_CLASS_NS', 4);
define('PLUGIN_MOVE_CLASS_DOC', 8);

/**
 * Class helper_plugin_move_plan
 *
 * This thing prepares and keeps progress info on complex move operations (eg. where more than a single
 * object is affected.
 *
 * Glossary:
 *
 *   document - refers to either a page or a media file here
 */
class helper_plugin_move_plan extends DokuWiki_Plugin {


    /**
     * @var array the options for this move plan
     */
    protected $options = array(
        // status
        'committed' => false,
        'started'   => 0,

        // counters
        'pages' => 0,
        'media' => 0,
        'affected' => 0,
    );

    /**
     * @var array holds the location of the different list and state files
     */
    protected $files = array();

    /**
     * @var array the planned moves
     */
    protected $plan = array();

    /**
     * Constructor
     *
     * initializes state (if any) for continuiation of a running move op
     */
    public function __construct() {
        global $conf;

        // set the file locations
        $this->files = array(
            'opts' => $conf['metadir'] . '/__move_opts',
            'pagelist' => $conf['metadir'] . '/__move_pagelist',
            'medialist' => $conf['metadir'] . '/__move_medialist',
            'affected' => $conf['metadir'] . '/__move_affected',
        );

        $this->loadOptions();
    }

    /**
     * Check if there is a move in progress currently
     *
     * @return bool
     */
    public function inProgress() {
        return $this->options['committed'];
    }

    /**
     * Plans the move of a namespace or document
     *
     * @param string $src ID of the item to move
     * @param string $dst   new ID of item namespace
     * @param int $class (PLUGIN_MOVE_CLASS_NS|PLUGIN_MOVE_CLASS_DOC)
     * @param int $type (PLUGIN_MOVE_TYPE_PAGE|PLUGIN_MOVE_TYPE_MEDIA)
     * @throws Exception
     */
    public function addMove($src, $dst, $class = PLUGIN_MOVE_CLASS_NS, $type = PLUGIN_MOVE_TYPE_PAGES) {
        if($this->options['commited']) throw new Exception('plan is commited already, can not be added to');

        $src = cleanID($src);
        $dst = cleanID($dst);

        // FIXME make sure source exists

        $this->plan[] = array(
            'src' => $src,
            'dst' => $dst,
            'class' => $class,
            'type' => $type
        );
    }

    /**
     * This locks up the plan and prepares execution
     *
     * the plan is reordered an the needed move operations are gathered and stored in the appropriate
     * list files
     */
    public function commit() {
        global $conf;

        if($this->options['commited']) throw new Exception('plan is commited already, can not be commited again');

        usort($this->plan, array($this, 'planSorter'));

        // get all the documents to be moved and store them in their lists
        foreach($this->plan as $move) {
            if($move['class'] == PLUGIN_MOVE_CLASS_DOC) {
                // these can just be added
                if($move['type'] == PLUGIN_MOVE_TYPE_PAGES) {
                    $this->addToPageList($move['src'], $move['dst']);
                } else {
                    $this->addToMediaList($move['src'], $move['dst']);
                }
            } else {
                // here we need a list of content first, search for it
                $docs = array();
                $path = utf8_encodeFN(str_replace(':', '/', $move['src']));
                $opts = array('depth' => 0, 'skipacl' => true);
                if($move['type'] == PLUGIN_MOVE_TYPE_PAGES) {
                    search($docs, $conf['datadir'], 'search_allpages', $opts, $path);
                } else {
                    search($docs, $conf['mediadir'], 'search_media', $opts, $path);
                }

                // how much namespace to strip?
                if($move['src'] !== '') {
                    $strip = strlen($move['src']) + 1;
                } else {
                    $strip = 0;
                }
                if($move['dst']) $move['dst'] .= ':';

                // now add all the found documents to our lists
                foreach($docs as $doc) {
                    $from = $doc['id'];
                    $to = $move['dst'] . substr($doc['id'], $strip);

                    if($move['type'] == PLUGIN_MOVE_TYPE_PAGES) {
                        $this->addToPageList($from, $to);
                    } else {
                        $this->addToMediaList($from, $to);
                    }
                }
            }
        }

        $this->options['committed'] = true;
        $this->options['started'] = time();
    }

    public function nextStep() {
        if(!$this->options['commited']) throw new Exception('plan is not committed yet!');
    }

    /**
     * Save the current options
     *
     * @return bool
     */
    protected function saveOptions(){
        return io_saveFile($this->files['opts'], serialize($this->options));
    }

    /**
     * Load the current options if any
     *
     * @return bool
     */
    protected function loadOptions() {
        $file = $this->files['opts'];
        if(!file_exists($file)) return false;
        $this->options = unserialize(io_readFile($file, false));
        return true;
    }

    /**
     * Appends a page move operation in the list file
     *
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function addToPageList($src, $dst) {
        $file = $this->files['pagelist'];

        if(io_saveFile($file, "$src\t$dst\n", true)) {
            $this->options['pages']++;
            return true;
        }
        return false;
    }

    /**
     * Appends a media move operation in the list file
     *
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function addToMediaList($src, $dst) {
        $file = $this->files['medialist'];

        if(io_saveFile($file, "$src\t$dst\n", true)) {
            $this->options['media']++;
            return true;
        }
        return false;
    }

    /**
     * Get the last line from the list that is stored in the file that is referenced by the handle
     * The handle is set to the newline before the file id
     *
     * @param resource $handle The file handle to read from
     * @return string|bool the last id from the list or false if there is none
     */
    protected function getLastLine($handle) {
        // begin the seek at the end of the file
        fseek($handle, 0, SEEK_END);
        $line = '';

        // seek one backwards as long as it's possible
        while (fseek($handle, -1, SEEK_CUR) >= 0) {
            $c = fgetc($handle);
            fseek($handle, -1, SEEK_CUR); // reset the position to the character that was read

            if ($c == "\n") {
                break;
            }
            if ($c === false) return false; // EOF, i.e. the file is empty
            $line = $c.$line;
        }

        if ($line === '') return false; // nothing was read i.e. the file is empty
        return $line;
    }


    /**
     * Callback for usort to sort the move plan
     *
     * Note that later on all lists will be worked on in reversed order, so we reverse what we
     * do from what we want here
     *
     * @param $a
     * @param $b
     * @return int
     */
    public function planSorter($a, $b) {
        // do page moves before namespace moves
        if($a['class'] == PLUGIN_MOVE_CLASS_DOC && $b['class'] == PLUGIN_MOVE_CLASS_NS) {
            return 1;
        }
        if($a['class'] == PLUGIN_MOVE_CLASS_NS && $b['class'] == PLUGIN_MOVE_CLASS_DOC) {
            return -1;
        }

        // do pages before media
        if($a['type'] == PLUGIN_MOVE_TYPE_PAGES && $b['type'] == PLUGIN_MOVE_TYPE_MEDIA) {
            return 1;
        }
        if($a['type'] == PLUGIN_MOVE_TYPE_MEDIA && $b['type'] == PLUGIN_MOVE_TYPE_PAGES) {
            return -1;
        }

        // from here on we compare only apples to apples
        // we sort by depth of namespace, deepest namespaces first

        $alen = substr_count($a['src'], ':');
        $blen = substr_count($a['src'], ':');

        if($alen > $blen) {
            return 1;
        } elseif($alen < $blen) {
            return -1;
        }
        return 0;
    }

    /**
     * Get the filenames for the metadata of the move plugin
     *
     * @return array The file names for opts, pagelist and medialist
     */
    protected function get_namespace_meta_files() {
        global $conf;

    }

}