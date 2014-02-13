<?php defined('_JEXEC') or die('Restricted access');

//import dependencies
jimport('joomla.application.component.model');
jimport('joomla.utilities.simplexml');

/*
 * ImportXml jevent model
 *
 * For use in reading an XML file and using it to add, update,
 * and delete events from the JEvents component.
 *
 */
class ImportXmlModelUpload extends JModel {
	protected $db;
	private $reporter;
	private $user_id;
	private $added = 0;
	private $deleted = 0;
	private $updated = 0;
	private $errored = 0;
	private $nexteventid;
	private $nextdetailid;

	private $jevents_categories = array();
	private $accesslevels = array();


	//corefields is an associative array where the keys are the fieldnames in the xml file,
	//and the values are the fieldnames in the JEvents database tables
	private $corefields = array(
		'id'=>'id',
		'title'=>'summary',
		'Audience'=>'category',
		'accessLevel'=>'access',
		'descrip'=>'description',
		'location'=>'location',
		'contact'=>'contact',
		'additionalInfo'=>'extrainfo',
		'allDay'=>'allDay',
		'twelveHour'=>'twelve_hour',
		'startDate'=>'startDate',
		'startTime'=>'startTime',
		'endDate'=>'endDate',
		'endTime'=>'endTime',
		'multiday'=>'multiday'
	);

	//customfields is an associative array where the keys are the fieldnames in the xml file,
	//and the values are the fieldnames in the customfields plugin

	//if desired, each customfield can be transformed and validated when it is imported from the file,
	//by adding private functions to the end of the file named fieldkey_transform and fieldkey_validate
	//having a null or empty value should be allowed for each custom field
	//
	//see live examples at the bottom of this file
	private $customfields = array(
		'priorityArea'=>'priority_area',
		'gradeLevel'=>'level',
		'department'=>'department',
		'content'=>'content',
		'trainingType'=>'training_type',
		'courseCreditHours'=>'course_credit_hours',
		'compensation'=>'teacher_stripend',
		'registrationType'=>'registration_type',
		'eTrainCourseNumber'=>'etrain_course_no',
		'eTrainRegistration'=>'registration_link',
		'invitationRegistrTimeframe'=>'invitation_registration_timeframe'
	);


	/*
	 * Constructor
	 */
	public function __construct($config = array()) {
		parent::__construct($config);
		$this->db = JFactory::getDBO();
	}


	/*
	 * Function to set the reporter for the class
	 * Includes error handling for if the given object is not a reporter
	 * This function must be called before the upload function
	 *
	 * Input: $object: The reporter object to set in this class
	 *
	 * Returns: True on success, false on failure.
	 *
	 */
	public function setReporter(&$object) {
		if(get_class($object) == 'ImportXmlModelReporter') {
			$this->reporter = $object;
			return true;
		} else {
			return false;
		}
	}

	/*
	 * Get functions for counting how many records were affected
	 *
	 * getAdded()
	 * getDeleted()
	 * getUpdated()
	 * getErrored()
	 * getTotal()
	 */
	public function getAdded() {
		return $this->added;
	}
	public function getDeleted() {
		return $this->deleted;
	}
	public function getUpdated() {
		return $this->updated;
	}
	public function getErrored() {
		return $this->errored;
	}
	public function getTotal() {
		return $this->added + $this->deleted + $this->updated + $this->errored;
	}

