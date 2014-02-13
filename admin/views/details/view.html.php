<?php
defined('_JEXEC') or die('Restricted access');

//import Joomla view library
jimport('joomla.application.component.view');

/*
 * HTML View class for the ImportXml component
 */
class ImportXmlViewDetails extends JView {
	function display($tpl=null) {
		//Set the toolbar
		ImportXmlHelper::addToolBar('details');

		// Check for errors.
		if(count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode('<br />',$errors));
			return false;
		}
		
		//Assign data to the view
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->upload_id = $this->get('UploadId');
		$this->filename = $this->get('Filename');
		$this->timestamp = $this->get('Timestamp');

		//Display the template
		parent::display($tpl);
	}
}