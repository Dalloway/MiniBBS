<?php
/* Handles the database using PDO (http://php.net/pdo) */
class Database extends PDO {
	/* Holds data for $this->exec() to concatenate into a SQL query. */
	private $sql = null;
	
	/* Holds default structure of $sql. */
	private $sql_default = array
	(
		'selects'  => array(),
		'distinct' => false,
		'froms'    => array(),
		'joins'    => array(),
		'wheres'   => array(),
		'order_by' => array(),
		'group_by' => array(),
		'limit'    => null,
		'offset'   => null,

		'params'   => array()
	);
	
	/* The number of SQL queries issued. */
	public $query_count = 0;
	
	/* The total time consumed by SQL queries, in seconds.*/
	public $query_time  = 0;

	/* Construct with our own statement class. */
	public function __construct($username, $password, $server, $database) {
		$dsn = 'mysql:host=' . $server . ';port=3306;dbname=' . $database; 
		try {
			parent::__construct($dsn, $username, $password);
		} catch(PDOException $e) {
			/* Rethrow with sensitive information stripped. (The trace remains very sensitive.) */
			$message = str_replace
			(
				array($username, $password, $server, $database), 
				array('', '', '(server)', '(database)'), 
				$e->getMessage()
			);
			throw new DatabaseConnectionException($message);
		}
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('DatabaseStatement', array($this)));
	}
	
	/* Execute a query (the first argument) with bound parameters (any additional arguments)*/
	public function q(/* string $query [, string $param ...] */) {
		$values = func_get_args();
		$query = array_shift($values);
		
		$statement = $this->prepare($query);
		$statement->execute($values);
		
		return $statement;
	}
	
	/* PDO has no reliable num_rows() function for SELECT. This only works on MySQL and should be phased out. */
	public function num_rows() {
		$res = $this->q('SELECT FOUND_ROWS()');
		return $res->fetchColumn();
	}
	
	/**
	 * Builds and sends a prepared statement using the contents of $sql, which is set by other functions.
	 * This function is still inflexible, but serves our purpose for now. It will soon be expanded
	 * to accomodate any kind of query. Besides allowing dynamic SQL, in the future we can use this to
	 * create drivers that support non-MySQL syntax.
	 */
	public function exec() {
		if( ! empty($this->sql['selects'])) {
			$query = $this->mysql_build_select();
		} else {
			throw new Exception('No query was built before calling Database::exec().');
		}
		
		$statement = $this->prepare($query);
		$statement->execute($this->sql['params']);

		/* Reset SQL for our next query. */
		$this->sql = null;
		return $statement;
	}
	
	/* Builds a SELECT query from $sql using MySQL's syntax. */
	private function mysql_build_select() {
		$query = 'SELECT';
		if($this->sql['distinct']) {
			$query .= ' DISTINCT';
		}
		$query .= ' ' . implode(', ', $this->sql['selects']) . ' FROM ' . implode(', ', $this->sql['froms']);
		if( ! empty($this->sql['joins'])) {
			$query .= ' ' . implode(' ', $this->sql['joins']);
		}
		if( ! empty($this->sql['wheres'])) {
			$query .= ' WHERE ' . implode(' AND ', $this->sql['wheres']);
		}
		if( ! empty($this->sql['group_by'])) {
			$query .= ' GROUP BY ' . implode(',', $this->sql['group_by']); 
		}
		if( ! empty($this->sql['order_by'])) {
			$query .= ' ORDER BY ' . implode(',', $this->sql['order_by']); 
		}
		if( ! is_null($this->limit)) {
			$query .= ' LIMIT ';
			if( ! is_null($this->offset)) {
				$query .= (int) $this->offset . ', ';
			}
			$query .= (int) $this->limit;
		}
		return $query;
	}
	
	/* Sets or resets $sql. */
	private function sql_init() {
		if($this->sql == null) {
			$this->sql = $this->sql_default;
		}
	}
	
	/**
	 * Adds a field or fields for selection by exec(). Chainable.
	 * @param string $field The fields to select (e.g., 'id, headline, author').
	 */
	public function select($field) {
		$this->sql_init();
		$this->sql['selects'][] = $field;
	
		return $this;
	}
	
	/* Enables the DISTINCT clause for a select. Chainable. */
	public function distinct() {
		$this->sql['distinct'] = true;
		
		return $this;
	}
	
	/**
	 * Adds a table or tables to be selected from by exec(). Chainable.
	 * @param string $table The tables to select (e.g., 'users, replies').
	 */
	public function from($table) {
		$this->sql['froms'][]  = $table;
	
		return $this;
	}
	
	/**
	 *  Adds a join to the exec() query. Chainable.
	 * @param string $table The table to be joined.
	 * @param string $on The ON predicate for the join (e.g., 'table1.field = table2.field')
	 * @param string $type The type of join (inner, outer, left, right)
	 */
	public function join($table, $on, $type = 'LEFT OUTER') {
		$this->sql['joins'][] = $type . ' JOIN ' . $table . ' ON ' . $on;
		
		return $this;
	}

	/**
	 * Adds a WHERE condition to the exec() query. Chainable.
	 * The first argument is the WHERE condition (e.g., 'id = 2' or 'id = ?' or '(x = 2 OR y = 3)').
	 * Any remaining arguments are the parameters for the WHERE condition (replacing the question marks).
	 */
	public function where(/* string $condition [, string $param ...] */) {
		$params = func_get_args();
		$condition = array_shift($params);
	
		$this->sql['wheres'][]  = $condition;
		if( ! empty($params)) {
			$this->sql['params'] = array_merge($this->sql['params'], $params);
		}
	
		return $this;
	}
	
	/**
	 *  Sets the GROUP BY field(s) for exec().
	 * @param string $field The fields to group by (e.g., 'country')
	 */
	public function group_by($field) {
		$this->sql['group_by'][] = $field;
		
		return $this;
	}
	
	/**
	 *  Sets the ORDER BY field(s) for exec().
	 * @param string $field The fields to order by (e.g., 'time' or 'sticky DESC, id DESC')
	 */
	public function order_by($field) {
		$this->sql['order_by'][] = $field;
		
		return $this;
	}
	
	/**
	 * Sets the limit and offset (combined into LIMIT offset,limit by MySQL). If only one argument is supplied, that
	 * argument will be used as the limit; if two are supplied, the first will be the offset, the second the limit.
	 */
	public function limit(/* ... */) {
		$args = func_get_args();
		if(count($args) == 2) {
			$this->offset = $args[0];
			$this->limit = $args[1];
		} else {
			$this->limit = $args[0];
		}
		
		return $this;
	}
}

/* The prepared statement; returned by MiniDB->prepare() */
class DatabaseStatement extends PDOStatement {
	/* The handle. */
	public $dbh;
	protected function __construct($dbh) {
		$this->dbh = $dbh;
	}
		
	/* Wraps a timer around the execute function. */
	public function execute($values) {
		global $db;
		
		$query_start = microtime(true);
		$res = parent::execute($values);
		$db->query_time += microtime(true) - $query_start;
		$db->query_count++;
	}
}

class DatabaseConnectionException extends Exception {}
?>