	/*
	 * Truncate function. This should be confirmed before it is called, since it will remove all events from JEvents
	 *
	 * Returns: True if there are were no errors. False if there is an error that prevents the truncate from continuing.
	 * The number of errors if there were errors that did not prevent the truncate operation from continuing (ie: with specific events).
	 *
	 */
	public function truncate() {
		// Double-check that user exists and has the correct permissions in the ImportXml component as well as in JEvents
		$user = JFactory::getUser();
		if (!$user->authorise('core.manage', 'com_importxml') || !$user->authorize('core.manage', 'com_jevents')) {
			JError::raiseWarning(403, JText::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'));
			return false;
		}

		// Double-check that the reporter has been set
		if(is_null($this->reporter) || get_class($this->reporter) != 'ImportXmlModelReporter') {
			JError::raiseError(500, JText::_('COM_IMPORTXML_UPLOAD_REPORTER_NOT_SET'));
			return false;
		}

		//get all of the events that need to be deleted
		//the only two field that the delete function needs are eventid and detail_id
		$query = 'SELECT ev_id as eventid, detail_id FROM #__jevents_vevent';
		$this->db->setQuery($query);
		$this->db->query();
		$jevents = $this->db->loadObjectList();

		// And start recording the "upload" events with the reporter
		$this->reporter->startUpload($user->id, JText::_('COM_IMPORTXML_TRUNCATE_FIENAME'));

		//Now delete each event in turn, and record it with the reporter
		foreach($jevents as $event) {
			$result = $this->deleteEvent($event);
			if($result === true) {
				$this->reporter->recordEvent('',$event->eventid,'deleted');
				$this->deleted++;
			} else {
				$this->reporter->recordEvent('',$event->eventid,'errored',$result);
				$this->errored++;
			}
		}

		return ($this->errored==0)?true:$this->errored;
	}

	/*
	 * Upload function. This is the main point of this model
	 * The reporter must be set before this function is called or else it will error
	 *
	 * Input: $userid: the userid that initiated the file import. Used for reporting.
	 * Input: $filelocation: the location of the file to import
	 *
	 * Returns: True if there are were no errors. False if there is an error that prevents the upload from continuing.
	 * The number of errors if there were errors that did not prevent the upload from continuing (ie: with specific events).
	 *
	 */
	public function upload($userid, $filepath, $filename) {
		$this->user_id = $userid;

		// Double-check that the reporter has been set
		if(is_null($this->reporter) || get_class($this->reporter) != 'ImportXmlModelReporter') {
			JError::raiseError(500, JText::_('COM_IMPORTXML_UPLOAD_REPORTER_NOT_SET'));
			return false;
		}

		// Load the file (error handling in getXml function)
		$events = $this->getXmlFileAsObject($filepath);
		if(empty($events)) {
			JError::raiseWarning(404, JText::_('COM_IMPORTXML_UPLOAD_FAILED_FILE_EMPTY'));
			return false;
		}
		if(get_class($events) != 'SimpleXMLElement') {
			JError::raiseWarning(404, JText::_('COM_IMPORTXML_UPLOAD_FAILED_NOT_SIMPLEXML'));
			return false;
		}
		$children = $events->children();
		if(empty($children)) {
			JError::raiseWarning(404, JText::_('COM_IMPORTXML_UPLOAD_FAILED_NO_EVENTS'));
			return false;
		}

		// And start recording the upload events with the reporter
		$this->reporter->startUpload($this->user_id, $filename);

		// Perform the appropriate action for each event in the file
		foreach($children as $e) {
			$eventid = $e->id;
			//Verify that the event has an ID
			if(empty($eventid)) {
				$this->reporter->recordEvent('','','errored',JText::_('COM_IMPORTXML_EVENT_WITH_NO_ID'));
				$this->errored++;
				continue;
			}

			//Perform any necessary transformations on the event
			$event = $this->prepareEvent($e);
			if(!$event || is_string($event)) {
				$this->reporter->recordEvent($eventid,'','errored',JText::sprintf('COM_IMPORTXML_EVENT_FAILED_PREPARATION', $eventid, $event));
				$this->errored++;
				continue;
			}

			//die('<pre>Event: '.var_export($event,true).'</pre>');

			//Add the event if the ID does not already exist
			if(empty($event->eventid)) {
				//First, check to see if we are really supposed to remove the event instead of add it
				//if this is the case, just ignore it - it already doesn't exist
				if (empty($event->summary) && empty($event->category) && empty($event->accessLevel)
						&& empty($event->description) && empty($event->location)
						&& empty($event->contact) /* That is probably enough */) {
					$this->reporter->recordEvent($event->id, $event->eventid,'ignored',JText::_('COM_IMPOTXML_DELETE_NONEXISTENT_EVENT'));
				} else {
					$result = $this->addEvent($event);
					if($result === true) {
						$this->reporter->recordEvent($event->id,$event->eventid,'added');
						$this->added++;
					} else {
						$this->reporter->recordEvent($event->id,$event->eventid,'errored',$result);
						$this->errored++;
					}
				}
				//Delete the event if the only field that is filled in is the ID
			} elseif(empty($event->summary) && empty($event->category) && empty($event->accessLevel)
					 && empty($event->description) && empty($event->location)
					 && empty($event->contact) /* That is probably enough */) {
				$result = $this->deleteEvent($event);
				if($result === true) {
					$this->reporter->recordEvent($event->id,$event->eventid,'deleted');
					$this->deleted++;
				} else {
					$this->reporter->recordEvent($event->id,$event->eventid,'errored',$result);
					$this->errored++;
				}
				//Update the event if the ID already exists and the rest of the fields are not empty
			} else {
				$result = $this->updateEvent($event);
				if($result === true) {
					$this->reporter->recordEvent($event->id,$event->eventid,'updated');
					$this->updated++;
				} else {
					$this->reporter->recordEvent($event->id,$event->eventid,'errored',$result);
					$this->errored++;
				}
			}
		}

		// Return the number of errors encountered during the upload
		return ($this->errored == 0)?true:$this->errored;
	}

	/*
	 * getXmlFileAsObject function. Translates an XML file to a PHP object
	 *
	 * Input: $filelocation: the location of the XML file to translate
	 *
	 * Returns: a SimpleXMLElement representing the XML file on success. False on error.
	 * Throws an error or warning on failure.
	 *
	 */
	protected function getXmlFileAsObject($filelocation) {
		if(!function_exists('simplexml_load_file')) {
			JError::raiseError(500, JText::_('COM_IMPORTXML_SIMPLEXML_NOT_ENABLED'));
			return false;
		}
		if(!file_exists($filelocation)) {
			JError::raiseWarning(405, JText::sprintf('COM_IMPORTXML_FILE_DOES_NOT_EXIST', $filelocation));
			return false;
		}
		$xml = simplexml_load_file($filelocation);
		if($xml===false) {
			JError::raiseWarning(406, JText::_('COM_IMPORTXML_FILE_LOAD_IS_FALSE'));
			return false;
		}
		return $xml;
	}

	/*
	 * prepareEvent function. Performs any modifications necessary to prepare
	 * an event object for the database. Verifies that the event elements are corerct.
	 * Also adds the JEvent ID to the record if it exists.
	 *
	 * Input: $event: The event to be modified/verified
	 *
	 * Returns: Basic PHP object containing the event on success, string containing an error message if any of the elements are not valid
	 *
	 */
	private function prepareEvent($event) {
		$newevent = array();

		//iterate through the core and custom event fields, performing transformations as necessary, and validating the format
		//additionally, each field must be converted from a SimpleXmlElement (in $event) to a string (in $newevent)
		foreach($this->corefields as $key=>$value) {
			$transformfunction = $key.'_transform';
			$validatefunction = $key.'_validate';
			if(method_exists($this,$transformfunction)) {
				$newevent[$value] = $this->$transformfunction(strval($event->$key));
			} else {
				$newevent[$value] = strval($event->$key);
			}
			if(method_exists($this,$validatefunction) && !empty($newevent[$value]) && !$this->$validatefunction($newevent[$value])) {
				return JText::sprintf('COM_IMPORTXML_EVENT_'.strtoupper($key).'_INVALID',$newevent[$value]);
			}
		}
		foreach($this->customfields as $key=>$value) {
			$transformfunction = $key.'_transform';
			$validatefunction = $key.'_validate';
			if(method_exists($this,$transformfunction)) {
				$newevent[$value] = $this->$transformfunction(strval($event->$key));
			} else {
				$newevent[$value] = strval($event->$key);
			}
			if(method_exists($this,$validatefunction) && !empty($newevent[$value]) && !$this->$validatefunction($newevent[$value])) {
				return JText::sprintf('COM_IMPORTXML_EVENT_CUSTOMFIELD_INVALID',$key,$newevent[$value]);
			}
		}

		//now add the elements that we can find but that are not in the XML file
		//starting with eventid (from JEvents in the Joomla database)
		list($newevent['eventid'],$newevent['detail_id']) = $this->getJEventId($event->id);

		//uid (it is an MD5 hash of a unique identifier based on the current time in microseconds)
		$newevent['uid'] = md5(uniqid(rand(),true));

		//dtstart and dtend. They are not provided in the xml file, but can be deduced from it
		$newevent['dtstart'] = strtotime($newevent['startDate'].' '.$newevent['startTime']);
		$newevent['dtend'] = strtotime($newevent['endDate'].' '.$newevent['endTime']);

		//rrule
		$newevent['rrule'] = array(
			'FREQ' => 'none',
			'COUNT' => '1',
			'INTERVAL' => '1',
			'BYDAY' => strtoupper(substr(date('D',strtotime($newevent['startDate'])),0,2))
		);

		//we need the rawdata as a serialized array to create/update events correctly
		$rawdata_array = array(
			'UID' => $newevent['uid'],
			'X-EXTRAINFO' => $newevent['extrainfo'],
			'LOCATION' => $newevent['location'],
			'allDayEvent' => $newevent['allDay'],
			'CONTACT' => $newevent['contact'],
			'DESCRIPTION' => $newevent['description'],
			'publish_down' => date('Y-m-d'),
			'publish_up' => date('Y-m-d'),
			'SUMMARY' => $newevent['summary'],
			'URL' => '',
			'X-CREATEDBY' => '0',
			'DTSTART' => $newevent['dtstart'],
			'DTEND' => $newevent['dtend'],
			'RRULE' => $newevent['rrule'],
			'MULTIDAY' => $newevent['multiday'],
			'NOENDTIME' => '0',
			'X-COLOR' => '',
			'LOCKEVENT' => '0'
		);
		foreach($this->customfields as $field) {
			$rawdata_array['custom_'.$field] = $newevent[$field];
		}
		$newevent['rawdata'] = serialize($rawdata_array);

		//now that we have done all of the data transformation and verification, return the new event
		//$mynewevent = (object) $newevent;
		//die('<pre>Event: '.var_export($mynewevent,true).'</pre>');
		return (object) $newevent;
	}

	/*
	 * getCatIdFromCatName function: gets the catid from the jevents category that matches the given name
	 *
	 * Input: $catname: The name of the category to look up
	 *
	 * Returns: The category ID of the correlating category. If no category is found, returns null
	 */
	private function getCatIdFromCatName($catname) {
		if(!array_key_exists($catname,$this->jevents_categories)) {
			$query = sprintf('SELECT id FROM #__categories WHERE extension = \'com_jevents\' AND title=%s',$this->db->quote($catname));
			$this->db->setQuery($query);
			$this->db->query();
			$this->jevents_categories[$catname] = $this->db->loadResult();
		}
		return $this->jevents_categories[$catname];
	}

	/*
	 * getAccessIdFromName function: gets the id from the access level that matches the given name
	 *
	 * Input: $accessname: The title of the access level to look up
	 *
	 * Returns: The ID of the correlating access level. If no match is found, returns null
	 */
	private function getAccessIdFromName($accessname) {
		if(!array_key_exists($accessname,$this->accesslevels)) {
			$query = sprintf('SELECT id FROM #__viewlevels WHERE title=%s',$this->db->quote($accessname));
			$this->db->setQuery($query);
			$this->db->query();
			$this->accesslevels[$accessname] = $this->db->loadResult();
		}
		return $this->accesslevels[$accessname];
	}

	/*
	 * getJEventId function. Gets the JEvent ID and Detail ID from the record with the given XML file ID.
	 *
	 * Input: $old_id: The ID from the XML file
	 *
	 * Returns: An array containing the JEvents ID and Detail ID from the correlating database record.
	 * If no record was located, returns an array containing two null values.
	 *
	 */
	private function getJEventId($old_id){
		$query = 'SELECT ev_id, detail_id FROM #__jevents_vevent WHERE import_id='.$this->db->quote($old_id);
		$this->db->setQuery($query);
		$this->db->query();
		$result = $this->db->loadRow();
		return $result?$result:array(NULL,NULL);
	}

	/*
	 * getEventFromId function. Fetches an object from the datbase with the specified JEvent's data.
	 *
	 * Input: $eventid: The JEvent ID of the event to fetch
	 *
	 * Returns: An Object containing the JEvent in the database that
	 * correlates to the specified ID. False on error.
	 *
	 */
	private function getEventFromEventid($eventid) {
		if(preg_match('/[^0-9]/',$eventid)) {
			JError::raiseError(500,JText::sprintf('COM_IMPORTXML_CHECKING_INVALID_ID',$eventid));
			return false;
		}

		//most of the data comes from the vevent and vevdetail tables
		$query1 = 'SELECT
				e.ev_id as eventid,
				e.detail_id,
				e.import_id as id,
				d.summary,
				e.catid as category,
				e.access as access,
				d.description as description,
				d.location as location,
				d.contact as contact,
				e.rawdata as rawdata
			  FROM #__jevents_vevent e
			  JOIN #__jevents_vevdetail d on e.detail_id = d.evdet_id
			  WHERE e.ev_id='.$this->db->quote($eventid);
		$this->db->setQuery($query1);
		$this->db->query();
		$event = $this->db->loadObject();

		//if the database didn't return an event, return false.
		if(!$event) {
			return false;
		}

		//the rawdata field contains much of the information we are looking for
		$rawdata = unserialize($event->rawdata);
		$event->uid = $rawdata['UID'];
		$event->extrainfo = $rawdata['X-EXTRAINFO'];
		$event->allDay = $rawdata['allDayEvent'];
		$event->rrule = $rawdata['RRULE'];
		$event->multiday = $rawdata['MULTIDAY'];
		//		$event->startDate = date('Y-m-d',$rawdata['DTSTART']);
		//		$event->startTime = date('H:i',$rawdata['DTSTART']);
		//		$event->endDate = date('Y-m-d',$rawdata['DTEND']);
		//		$event->endTime = date('H:i',$rawdata['DTEND']);
		$event->dtstart = $rawdata['DTSTART'];
		$event->dtend = $rawdata['DTEND'];

		//and the rest of the information is in the customfields table
		$query2 = 'SELECT name, value FROM #__jev_customfields WHERE evdet_id='.$event->detail_id;
		$this->db->setQuery($query2);
		$this->db->query();
		$result = $this->db->loadRowList();
		foreach($result as $r) {
			$fieldname = array_search($r[0],$this->customfields);
			if(is_string($fieldname)) {
				$event->$fieldname = $r[1];
			}
		}

		//I wasn't able to find where the twelveHour field is stored in the database, or what affect it has
		$event->twelveHour = 'unknown';

		//now we need to loop through all of the custom fields and set them to null if they were not already set
		foreach($this->customfields as $key => $value) {
			if(!property_exists($event, $value)) {
				$event->$value = null;
			}

		}

		return $event;
	}

	/*
	 * getNewEventId function. Gets the next available JEvent ID from the database.
	 * To be used for adding new events to the database.
	 *
	 * Returns: The next available JEvent ID
	 *
	 */
	private function getNewEventId() {
		if(is_null($this->nexteventid)) {
			$query = 'SELECT MAX(ev_id) FROM #__jevents_vevent';
			$this->db->setQuery($query);
			$this->db->query();
			if($this->db->getNumRows() == 0) {
				$this->nexteventid=0;
			} else {
				$this->nexteventid = $this->db->loadResult();
			}
		}
		return ++$this->nexteventid;
	}

	/*
	 * getNewDetailId function. Gets the next available JEvent Detail ID from the database.
	 * To be used for adding new events to the database.
	 *
	 * Returns: The next available JEvent Detail ID
	 *
	 */
	private function getNewDetailId() {
		if(is_null($this->nextdetailid)) {
			$query = 'SELECT MAX(evdet_id) FROM #__jevents_vevdetail';
			$this->db->setQuery($query);
			$this->db->query();
			if($this->db->getNumRows() == 0) {
				$this->nextdetailid=0;
			} else {
				$this->nextdetailid = $this->db->loadResult();
			}
		}
		return ++$this->nextdetailid;
	}


	/*
	 * executeQueryTest function. Executes the specified query.
	 *
	 * Input: $query: The query to execute (should be an INSERT, UPDATE, or DELETE query)
	 *
	 * Returns: True on success. Database error message on failure.
	 *
	 */
	private function executeQueryTest($query) {
		$this->db->setQuery($query);
		$this->db->query();
		if($dberror = $this->db->getErrorMsg()) {
			return $dberror;
		}
		return true;
	}

	/*
	 * deleteEvent function. Helper function to delete a JEvent record.
	 *
	 * Input: $event: The event to be deleted
	 *
	 * Returns: True on success. String containing an error message on failure.
	 *
	 * Runs all of the database operations in a transaction. If any step fails, the
	 * whole transaction is rolled back.
	 *
	 */
	private function deleteEvent(&$event) {
		//verify that the event exists
		if(!$event->eventid || !$event->detail_id) {
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_NOID',$event->id);
		}

		//and delete it inside a transaction
		$this->db->transactionStart();

		$query = "DELETE FROM #__jev_customfields WHERE evdet_id=".$event->detail_id;
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_SQLERROR', $event->id, $result);
		}

		$query = "DELETE FROM #__jevents_vevent WHERE ev_id=".$event->eventid;
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_SQLERROR', $event->id, $result);
		}

