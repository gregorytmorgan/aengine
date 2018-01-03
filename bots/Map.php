<?php

require_once "AntLogger.php";

/**
 * Description of Map
 *
 * @author gmorgan
 */
class Map {

	const DEBUG_LEVEL_DEFAULT = AntLogger::LOG_ALL;

	static $instance = 1;
	
	protected $logger;

	protected $debug;

	public $grid = array();

	public $rows;

	public $columns;

    public $viewradius2 = 0;
	public $viewradius = 0;

	/**
	 *
	 * @param array $args
	 */
	function __construct ($args = array()) {
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . Map::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . Map::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;
		$this->viewradius2 = (isset($args['viewradius2'])) ? $args['viewradius2'] : 0;
		$this->defaultChar = (isset($args['defaultChar'])) ? $args['defaultChar'] : '?';
		
		if (isset($args['map'])) {
			$this->grid = $args['map'];
			$this->rows = count($this->grid);
			$this->columns = count($this->grid[0]);
		} else if (isset($args['rows']) && isset($args['columns'])) {
			$this->grid = array();
			$this->rows = (int)$args['rows'];
			$this->columns = (int)$args['columns'];		
			for ($i = 0; $i < $this->rows; $i++) {
				array_push($this->grid, array_fill(0, $this->columns, $this->defaultChar));
			}
		} else {
			$this->grid = array(array($this->defaultChar));
		}
		
		$this->logger = new AntLogger(array(
			'logLevel' => $this->debug,
//			'output' => STDERR
		));

		if ($this->viewradius2) {
			$this->viewradius = round(sqrt($this->viewradius2));
		}

		Map::$instance++;

		$this->logger->write(sprintf("%s Initialized (%dx%d)", $this->name, $this->rows, $this->columns), AntLogger::LOG_MAP);
	}

	/**
	 *
	 * @return string
	 */
    public function __toString () {
		$str = '';
		for ($i = 0, $ilen = count($this->grid); $i < $ilen; $i++) {
			for ($j = 0, $jlen = count($this->grid[$i]); $j < $jlen; $j++) {
				$str .= $this->grid[$i][$j];
			}
			$str .= "\n";
		}
		return $str;
    }

	/**
	 * get
	 * 
	 * @param array $pt Point
	 * @return string
	 */
    public function get ($arg1, $arg2 = null) {
		if (is_array($arg1)) {
			$wPt = $this->gridWrap($arg1);
if ($wPt[0] < 0 || $wPt[0] >= $this->rows || $wPt[1] < 0 || $wPt[1] >= $this->columns) {
	$this->logger->write(sprintf("Map.get(%d,%d) -> (%d,%d) 1", $arg1[0], $arg1[1], $wPt[0], $wPt[1]), AntLogger::LOG_ERROR);
}			
		} else {
			$wPt = $this->gridWrap(array($arg1, $arg2));
if ($wPt[0] < 0 || $wPt[0] >= $this->rows || $wPt[1] < 0 || $wPt[1] >= $this->columns) {
	$this->logger->write(sprintf("Map.get(%d,%d) -> (%d,%d) 2", $arg1, $arg2, $wPt[0], $wPt[1]), AntLogger::LOG_ERROR);
}
		}

		return $this->grid[$wPt[0]][$wPt[1]];
	}

	/**
	 * 
	 * @param type $pt
	 * @param type $value
	 */
    public function set ($arg1, $arg2, $arg3 = null) {
		if (is_array($arg1)) {
			$wPt = $this->gridWrap($arg1);
			$value = $arg2;
//$this->logger->write(sprintf("Map.set(%d,%d) -> (%d,%d) = %d", $arg1[0], $arg1[1], $wPt[0], $wPt[1], $value));
		} else {
			$wPt = $this->gridWrap(array($arg1, $arg2));
			$value = $arg3;
//$this->logger->write(sprintf("Map.set(%d,%d) -> (%d,%d) = %d", $arg1, $arg2, $wPt[0], $wPt[1], $value));
		}		
		
		$this->grid[$wPt[0]][$wPt[1]] = $value;
	}

