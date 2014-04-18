<?php
// sudo pear install --alldeps pear.php.net/Math_Vector-0.7.0
require_once "Math/Vector.php";
require_once "Math/Vector2.php";
require_once "Math/Vector3.php";
require_once "Math/VectorOp.php";

require_once "AntLogger.php";
require_once "Ant.php";

define('MY_ANT', 0);
define('ANTS', 0);
define('DEAD', -1);
define('LAND', -2);
define('FOOD', -3);
define('WATER', -4);
define('UNSEEN', -5);

/**
 * Ants
 */
class Ants {
    public $turns = 0;
	public $turn = 1;
    public $rows = 0;
    public $cols = 0;
    public $loadtime = 0;
    public $turntime = 0;
    public $viewradius2 = 0;
    public $attackradius2 = 0;
    public $spawnradius2 = 0;
    public $map;
    public $myAnts = array();
	public $nMyAnts = 0;
    public $enemyAnts = array();
    public $myHills = array();
    public $enemyHills = array();
    public $deadAnts = array();
    public $food = array();

    public $AIM = array(
        'n' => array(-1, 0),
        'e' => array(0, 1),
        's' => array(1, 0),
        'w' => array(0, -1) );
    public $RIGHT = array (
        'n' => 'e',
        'e' => 's',
        's' => 'w',
        'w' => 'n');
    public $LEFT = array (
        'n' => 'w',
        'e' => 'n',
        's' => 'e',
        'w' => 's');
    public $BEHIND = array (
        'n' => 's',
        's' => 'n',
        'e' => 'w',
        'w' => 'e'
        );


	protected $logger = null;
	
	/**
	 * 
	 * @param array $args
	 */
	function __construct($args = array()) {
		$this->logger = new AntLogger();
	}
	
    public function issueOrder($aRow, $aCol, $direction) {
        printf("o %s %s %s\n", $aRow, $aCol, $direction);
        flush();
    }

    public function finishTurn() {
		$this->logger->write("Finished turn " . $this->turn);	
        echo("go\n");
		$this->turn++;
        flush();
    }
    
