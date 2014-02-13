CREATE TABLE `#__importxml_upload` (
  `upload_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `filename` varchar(255) NOT NULL,
  PRIMARY KEY (`upload_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

CREATE TABLE `#__importxml_details` (
  `details_id` int(11) NOT NULL AUTO_INCREMENT,
  `upload_id` int(11),
  `import_id` varchar(64),
  `jevent_id` varchar(128),
  `status` varchar(10),
  `details` varchar(255),
  PRIMARY KEY (`details_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