	/**
	 * Convert raw point coordinates to wrapped map coordinates.
	 *
	 * @param array $pt Raw point coordinates
	 * @return array Returns a point array.
	 */
	public function gridWrap($pt) {
		$row = $pt[0];
		$col = $pt[1];
		$r = $row % $this->rows;
		$c = $col % $this->columns;

		$retval = array(
			($r < 0) ? $r + $this->rows : $r,
			($c < 0) ? $c + $this->columns : $c,
		);

//$this->logger->write(sprintf("Map.gridWrap(%d,%d) -> (%d,%d) = (%d,%d)", $row, $col, $r, $c, $retval[0], $retval[1]), AntLogger::LOG_MAP);

		return $retval;
	}

	/**
	 * passable
	 *
	 * @param array $pt
	 * @return boolean
	 */
    public function passable($pt) {
		$r = $pt[0];
		$c = $pt[1];

		$retval = $this->get($r, $c) > Ants::WATER;

//$this->logger->write(sprintf("Map.passable(%d,%d) = %d", $r, $c, $retval), AntLogger::LOG_MAP);

		return $retval; // || $this->grid[$r][$c] === Ants::UNSEEN;
	}

	/**
	 * Get a passable point
	 *
	 * @param array $pt Point
	 * @param integer $radius Radius
	 * @return array Point
	 */
    public function getPassablePoint($pt, $radius) {
		$attempts = 0;
		do {
			$hr = round($radius/2);
			$r = $pt[0] + rand(-$hr, $hr);
			$c = $pt[1] + rand(-$hr, $hr);

			if ($this->passable($r, $c)) {
				return array($r, $c);
			}
		} while ($attempts++ < 100);

		return false;
	}

	/**
	 * printPath
	 *
	 * A path is just an array of points (arrays).
	 *
	 * @param array $path
	 * @return string
	 */
	public function printPath($path) {
		$str = '';
		if ($path) {
			foreach ($path as $p) {
				$str .= '(' . implode(',', $p) . ')';
			}
		}
		return $str;
	}






	/**
	 * updateView
	 *
	 * @param array $pt
	 * @param integer $mode
	 * @return boolean
	 */
    public function updateView($pt) {
		$row = $pt[0];
		$col = $pt[1];

		if ($this->viewradius2) {
			$radius = round(sqrt($this->viewradius2));
			$topRow = $row - $radius;
			$lowRow = $row + $radius;
			$leftCol = $col - $radius;
			$rightCol = $col + $radius;

//$this->logger->write(sprintf("seen box %d %d %d %d %d", $radius, $topRow, $lowRow, $leftCol, $rightCol), AntLogger::LOG_MA);

			for ($r = $topRow; $r <= $lowRow; $r++) {
				$dr = abs($row - $r);
				$r2 = $dr * $dr;
				for ($c = $leftCol; $c <= $rightCol; $c++) {
					$cr = abs($col - $c);
					if ($r2 + $cr*$cr <= $this->viewradius2) {
						if ($this->get(array($r, $c)) !== Ants::WATER) {
							$this->set(array($r, $c), Ants::LAND);
						}
					} else {
//$this->logger->write(sprintf("seen 2  %d,%d %d", $r, $c, $this->viewradius2), AntLogger::LOG_MA);
					}
				}
			}
		}
	}
	//
	// http://www.gamasutra.com/view/feature/131724/smart_move_intelligent_.php?print=1
	//
	//	priorityqueue	Open
	//	list			Closed
	//
	//  /*
	//   * f(n) = g(n) + h(n)
	//   *		f(n):score assigned to node n
	//   *		g(n):actual cheapest cost of arriving at n from the start
	//   *		h(n):heuristic estimate of the cost to the goal from n
	//   */
	//	AStarSearch (s)
	//		s.g = 0		// s is the start node
	//		s.h = GoalDistEstimate(s)
	//		s.f = s.g + s.h
	//		s.parent = null
	//		push s on Open
	//		while Open is not empty
	//			pop node n from Open  // n has the lowest f
	//			if n is a goal node
	//				construct path
	//				return success
	//			for each successor n' of n		Note: successor = neighbors
	//				newg = n.g + cost(n,n')
	//				if n' is in Open or Closed,
	//				 and n'.g <= newg
	//					skip
	//				n'.parent = n
	//				n'.g = newg
	//				n'.h = GoalDistEstimate(n')
	//				n'.f = n'.g + n'.h
	//				if n' is in Closed
	//					remove it from Closed
	//				if n' is not yet in Open
	//					push n' on Open
	//			push n onto Closed
	//		return failure  // if no path found


