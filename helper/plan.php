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
    /** Number of operations per step  */
    const OPS_PER_RUN = 10;

    const TYPE_PAGES = 1;
    const TYPE_MEDIA = 2;
    const CLASS_NS = 4;
    const CLASS_DOC = 8;

    /**
     * @var array the options for this move plan
     */
    protected $options = array(); // defaults are set in loadOptions()

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
            'opts'       => $conf['metadir'] . '/__move_opts',
            'pagelist'   => $conf['metadir'] . '/__move_pagelist',
            'medialist'  => $conf['metadir'] . '/__move_medialist',
            'affected'   => $conf['metadir'] . '/__move_affected',
            'namespaces' => $conf['metadir'] . '/__move_namespaces'
        );

        $this->loadOptions();
    }

    /**
     * Load the current options if any
     *
     * If no options are found, the default options will be extended by any available
     * config options
     */
    protected function loadOptions() {
        // (re)set defaults
        $this->options = array(
            // status
            'committed'   => false,
            'started'     => 0,

            // counters
            'pages_all'   => 0,
            'pages_run'   => 0,
            'media_all'   => 0,
            'media_run'   => 0,
            'affpg_all'   => 0,
            'affpg_run'   => 0,

            // options
            'autoskip'    => $this->getConf('autoskip'),
            'autorewrite' => $this->getConf('autorewrite'),

            // errors
            'lasterror'   => false
        );

        // merge whatever options are saved currently
        $file = $this->files['opts'];
        if(file_exists($file)) {
            $options = unserialize(io_readFile($file, false));
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * Save the current options
     *
     * @return bool
     */
    protected function saveOptions() {
        return io_saveFile($this->files['opts'], serialize($this->options));
    }

    /**
     * Return the current state of an option, null for unknown options
     *
     * @param $name
     * @return mixed|null
     */
    public function getOption($name) {
        if(isset($this->options[$name])) {
            return $this->options[$name];
        }
        return null;
    }

    /**
     * Set an option
     *
     * Note, this otpion will only be set to the current instance of this helper object. It will only
     * be written to the option file once the plan gets committed
     *
     * @param $name
     * @param $value
     */
    public function setOption($name, $value) {
        $this->options[$name] = $value;
    }

    /**
     * Returns the progress of this plan in percent
     *
     * @return float
     */
    public function getProgress() {
        $max =
            $this->options['pages_all'] +
            $this->options['media_all'] +
            $this->options['affpg_all'];

        $remain =
            $this->options['pages_run'] +
            $this->options['media_run'] +
            $this->options['affpg_run'];

        if($max == 0) return 0;
        return round((($max - $remain) * 100) / $max, 2);
    }

    /**
     * Check if there is a move in progress currently
     *
     * @return bool
     */
    public function inProgress() {
        return (bool) $this->options['started'];
    }

    /**
     * Check if this plan has been commited, yet
     *
     * @return bool
     */
    public function isCommited() {
        return $this->options['commited'];
    }

    /**
     * Add a single page to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addPageMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_DOC, self::TYPE_PAGES);
    }

    /**
     * Add a single media file to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addMediaMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_DOC, self::TYPE_MEDIA);
    }

    /**
     * Add a page namespace to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addPageNamespaceMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_NS, self::TYPE_PAGES);
    }

    /**
     * Add a media namespace to be moved to the plan
     *
     * @param string $src
     * @param string $dst
     */
    public function addMediaNamespaceMove($src, $dst) {
        $this->addMove($src, $dst, self::CLASS_NS, self::TYPE_MEDIA);
    }

    /**
     * Plans the move of a namespace or document
     *
     * @param string $src   ID of the item to move
     * @param string $dst   new ID of item namespace
     * @param int    $class (self::CLASS_NS|self::CLASS_DOC)
     * @param int    $type  (PLUGIN_MOVE_TYPE_PAGE|self::TYPE_MEDIA)
     * @throws Exception
     */
    protected function addMove($src, $dst, $class = self::CLASS_NS, $type = self::TYPE_PAGES) {
        if($this->options['commited']) throw new Exception('plan is commited already, can not be added to');

        $src = cleanID($src);
        $dst = cleanID($dst);

        $this->plan[] = array(
            'src'   => $src,
            'dst'   => $dst,
            'class' => $class,
            'type'  => $type
        );
    }

    /**
     * Abort any move or plan in progress and reset the helper
     */
    public function abort() {
        foreach($this->files as $file) {
            @unlink($file);
        }
        $this->plan = array();
        $this->loadOptions();
    }

    /**
     * This locks up the plan and prepares execution
     *
     * the plan is reordered an the needed move operations are gathered and stored in the appropriate
     * list files
     *
     * @throws Exception if you try to commit a plan twice
     * @return bool true if the plan was commited
     */
    public function commit() {
        global $conf;

        if($this->options['commited']) throw new Exception('plan is commited already, can not be commited again');

        usort($this->plan, array($this, 'planSorter'));

        // get all the documents to be moved and store them in their lists
        foreach($this->plan as $move) {
            if($move['class'] == self::CLASS_DOC) {
                // these can just be added
                if($move['type'] == self::TYPE_PAGES) {
                    $this->addToPageList($move['src'], $move['dst']);
                } else {
                    $this->addToMediaList($move['src'], $move['dst']);
                }
            } else {
                // here we need a list of content first, search for it
                $docs = array();
                $path = utf8_encodeFN(str_replace(':', '/', $move['src']));
                $opts = array('depth' => 0, 'skipacl' => true);
                if($move['type'] == self::TYPE_PAGES) {
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

                    if($move['type'] == self::TYPE_PAGES) {
                        $this->addToPageList($from, $to);
                    } else {
                        $this->addToMediaList($from, $to);
                    }
                }

                // remember the namespace move itself
                if($move['type'] == self::TYPE_PAGES) {
                    // FIXME we use this to move namespace subscriptions later on and for now only do it on
                    //       page namespace moves, but subscriptions work for both, but what when only one of
                    //       them is moved? Should it be copied then? Complicated. This is good enough for now
                    $this->addToNamespaceList($move['src'], $move['dst']);
                }
            }
        }

        if(!$this->options['pages_all'] && !$this->options['media_all']) {
            msg($this->getLang('noaction'), -1);
            return false;
        }

        $this->options['commited'] = true;
        $this->saveOptions();
        return true;
    }

    /**
     * Execute the next steps
     *
     * @param bool $skip set to true to skip the next first step (skip error)
     * @return bool|int false on errors, otherwise the number of remaining steps
     * @throws Exception
     */
    public function nextStep($skip = false) {
        if(!$this->options['commited']) throw new Exception('plan is not committed yet!');

        // execution has started
        if(!$this->options['started']) $this->options['started'] = time();

        if(@filesize($this->files['pagelist']) > 1) {
            $todo = $this->stepThroughDocuments(self::TYPE_PAGES, $skip);
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['medialist']) > 1) {
            $todo = $this->stepThroughDocuments(self::TYPE_MEDIA, $skip);
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if($this->options['autorewrite'] && @filesize($this->files['affected']) > 1) {
            $todo = $this->stepThroughAffectedPages();
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        if(@filesize($this->files['namespaces']) > 1) {
            $todo = $this->stepThroughNamespaces();
            if($todo === false) return $this->storeError();
            return max($todo, 1); // force one more call
        }

        // we're done here, clean up
        $this->abort();
        return 0;
    }

    /**
     * Returns the list of page and media moves and the affected pages as a HTML list
     *
     * @fixme This will gather affected pages on its own, maybe it would be better to make this somehow part of
     *        commit() process as it would also make the progress bar behave better
     *
     * @return string
     */
    public function previewHTML() {
        $html = '';

        $affected = array();

        $html .= '<ul>';
        if(@file_exists($this->files['pagelist'])) {
            $pagelist = file($this->files['pagelist']);
            foreach($pagelist as $line) {
                list($old, $new) = explode("\t", trim($line));

                $html .= '<li class="page"><div class="li">';
                $html .= hsc($old);
                $html .= '→';
                $html .= hsc($new);
                $html .= '</div></li>';

                // get all pages linking to the original page
                $affected = array_merge($affected, idx_get_indexer()->lookupKey('relation_references', $old));
            }
        }
        if(@file_exists($this->files['medialist'])) {
            $medialist = file($this->files['medialist']);
            foreach($medialist as $line) {
                list($old, $new) = explode("\t", trim($line));

                $html .= '<li class="media"><div class="li">';
                $html .= hsc($old);
                $html .= '→';
                $html .= hsc($new);
                $html .= '</div></li>';

                // get all pages using this media
                $affected = array_merge($affected, idx_get_indexer()->lookupKey('relation_media', $old));
            }
        }
        $affected = array_unique($affected);
        sort($affected);
        foreach($affected as $page) {
            $html .= '<li class="affected"><div class="li">';
            $html .= '↷';
            $html .= hsc($page);
            $html .= '</div></li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Step through the next bunch of pages or media files
     *
     * @param int  $type (self::TYPE_PAGES|self::TYPE_MEDIA)
     * @param bool $skip should the first item be skipped?
     * @return bool|int false on error, otherwise the number of remaining documents
     */
    protected function stepThroughDocuments($type = self::TYPE_PAGES, &$skip = false) {
        /** @var helper_plugin_move_op $MoveOperator */
        $MoveOperator = plugin_load('helper', 'move_op');

        if($type == self::TYPE_PAGES) {
            $file = $this->files['pagelist'];
            $mark = 'P';
            $call = 'movePage';
            $counter = 'pages_num';
        } else {
            $file = $this->files['medialist'];
            $mark = 'M';
            $call = 'moveMedia';
            $counter = 'media_num';
        }

        $doclist = fopen($file, 'a+');
        for($i = 0; $i < helper_plugin_move_plan::OPS_PER_RUN; $i++) {
            $line = $this->getLastLine($doclist);
            if($line === false) break;
            list($src, $dst) = explode("\t", trim($line));

            // should this item be skipped?
            if($skip) goto FINISH;

            // move the page
            if(!$MoveOperator->$call($src, $dst)) {
                $this->log($mark, $src, $dst, false); // FAILURE!

                // automatically skip this item if wanted...
                if($this->options['autoskip']) goto FINISH;
                // ...otherwise abort the operation
                fclose($doclist);
                return false;
            } else {
                $this->log($mark, $src, $dst, true); // SUCCESS!

                // remember affected pages
                $this->addToAffectedPagesList($MoveOperator->getAffectedPages());
            }

            /*
             * This adjusts counters and truncates the document list correctly
             * It is used to finalize a successful or skipped move
             */
            FINISH:
            $skip = false;
            ftruncate($doclist, ftell($doclist));
            $this->options[$counter]--;
            $this->saveOptions();
        }

        fclose($doclist);
        return $this->options[$counter];
    }

    /**
     * Step through the next bunch of pages that need link corrections
     *
     * @return bool|int false on error, otherwise the number of remaining documents
     */
    protected function stepThroughAffectedPages() {
        /** @var helper_plugin_move_rewrite $Rewriter */
        $Rewriter = plugin_load('helper', 'move_rewrite');

        // if this is the first run, clean up the file and remove duplicates
        if($this->options['affpg_all'] == $this->options['affpg_num']) {
            $affected = io_readFile($this->files['affected']);
            $affected = explode("\n", $affected);
            $affected = array_unique($affected);
            $affected = array_filter($affected);
            sort($affected);
            if($affected[0] === '') array_shift($affected);
            io_saveFile($this->files['affected'], join("\n", $affected));

            $this->options['affpg_all'] = count($affected);
            $this->options['affpg_num'] = $this->options['affpg_all'];

            $this->saveOptions();
        }

        // handle affected pages
        $doclist = fopen($this->files['affected'], 'a+');
        for($i = 0; $i < helper_plugin_move_plan::OPS_PER_RUN; $i++) {
            $page = $this->getLastLine($doclist);
            if($page === false) break;

            // rewrite it
            $Rewriter->execute_rewrites($page, null);

            // update the list file
            ftruncate($doclist, ftell($doclist));
            $this->options['affpg_num']--;
            $this->saveOptions();
        }

        fclose($doclist);
        return $this->options['affpg_num'];
    }

    /**
     * Step through all the namespace moves
     *
     * Currently moves namespace subscriptions only. This does not step, but handles all namespaces
     * in one step.
     *
     * @return int always 0
     * @todo maybe add an event so plugins can move more stuff?
     */
    protected function stepThroughNamespaces() {
        /** @var helper_plugin_move_file $FileMover */
        $FileMover = plugin_load('helper', 'move_file');

        $lines = io_readFile($this->files['namespaces']);
        $lines = explode("\n", $lines);

        foreach($lines as $line) {
            list($src, $dst) = explode("\n", trim($line));
            $FileMover->moveNamespaceSubscription($src, $dst);
        }

        @unlink($this->files['namespaces']);
        return 0;
    }

    /**
     * Retrieve the last error from the MSG array and store it in the options
     *
     * @todo rebuild error handling based on exceptions
     *
     * @return bool always false
     */
    protected function storeError() {
        global $MSG;

        if(is_array($MSG) && count($MSG)) {
            $last = array_shift($MSG);
            $this->options['lasterror'] = $last['msg'];
            unset($GLOBALS['MSG']);
        } else {
            $this->options['lasterror'] = 'Unknown error';
        }
        $this->saveOptions();

        return false;
    }

    /**
     * Reset the error state
     */
    protected function clearError() {
        $this->options['lasterror'] = false;
        $this->saveOptions();
    }

    /**
     * Get the last error message or false if no error occured
     *
     * @return bool|string
     */
    public function getLastError() {
        return $this->options['lasterror'];
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
            $this->options['pages_all']++;
            $this->options['pages_run']++;
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
            $this->options['media_all']++;
            $this->options['media_run']++;
            return true;
        }
        return false;
    }

    /**
     * Appends a namespace move operation in the list file
     *
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function addToNamespaceList($src, $dst) {
        $file = $this->files['namespaces'];
        return io_saveFile($file, "$src\t$dst\n", true);
    }

    /**
     * Add the list of pages to the list of affected pages whose links need adjustment
     *
     * This is only done when autorewrite is enabled, otherwise we don't need to track
     * those pages
     *
     * @param array $pages
     * @return bool
     */
    protected function addToAffectedPagesList($pages) {
        if(!$this->options['autorewrite']) return false;

        $this->options['affpg_all'] += count($pages);
        $this->options['affpg_num'] = $this->options['affpg_all'];
        return io_saveFile($this->files['affected'], join("\n", $pages) . "\n", true);
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
        while(fseek($handle, -1, SEEK_CUR) >= 0) {
            $c = fgetc($handle);
            if($c === false) return false; // EOF, i.e. the file is empty
            fseek($handle, -1, SEEK_CUR); // reset the position to the character that was read

            if($c == "\n") {
                if($line === '') {
                    continue; // this line was empty, continue
                } else {
                    break; // we have a line, finish
                }
            }

            $line = $c . $line; // prepend char to line
        }

        if($line === '') return false; // beginning of file reached and no content

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
        if($a['class'] == self::CLASS_DOC && $b['class'] == self::CLASS_NS) {
            return 1;
        }
        if($a['class'] == self::CLASS_NS && $b['class'] == self::CLASS_DOC) {
            return -1;
        }

        // do pages before media
        if($a['type'] == self::TYPE_PAGES && $b['type'] == self::TYPE_MEDIA) {
            return 1;
        }
        if($a['type'] == self::TYPE_MEDIA && $b['type'] == self::TYPE_PAGES) {
            return -1;
        }

        // from here on we compare only apples to apples
        // we sort by depth of namespace, deepest namespaces first

        $alen = substr_count($a['src'], ':');
        $blen = substr_count($b['src'], ':');

        if($alen > $blen) {
            return 1;
        } elseif($alen < $blen) {
            return -1;
        }
        return 0;
    }

    /**
     * Log result of an operation
     *
     * @param string $type
     * @param string $from
     * @param string $to
     * @param bool   $success
     * @author Andreas Gohr <gohr@cosmocode.de>
     */
    protected function log($type, $from, $to, $success) {
        global $conf;
        global $MSG;

        $optime = $this->options['started'];
        $file = $conf['cachedir'] . '/move-' . $optime . '.log';
        $now = time();
        $date = date('Y-m-d H:i:s', $now); // for human readability

        if($success) {
            $ok = 'success';
            $msg = '';
        } else {
            $ok = 'failed';
            $msg = $MSG[count($MSG) - 1]['msg']; // get detail from message array
        }

        $log = "$now\t$date\t$type\t$from\t$to\t$ok\t$msg\n";
        io_saveFile($file, $log, true);
    }
}