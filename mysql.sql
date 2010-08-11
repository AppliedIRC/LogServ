-- --------------------------------------------------------

--
-- Table structure for table `channels`
--

CREATE TABLE `channels` (
  `channel` varchar(32) NOT NULL,
  PRIMARY KEY  (`channel`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `commands`
--

CREATE TABLE `commands` (
  `id` bigint(10) NOT NULL auto_increment,
  `command` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `dates`
--

CREATE TABLE `dates` (
  `id` bigint(20) NOT NULL auto_increment,
  `channel` varchar(32) NOT NULL,
  `date` varchar(20) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL auto_increment,
  `channel` varchar(32) character set latin1 NOT NULL,
  `nick` varchar(32) character set latin1 NOT NULL,
  `ident` varchar(32) character set latin1 NOT NULL,
  `host` varchar(100) character set latin1 NOT NULL,
  `type` varchar(30) character set latin1 NOT NULL,
  `message` text character set latin1 NOT NULL,
  `time` int(10) NOT NULL,
  `target` varchar(32) character set latin1 NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `time` (`time`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
