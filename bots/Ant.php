<?php

require_once 'Mission.php';

/**
 * 
 */
class Ant {
	
	static $instance = 1; 
	
	protected $id;
	protected $pos;
	protected $ppos;
	public $name;
	protected $owner;
	protected $logger;
	protected $debug;
	public $mission;

	const DEBUG_LEVEL_DEFAULT = AntLogger::LOG_ALL;

	/**
	 * 
	 * @param array $args
	 */
	function __construct($args){
		
		$this->id = (isset($args['id'])) ? $args['id'] : Ant::$instance++;
		
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . $this->id;
	
		$this->owner = (isset($args['owner'])) ?  $args['owner'] : -1;
		
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;
		
		$this->pos = array(
			$args['row'],
			$args['col']
		);

		$this->ppos = array(
			$args['row'],
			$args['col']
		);

		$this->logger = new AntLogger(array(
			'logLevel' => $this->debug,
//			'output' => STDERR
		));

		$this->mission = (isset($args['mission'])) ? $args['id'] : new MissionGoNESW(array(
			'debug' => $this->debug
		));
		
		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_ANT);
	}
	
	/**
	 * 
	 */
    public function __set($name, $value) {
		switch ($name) {
			case 'row':
				$this->ppos = $this->pos;
				$this->pos[0] = $value;
				break;
			case 'col':
				$this->ppos = $this->pos;
				$this->pos[1] = $value;
				break;
			case 'pos':
				$this->ppos = $this->pos;
				$this->pos =  $value;
				break;
			case 'prow':
				$this->ppos[0] = $value;
				break;
			case 'pcol':
				$this->ppos[1] = $value;
				break;
			default:
				$this->$name = $value;
		}
		
		return $value;
    }

	/**
	 * __get
	 * 
	 * @param string $name
	 * @return mixed
	 */
    public function __get($name) {
		switch ($name) {
			case 'row':
				$retval = $this->pos[0];
				break;
			case 'col':
				$retval = $this->pos[1];
				break;
			case 'prow':
				$retval = $this->ppos[0];
				break;
			case 'pcol':
				$retval = $this->ppos[1];
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
	 * __toString
	 * 
	 * @return string
	 */
    public function __toString (){
		$str =  $this->name . ' ('.  $this->id . ")";
		$str .= " Pos: " . $this->pos[0] . ', ' . $this->pos[1];
		$str .= " Mission:" . $this->mission->name . "(" . $this->mission->getState()->name . ")";
		return $str;
    } 	

	/**
	 * 
	 */
	function __destruct () {
		if ($this->debug) {
			$this->logger->write("Destroying " . $this->name);
		}
	}
	
//    /**
//     * doTurn
//     */
//    public function doTurn ($data) {
//		$result = $this->mission->takeAction($this, $data); // ants = game state data
//		return $result;
//	}
	
	
} // end Ant

