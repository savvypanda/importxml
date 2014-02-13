<?php
defined('_JEXEC') or die('Restricted access');

//import joomla controller library
jimport('joomla.application.component.controller');

/*
 * ImportXml Controller
 */
class ImportXmlControllerImportXml extends JController {

	/*
	 * Constructor
	 */
	public function __construct($config = array()) {
		parent::__construct($config);

		$this->registerTask('truncate','truncate');
		$this->registerTask('truncateconfirmed','truncateconfirmed');
	}

	/*
	 * Display function
	 */
	function display($cachable=false) {
		JRequest::setVar('view', JRequest::getCmd('view','ImportXml'));
		parent::display($cachable);
	}
	
	/*
	 * Truncate function: To handle the truncate task.
	 * Should not actually do anything other than asking for confirmation
	 */
	function truncate() {
		$view = $this->getView('ImportXml','html','ImportXmlView');
		$view->setLayout('truncate');
		$view->display();
	}
	
	/*
	 * Truncateconfirmed function: This is where we should actually truncate all of the events.
	 */
	function truncateconfirmed() {
		$uploadModel = $this->getModel('Upload');
		$reporterModel = $this->getModel('Reporter');
		$uploadModel->setReporter($reporterModel);
		
		$result = $uploadModel->truncate();
		if($result === true) {
			$this->setMessage(JText::_('COM_IMPORTXML_TRUNCATE_SUCCESSFULL'));
			$this->display();
		} elseif ($result === false) {
			JError::raiseWarning(499, JText::_('COM_IMPORTXML_TRUNCATE_UNSUCCESSFUL'));
		} else {
			$this->setRedirect('index.php?option=com_importxml&view=details&id='.$reporterModel->getUploadId(), JText::_('COM_IMPORTXML_TRUNCATE_INCOMPLETE'));
		}
	}
}
