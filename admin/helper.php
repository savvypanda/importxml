<?php
defined('_JEXEC') or die('Restricted Access');

/*
 * ImportXMLHelper class
 */
class ImportXmlHelper {
	/*
	 * addToolbar function: Adds the toolbar in the administrator interface (called from the view's display function)
	 *
	 * Input: $view: The view for which we are adding the toolbar. The default is 'all'. Options:
	 * 'upload': for uploading a new file to import
	 * 'reporter': for listing all of the previous imports
	 * 'details': for listing the details of a single import
	 * 'all': for other pages. Will include all of the toolbar buttons
	 *
	 * Available Toolbar Buttons
	 * Title: Always included
	 * Upload: For displaying file upload interface
	 * Reporter: For displaying the upload reporter
	 * Preferences: Always included (if the user is authorized)
	 *
	 * No return value
	 */
	static function addToolbar($view = 'all') {
		//always add the title
		JToolBarHelper::title(JText::_('COM_IMPORTXML','importxml'));
		//conditionally add the upload Button
		if($view == 'reporter' || $view == 'details' || $view == 'all') {
			JToolBarHelper::custom('importxml.display', 'upload', 'upload', 'COM_IMPORTXML_VIEW_DEFAULT_SHORT', false);
		}
		//conditionally add the reporter button
		if($view == 'upload' || $view == 'details' || $view == 'all') {
			JToolBarHelper::custom('reporter.display', 'menus', 'menus', 'COM_IMPORTXML_VIEW_REPORTER_SHORT', false);
		}
		//only display the preferences if the user is authorized to access them
		if(JFactory::getUser()->authorize('core.admin','com_importxml')) {
			JToolBarHelper::preferences('com_importxml');
		}
	}


	/*
	 * initializeDocument function: Adds the title and other information that is set on every page.
	 */
	static function initializeDocument($titletext = 'COM_IMPORTXML_IMPORT_TITLE') {
		$document = JFactory::getDocument();
		$document->addStyleDeclaration('.icon-48-importxml{background-image: url(../media/com_importxml/images/importxml_49x48.png);}');
		$document->setTitle(JText::_($titletext));
	}
}