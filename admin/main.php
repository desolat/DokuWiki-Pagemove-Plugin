<?php
/**
 * Plugin : Move
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 * @author     Gary Owen,
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Admin component of the move plugin. Provides the user interface.
 */
class admin_plugin_move_main extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_move_plan $plan */
    protected $plan;

    public function __construct() {
        $this->plan = plugin_load('helper', 'move_plan');
    }

    /**
     * Get the sort number that defines the position in the admin menu.
     *
     * @return int The sort number
     */
    function getMenuSort() {
        return 1000;
    }

    /**
     * If this admin plugin is for admins only
     * @return bool false
     */
    function forAdminOnly() {
        return false;
    }

    function handle() {
        global $INPUT;

        // create a new plan if possible and sufficient data was given
        $this->createPlanFromInput();

        // handle workflow button presses
        if($this->plan->isCommited()) {
            switch($INPUT->str('ctl')) {
                case 'continue':
                    $this->plan->nextStep();
                    break;
                case 'skip':
                    $this->plan->nextStep(true);
                    break;
                case 'abort':
                    $this->plan->abort();
                    break;
            }
        }
    }

    function html() {
        // decide what to do based on the plan's state
        if($this->plan->isCommited()) {
            $this->GUI_progress();
        } else {
            // display form
            $this->GUI_simpleForm();
        }
    }

    /**
     * Create a plan from input
     *
     * @return bool
     */
    protected function createPlanFromInput() {
        global $INPUT;
        global $ID;

        if($this->plan->isCommited()) return false;

        $this->plan->setOption('autoskip', $INPUT->bool('autoskip'));
        $this->plan->setOption('autorewrite', $INPUT->bool('autorewrite'));

        if($ID && $INPUT->has('dst')) {
            $dst = trim($INPUT->str('dst'));
            if($dst == '') {
                msg($this->getLang('nodst'), -1);
                return false;
            }

            // input came from form
            if($INPUT->str('class') == 'namespace') {
                $src = getNS($ID);

                if($INPUT->str('type') == 'both') {
                    $this->plan->addPageNamespaceMove($src, $dst);
                    $this->plan->addMediaNamespaceMove($src, $dst);
                } else if($INPUT->str('type') == 'page') {
                    $this->plan->addPageNamespaceMove($src, $dst);
                } else if($INPUT->str('type') == 'media') {
                    $this->plan->addMediaNamespaceMove($src, $dst);
                }
            } else {
                $this->plan->addPageMove($ID, $INPUT->str('dst'));
            }
            $this->plan->commit();
            return true;
        } elseif($INPUT->has('json')) {
            // input came via JSON from tree manager
            // FIXME
            $this->plan->commit();
            return true;
        }

        return false;
    }

    /**
     * Display the simple move form
     */
    protected function GUI_simpleForm() {
        global $ID;
        $form = new Doku_Form(array('action' => wl($ID), 'method' => 'post', 'class' => 'plugin_move_form'));
        $form->addHidden('page', 'move_main');
        $form->addHidden('id', $ID);

        $form->startFieldset($this->getLang('legend'));

        $form->addElement(form_makeRadioField('class', 'page', $this->getLang('movepage') . ' <code>'.$ID.'</code>', '', 'block radio click-page', array('checked' => 'checked')));
        $form->addElement(form_makeRadioField('class', 'namepsace', $this->getLang('movens') . ' <code>'.getNS($ID).'</code>', '', 'block radio click-ns'));
        $form->addElement(form_makeTextField('dst', $ID, $this->getLang('dst'), '', 'block indent'));

        $form->addElement(form_makeMenuField('type', array('pages' => $this->getLang('move_pages'), 'media' => $this->getLang('move_media'), 'both' => $this->getLang('move_media_and_pages')), 'both', $this->getLang('content_to_move'), '', 'block indent select'));

        $form->addElement(form_makeCheckboxField('autoskip', '1', $this->getLang('autoskip'), '', 'block', ($this->getConf('autoskip') ? array('checked' => 'checked') : array())));
        $form->addElement(form_makeCheckboxField('autorewrite', '1', $this->getLang('autorewrite'), '', 'block', ($this->getConf('autorewrite') ? array('checked' => 'checked') : array())));

        $form->addElement(form_makeButton('submit', 'admin', $this->getLang('btn_start')));
        $form->endFieldset();
        $form->printForm();
    }

    protected function GUI_previewPlan() {
        $this->createPlanFromInput();

    }

    /**
     * Display the GUI while the move progresses
     */
    protected function GUI_progress() {
        $progress = $this->plan->getProgress();

        // FIXME add intro here depending on start or progress

        echo '<div class="progress" data-progress="' . $progress . '">' . $progress . '%</div>';


        echo '<div class="output">';
        if($this->plan->getLastError()) {
            echo '<div class="error">' . $this->plan->getLastError() . '</div>';
        }
        echo '</div>';


        echo '<div class="controls">';
        if(!$this->plan->inProgress()) {
            $this->btn('start');
        } else if($this->plan->getLastError()) {
            $this->btn('skip');
            $this->btn('retry');
        } else {
            $this->btn('continue');
        }
        $this->btn('abort');
        echo '</div>';
    }

    /**
     * Display a move workflow button
     *
     * continue, start, retry - continue next steps
     * abort - abort the whole move
     * skip - skip error and continue
     *
     * @param string $control
     */
    protected function btn($control) {
        global $ID;

        $label = $this->getLang('btn_' . $control);
        if($control == 'start') $control = 'continue';
        if($control == 'retry') $control = 'continue';

        $form = new Doku_Form(array('action' => wl($ID), 'method' => 'post', 'class' => 'move__control'));
        $form->addHidden('page', 'move_main');
        $form->addHidden('id', $ID);
        $form->addHidden('ctl', $control);
        $form->addElement(form_makeButton('submit', 'admin', $label));
        $form->printForm();
    }
}