<?php
// sudo pear install --alldeps pear.php.net/Math_Vector-0.7.0
require_once "Math/Vector.php";
require_once "Math/Vector2.php";
require_once "Math/Vector3.php";
require_once "Math/VectorOp.php";

define('MY_ANT', 0);
define('ANTS', 0);
define('DEAD', -1);
define('LAND', -2);
define('FOOD', -3);
define('WATER', -4);
define('UNSEEN', -5);


class Ant {
	
	protected $id;
	
	protected $pos;
	
	protected $name;	

	static $instance = 1; 
	
	/**
	 * 
	 * @param array $args
	 */
	function __construct($args){
		$this->id = Ant::$instance++;
		
		$this->pos = array(
			$args['row'],
			$args['col']
		);
		
		$this->name = (isset($args['name'])) ?  $args['name'] : 'Ant #' . $this->id;
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
		$str .= "Pos: " . $this->pos[0] . ', ' . $this->pos[1] . "\n";;
		return $str;
    } 	

} // end Ant

/**
 * Ants
 */
class Ants {
    public $turns = 0;
    public $rows = 0;
    public $cols = 0;
    public $loadtime = 0;
    public $turntime = 0;
    public $viewradius2 = 0;
    public $attackradius2 = 0;
    public $spawnradius2 = 0;
    public $map;
    public $myAnts = array();
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


    public function issueOrder($aRow, $aCol, $direction) {
        printf("o %s %s %s\n", $aRow, $aCol, $direction);
        flush();
    }

    public function finishTurn() {
        echo("go\n");
        flush();
    }
    
    public function setup($data) {
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
        // clear ant and food data
        foreach ($this->myAnts as $ant) {
            list($row,$col) = $ant->pos;
            $this->map[$row][$col] = LAND;
        }
        $this->myAnts = array();

        foreach ($this->enemyAnts as $ant) {
            list($row,$col) = $ant->pos;
            $this->map[$row][$col] = LAND;
        }
        $this->enemyAnts = array();

        foreach ($this->deadAnts as $ant ) {
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

        # update map and create new ant and food lists
        foreach ( $data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);

                if (count($tokens) >= 3) {
                    $row = (int)$tokens[1];
                    $col = (int)$tokens[2];
                    if ($tokens[0] == 'a') {
                        $owner = (int)$tokens[3];
                        $this->map[$row][$col] = $owner;
                        if( $owner === 0) {
                            $this->myAnts[]= new Ant(array('row' => $row, 'col' => $col));
                        } else {
                            $this->enemyAnts[]= new Ant(array('row' => $row, 'col' => $col));
                        }
                    } elseif ($tokens[0] == 'f') {
                        $this->map[$row][$col] = FOOD;
                        $this->food []= array($row, $col);
                    } elseif ($tokens[0] == 'w') {
                        $this->map[$row][$col] = WATER;
                    } elseif ($tokens[0] == 'd') {
                        if ($this->map[$row][$col] === LAND) {
                            $this->map[$row][$col] = DEAD;
                        }
                        $this->deadAnts []= array($row,$col);
                    } elseif ($tokens[0] == 'h') {
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


    public function passable($row, $col)
    {
        return $this->map[$row][$col] > WATER;
    }

    public function unoccupied($row, $col) {
        return in_array($this->map[$row][$col], array(LAND, DEAD));
    }

    /**
     *
     */
    public function destination($row, $col, $direction)
    {
        list($dRow, $dCol) = $this->AIM[$direction];
        $nRow = ($row + $dRow) % $this->rows;
        $nCol = ($col +$dCol) % $this->cols;
        if ($nRow < 0) $nRow += $this->rows;
        if ($nCol < 0) $nCol += $this->cols;
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