	//
	//	http://en.wikipedia.org/wiki/A*_search_algorithm
	//
	//	function A*(start, goal)
	//		closedset := the empty set    // The set of nodes already evaluated.
	//		openset := {start}    // The set of tentative nodes to be evaluated, initially containing the start node
	//		came_from := the empty map    // The map of navigated nodes.
	//
	//		g_score[start] := 0    // Cost from start along best known path.
	//		// Estimated total cost from start to goal through y.
	//		f_score[start] := g_score[start] + heuristic_cost_estimate(start, goal)
	//
	//		while openset is not empty
	//			current := the node in openset having the lowest f_score[] value
	//			if current = goal
	//				return reconstruct_path(came_from, goal)
	//
	//			remove current from openset
	//			add current to closedset
	//			for each neighbor in neighbor_nodes(current)
	//				if neighbor in closedset
	//					continue
	//				tentative_g_score := g_score[current] + dist_between(current,neighbor)
	//
	//				if neighbor not in openset or tentative_g_score < g_score[neighbor]
	//					came_from[neighbor] := current
	//					g_score[neighbor] := tentative_g_score
	//					f_score[neighbor] := g_score[neighbor] + heuristic_cost_estimate(neighbor, goal)
	//					if neighbor not in openset
	//						add neighbor to openset
	//
	//		return failure
	//
	//	function reconstruct_path(came_from, current_node)
	//		if current_node in came_from
	//			p := reconstruct_path(came_from, came_from[current_node])
	//			return (p + current_node)
	//		else
	//			return current_node

	/**
	 * getNeighbors
	 *
	 * @param object $node
	 * @return object Map node object
	 */
	function getNeighbors($node) {

		$USE_WRAP = false;

		$pt = $node->pt;
		
		$retval = array();


		$npt = array($pt[0] - 1, $pt[1]);
		if (($USE_WRAP || $pt[0] > 0) && $this->passable($npt)) {
			$retval[] = (object)array('pt' => $npt, 'f' => null, 'g' => null, 'h' => null, 'parent' => $node);
		}

		$ept = array($pt[0], $pt[1] + 1);
		if (($USE_WRAP || $pt[1] < $this->columns - 2) && $this->passable($ept)) {
			$retval[] = (object)array('pt' => $ept, 'f' => null, 'g' => null, 'h' => null, 'parent' => $node);
		}

		$spt = array($pt[0] + 1, $pt[1]);
		if (($USE_WRAP || $pt[0] < $this->columns - 2) && $this->passable($spt)) {
			$retval[] = (object)array('pt' => $spt, 'f' => null, 'g' => null, 'h' => null, 'parent' => $node);
		}

		$wpt = array($pt[0], $pt[1] - 1);
		if (($USE_WRAP || $pt[1] > 0) && $this->passable($wpt)) {
			$retval[] = (object)array('pt' => $wpt, 'f' => null, 'g' => null, 'h' => null, 'parent' => $node);
		}

		return $retval;
	}

	/**
	 * reconstruct_path
	 *
	 * @param Map $came_from
	 * @param array $current_node
	 * @return array Return a array of points.
	 */
	function reconstruct_path($goal) {

		$retval = array($goal->pt);

		$next = $goal->parent;
		
		while ($next->parent !== null) {
			array_push($retval, $next->pt);
			$next = $next->parent;
		}

		return array_reverse($retval);
	}