		$query = "DELETE FROM #__jevents_vevdetail WHERE evdet_id=".$event->detail_id;
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_SQLERROR', $event->id, $result);
		}

		$query = "DELETE rbd FROM #__jevents_repbyday rbd JOIN #__jevents_repetition rr ON rr.rp_id=rbd.rp_id WHERE rr.eventid=".$event->eventid;
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_SQLERROR', $event->id, $result);
		}

		$query = "DELETE FROM #__jevents_repetition WHERE eventid=".$event->eventid;
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_SQLERROR', $event->id, $result);
		}

		$query = "DELETE FROM #__jevents_rrule WHERE eventid=".$event->eventid;
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_DELETE_SQLERROR', $event->id, $result);
		}

		//we have successfully removed the event. Now commit the transaction and return success
		$this->db->transactionCommit();
		return true;
	}

	/*
	 * updateEvent function. Helper function to update a JEvent record.
	 *
	 * Input: $event: The event to be updated
	 *
	 * Returns: True on success. Error message on failure.
	 *
	 * Runs all of the database operations in a transaction. If any step fails, the
	 * whole transaction is rolled back.
	 *
	 */
	private function updateEvent($event) {
		//verify that the event exists
		if(!$event->eventid || !$event->detail_id) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_NOID',$event->id);
		}

		//we also need to verify that some of the other information is present (ie: category)
		if(!$event->category) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_NOCATEGORY',$event->id);
		}
		if(!$event->summary) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_NOTITLE',$event->id);
		}
		if(!$event->access) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_NOACCESS',$event->id);
		}
		if(!$event->dtstart) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_NODTSTART',$event->id);
		}
		if(!$event->dtend) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_NODTEND',$event->id);
		}


		//then get the old event to update
		$oldevent = $this->getEventFromEventid($event->eventid);
		if(!$oldevent || empty($oldevent) || empty($oldevent->id) || empty($oldevent->eventid) || empty($oldevent->detail_id)) {
			return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_MATCH_EMPTY',$event->id);
		}
		//and change the uid on the newevent to the uid of the oldevent since it doesn't need to change
		//as well as changing the information in the rawdata field, which is a serialized array
		$event->uid = $oldevent->uid;
		$rawdata_array = unserialize($event->rawdata);
		$oldrawdata = unserialize($oldevent->rawdata);
		$rawdata_array['UID'] = $oldrawdata['UID'];
		$rawdata_array['publish_down'] = $oldrawdata['publish_down'];
		$rawdata_array['publish_up'] = $oldrawdata['publish_up'];
		$rawdata_array['URL'] = $oldrawdata['URL'];
		$rawdata_array['X-CREATEDBY'] = $oldrawdata['X-CREATEDBY'];
		$rawdata_array['NOENDTIME'] = $oldrawdata['NOENDTIME'];
		$rawdata_array['X-COLOR'] = $oldrawdata['X-COLOR'];
		$rawdata_array['LOCKEVENT'] = $oldrawdata['LOCKEVENT'];
		$event->rawdata = serialize($rawdata_array);


		//and update it inside a transaction
		$this->db->transactionStart();


		//update each of the customfields
		foreach($this->customfields as $key => $value) {
			$fieldname=$value;
			$newvalue = $event->$value;
			$oldvalue = $oldevent->$value;

			if(is_null($oldvalue) && !is_null($newvalue)) { //if it didn't previously have a value but it does now
				$query = sprintf('INSERT INTO #__jev_customfields(evdet_id, user_id, name, value) VALUES(%s,0,%s,%s)',
								 $this->db->quote($event->detail_id),
								 $this->db->quote($fieldname),
								 $this->db->quote($newvalue)
				);
				if(($result = $this->executeQueryTest($query)) !== true) {
					$this->db->transactionRollback();
					return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
				}
			} elseif(is_null($newvalue) && !is_null($oldvalue)) { //if it previously had a value but it doesn't now
				$query = sprintf('DELETE #__jev_customfields WHERE evdet_id=%s AND name=%s',
								 $this->db->quote($event->detail_id),
								 $this->db->quote($fieldname)
				);
				if(($result = $this->executeQueryTest($query)) !== true) {
					$this->db->transactionRollback();
					return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
				}
			} elseif($oldvalue != $newvalue) { //the value has changed
				$query = sprintf('UPDATE #__jev_customfields SET value=%s WHERE evdet_id=%s AND name=%s',
								 $this->db->quote($newvalue),
								 $this->db->quote($event->detail_id),
								 $this->db->quote($fieldname)
				);
				if(($result = $this->executeQueryTest($query)) !== true) {
					$this->db->transactionRollback();
					return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
				}
			}
		}


		//updating vevent fields. rawdata and the category information are the only fields that would change
		if($oldevent->rawdata != $event->rawdata || $oldevent->category != $event->category) {
			$query = sprintf('UPDATE #__jevents_vevent SET catid=%s, uid=%s, modified_by=%s, rawdata=%s WHERE ev_id=%s',
							 $this->db->quote($event->category),
							 $this->db->quote($event->uid),
							 $this->db->quote($this->user_id),
							 $this->db->quote($event->rawdata),
							 $this->db->quote($event->eventid)
			);
			if(($result = $this->executeQueryTest($query)) !== true) {
				$this->db->transactionRollback();
				return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
			}
		}

		//updating vevdetail fields
		if($oldevent->dtstart != $event->dtstart || $oldevent->dtend != $event->dtend ||
		   $oldevent->description != $event->description || $oldevent->location != $event->location ||
		   $oldevent->summary != $event->summary || $oldevent->contact != $event->contact || $oldevent->content != $event->content) {

			$query = sprintf('UPDATE #__jevents_vevdetail SET dtstart=%s, dtend=%s, description=%s, location=%s, summary=%s, contact=%s, extra_info=%s, modified=%s WHERE evdet_id=%s'
				,$this->db->quote($event->dtstart)
				,$this->db->quote($event->dtend)
				,$this->db->quote($event->description)
				,$this->db->quote($event->location)
				,$this->db->quote($event->summary)
				,$this->db->quote($event->contact)
				,$this->db->quote($event->extrainfo)
				,$this->db->quote(date('Y-m-d G:i:s'))
				,$this->db->quote($event->detail_id)
			);
			if(($result = $this->executeQueryTest($query)) !== true) {
				$this->db->transactionRollback();
				return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
			}
		}

		//updating repetition fields
		if($oldevent->dtstart != $event->dtstart || $oldevent->dtend!= $event->dtend) {
			$query = sprintf('UPDATE #__jevents_repetition SET startrepeat=%s, endrepeat=%s WHERE eventid=%s'
				,$this->db->quote(date('Y-m-d H:i:s',$event->dtstart))
				,$this->db->quote(date('Y-m-d H:i:s',$event->dtend))
				,$this->db->quote($event->eventid)
			);
			if(($result = $this->executeQueryTest($query)) !== true) {
				$this->db->transactionRollback();
				return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
			}
		}

		//updating rrule fields
		if($oldevent->rrule['BYDAY'] != $event->rrule['BYDAY']) {
			$query = sprintf('UPDATE #__jevents_rrule SET byday=%s WHERE eventid=%s'
				,$this->db->quote($event->rrule['BYDAY'])
				,$this->db->quote($event->eventid)
			);
			if(($result = $this->executeQueryTest($query)) !== true) {
				$this->db->transactionRollback();
				return JText::sprintf('COM_IMPORTXML_EVENT_UPDATE_SQLERROR', $event->id, $result);
			}
		}

		//we have successfully updated the event. Now commit the transaction and return success
		$this->db->transactionCommit();
		return true;
	}

	/*
	 * addEvent function. Helper function to add a new JEvent record.
	 *
	 * Input: $event: The event to be added
	 *
	 * Returns: True on success. Error message on failure.
	 *
	 * Runs all of the database operations in a transaction. If any step fails, the
	 * whole transaction is rolled back.
	 *
	 */
	private function addEvent($event) {
		//verify that the event does not exist yet
		if(!is_null($event->eventid) || !is_null($event->detail_id)) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_HAS_EVENTID');
		}

		//we also need to verify that certain information is present (ie: category)
		if(!$event->category) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NOCATEGORY',$event->id);
		}
		if(!$event->summary) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NOTITLE',$event->id);
		}
		if(!$event->access) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NOACCESS',$event->id);
		}
		if(!$event->dtstart) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NODTSTART',$event->id);
		}
		if(!$event->dtend) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NODTEND',$event->id);
		}


		//get a new eventid
		$event->eventid = $this->getNewEventId();
		if(!$event->eventid) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NOID',$event->id);
		}
		//and a new detail_id
		$event->detail_id = $this->getNewDetailId();
		if(!$event->detail_id) {
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_NOID',$event->id);
		}

		//then add it inside a transaction
		$this->db->transactionStart();

		//start by adding the event
		$query = sprintf('INSERT INTO #__jevents_vevent (ev_id, icsid, catid, uid, refreshed, created, created_by, created_by_alias, modified_by,'
						 .' rawdata, recurrence_id, detail_id, state, lockevent, author_notified, access,import_id)'
						 .' VALUES'
						 .' (%s,\'1\',%s,%s,\'0000-00-00 00:00:00\',%s,%s,\'ImportXml Script\',0,%s,\'\',%s,\'1\',\'0\',\'0\',%s,%s)'
			,$this->db->quote($event->eventid)
			,$this->db->quote($event->category)
			,$this->db->quote($event->uid)
			,$this->db->quote(date('Y-m-d G:i:s'))
			,$this->db->quote($this->user_id)
			,$this->db->quote($event->rawdata)
			,$this->db->quote($event->detail_id)
			,$this->db->quote($event->access)
			,$this->db->quote($event->id)
		);
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_SQLERROR', $event->id, $result);
		}

		//then add the event details
		$query = sprintf('INSERT INTO #__jevents_vevdetail (evdet_id, rawdata, dtstart, dtstartraw, duration, durationraw, dtend, dtendraw, dtstamp, class,'
						 .' categories, color, description, geolon, geolat, location, priority, status, summary, contact,'
						 .' organizer, url, extra_info, created, sequence, state, modified, multiday, hits, noendtime)'
						 .' VALUES'
						 .' (%s, \'\', %s, \'\', \'0\', \'\', %s, \'\', \'\', \'\', \'\', \'\', %s, \'0\', \'0\', %s, \'0\','
						 .' \'\', %s, %s, \'\', \'\', %s, \'\', \'0\', \'1\', %s, %s, \'0\', \'0\')'
			,$this->db->quote($event->detail_id)
			,$this->db->quote($event->dtstart)
			,$this->db->quote($event->dtend)
			,$this->db->quote($event->description)
			,$this->db->quote($event->location)
			,$this->db->quote($event->summary)
			,$this->db->quote($event->contact)
			,$this->db->quote($event->extrainfo)
			,$this->db->quote(date('Y-m-d G:i:s'))
			,$this->db->quote($event->multiday)
		);
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_SQLERROR', $event->id, $result);
		}

		//and the repitition
		$query = sprintf('INSERT INTO #__jevents_repetition (eventid, eventdetail_id, duplicatecheck, startrepeat, endrepeat)'
						 .' VALUES'
						 .' (%s,%s,%s,%s,%s)'
			,$this->db->quote($event->eventid)
			,$this->db->quote($event->detail_id)
			,$this->db->quote($event->uid)
			,$this->db->quote(date('Y-m-d H:i:s',$event->dtstart))
			,$this->db->quote(date('Y-m-d H:i:s',$event->dtend))
		);
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_SQLERROR', $event->id, $result);
		}
		$event->rr_id = $this->db->insertid();

		//the last jevents table to populate is the rrule table
		$query = sprintf('INSERT INTO #__jevents_rrule (rr_id, eventid, freq, until, untilraw, count, rinterval, bysecond, byminute,'
						 .' byhour, byday, bymonthday, byyearday, byweekno, bymonth, bysetpos, wkst)'
						 .' VALUES'
						 .' (%s,%s,\'none\',\'0\',\'\',\'1\',\'1\',\'\',\'\',\'\',%s,\'\',\'\',\'\',\'\',\'\',\'\')'
			,$this->db->quote($event->rr_id)
			,$this->db->quote($event->eventid)
			,$this->db->quote($event->rrule['BYDAY'])
		);
		if(($result = $this->executeQueryTest($query)) !== true) {
			$this->db->transactionRollback();
			return JText::sprintf('COM_IMPORTXML_EVENT_ADD_SQLERROR', $event->id, $result);
		}

		//lastly add the custom fields
		$custom_query_parts = array();
		foreach($this->customfields as $key => $value) {
			if(!empty($event->$value)) {
				$custom_query_parts[] = sprintf('(%s,0,%s,%s)'
					,$this->db->quote($event->detail_id)
					,$this->db->quote($value)
					,$this->db->quote($event->$value)
				);
			}
		}
		if(count($custom_query_parts) > 0) {
			$query = 'INSERT INTO #__jev_customfields (evdet_id, user_id, name, value) VALUES '.implode(', ',$custom_query_parts);
			if(($result = $this->executeQueryTest($query)) !== true) {
				$this->db->transactionRollback();
				return JText::sprintf('COM_IMPORTXML_EVENT_ADD_SQLERROR', $event->id, $result);
			}
		}

		//we have successfully added the event. Now commit the transaction and return success
		$this->db->transactionCommit();
		return true;
	}



	/*
	 * Begin the core field transformation and validation functions
	 *
	 * If no transformation function is present for a field, it will not be transformed
	 * If no validation function is present for a field, it will not be validated
	 *
	 */

	/*
	 * id core field validate function
	 */
	private function id_validate($value) {
		return preg_match('/^[a-zA-Z0-9\-_]{1,64}$/',$value);
	}

	/*
	 * category core field transform and validate functions
	 */
	private function Audience_transform($value) {
		return $this->getCatIdFromCatName($value);
	}
	private function Audience_validate($value) {
		return preg_match('/^\d*$/',$value);
	}

	/*
	 * accessLevel core field transform and validate functions
	 */
	private function accessLevel_transform($value) {
		return $this->getAccessIdFromName($value);
	}
	private function accessLevel_validate($value) {
		return preg_match('/^\d*$/',$value);
	}

	/*
	 * allDay core field transform function
	 */
	private function allDay_transform($value) {
		return ($value==1)?'on':'off';
	}

	/*
	 * twelveHour core field validate function
	 */
	private function twelveHour_validate($value) {
		return preg_match('/^[01]$/',$value);
	}

	/*
	 * startDate core field validate function
	 */
	private function startDate_transform($value) {
		return substr($value, 0, 10);
	}
	private function startDate_validate($value) {
		return preg_match('/^20\d\d-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/',$value);
	}


	/*
	 * startTime core field transform and validate functions
	 */
	private function startTime_transform($value) {
		return DATE('H:i', strtotime($value));
	}
	private function startTime_validate($value) {
		return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$value);
	}

	/*
	 * endDate core field validate function
	 */
	private function endDate_transform($value) {
		return substr($value,0,10);
	}
	private function endDate_validate($value) {
		return preg_match('/^20\d\d-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/',$value);
	}


	/*
	 * endTime core field transform and validate functions
	 */
	private function endTime_transform($value) {
		return DATE('H:i', strtotime($value));
	}
	private function endTime_validate($value) {
		return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$value);
	}

	/*
	 * multiday core field validate function
	 */
	private function multiday_validate($value) {
		return preg_match('/^[01]$/',$value);
	}



	/*
	 * Begin the custom field transformation and validation functions
	 *
	 * tranformation functions should be named as fieldkey_transform
	 * and requires 1 argument.
	 * The function should tranform that field as necessary and
	 * return the new value
	 *
	 * validate functions should be named as fieldkey_validate
	 * and requires 1 argument.
	 * The function should return true if the argument is formatted
	 * correctly and false if it is formatted incorrectly.
	 * The valid function should not have to worry about if it is empty
	 *
	 */

	/*
	 * priorityArea custom field transform and validate functions
	 */
	private function priorityArea_transform($value) {
		$search = array('Appraisal and Development','Bilingual/ESL/ELL','Appraisal and Development','Bullying','Curriculum and Assessment','Dual Language','Gifted and Talented','IB',
						'Leadership Development','Literacy','Mentors','New Teacher Development','PreAP/AP','Special Education','Other',', ');
		$replace = array('10000','20000','10000','30000','40000','50000','60000','70000','80000','90000','10001','10002','10003','10004','10005',',');
		return str_replace($search, $replace, $value);
	}
	private function priorityArea_validate($value) {
		return preg_match('/^(([1-9]000[0-5]),?)*$/',$value);
	}

	/*
	 * gradeLevel custom field transform and validate functions
	 */
	private function gradeLevel_transform($value) {
		$search = array('All','PK','K-2','3-5','6','7-8','9-12','Other',', ');
		$replace = array('-1','10','20','30','40','50','60','70',',');
		return str_replace($search, $replace, $value);
	}
	private function gradeLevel_validate($value) {
		return preg_match('/^((-1|[1-7]0),?)*$/',$value);
	}

	/*
	 * department custom field transform and validate functions
	 */
	private function department_transform($value) {
		$search = array('All','Curriculum','Multilingual','PSD','College and Career Preparation','MS Office','Special Pops','Special Education Services','Leadership Development','Other',', ');
		$replace = array('-1','100','200','300','400','500','600','700','800','900',',');
		return str_replace($search, $replace, $value);
	}
	private function department_validate($value) {
		return preg_match('/^((-1|[1-9]00),?)*$/',$value);
	}

	/*
	 * content custom field transform and validate functions
	 */
	private function content_transform($value) {
		$search = array('All','Bilingual','CTE','ESL','Foreign Languages','Health/PE','Language Arts','Math','Science','Social Studies','Other',', ');
		$replace = array('-1','1554','10555','2555','8555','11555','3555','4555','9555','5555','123',',');
		return str_replace($search, $replace, $value);
	}
	private function content_validate($value) {
		return preg_match('/^((-1|123|1554|(1[01]|[234589])555),?)*$/',$value);
	}

	/*
	 * trainingType custom field transform and validate functions
	 */
	private function trainingType_transform($value) {
		$search = array('All','Face-to-face','Online','Blended','College and Career Preparation','MS Office','Special Pops','Special Education','Other',', ');
		$replace = array('-1','1000','2000','3000','4000','5000','6000','7000','8000',',');
		return str_replace($search, $replace, $value);
	}
	private function trainingType_validate($value) {
		return preg_match('/^((-1|[1-8]000),?)*$/',$value);
	}

	/*
	 * registrationType custom field transform and validate functions
	 */
	private function registrationType_transform($value) {
		$search = array('eTRAIN','By Invitation','Campus-based','Other',', ');
		$replace = array('1','2','3','4',',');
		return str_replace($search, $replace, $value);
	}
	private function registrationType_validate($value) {
		return preg_match('/^(([1-4]),?)*$/',$value);
	}

	/*
	 * this is where you would add more customfield transform and validate functions
	 */
}
