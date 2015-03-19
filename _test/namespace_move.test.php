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
        file_exists($lockfile) ? print_r(file($lockfile)) :print_r('lockfile not found' . "\n");

        print_r("\n" . "\n");
        $this->assertSame(10, $plan->nextStep(),"After processing first chunk of pages, 10 stepsshould be left");
        print_r(':start ' . rawWiki(':start') . "\n");
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        print_r($start_file[0]);

        print_r("\n" . "\n");
        $this->assertSame(1, $plan->nextStep(),"pages2");
        print_r(':start ' . rawWiki(':start') . "\n");
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        print_r($start_file[0]);

        print_r("\n" . "\n");
        $this->assertSame(1, $plan->nextStep(),"pages3");
        print_r(':start ' . rawWiki(':start') . "\n");
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        print_r($start_file[0]);

        $this->assertSame(0, $plan->nextStep());
        print_r("\n" . "\n" . "done?\n");
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        print_r($start_file[0] . "\n");
        print_r(':start ' . rawWiki(':start') . "\n");
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        print_r($start_file[0] . "\n". "\n");

        /*$this->assertFileNotExists(wikiFN('testns:start'));
        $this->assertFileExists(wikiFN('foo:testns:start'));
        $this->assertFileExists(wikiFN('testns:test_page17'))*/;
        print_r(':start ' . rawWiki(':start') . "\n");
        print_r('testns:start ' . rawWiki('testns:start') . "\n");
        print_r('foo:testns:start ' . rawWiki('foo:testns:start') . "\n");
        print_r('testns:test_page17 ' . rawWiki('testns:test_page17') . "\n");
        $start_file = file(TMP_DIR . '/data/pages/start.txt');
        print_r($start_file[0]);
        file_exists($lockfile) ? print_r(file($lockfile)) :print_r('lockfile not found' . "\n");

        $rewrite = plugin_load('helper', 'move_rewrite');
        print_r($rewrite->getMoveMeta(':start')); //todo: ':start' still has metadata, 'start' does not -- why?
        print_r(':start ' . rawWiki(':start') . "\n");


    }

}
