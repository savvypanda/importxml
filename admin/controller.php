<?php
defined('_JEXEC') or die('Restricted access');

//import dependencies
jimport('joomla.application.component.controller');

/*
 * ImportXml Controller
 */
class ImportXmlController extends JController {
	
	function display($cachable=false, $urlparams=false) {
		JRequest::setVar('view', JRequest::getCmd('view','ImportXml'));
		parent::display($cachable, $urlparams);
		return $this;
	}

	
	public function getModel($name = 'ImportXml', $prefix = 'ImportXmlModel', $config = array('ignore_request' => true)) {
		return parent::getModel($name, $prefix, $config);
	}
}
