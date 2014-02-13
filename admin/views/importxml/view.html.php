<?php
defined('_JEXEC') or die('Restricted access');

//import Joomla view library
jimport('joomla.application.component.view');

/*
 * HTML View class for the ImportXml component
 */
class ImportXmlViewImportXml extends JView {
	function display($tpl=null) {
		//Set the toolbar
		$layout = $this->getLayout();
		if(is_null($layout) || $layout == 'default') {
			ImportXmlHelper::addToolBar('upload');
		} else {
			ImportXmlHelper::addToolBar();
		}

		//Display the template
		parent::display($tpl);
	}
}