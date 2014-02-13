<?php
defined('_JEXEC') or die('Restricted access');

// Access check.
if (!JFactory::getUser()->authorise('core.manage', 'com_importxml')) {
	return JError::raiseWarning(404, JText::_('JERROR_ALERTNOAUTHOR'));
}

//import dependencies
jimport('joomla.application.component.controller');
include_once(dirname(__FILE__).DS.'helper.php');

//set the page title and icon
ImportXmlHelper::initializeDocument();


$controller = JController::getInstance('ImportXml');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();
