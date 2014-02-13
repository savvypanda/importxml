<?php
define('_JEXEC',1);
define('DS', DIRECTORY_SEPARATOR);

if (file_exists(dirname(dirname(__FILE__)).'/defines.php')) {
	include_once dirname(dirname(__FILE__)).'/defines.php';
}
if (!defined('_JDEFINES')) {
	define('JPATH_BASE', dirname(dirname(__FILE__)));
	require_once JPATH_BASE.'/includes/defines.php';
}

require_once(JPATH_LIBRARIES.'/import.php');
if(file_exists(JPATH_BASE.'/includes/version.php')) {
	require_once JPATH_BASE.'/includes/version.php';
} else {
	require_once JPATH_LIBRARIES.'/cms.php';
}

jimport('joomla.application.cli');

class ImportXmlApp extends JApplicationCli {
	protected function doExecute() {
		restore_error_handler();
		JError::setErrorHandling(E_ERROR, 'die');
		JError::setErrorHandling(E_WARNING, 'echo');
		JError::setErrorHandling(E_NOTICE, 'ignore');

		jimport('joomla.environment.request');

		if(function_exists('set_time_limit')) {
			@set_time_limit(0);
		}

		jimport('joomla.application.component.helper');

		define('JPATH_COMPONENT_ADMINISTRATOR',JPATH_ADMINISTRATOR.DS.'components'.DS.'com_importxml');
		JFactory::getLanguage()->load('com_importxml', JPATH_ADMINISTRATOR, 'en-GB', true);
		$this->_importXmlFile();
	}

	private function _importXmlFile() {
		$userid = 0;
		require_once(JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'upload.php');
		require_once(JPATH_COMPONENT_ADMINISTRATOR.DS.'models'.DS.'reporter.php');
		$uploadModel = new ImportXmlModelUpload();
		$reporterModel = new ImportXmlModelReporter();
		$uploadModel->setReporter($reporterModel);

		//Get the configurable parameters from the component
		$params = JComponentHelper::getParams('com_importxml');
		$cron_filename = $params->get('cron_filename');
		$archive_filename = $params->get('archive_filename');
		$archive_days = $params->get('archive_days');

		//If the file does not exist, we can't upload it
		$filepath = JPATH_COMPONENT_ADMINISTRATOR.DS.'upload'.DS.$cron_filename;
		if(!file_exists($filepath)) {
			JError::raiseWarning(409,JText::sprintf("COM_IMPORTXML_FILE_DOES_NOT_EXIST",$cron_filename));
			return false;
		}

		$filename_parts = pathinfo($filepath);
		$archive_filename = str_replace('{FILENAME}',$cron_filename,$archive_filename);
		$archive_filename = str_replace('{FILENAME_BASE}',$filename_parts['filename'],$archive_filename);
		$archive_filename = str_replace('{TIMESTAMP}',date('Y-m-d_His'),$archive_filename);

		//Now fetch the file and upload it
		$success = $uploadModel->upload($userid, $filepath, $archive_filename);

		//Then archive the file and remove old files from the archive directory
		$archives_dir = JPATH_COMPONENT_ADMINISTRATOR.DS.'upload'.DS.'archived';
		rename($filepath, $archives_dir.DS.$archive_filename);
		$archives_handle = opendir($archives_dir);
		while(false !== ($file = readdir($archives_handle))) {
			if($file != '.' && $file != '..' && $file != 'index.html' && time() - filemtime($archives_dir.DS.$file) > $archive_days*24*3600) {
				unlink($archives_dir.DS.$file);
			}
		}
		closedir($archives_handle);

		//If we actually got the start of the import, send an email to the administrator(s) based on how the component configuration is set
		if($success !== false) {
			switch($params->get('notify_admin')) {
				case 0: //never. Do not send an email
					break; //do not send the email
				case 1: //on error. Break unless there was an error, in which case we should send an email
					if($success !== true) {
						break;
					}
				case 2: //always
					//send a summary email to the admin email addresses listed in the component parameters
					//send the email from the address listed in the joomla configuration
					$config = JFactory::getConfig();
					$fromemail = $config->getValue('config.mailfrom');
					$fromname = $config->getValue('config.fromname');
					$recipient = explode(',',$params->get('admin_emails'));
					$subject = JText::_('COM_IMPORTXML_IMPORT_EMAIL_SUBJECT');
					$body = JText::sprintf('COM_IMPORTXML_IMPORT_EMAIL_BODY',$uploadModel->getAdded(),$uploadModel->getDeleted(),$uploadModel->getUpdated(),$uploadModel->getErrored(),$uploadModel->getTotal(),'index.php?option=com_importxml&view=reporter&task=details&id='.$reporterModel->getUploadId());
					JMail::sendMail($fromemail, $fromname, $recipient, $subject, $body);
					break;
			}
		}


		//If the upload was successful, display success message. If not, display error message.
		if($success !== false) {
			$this->out(JText::_('COM_IMPORTXML_CRON_UPLOAD_SUCCESS'));
		} else {
			JError::raiseWarning(499, JText::_('COM_IMPORTXML_ERROR_PROCESSING'));
		}
	}
}

JApplicationCli::getInstance('ImportXmlApp')->execute();