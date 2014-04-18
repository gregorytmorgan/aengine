<?php



define('DEBUG_DEFAULT', 255);


/**
 * 
 */
class Ant {
	
	static $instance = 1; 
	
	protected $id;
	protected $pos;
	protected $name;
	protected $owner;
	protected $logger;

	protected $debug = LOG_DEFAULT;
	
	/**
	 * 
	 * @param array $args
	 */
	function __construct($args){
		
		$this->id = (isset($args['id'])) ? $args['id'] : Ant::$instance++;
		
		$this->name = (isset($args['name'])) ?  $args['name'] : 'Ant #' . $this->id;
	
		$this->owner = (isset($args['owner'])) ?  $args['owner'] : -1;
		
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : DEBUG_DEFAULT;
		
		$this->pos = array(
			$args['row'],
			$args['col']
		);

		$this->logger = new AntLogger(array(
//			'output' => STDERR
		));
		
		if ($this->debug & LOG_ALL) {
			$msg = "Bot " . $this->name . " (" . $this->id . ") Initialized";
			$this->logger->write($msg);
		}
	}
	
	/**
	 * 
	 */
    public function __set($name, $value) {
		switch ($name) {
			case 'row':
				if (!is_numeric($value)) {
					throw Exception('Non numeric value for Ant.row');
				}
				$this->pos[0] = $value;
				break;
			case 'col':
				if (!is_numeric($value)) {
					throw Exception('Non numeric value for Ant.row');
				}				
				$this->pos[1] = $value;
				break;
			default:
				$this->$name = $value;
		}
		 
		return $value;
    }

	/**
	 * 
	 */	
    public function __get($name) {
		switch ($name) {
			case 'row':
				$retval = $this->pos[0];
				break;
			case 'col':
				$retval = $this->pos[1];
				break;
			default:
				if (!isset($this->$name)) {
					$trace = debug_backtrace();
					trigger_error("Undefined property via __get(): $name in " . $trace[0]['file'] . " on line " . $trace[0]['line'], E_USER_NOTICE);
					return null;
				}	
				$retval =  $this->$name;
		}

		 return $retval;
    }

	/**
	 * 
	 * @return string
	 */
    public function __toString(){
		$str =  $this->name . ' ('.  $this->id . "), ";
		$str .= "Pos: " . $this->pos[0] . ', ' . $this->pos[1] . "\n";
		return $str;
    } 	
	
	function __destruct() {
		if ($this->debug) {
			$this->logger->write("Destroying " . $this->name);
		}
	}		
	

} // end Ant

