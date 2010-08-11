<?php

/**
 * AppliedIRC logging system
 *
 * @author Joshua Gigg <giggsey@appliedirc.com>
 * @license Apache License 2.0
 * @version $Id$
 * @copyright 2009+
 */
date_default_timezone_set('UTC');
mb_internal_encoding("utf-8");
ini_set('default_charset','utf-8');
ini_set('mysqli.reconnect', 'off');

include_once('Net/SmartIRC.php');

// Config

$server = 'irc.appliedirc.com';
$name = 'LogServTest';
$realname = $name;
$dbconfig = array(
'host' => 'localhost'
'user' => 'user',
'pass' => 'mySQLpass',
'db'   => 'dbname',
);

// Please leave blank :)
$channels = array();
$ohhhai = array();

class SQL
{
    public $db;
    private $count = 0;
	/**
	 * @todo This really needs to be changed, and a better way of doing it worked out
	 */
    private $seperator = '\n\n============================42342342343=======================\n\n';
    private $filename = 'waiting.sql';

    function connect()
    {
		global $dbconfig;
        echo "Connecting to database...\n";
        $this->db = new mysqli($dbconfig['host'],$dbconfig['user'],$dbconfig['pass'],$dbconfig['db']);

        if ($this->db->connect_error)
        {
            echo "Connect error... {$this->db->connect_error}\n";
            return false;
        }

        $this->db->query("SET @@sql_mode := ''"); $this->count++;
        $this->db->query("SET NAMES 'utf8'"); $this->count++;
        $this->checkfile();
    }

    function insert($line,$table = 'logs')
    {
        // Check if DB is up

        if ($this->dbactive())
        {
            echo "Database up, running query {$query}\n";
            $query = $this->buildinsert($line,$table);
            $this->db->query($query);
        }
        else
        {
            echo "Database DOWN, saving line \n";
            $this->savetofile($line,$table);
        }
    }

    function query($query)
    {
        $this->count++;
        echo "Running query #" . $this->count . "\n";
        return $this->db->query($query);
    }

    function fetch($query)
    {
        if ($query == FALSE)
        {
            echo "THIS QUERY AINT WORKING\n";
            return FALSE;
        }
        return $query->fetch_array();
    }

    function buildinsert($info,$table)
    {
        //build the query
        $sql = "INSERT INTO ".$table." (";
        for ($i=0; $i<count($info); $i++) {
                //we need to get the key in the info array, which represents the column in $table
                $sql .= key($info);
                //echo commas after each key except the last, then echo a closing parenthesis
                if ($i < (count($info)-1)) {
                        $sql .= ", ";
                } else $sql .= ") ";
                //advance the array pointer to point to the next key
                next($info);
        }
        //now lets reuse $info to get the values which represent the insert field values
        reset($info);
        $sql .= "VALUES (";
        for ($j=0; $j<count($info); $j++) {
                $str = mb_convert_encoding(current($info), 'UTF-8', 'auto');
                $sql .= "CONVERT( _utf8 '".utf8_encode($this->db->real_escape_string($str))."' USING latin1 )";
                if ($j < (count($info)-1)) {
                        $sql .= ", ";
                } else $sql .= "); ";
                next($info);
        }
       return $sql;
    }

	/**
	 * @todo This can be changed to serialize the whole thing?
	 */
    function savetofile($line,$table)
    {
        $line['SaveToTable'] = $table;
        $newline = serialize($line);
        $tofile = $newline . $this->seperator;

        $fp = fopen($this->filename, 'a+');
        fwrite($fp,$tofile);
        fclose($fp);
        echo "Saved line to file\n";
    }
    
    function checkfile()
    {
        echo "Checking file\n";
        $adres = unserialize(file_get_contents($filename));

        $file = file_get_contents($this->filename);

        $lines = explode($this->seperator,$file);

        // Wipe the file
        $fp = fopen($this->filename, 'w');
        fwrite($fp,'');
        fclose($fp);

        
        foreach ($lines as $serline)
        {
            $line = unserialize($serline);
            $table = $line['SaveToTable'];
            if ($line['SaveToTable'] == '' || !isset($line['SaveToTable']))
                continue;
            unset($line['SaveToTable']);
            echo "Found a line to parse - " . print_r($line,1) . "\n";
            $this->insert($line,$table);
        }
    }

