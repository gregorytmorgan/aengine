<?php
define('DS', DIRECTORY_SEPARATOR);

/**
 * AntLogger
 *
 */
class AntLogger {
	protected $resource;

	/* console output font colors */
	const NO_COLOR = "\033[0m";
	const YELLOW = "\033[0;33m";
	const RED = "\033[0;31m";
	const GREEN = "\033[0;32m";

	const LOG_NONE = 0;
	const LOG_GAME_FLOW = 1;
	const LOG_MAPDUMP = 2;
	const LOG_GAMEDUMP = 4;
	const LOG_MISSION = 8;
	const LOG_ANT = 16;	
	const LOG_INPUT = 32;
	const LOG_OUTPUT = 64;
	const LOG_INFO = 128;
	const LOG_WARN = 256;
	const LOG_ERROR = 512;

	const LOG_ALL = 4095;

	const LOG_FILENAME_PREFIX = 'ant';
	const LOG_FILENAME_EXTENSION = 'log';

	/**
	 * __construct
	 * 
	 * @param array $args
	 */
	function __construct($args = array()){

		$this->logLevel = (isset($args['logLevel'])) ? $args['logLevel'] : self::LOG_ALL;

		if (isset($args['output'])) {
			if (is_string($args['output'])) {
				$this->resource = fopen($args['output'], 'a+');
			} else if (is_resource($args['output'])) {
				$this->resource = $args['output'];
			} else {
			  throw new Exception ('Invalid logger output target.');	
			}
		} else {
			$this->resource = fopen(self::LOG_FILENAME_PREFIX . '.' . self::LOG_FILENAME_EXTENSION, 'a+');
		}
	}
	
	/**
	 * write
	 * 
	 * @param string $msg
	 * @param string $grp Bitmask of AntLogger log group constants
	 * @param array $opts <pre>
	 *		addEndline: boolean, default = true
	 * <pre>
	 */
	public function write($msg, $grp = self::LOG_ALL, $opt = array('noEndline' => false)) {
		if (!$opt['noEndline']) {
			$msg .= "\n";
		}
		if ($this->logLevel & $grp) {

			if ($grp === self::LOG_ERROR) {
				$msg = self::RED . $msg . self::NO_COLOR;
			} else if ($grp === self::LOG_WARN) {
				$msg = self::YELLOW . $msg . self::NO_COLOR;
			} else {
				$msg = str_replace('<RED>', self::RED, $msg);
				$msg = str_replace('<YELLOW>', self::YELLOW, $msg);
				$msg = str_replace('<GREEN>', self::GREEN, $msg);
				$msg = str_replace(array('</RED>', '</YELLOW>', '</GREEN>'), self::NO_COLOR, $msg);
			}

			fwrite($this->resource, $msg, strlen($msg));
		}
	}

	/**
	 * __destruct
	 * 
	 * @param array $args
	 */
	function __destruct() {
		if ($this->resource) {
			fclose($this->resource);
		}
	}	
}
