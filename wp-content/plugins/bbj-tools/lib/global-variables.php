<?php 

class bbj {
  private $season;
  
  public function __construct($seasonID) {
    $this->season = $seasonID;
  }
  
  public function seasonDates($seasonID) {
    echo "plays in the " . $this->season . " season.";
  }
}