    function dbactive()
    {
        if ($this->db->ping())
        {
            // We have a connection
            return TRUE;
        }
        else
        {
            // Attempt to reconnect
            echo "Reconnecting to DB. Error: " . $this->db->error . "\n";
            $this->db->close();
            $this->connect();
            if ($this->db->ping())
            {
                // Worked this time
                return TRUE;
            }
            else
            {
                echo "Database still not working. Error: " . $this->db->error . "\n";
                return FALSE;
            }
        }
    }
}


class mybot
{
	public $chans;
	public $c;
        public $previousdate;

	    function display(&$irc, &$data)
	    {
	    	echo '<pre>';
                    print_r($data);
                    print_r($irc);
		echo '</pre>';
	    }

		function channel(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'msg',
				'message'		=>		$data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
		}

		function joinchan(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'join',
				'message'		=>		$data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
			$this->updatearray($irc);
		}

		function partchan(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'part',
				'message'		=>		$data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
			$this->updatearray($irc);
		}

		function kickchan(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'kick',
				'message'		=>		$data->message,
				'time'			=>		time(),
				'target'		=>		$data->rawmessageex[3],
			);
			$sql->insert($insert,'logs');
			$this->updatearray($irc);
		}

		function modechan(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$message = str_replace($data->rawmessageex[0] . ' ' . $data->rawmessageex[1] . ' ' . $data->rawmessageex[2] . ' ','',$data->rawmessage);
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'mode',
				'message'		=>		$message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
		}

		function topicchan(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'topic',
				'message'		=>		$data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
		}

		function noticechan(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = $data->rawmessageex[2];
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'notice',
				'message'		=>		$data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
		}


		function action(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$data->message = substr($data->message,7);
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'action',
				'message'		=>		$data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
		}

		function quitchan(&$irc,&$data)
		{
			global $sql,$channels;
			foreach ($this->c as $chan => $key)
			{
				if (in_array($data->nick,$key))
				{
					$data->channel = $chan;
				}
				else
					continue;
				if (!in_array($data->channel,$channels))
					return false;
				$insert = array(
						'channel' 		=> 		$data->channel,
						'nick'			=>		$data->nick,
						'ident'			=>		$data->ident,
						'host'			=>		$data->host,
						'type'			=>		'quit',
						'message'		=>		$data->message,
						'time'			=>		time(),
				);
				$sql->insert($insert,'logs');
			}
			$this->updatearray($irc);
		}


		function nick(&$irc,&$data)
		{
			global $sql,$channels;
			foreach ($this->c as $chan => $key)
			{
				if (in_array($data->nick,$key))
				{
					$data->channel = $chan;
				}
				else
					continue;
				if (!in_array($data->channel,$channels))
					return false;
				$insert = array(
						'channel' 		=> 		$data->channel,
						'nick'			=>		$data->nick,
						'ident'			=>		$data->ident,
						'host'			=>		$data->host,
						'type'			=>		'nick',
						'message'		=>		$data->rawmessageex[2],
						'time'			=>		time(),
				);
				$sql->insert($insert,'logs');
			}
			$this->updatearray($irc);
		}


		function ctcp(&$irc,&$data)
		{
			global $sql,$channels;
			$data->channel = substr($data->channel, 1);
			if (!in_array($data->channel,$channels))
				return false;
			$insert = array(
				'channel' 		=> 		$data->channel,
				'nick'			=>		$data->nick,
				'ident'			=>		$data->ident,
				'host'			=>		$data->host,
				'type'			=>		'ctcp',
				'message'		=>		'CTCP ' . $data->message,
				'time'			=>		time(),
			);
			$sql->insert($insert,'logs');
		}

	// ========================================
		function checkchannels(&$irc)
		{
			global $sql,$channels;
			$dbchans = array();
			$c = $sql->query("SELECT * FROM `channels`;");
                        // Don't part the channels when we lose SQL
                        if ($c == FALSE) return;
                        echo "\t\t\tHere\n";
			while($row = $sql->fetch($c))
			{
				if (!in_array($row['channel'],$channels))
				{
					// We need to join this channel.
					$irc->join(array('#' . $row['channel']));
					$this->updatearray($irc);
					$this->adddate($irc,$row['channel']);
					$channels[] = $row['channel'];
				}
				$dbchans[] = $row['channel'];
			}
			foreach ($channels as $chan)
			{
				if (!in_array($chan,$dbchans))
				{
					// Part
					$irc->part(array('#' . $chan),'Logging disabled for #' . $chan);
					$this->updatearray($irc);
					$this->adddate($irc,$row['channel']);
					$t = array_keys($channels,$chan);
					unset($channels[$t[0]]);
				}
			}
		}

		function checkcommands(&$irc)
		{
			global $sql;
			$c = $sql->query("SELECT * FROM `commands`;");
			while($r = $sql->fetch($c))
			{
				$irc->send($r['command']);
				$sql->query("DELETE FROM `commands` WHERE `id` = '{$r['id']}' LIMIT 1;");
			}
		}

		function updatearray(&$irc)
		{
			global $channels;
			$this->c = '';
			$this->c = array();
			foreach ($irc->channel as $chan => $object)
			{
				$key = $object->name;
				$key = str_replace('#','',$key);
					if (in_array($key,$channels))
					{
						foreach ($object->users as $k => $users)
						{
								$this->c[$key][] = $users->nick;
						}
					}
			}
		}

		function adddate(&$irc,$chan = NULL)
		{
			global $sql,$channels;
			$now = time();
			if ($chan == NULL)
			{
				// Loop through all
				foreach ($channels as $chan)
				{
					$insert = array(
						'channel' 	=> $chan,
						'date'		=> $now,
							);
					$sql->insert($insert,'dates');
				}
			}
			else
			{
				$insert = array(
						'channel' 	=> $chan,
						'date'		=> $now,
								);
				$sql->insert($insert,'dates');
			}
		}

                function checkdate(&$irc)
                {
                    $now = date('d');
                    if ($now != $this->previousdate)
                    {
                        $this->previousdate = $now;
                        $this->adddate($irc);
                    }
                }

	}


