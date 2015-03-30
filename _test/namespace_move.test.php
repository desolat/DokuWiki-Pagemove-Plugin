<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Test cases for namespace move functionality of the move plugin
 *
 * @group plugin_move
 * @group plugins
 */
class plugin_move_namespace_move_test extends DokuWikiTest {

    public function setUp() {
        $this->pluginsEnabled[] = 'move';
        parent::setUp();
    }

    /**
     * @coversNothing
     */
    public function tearDown() {
        /** @var helper_plugin_move_plan $plan  */
        $plan = plugin_load('helper', 'move_plan');
        $plan->abort();
        parent::tearDown();
    }

    /**
     * This is an integration test, which checks the correct working of an entire namespace move.
     * Hence it is not an unittest, hence it @coversNothing
     */
    public function test_move_wiki_namespace() {
        global $AUTH_ACL;

        $AUTH_ACL[] = "wiki:*\t@ALL\t16";

        idx_addPage('wiki:dokuwiki');
        idx_addPage('wiki:syntax');

        /** @var helper_plugin_move_plan $plan  */
        $plan = plugin_load('helper', 'move_plan');

        $this->assertFalse($plan->inProgress());

        $plan->addPageNamespaceMove('wiki', 'foo');
        $plan->addMediaNamespaceMove('wiki', 'foo');

        $plan->commit();

        $this->assertSame(1, $plan->nextStep()); // pages
        $this->assertSame(1, $plan->nextStep()); // media
        $this->assertSame(1, $plan->nextStep()); // missing
        $this->assertSame(1, $plan->nextStep()); // links
        $this->assertSame(1, $plan->nextStep()); // namepaces
        $this->assertSame(0, $plan->nextStep()); // done

        $this->assertFileExists(wikiFN('foo:dokuwiki'));
        $this->assertFileNotExists(wikiFN('wiki:syntax'));
        $this->assertFileExists(mediaFN('foo:dokuwiki-128.png'));
    }

    /**
     * This is an integration test, which checks the correct working of an entire namespace move.
     * Hence it is not an unittest, hence it @coversNothing
     */
    public function test_move_missing() {
        saveWikiText('oldspace:page', '[[missing]]', 'setup');
        idx_addPage('oldspace:page');

        /** @var helper_plugin_move_plan $plan  */
        $plan = plugin_load('helper', 'move_plan');

        $this->assertFalse($plan->inProgress());

        $plan->addPageNamespaceMove('oldspace', 'newspace');

        $plan->commit();

        $this->assertSame(1, $plan->nextStep()); // pages
        $this->assertSame(1, $plan->nextStep()); // missing
        $this->assertSame(1, $plan->nextStep()); // links
        $this->assertSame(1, $plan->nextStep()); // namepaces
        $this->assertSame(0, $plan->nextStep()); // done

        $this->assertFileExists(wikiFN('newspace:page'));
        $this->assertFileNotExists(wikiFN('oldspace:page'));

        $this->assertEquals('[[missing]]', rawWiki('newspace:page'));
    }

    /**
     * @covers helper_plugin_move_plan::findAffectedPages
     * @uses Doku_Indexer
     */
    public function test_move_affected() {
        saveWikiText('oldaffectedspace:page', '[[missing]]', 'setup');
        idx_addPage('oldaffectedspace:page');
        /** @var helper_plugin_move_plan $plan  */
        $plan = plugin_load('helper', 'move_plan');

        $this->assertFalse($plan->inProgress());

        $plan->addPageNamespaceMove('oldaffectedspace', 'newaffectedspace');

        $plan->commit();

        $affected_file = file(TMP_DIR . '/data/meta/__move_affected');
        $this->assertSame('newaffectedspace:page',trim($affected_file[0]));


    }

