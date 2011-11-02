<?php

class Timer {
	/* The script's start time. */
	public $start;
	
	/* The total execution time, in seconds. */
	public $total = 0;
	
	/* The total time consumed by SQL queries, in seconds.*/
	public $sql_total = 0;
	
	/* The number of SQL queries issued. */
	public $sql_count = 0;
	
	/* The SQL start time (updates with each query). */
	private $sql_start;
	
	/* Begin the timer. */
	public function __construct($start_time = null) {
		$this->start = (is_null($start_time) ? microtime(true) : $start_time);
	}
	
	/* End the timer. */
	public function stop() {
		$this->total = microtime(true) - $this->start;
	}
	
	/* Begin timer for SQL queries. */
	public function sql_start() {
		$this->sql_start = microtime(true);
	}
	
	/* Add the last query time to SQL total */
	public function sql_stop() {
		$this->sql_total += microtime(true) - $this->sql_start;
		$this->sql_count++;
	}
}

?>