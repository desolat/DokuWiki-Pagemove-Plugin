<?php
require_once(DOKU_INC.'_test/lib/unittest.php');

class PagemoveGroupTest extends Doku_GroupTest {
	function testPagemoveGroup() {
		$dir = dirname(__FILE__).'/';
		$this->GroupTest('pagemove_group_test');
		$this->addTestFile($dir . 'pagemove.test.php');
	}
}