	/**
	 * findPath
	 *
	 * Cost of a path at postion nL
	 *
	 * f(n) = g(n) + h(n)
	 *
	 * g(n) = the actual cheapest path start to n. $this->distance(n, goal)
	 * h(n) = heuristic estimate from n to goal.
	 *
	 * @param array $start Start point.
	 * @param array $goal Goal (end) point.
	 * @return array
	 */
	public function findPath($start, $goal) {
		
		$this->logger->write(sprintf("findPath (%d,%d) to (%d,%d)", $start[0], $start[1], $goal[0],  $goal[1]), AntLogger::LOG_MAP);			
		
		$closedset = new LinkedList();
		$openset = new PQueue();

		$g = (object)array (
			'pt' => $goal,
			'f' => null,
			'g' => null,
			'h' => null,
			'parent' => null
		);		
		
		$s = (object)array (
			'pt' => $start,
			'f' => null,
			'g' => null,
			'h' => null,
			'parent' => null
		);
		$s->g = 0;
		$s->h = $this->travelDistance($s->pt, $g->pt);
		$s->f = $s->g + $s->h;

		$openset->insert($s, 0);

$it = 0;	

		while (!$openset->isEmpty()) { // is not empty
				
//if ($s->pt[0] === 3) {
// if ($it++ > 555) { die(); }
//}

//$it++;
//$this->logger->write(sprintf("Openset while entry %d. %s", $it, $this->dumpList($openset)), AntLogger::LOG_MAP);
//$this->logger->write(sprintf("Closeset while entry %d. %s", $it, $this->dumpList($closedset)), AntLogger::LOG_MAP);

			
			$current = $openset->extract();
	
//$this->logger->write('Current: ' . $current->pt[0] . ',' . $current->pt[1]);
//$this->logger->write('g: ' . $g->pt[0] . ',' . $g->pt[1]);
//$this->logger->write('cmp current = g: ' . (int)($current->pt === $g->pt));

			if ($current->pt[0] === $g->pt[0] && $current->pt[1] === $g->pt[1]) {
				$path = $this->reconstruct_path($current);
				$this->logger->write(sprintf("Found path (%d,%d) to (%d,%d) distance: %d ", $s->pt[0], $s->pt[1], $g->pt[0], $g->pt[1], count($path)), AntLogger::LOG_MAP | AntLogger::LOG_WARN);
				return $path;
			}

			$neighbor_nodes = $this->getNeighbors($current);

//$this->logger->write(sprintf("Neighbors: %d", count($neighbor_nodes)));

			foreach ($neighbor_nodes as $k => $v) {

				$neighbor = $neighbor_nodes[$k];
				//$neighbor = unserialize(serialize($v));

				$newg =  $current->g + 1; //$this->cost($current->pt, $neighbor->pt), in this map one square always cost the same.

				$neighbor->g = $this->travelDistance($s->pt, $neighbor->pt);

//$this->logger->write(sprintf("start to neighbor (%d,%d) g dist: %d", $neighbor->pt[0], $neighbor->pt[1], $neighbor->g));
//$this->logger->write(sprintf("neighbor to goal dist: %d", $this->travelDistance($neighbor->pt, $g->pt)));
//$this->logger->write('open find: ' . $openset->find($neighbor));
//$this->logger->write('close find: ' . $closedset->find($neighbor));

				if (($closedset->find($neighbor) !== false || $openset->find($neighbor) !== false) && $neighbor->g <= $newg) {
//$this->logger->write(sprintf("continue"));
					continue;
				}

				$neighbor->parent = $current;
				$neighbor->g = $newg;
				$neighbor->h = $this->travelDistance($neighbor->pt, $g->pt);
				$neighbor->f = $neighbor->g + $neighbor->h;

				$loc = $closedset->find($neighbor);
				if ($loc !== false) {
					$closedset->offsetUnset($loc);
				}

				if ($openset->find($neighbor) === false) {
					$openset->insert($neighbor, $neighbor->f);
				}
			} // each neighbor

			$closedset->push($current);

//$this->logger->write(sprintf("Closeset count %s", $openset->count()), AntLogger::LOG_MAP);
//$this->logger->write(sprintf("Closeset count %s", $closedset->count()), AntLogger::LOG_MAP);
//$this->logger->write(sprintf("Openset while end. %s", $this->dumpList($openset)), AntLogger::LOG_MAP);
//$this->logger->write(sprintf("Closeset while end. %s", $this->dumpList($closedset)), AntLogger::LOG_MAP);
		
		} // while

		$this->logger->write(sprintf("Unable to find path (%d,%d) to (%d,%d) ", $s->pt[0], $s->pt[1], $g->pt[0], $g->pt[1]), AntLogger::LOG_MAP | AntLogger::LOG_WARN);
		
		return false;
	} // findPath

