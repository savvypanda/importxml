<?php
defined('_JEXEC') or die('Restricted access');

//import Joomla view library
jimport('joomla.application.component.view');

/*
 * HTML View class for the ImportXml component
 */
class ImportXmlViewReporter extends JView {
	function display($tpl=null) {
		//Set the toolbar
		ImportXmlHelper::addToolBar('reporter');

		// Check for errors.
		if(count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode('<br />',$errors));
			return false;
		}
		
		//Assign data to the view
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');

		//Display the template
		parent::display($tpl);
	}
}