<?php
define('DS', DIRECTORY_SEPARATOR);

// debug level
// 0 - off

// 1 - GENERAL
define('LOG_BASE', 1);
define('LOG_ALL', 255);
define('LOG_NONE', 0);
define('LOG_DEFAULT', 255);
define('LOG_FILENAME_PREFIX', 'ant');
define('LOG_FILENAME_EXTENSION', 'log');

/**
 * 
 */
class AntLogger {
	protected $resource;

	/**
	 * __construct
	 * 
	 * @param array $args
	 */
	function __construct($args = array()){
		
		if (isset($args['output'])) {
			if (is_string($args['output'])) {
				$this->resource = fopen($args['output'], 'a+');
			} else if (is_resource($args['output'])) {
				$this->resource = $args['output'];
			} else {
			  throw new Exception ('Invalid logger output target.');	
			}
		} else {
			$this->resource = fopen(LOG_FILENAME_PREFIX . '.' . LOG_FILENAME_EXTENSION, 'a+');
		}
	}
	
	/**
	 * write
	 * 
	 * @param string $msg
	 */
	public function write($msg) {
		$msg .= "\n";
		fwrite($this->resource, $msg, strlen($msg)); 
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
