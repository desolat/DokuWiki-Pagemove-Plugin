<?php
require_once(DOKU_INC.'_test/lib/unittest.php');

require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/plugin.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'lib/plugins/pagemove/admin.php');

class PagemovePageTest extends Doku_UnitTestCase {

    var $movedId = 'parent_ns:current_ns:test_page';
    var $backlinkId = 'parent_ns:some_page';

    function setUp() {
        global $ID;
        global $INFO;

        $ID = $this->movedId;

        $text = <<<EOT
[[start|start]]
[[parallel_page|parallel_page]]
[[.:|.:]]
[[..current_ns:|..current_ns:]]
[[..:current_ns:|..:current_ns:]]
[[..parallel_ns:|..parallel_ns:]]
[[..:parallel_ns:|..:parallel_ns:]]
[[..:..:|..:..:]]
[[..:..:parent_ns:|..:..:parent_ns:]]
[[parent_ns:new_page|parent_ns:new_page]]
[[parent_ns/new_page|parent_ns/new_page]]
[[/start|/start]]
EOT;
        $summary = 'Test';
        saveWikiText($this->movedId, $text, $summary);
        $INFO = pageinfo();

        $text = <<<EOT
[[$this->movedId|$this->movedId]]
[[.current_ns:test_page|.current_ns:test_page]]
[[test_page|test_page]]
[[new_page|new_page]]
[[ftp://somewhere.com|ftp://somewhere.com]]
[[http://somewhere.com|http://somewhere.com]]

[[start|start]]
[[parallel_page|parallel_page]]
[[.:|.:]]
[[..current_ns:|..current_ns:]]
[[..:current_ns:|..:current_ns:]]
[[..parallel_ns:|..parallel_ns:]]
[[..:parallel_ns:|..:parallel_ns:]]
[[..:..:|..:..:]]
[[..:..:parent_ns:|..:..:parent_ns:]]
[[parent_ns:new_page|parent_ns:new_page]]
[[parent_ns/new_page|parent_ns/new_page]]
[[/start|/start]]
EOT;
        saveWikiText($this->backlinkId, $text, $summary);

        $this->pagemove = new admin_plugin_pagemove();
    }

#	function testPagemove() {
#		$this->assertEqual(1,1);
#	}

// 	function test_pm_getforwardlinks() {
// 		$origLinkAbsLinkArray = $this->pagemove->_pm_getforwardlinks($this->movedId);
// 	}

	function test_move_page_in_same_ns() {
	    global $ID;

	    $newPagename = 'new_page';

	    $opts = array();
	    $opts['ns']   = getNS($ID);
        $opts['name'] = noNS($ID);
        $opts['newns'] = $opts['ns'];
        $opts['newname'] = $newPagename;
        $this->movedToId = $opts['newns'].':'.$newPagename;
	    $this->pagemove->_pm_move_page($opts);

	    $newId = $opts['newns'].':'.$opts['newname'];
	    $newContent = rawWiki($newId);
	    $expectedContent = <<<EOT
[[start|start]]
[[parallel_page|parallel_page]]
[[start|.:]]
[[start|..current_ns:]]
[[start|..:current_ns:]]
[[parent_ns:parallel_ns:start|..parallel_ns:]]
[[parent_ns:parallel_ns:start|..:parallel_ns:]]
[[..:..:|..:..:]]
[[parent_ns:start|..:..:parent_ns:]]
[[parent_ns:new_page|parent_ns:new_page]]
[[parent_ns:new_page|parent_ns/new_page]]
[[/start|/start]]
EOT;
	    $this->assertEqual($expectedContent, $newContent);

	    $newContent = rawWiki($this->backlinkId);
	    $expectedContent = <<<EOT
[[parent_ns:current_ns:new_page|$this->movedId]]
[[parent_ns:current_ns:new_page|.current_ns:test_page]]
[[test_page|test_page]]
[[new_page|new_page]]
[[ftp://somewhere.com|ftp://somewhere.com]]
[[http://somewhere.com|http://somewhere.com]]

[[start|start]]
[[parallel_page|parallel_page]]
[[.:|.:]]
[[..current_ns:|..current_ns:]]
[[..:current_ns:|..:current_ns:]]
[[..parallel_ns:|..parallel_ns:]]
[[..:parallel_ns:|..:parallel_ns:]]
[[..:..:|..:..:]]
[[..:..:parent_ns:|..:..:parent_ns:]]
[[parent_ns:new_page|parent_ns:new_page]]
[[parent_ns/new_page|parent_ns/new_page]]
[[/start|/start]]
EOT;
	    $this->assertEqual($expectedContent, $newContent);

	}


	function test_move_page_to_parallel_ns() {
	    global $ID;

	    $newPagename = 'new_page';

	    $opts = array();
	    $opts['ns']   = getNS($ID);
	    $opts['name'] = noNS($ID);
	    $opts['newns'] = 'parent_ns:parallel_ns';
	    $opts['newname'] = $newPagename;
	    $this->movedToId = $opts['newns'].':'.$newPagename;
	    $this->pagemove->_pm_move_page($opts);

	    $newId = $opts['newns'].':'.$opts['newname'];
	    $newContent = rawWiki($newId);
	    $expectedContent = <<<EOT
[[parent_ns:current_ns:start|start]]
[[parent_ns:current_ns:parallel_page|parallel_page]]
[[parent_ns:current_ns:start|.:]]
[[parent_ns:current_ns:start|..current_ns:]]
[[parent_ns:current_ns:start|..:current_ns:]]
[[start|..parallel_ns:]]
[[start|..:parallel_ns:]]
[[..:..:|..:..:]]
[[parent_ns:start|..:..:parent_ns:]]
[[parent_ns:new_page|parent_ns:new_page]]
[[parent_ns:new_page|parent_ns/new_page]]
[[/start|/start]]
EOT;
	    $this->assertEqual($expectedContent, $newContent);
	}


	function test_move_page_to_parent_ns() {
	    global $ID;

	    $newPagename = 'new_page';

	    $opts = array();
	    $opts['ns']   = getNS($ID);
	    $opts['name'] = noNS($ID);
	    $opts['newns'] = 'parent_ns';
	    $opts['newname'] = $newPagename;
	    $this->movedToId = $opts['newns'].':'.$newPagename;
	    $this->pagemove->_pm_move_page($opts);

	    $newId = $opts['newns'].':'.$opts['newname'];
	    $newContent = rawWiki($newId);
	    $expectedContent = <<<EOT
[[parent_ns:current_ns:start|start]]
[[parent_ns:current_ns:parallel_page|parallel_page]]
[[parent_ns:current_ns:start|.:]]
[[parent_ns:current_ns:start|..current_ns:]]
[[parent_ns:current_ns:start|..:current_ns:]]
[[parent_ns:parallel_ns:start|..parallel_ns:]]
[[parent_ns:parallel_ns:start|..:parallel_ns:]]
[[..:..:|..:..:]]
[[start|..:..:parent_ns:]]
[[new_page|parent_ns:new_page]]
[[new_page|parent_ns/new_page]]
[[/start|/start]]
EOT;
	    $this->assertEqual($expectedContent, $newContent);
	}


	function tearDown() {
	    saveWikiText($this->movedId, '', 'removed');
	    saveWikiText($this->movedToId, '', 'removed');
	    saveWikiText($this->backlinkId, '', 'removed');
	}

}

?>