    public function setup($data) {
		
		$this->logger->write("Starting setup processing start for turn " . $this->turn);		
		
        foreach ($data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);
                $key = $tokens[0];
                if (property_exists($this, $key)) {
                    $this->{$key} = (int)$tokens[1];
                }
            }
        }
        for ($row = 0; $row < $this->rows; $row++) {
            for ($col = 0; $col < $this->cols; $col++) {
                $this->map[$row][$col] = LAND;
            }
        }
    }

    /** not tested */

    /**
     * update
     * 
     */
    public function update($data) {
		
		$this->logger->write("Starting update processing start for turn " . $this->turn);
				
        // clear ant and food data
        foreach ($this->myAnts as $ant) {
            list($row,$col) = $ant->pos;
            $this->map[$row][$col] = LAND;
        }
        //$this->myAnts = array();

        foreach ($this->enemyAnts as $ant) {
            list($row,$col) = $ant->pos;
            $this->map[$row][$col] = LAND;
        }
        $this->enemyAnts = array();

        foreach ($this->deadAnts as $ant) {
            list($row,$col) = $ant->pos;
            $this->map[$row][$col] = LAND;
        }
        $this->deadAnts = array();

        foreach ($this->food as $ant) {
            list($row,$col) = $ant->pos;
            $this->map[$row][$col] = LAND;
        }

        $this->food = array();
        $this->myHills = array();
        $this->enemyHills = array();

		// turn input processing
		//
        // update map and create new ant and food lists
		//
		// store long term?
        foreach ($data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);

                if (count($tokens) >= 3) {
                    $row = (int)$tokens[1];
                    $col = (int)$tokens[2];
                    if ($tokens[0] == 'a') {				// a = live ant, format: w row col owner
                        $owner = (int)$tokens[3];
                        $this->map[$row][$col] = $owner;
                        if($owner === 0) {
							if ($this->turn === 1) {
								$ant = new Ant(array('row' => $row, 'col' => $col, 'owner' => $owner));
								$this->addAnt($ant);
							} else {
								$ant = $this->lookupAnt($row, $col, $owner);
								if ($ant) {
									$ant->pos = array($row, $col);
								} else {
									$this->logger->write('Lost ant at $row, $col');
								}
							}
                        } else {
                            $this->enemyAnts[]= new Ant(array('row' => $row, 'col' => $col, 'owner' => $owner));
                        }
                    } elseif ($tokens[0] == 'f') {			// f = food, format: w row col
                        $this->map[$row][$col] = FOOD;
                        $this->food []= array($row, $col);
                    } elseif ($tokens[0] == 'w') {			// w = water, format: w row col
                        $this->map[$row][$col] = WATER;
                    } elseif ($tokens[0] == 'd') {			// dead ant, format: w row col owner
                        if ($this->map[$row][$col] === LAND) {
                            $this->map[$row][$col] = DEAD;
                        }
                        $this->deadAnts []= array($row,$col);
                    } elseif ($tokens[0] == 'h') {			// h = hill, format: w row col owner
                        $owner = (int)$tokens[3];
                        if ($owner === 0) {
                            $this->myHills []= array($row,$col);
                        } else {
                            $this->enemyHills []= array($row,$col);
                        }
                    }
                }
            }
        }
    }


    public function passable($row, $col) {
        return $this->map[$row][$col] > WATER;
    }

    public function unoccupied($row, $col) {
        return in_array($this->map[$row][$col], array(LAND, DEAD));
    }

    /**
     *
     */
    public function destination($row, $col, $direction) {
        list($dRow, $dCol) = $this->AIM[$direction];
        $nRow = ($row + $dRow) % $this->rows;
        $nCol = ($col + $dCol) % $this->cols;
        if ($nRow < 0) { 
			$nRow += $this->rows;
		}
        if ($nCol < 0) {
			$nCol += $this->cols;
		}
        return array( $nRow, $nCol );
    }

	/**
	 * Distance between two cells
	 *
	 * @param integer $row1
	 * @param integer $col1
	 * @param integer $row2
	 * @param integer $col2
	 * @return integer
	 */
    public function distance($row1, $col1, $row2, $col2) {
        $dRow = abs($row1 - $row2);
        $dCol = abs($col1 - $col2);

        $dRow = min($dRow, $this->rows - $dRow);
        $dCol = min($dCol, $this->cols - $dCol);

        return sqrt($dRow * $dRow + $dCol * $dCol);
    }

    /**
     * calc the direction(n,s,e,w) based on current square and next square
     */
    public function direction($row1, $col1, $row2, $col2) {
        $d = array();
        $row1 = $row1 % $this->rows;
        $row2 = $row2 % $this->rows;
        $col1 = $col1 % $this->cols;
        $col2 = $col2 % $this->cols;

        if ($row1 < $row2) {
            if ($row2 - $row1 >= $this->rows/2) {
                $d []= 'n';
            }
            if ($row2 - $row1 <= $this->rows/2) {
                $d []= 's';
            }
        } elseif ($row2 < $row1) {
            if ($row1 - $row2 >= $this->rows/2) {
                $d []= 's';
            }
            if ($row1 - $row2 <= $this->rows/2) {
                $d []= 'n';
            }
        }
        if ($col1 < $col2) {
            if ($col2 - $col1 >= $this->cols/2) {
                $d []= 'w';
            }
            if ($col2 - $col1 <= $this->cols/2) {
                $d []= 'e';
            }
        } elseif ($col2 < $col1) {
            if ($col1 - $col2 >= $this->cols/2) {
                $d []= 'e';
            }
            if ($col1 - $col2 <= $this->cols/2) {
                $d []= 'w';
            }
        }
        return $d;

    }

	/**
	 * Add an Ant
	 * 
	 * @param object $ant
	 * @return boolean Return an Ant object on success, false otherwise;
	 */
	public function addAnt($ant) {
		$this->myAnts[] = $ant;
		$this->nMyAnts++;
	}
	
	/**
	 * Lookup an Ant
	 * 
	 * @param integer|array $arg1
	 * @param integer $arg2
	 * @return object|null Return an Ant object on success, false otherwise;
	 */
	public function lookupAnt($arg1, $arg2 = null) {
		if (is_array($arg1)) {
			$row = (isset($arg1['row']) ? $arg1['row'] : $arg1[0]);
			$col = (isset($arg1['col']) ? $arg1['col'] : $arg1[0]);
		} else {
			$row = $arg1;
			$col = $arg2;
		}

$this->logger->write('cnt : ' .  count($this->myAnts));
$this->logger->write('n : ' .  $this->nMyAnts);
//$this->logger->write(var_export($this->myAnts, true));
		
		for ($i = 0; $i < $this->nMyAnts; $i++) {
			$ant = $this->myAnts[$i];
			if ($ant->row === $row && $ant->col == $col) {
				return $ant;
			}
		}
		
		return false;
	}
	
	/**
	 * Start the strdin loop
	 *
	 * @param Ant $bot
	 */
    public static function dumpMap() {
		
	}
	
	/**
	 * Start the strdin loop
	 *
	 * @param Ant $bot
	 */
    public static function run($bot){
		$ants = new Ants();
		$map_data = array();
		while(true) {
			$current_line = fgets(STDIN,1024);
			$current_line = trim($current_line);
			if ($current_line === 'ready') {
				$ants->setup($map_data);
				$ants->finishTurn();
				$map_data = array();
			} elseif ($current_line === 'go') {
				$ants->update($map_data);
				$bot->doTurn($ants);
				$ants->finishTurn();
				$map_data = array();
			} else {
				$map_data []= $current_line;
			}
		}
	}
}
