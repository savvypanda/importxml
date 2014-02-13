<?php
defined('_JEXEC') or die('Restricted access');

//import joomla controlleradmin library
jimport('joomla.application.component.controller');

/*
 * ImportXml Controller
 */
class ImportXmlControllerUpload extends JController {
	
	public function __construct($config = array()) {
		parent::__construct($config);

		$this->registerTask('upload', 'upload');
	}

	public function getModel($name = 'Upload', $prefix = 'ImportXmlModel', $config = array('ignore_request' => true)) {
		return parent::getModel($name, $prefix, $config);
	}
	
	function upload() {
		JRequest::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
		
		//Verify that the user is allowed to upload the file
		$user = JFactory::getUser();
		$userid = $user->id;
		if(!$user->authorise('core.manage', 'com_importxml')) {
			JError::raiseWarning(403, JText::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'));
			return false;
		}

		//Initialize the models and perform the upload
		$uploadModel = $this->getModel();
		$reporterModel = $this->getModel('Reporter');
		$uploadModel->setReporter($reporterModel);
		
		//Now fetch the file and upload it
		$uploadfile = JRequest::getVar('jevent_import', null, 'files', 'array');
		if(is_null($uploadfile)) {
			JError::raiseError(500, JText::_('COM_IMPORTXML_UPLOAD_NULL'));
			return false;
		} elseif($uploadfile['error'] > 0) {
			JError::raiseError(500, JText::sprintf('COM_IMPORTXML_UPLOAD_FILEERROR',$uploadfile['error']));
			return false;
		}

		$success = $uploadModel->upload($userid, $uploadfile['tmp_name'], $uploadfile['name']);
		
		//If we actually got the start of the import, send an email to the administrator(s) based on how the component configuration is set
		if($success !== false) {
			$params = JComponentHelper::getParams('com_importxml');
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
		
		
		//If the upload was successful, display success page. If not, display error message.
		if($success !== false) {
			$this->setRedirect('index.php?option=com_importxml&view=details&id='.$reporterModel->getUploadId(),JText::_('COM_IMPORTXML_UPLOAD_SUCCESSFULL'));
		} else {
			JError::raiseWarning(499, JText::_('COM_IMPORTXML_ERROR_PROCESSING'));
		}
	}
	
}
