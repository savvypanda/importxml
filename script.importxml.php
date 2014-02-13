<?php
defined('_JEXEC') or die('Restricted access');
 
// include required libraries
jimport('joomla.application.component.helper');

 
/**
 * Script file of ImportXml component
 */
class com_importXmlInstallerScript {

	/*
     * method to install the component
	 *
	 * @return void
	*/
	function install($parent) {
		//if #__jevents_vevent does not already have an import_id column, add it
		$db = JFactory::getDBO();
		$fields = $db->getTableFields('#__jevents_vevent');
		if (!array_key_exists('import_id', $fields['#__jevents_vevent'])) {
			$query = 'ALTER TABLE `#__jevents_vevent` ADD COLUMN `import_id` VARCHAR(64), ADD INDEX(`import_id`)';
			$db->setQuery($query);
			$db->query();
		}
	}

	/*
	 * method to uninstall the component
	 *
	 * @return void
	*/
	function uninstall($parent) {
		$this->_removeCliScript();
		echo '<p>'.JText::_('COM_IMPORTXML_UNINSTALL_TEXT').'</p>';
	}

	/*
	 * method to update the component
	 *
	 * @return void
	*/
	function update($parent) {
		JController::setMessage(JText::sprintf('COM_IMPORTXML_UPDATE_TEXT', $parent->get('manifest')->version));
		$parent->getParent()->setRedirectURL('index.php?option=com_importxml');
	}

	/*
	 * method to run before an install/update/discover_install method
	 *
	 * @return void
	*/
	function preflight($type, $parent) {
		//First, we have to verify that JEvents and the jevents customfields plugin have been installed. Do not proceed if they have not been installed
		$db = JFactory::getDBO();
		$sql = 'SELECT element FROM #__extensions WHERE (type=\'component\' AND element=\'com_jevents\') or (type=\'plugin\' and element=\'jevcustomfields\')';
		$db->setQuery($sql);
		$db->query();
		if($db->getNumRows() < 2) {
			$parent->getParent()->abort(JText::_('COM_IMPORTXML_INSTALL_FAILED_JEVENTS_REQUIRED'));
		}
	}

	/*
	 * method to run after an install/update/discover_install method
	 *
	 * @return void
	*/
	function postflight($type, $parent) {
		// $type is the type of change (install, update or discover_install)
		//all we have to do (no matter what type of install it was) is copy the cli files
		$this->_copyCliScript($parent);
	}

	/*
	 * method to run during install to copy the cli script to the joomla cli directory
	 */
	function _copyCliScript($parent) {
		$src = $parent->getParent()->getPath('source');

		jimport("joomla.filesystem.file");

		$clifile = $src.DS.'cli'.DS.'importxml_cron.php';
		$clitarget = JPATH_ROOT.DS.'cli'.DS.'importxml_cron.php';
		if(JFile::exists($clitarget)) {
			JFile::delete($clitarget);
		}
		if(JFile::exists($clifile)) {
			JFile::move($clifile, $clitarget);
		}
	}

	/*
	 * method to run during uninstall to remove the cli script if present
	 */
	function _removeCliScript() {
		jimport("joomla.filesystem.file");

		$clitarget = JPATH_ROOT.DS.'cli'.DS.'importxml_cron.php';
		if(JFile::exists($clitarget)) {
			JFile::delete($clitarget);
		}
	}
}