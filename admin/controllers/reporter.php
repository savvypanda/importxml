<?php
defined('_JEXEC') or die('Restricted access');

//import joomla controller library
jimport('joomla.application.component.controller');

/*
 * ImportXml Controller
 */
class ImportXmlControllerReporter extends JController {
	function display($cachable=false, $urlparams=false) {
		JRequest::setVar('view', JRequest::getCmd('view','Reporter'));
		parent::display($cachable, $urlparams);
	}
	
	public function getModel($name = 'Reporter', $prefix = 'ImportXmlModel', $config = array('ignore_request' => true)) {
		return parent::getModel($name, $prefix, $config);
	}
}