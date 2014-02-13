<?php
defined('_JEXEC') or die('Restricted access');

//import Joomla modellist library
jimport('joomla.application.component.modellist');

/*
 * ImportXml details model - for displaying the details only. Does not perform any create, update, or delete operations
 */
class ImportXmlModelDetails extends JModelList {
	protected $db;
	protected $upload_id;
	protected $filename;
	protected $timestamp;

	/*
	 * Constructor
	 *
	 * If there is not an upload_id set in the request, throw an error
	 */
	public function __construct($config = array()) {
			parent::__construct($config);
			$this->db = JFactory::getDBO();
			
			$id = JRequest::getInt('id',-1);
			if($id > 0) {
				$this->upload_id = $id;
				
				//now get the timestamp of the upload
				$query = 'SELECT filename, timestamp FROM #__importxml_upload WHERE upload_id='.$id;
				$this->db->setQuery($query);
				$this->db->query();
				$results = $this->db->loadRow();
				if(is_null($results)) {
					JError::raiseWarning(100,'COM_IMPORTXML_DETAILS_WITHOUT_TIMESTAMP');
				} else {
					list($this->filename, $this->timestamp) = $results;
				}
			} else {
				JError::raiseWarning(100,'COM_IMPORTXML_DETAILS_WITHOUT_UPLOAD_ID');
				return;
			}
	}

	/*
	 * get function for view to access data
	 *
	 * getUploadId
	 * getTimestamp
	 */
	public function getUploadId() {
		return $this->upload_id;
	}
	public function getTimestamp() {
		return $this->timestamp;
	}
	public function getFilename() {
		return $this->filename;
	}
	
	/*
	 * getListQuery function: used by the Joomla listmodel to get the items, and for the pagination
	 *
	 * No input
	 *
	 * Returns the Joomla query object containing the information to be displayed
	 */
	protected function getListQuery() {
		if(!is_null($this->upload_id) && $this->upload_id > 0) {
			$query = 'select * from #__importxml_details WHERE upload_id='.$this->db->quote($this->upload_id);
			$this->db->setQuery($query);
			return $this->db->getQuery();
		} else {
			JError::raiseError(500, JText::_('COM_IMPORTXML_REPORTER_GET_DETAILS_NO_ID'));
			return false;
		}
	}
}