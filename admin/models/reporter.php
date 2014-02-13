<?php
defined('_JEXEC') or die('Restricted access');

//import Joomla modellist library
jimport('joomla.application.component.modellist');

/*
 * ImportXml reporter model
 */
class ImportXmlModelReporter extends JModelList {
	protected $db;
	private $upload_id;

	/*
	 * Constructor
	 */
	public function __construct($config = array()) {
		parent::__construct($config);
		
		$this->db = JFactory::getDBO();
	}

	/*
	 * getUploadId function: returns the upload_id of the current upload after startUpload has been called
	 *
	 * No input
	 *
	 * Returns the upload_id of the current upload if it has been set. False otherwise.
	 */
	public function getUploadId() {
		if(is_int($this->upload_id)) {
			return $this->upload_id;
		} else {
			return false;
		}
	}
	
	/*
	 * getListQuery function: used by the Joomla listmodel to get the items, and for the pagination
	 *
	 * No input
	 *
	 * Returns the Joomla query object containing the information to be displayed
	 */
	protected function getListQuery() {
		$query = 'SELECT u.upload_id, u.user_id, u.filename, u.timestamp, '
				.'		 SUM(CASE WHEN d.status=\'added\' THEN 1 ELSE 0 END) as added_events, '
				.'		 SUM(CASE WHEN d.status=\'updated\' THEN 1 ELSE 0 END) as updated_events, '
				.'		 SUM(CASE WHEN d.status=\'deleted\' THEN 1 ELSE 0 END) as deleted_events, '
				.'		 SUM(CASE WHEN d.status=\'errored\' THEN 1 ELSE 0 END) as errored_events, '
				.'		 COUNT(d.details_id) as total_events '
				.'FROM #__importxml_upload u '
				.'LEFT JOIN #__importxml_details d ON u.upload_id=d.upload_id '
				.'GROUP BY u.upload_id, u.user_id, u.timestamp, u.filename '
				.'ORDER BY u.timestamp DESC';
		$this->db->setQuery($query);
		return $this->db->getQuery();
	}
	
	
	/*
	 * startUpload function: starts recording a new upload in the database.
	 *
	 * Input: $user_id: the user_id of the user that initiated the upload
	 * Input: $filename: the original name of the file that is being uploaded
	 *
	 * Returns true on success, false on error
	 *
	 */
	public function startUpload($user_id, $filename) {
		/* $uploadUser = JFactory::getUser($user_id);
		if(!$uploadUser->authorise('core.manage','com_importxml')) {
			JError::raiseWarning(403, JText::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'));
			return false;
		} */
		
		$query = 'INSERT INTO #__importxml_upload(user_id, filename, timestamp) VALUES ('.$this->db->quote($user_id).','.$this->db->quote($filename).', CURRENT_TIMESTAMP)';
		$this->db->setQuery($query);
		$this->db->query();
		if($dberror = $this->db->getErrorMsg()) {
			JError::raiseError(500, JText::sprintf('COM_IMPORTXML_REPORT_INSERT_SQLERROR',$dberror));
			return false;
		}
		$upload_id = $this->db->insertid();
		if(!is_int($upload_id) || $upload_id <= 0) {
			JError::raiseError(500, JText::_('COM_IMPORTXML_REPORT_INSERT_NOID'));
			return false;
		}
		
		$this->upload_id = $upload_id;
		return true;
	}
	
	/*
	 * recordEvent function: Adds a details record to the database for the current upload with the specified information.
	 *
	 * Requires that the startUpload function has been run first
	 *
	 * Input: $id: The id of the record in the upload file
	 * Input: $eventid: If a matching event was found in the database, the JEvents ID of the matching event
	 * Input: $status: should be one of: 'added', 'updated', 'deleted', or 'errored'
	 * Input: $details: Additional information about the upload to be displayed in the details view. If the status is 'errored', then this should be the error message
	 *
	 * returns true on success, false on error
	 *
	 */
	public function recordEvent($id, $eventid, $status, $details = '') {
		if(empty($this->upload_id)) {
			JError::raiseError(500, JText::_('COM_IMPORTXML_REPORT_NOUPLOAD'));
			return false;
		}
		
		if($status == 'errored' && $details == '') {
			$backtrace = debug_backtrace();
			$details = '<p>No Error Message included: Call stack:';
			for($i=count($details),$j=0;$i>=0&&$j<5;$i--,$j++) :
				$details .= '<br />'.$details['file'].':'.$details['line'].' in '.$details['function'].'('.explode(',',$details['args']).')';
			endfor;
			$details .= '</p>';
		}

		$query = sprintf('INSERT INTO #__importxml_details(upload_id, import_id, jevent_id, status, details) VALUES (%s,%s,%s,%s,%s)'
							,$this->db->quote($this->upload_id)
							,$this->db->quote($id)
							,$this->db->quote($eventid)
							,$this->db->quote($status)
							,$this->db->quote($details)
		);
		$this->db->setQuery($query);
		$this->db->query();
		if($dberror = $this->db->getErrorMsg()) {
			JError::raiseError(500, JText::sprintf('COM_IMPORTXML_REPORT_INSERT_EVENT_SQLERROR',$dberror));
			return false;
		}
		return true;
	}
}