    /**
     * This is an integration test, which checks the correct working of an entire namespace move.
     * Hence it is not an unittest, hence it @coversNothing
     */
    function test_move_large_ns(){
        global $conf;

        $test = '[[testns:start]] [[testns:test_page17]]';
        $summary = 'testsetup';


        saveWikiText(':start', $test, $summary);
        idx_addPage(':start');
        saveWikiText('testns:start', $test, $summary);
        idx_addPage('testns:start');
        saveWikiText('testns:test_page1', $test, $summary);
        idx_addPage('testns:test_page1');
        saveWikiText('testns:test_page2', $test, $summary);
        idx_addPage('testns:test_page2');
        saveWikiText('testns:test_page3', $test, $summary);
        idx_addPage('testns:test_page3');
        saveWikiText('testns:test_page4', $test, $summary);
        idx_addPage('testns:test_page4');
        saveWikiText('testns:test_page5', $test, $summary);
        idx_addPage('testns:test_page5');
        saveWikiText('testns:test_page6', $test, $summary);
        idx_addPage('testns:test_page6');
        saveWikiText('testns:test_page7', $test, $summary);
        idx_addPage('testns:test_page7');
        saveWikiText('testns:test_page8', $test, $summary);
        idx_addPage('testns:test_page8');
        saveWikiText('testns:test_page9', $test, $summary);
        idx_addPage('testns:test_page9');
        saveWikiText('testns:test_page10', $test, $summary);
        idx_addPage('testns:test_page10');
        saveWikiText('testns:test_page11', $test, $summary);
        idx_addPage('testns:test_page11');
        saveWikiText('testns:test_page12', $test, $summary);
        idx_addPage('testns:test_page12');
        saveWikiText('testns:test_page13', $test, $summary);
        idx_addPage('testns:test_page13');
        saveWikiText('testns:test_page14', $test, $summary);
        idx_addPage('testns:test_page14');
        saveWikiText('testns:test_page15', $test, $summary);
        idx_addPage('testns:test_page15');
        saveWikiText('testns:test_page16', $test, $summary);
        idx_addPage('testns:test_page16');
        saveWikiText('testns:test_page17', $test, $summary);
        idx_addPage('testns:test_page17');
        saveWikiText('testns:test_page18', $test, $summary);
        idx_addPage('testns:test_page18');
        saveWikiText('testns:test_page19', $test, $summary);
        idx_addPage('testns:test_page19');

        $conf['plugin']['move']['autorewrite'] = 0;

        /** @var helper_plugin_move_plan $plan  */
        $plan = plugin_load('helper', 'move_plan');

        $this->assertFalse($plan->inProgress());

        $plan->addPageNamespaceMove('testns', 'foo:testns');

        $plan->commit();
        global $conf;
        $lockfile = $conf['lockdir'] . 'move.lock';

        $this->assertSame(10, $plan->nextStep(),"After processing first chunk of pages, 10 steps should be left");

        $request = new TestRequest();
        $response = $request->get();
        $actual_response = $response->getContent();
        //clean away clutter
        $actual_response = substr($actual_response,strpos($actual_response,"<!-- wikipage start -->") + 23);
        $actual_response = substr($actual_response,0,strpos($actual_response,"<!-- wikipage stop -->"));
        $actual_response = trim($actual_response);
        $actual_response = ltrim($actual_response,"<p>");
        $actual_response = rtrim($actual_response,"</p>");
        $actual_response = trim($actual_response);

        $expected_response = '<a href="/./doku.php?id=foo:testns:start" class="wikilink1" title="foo:testns:start">testns</a> <a href="/./doku.php?id=testns:test_page17" class="wikilink1" title="testns:test_page17">test_page17</a>';
        $this->assertSame($expected_response,$actual_response);

        $expected_file_contents = '[[testns:start]] [[testns:test_page17]]';
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        $actual_file_contents = $start_file[0];
        $this->assertSame($expected_file_contents,$actual_file_contents);

        /** @var helper_plugin_move_rewrite $rewrite */
        $rewrite = plugin_load('helper', 'move_rewrite');
        $expected_move_meta = array('origin'=> 'testns:start', 'pages' => array(array('testns:start','foo:testns:start')),'media' => array());
        $actual_move_media = $rewrite->getMoveMeta('foo:testns:start');
        $this->assertSame($expected_move_meta,$actual_move_media);
        $this->assertFileExists($lockfile);

    }

}