	/**
	 * dumpList
	 * 
	 * @param type $nodeList
	 * @return string
	 */
	function dumpList($nodeList) {
		$str = '';
		foreach (clone $nodeList as $node) {
				$str .= "(" . implode(",", $node->pt) . ") ";
			}
		return $str;
	}

	/**
	 * Straight line distance squared between two cells squared.
	 *
	 * For actual distance, take the square root of the retured value.
	 *
	 * @param array $pt1 (row, col)
	 * @param array $pt2 (row, col)
	 * @return integer
	 */
    public function distance2($pt1, $pt2) {
		list($row1, $col1) = $pt1;
		list($row2, $col2) = $pt2;

        $dRow = abs($row1 - $row2);
        $dCol = abs($col1 - $col2);

        $dRow = min($dRow, $this->rows - $dRow);
        $dCol = min($dCol, $this->cols - $dCol);

        return $dRow * $dRow + $dCol * $dCol;
    }

	/**
	 * travelDistance - order is important
	 *
	 * @param array $pt1
	 * @param array $pt2
	 * @return integer
	 */
    public function travelDistance($pt1, $pt2) {

		list($row1, $col1) = $this->gridWrap($pt1);
		list($row2, $col2) = $this->gridWrap($pt2);

//		list($row1, $col1) = $pt1;
//		list($row2, $col2) = $pt2;
		
        $dRow = abs($row1 - $row2);
        $dCol = abs($col1 - $col2);

        return $dRow + $dCol;
    }


	/**
	 * Cost = Straight line distance squared.
	 */
	protected function cost(array $s, array $g) {
		return $this->travelDistance($s, $g);
	}

	/**
	 * plotPath
	 *
	 * @param array $grid Data grid
	 * @param array $path Array of points
	 * @param integer $grp
	 */
	public function plotPath(&$grid, $path, $grp) {
		if ($path) {
			foreach ($path as $pt) {
				$grid[$pt[0]][$pt[1]] = '* ';
			}
		} else {
			$this->logger->write(sprintf("PlotPath - no path data"), AntLogger::LOG_ERROR);
		}
	}

} // Map

/**
 * 
 */
class PQueue extends SplPriorityQueue {

	/**
	 * compare
	 *
	 * @param integer $priority1
	 * @param integer $priority2
	 * @return integer
	 */
    public function compare($priority1, $priority2) {
        if ($priority1 === $priority2) {
			return 0;
		}

		// reverse priority so the lower values come off first
        return ($priority1 < $priority2) ? 1 : -1;
    }

	/**
	 * Find an item in the list and return it.
	 *
	 * @param type $target
	 * @return intger The offset.
	 */
	function find($target) {
		$targetPt = $target->pt;

		$nodeList = clone $this;
		//$nodeList = unserialize(serialize($this));

		for ($nodeList->rewind(); $nodeList->valid(); $nodeList->next()) {
			$nodePt = $nodeList->current()->pt;
			if ($targetPt[0] === $nodePt[0] && $targetPt[1] === $nodePt[1]) {
				return $nodeList->key();
			}
		}

		return false;
	}

}

/**
 * Linked list to keep array points. Compare check against the first two elements
 * i.e. the row,column, x,y, etc.
 *
 * Default iterator mode is: IT_MODE_FIFO | IT_MODE_KEEP
 *
 * Other options are IT_MODE_LIFO, and IT_MODE_DELETE respectively.
 */
class LinkedList extends SplDoublyLinkedList {

	/**
	 * Find an item in the list and return it.
	 *
	 * @param type $target
	 * @return intger Offset. Use offsetGet to get the item
	 */
	function find ($target) {

		$nodeList = clone $this;
		//$nodeList = unserialize(serialize($this));

		$targetPt = $target->pt;

		for ($nodeList->rewind(); $nodeList->valid(); $nodeList->next()) {
			$nodePt = $nodeList->current()->pt;
			if ($targetPt[0] === $nodePt[0] && $targetPt[1] === $nodePt[1]) {
				return $nodeList->key();
			}
		}

		return false;
	}

}
