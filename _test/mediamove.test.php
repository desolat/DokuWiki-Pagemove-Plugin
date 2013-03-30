<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Test cases for the pagemove plugin
 */
class plugin_pagemove_mediamove_test extends DokuWikiTest {

    public function setUp() {
        $this->pluginsEnabled[] = 'pagemove';
        parent::setUp();
    }

    public function test_movePageWithRelativeMedia() {
        global $ID;

        $ID = 'mediareltest:foo';
        saveWikiText($ID,
            '{{ myimage.png}} [[:start|{{ testimage.png?200x800 }}]] [[bar|{{testimage.gif?400x200}}]]
[[doku>wiki:dokuwiki|{{wiki:logo.png}}]] [[http://www.example.com|{{testimage.jpg}}]]
[[doku>wiki:foo|{{foo.gif?200x3000}}]]', 'Test setup');
        idx_addPage($ID);

        $opts = array();
        $opts['ns']   = getNS($ID);
        $opts['name'] = noNS($ID);
        $opts['newns'] = '';
        $opts['newname'] = 'foo';
        /** @var helper_plugin_pagemove $pagemove */
        $pagemove = plugin_load('helper', 'pagemove');
        $pagemove->move_page($opts);

        $this->assertEquals('{{ mediareltest:myimage.png}} [[:start|{{ mediareltest:testimage.png?200x800 }}]] [[mediareltest:bar|{{mediareltest:testimage.gif?400x200}}]]
[[doku>wiki:dokuwiki|{{wiki:logo.png}}]] [[http://www.example.com|{{mediareltest:testimage.jpg}}]]
[[doku>wiki:foo|{{mediareltest:foo.gif?200x3000}}]]', rawWiki('foo'));
    }
}
