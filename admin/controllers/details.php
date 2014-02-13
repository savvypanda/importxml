<?php
defined('_JEXEC') or die('Restricted access');

//import joomla controller library
jimport('joomla.application.component.controller');

/*
 * ImportXml Controller
 */
class ImportXmlControllerDetails extends JController {
	function display($cachable=false, $urlparams=false) {
		JRequest::setVar('view', JRequest::getCmd('view','Details'));
		parent::display($cachable, $urlparams);
	}
	
	public function getModel($name = 'Details', $prefix = 'ImportXmlModel', $config = array('ignore_request' => true)) {
		return parent::getModel($name, $prefix, $config);
	}
}