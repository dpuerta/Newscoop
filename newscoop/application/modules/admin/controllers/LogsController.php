<?php

class Admin_LogsController extends Zend_Controller_Action
{
    /** @var array */
    private $priorityNames = NULL;

    /** @var Newscoop\Entity\Repository\LogRepository */
    private $logRepository = NULL;

    /**
     * Check permissions
     */
    public function preDispatch()
    {
        $this->_helper->acl->check('log', 'view');
    }

    /**
     * Init
     */
    public function init()
    {
        camp_load_translation_strings('logs');

        // get repository
        $this->logRepository = $this->_helper->entity->getRepository('Newscoop\Entity\Log');

        // set priority names
        $this->priorityNames = array(
            getGS('Emergency'),
            getGS('Alert'),
            getGS('Critical'),
            getGS('Error'),
            getGS('Warning'),
            getGS('Notice'),
            getGS('Info'),
            getGS('Debug'),
        );
    }

    /**
     * List logs
     */
    public function indexAction()
    {
        // get priority form
        $priority = NULL;
        $form = $this->getPriorityForm()
            ->setMethod('get')
            ->setAction('');

        // handle form if valid
        if ($form->isValid($this->getRequest()->getParams())) {
            $priority = $this->getRequest()->getParam('priority', NULL);
        }

        $this->view->form = $form;
        $this->view->priorityName = !empty($this->priorityNames[$priority]) ? $this->priorityNames[$priority] : '';
        $this->view->priorityNames = $this->priorityNames;

        // fetch logs
        $limit = 15;
        $offset = max(0, (int) $_GET['offset']);
        $this->view->logs = $this->logRepository->getLogs($offset, $limit, $priority);

        // set pager
        $count = $this->logRepository->getCount($priority);
        $this->view->pager = new SimplePager($count, $limit, 'offset', isset($priority) ? "?priority={$priority}&" : '?');
    }

    /**
     * Get priority form
     *
     * @return \Zend_Form
     */
    private function getPriorityForm()
    {
        $form = new Zend_Form;

        $form->addElement('select', 'priority', array(
            'multioptions' => array('' => getGS('All')) + $this->priorityNames,
            'label' => getGS('Severity:'),
        ));

        $form->addElement('submit', 'submit', array(
            'ignore' => true,
            'label' => getGS('Filter'),
        ));

        return $form;
    }
}
