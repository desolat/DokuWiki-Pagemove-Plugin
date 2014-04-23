<?php
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once(__DIR__ . '/../helper/handler.php');

/**
 * Test cases for the move plugin
 *
 * @group plugin_move
 * @group plugins
 */
class plugin_move_handler_test extends DokuWikiTest {

    public function test_relativeLink() {

        $handler = new test_helper_plugin_move_handler('deep:namespace:page', 'deep:namespace:page', array(), array(), array());

        $tests = array(
            'deep:namespace:new1' => 'new1',
            'deep:new2'  => '..:new2',
            'new3'   => ':new3', // absolute is shorter than relative
            'deep:namespace:deeper:new4' => '.deeper:new4',
            'deep:namespace:deeper:deepest:new5' => '.deeper:deepest:new5',
        );

        foreach($tests as $new => $rel) {
            $this->assertEquals($rel, $handler->relativeLink('foo', $new));
        }

    }


}


/**
 * Class test_helper_plugin_move_handler
 *
 * gives access to some internal stuff of the class
 */
class test_helper_plugin_move_handler extends helper_plugin_move_handler {
    public function relativeLink($relold, $new) {
        return parent::relativeLink($relold, $new);
    }
}