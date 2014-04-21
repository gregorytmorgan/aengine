#!/usr/bin/php
<?php

require_once 'bots/Map.php';

$a = array('a', 'b', 'c', 'd');

class LL extends SplDoublyLinkedList {

  function find ($target) {
    for ($this->rewind(); $this->valid(); $this->next()) {
      $item = $this->current();
      if ($target === $item && $target === $item) {
	return $this->key();
      }
    }
    return false;
  } 

}

$ll = new LL();

foreach ($a as $v) {
  $ll->push($v);
}


$ll->setIteratorMode(SplDoublyLinkedList::IT_MODE_KEEP);
//$ll->setIteratorMode(SplDoublyLinkedList::IT_MODE_DELETE);

echo "count1:" . $ll->count() . "\n";

for ($ll->rewind(); $ll->valid(); $ll->next()) {
  $c = $ll->current();
  echo $ll->key() . " : " . $c . "\n";
}

echo "count2:" . $ll->count() . "\n";

for ($ll->rewind(); $ll->valid(); $ll->next()) {
  $c = $ll->current();
  echo $ll->key() . " : " . $c . "\n";
}

echo "count3:" . $ll->count() . "\n";

echo "Found at: " . $ll->find('c') . "\n";

// end file


	