$sql = new SQL();
$sql->connect();

$sql->checkfile();
// Log file SQL excuted and wiped clean

$bot = &new mybot();
$irc = &new Net_SmartIRC();
$irc->setDebug(SMARTIRC_DEBUG_NONE);
$irc->setUseSockets(TRUE);
$irc->setAutoReconnect(TRUE);
$irc->setChannelSyncing(TRUE);
$irc->setUserSyncing(TRUE);
$irc->setCtcpVersion('AppliedIRC Logs Bot');
$checkchans = $irc->registerTimehandler(10000, $bot, 'checkchannels');
$checkcommands = $irc->registerTimehandler(10000, $bot, 'checkcommands');
$checkdate = $irc->registerTimehandler(60000, $bot, 'checkdate');


// Various channel methods
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^',$bot,'channel');
$irc->registerActionhandler(SMARTIRC_TYPE_JOIN,'^',$bot,'joinchan');
$irc->registerActionhandler(SMARTIRC_TYPE_PART,'^',$bot,'partchan');
$irc->registerActionhandler(SMARTIRC_TYPE_KICK,'^',$bot,'kickchan');
$irc->registerActionhandler(SMARTIRC_TYPE_MODECHANGE,'^',$bot,'modechan');
$irc->registerActionhandler(SMARTIRC_TYPE_TOPICCHANGE,'^',$bot,'topicchan');
$irc->registerActionhandler(SMARTIRC_TYPE_NOTICE,'^',$bot,'noticechan');
$irc->registerActionhandler(SMARTIRC_TYPE_ACTION, '^', $bot, 'action');
$irc->registerActionhandler(SMARTIRC_TYPE_QUIT, '^', $bot, 'quitchan');
$irc->registerActionhandler(SMARTIRC_TYPE_NICKCHANGE, '^', $bot, 'nick');
$irc->registerActionhandler(SMARTIRC_TYPE_CTCP_REQUEST|SMARTIRC_TYPE_CTCP, '^', $bot, 'ctcp');


$irc->connect($server, 6667);
$irc->login($name, $realname, 0, $name);
$bot->checkchannels($irc);
$bot->adddate($irc);
$bot->previousdate = date('d');
$irc->listen();
$irc->disconnect();

?>