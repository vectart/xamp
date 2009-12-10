<?php
/**
 *  @desc DataBase class - use it to work with any DB from the list (MySQL \ PostgreSQL):(mysql \ pg)
 */


class dbcon {
	static $instance = false;		// Ссылка на самого себя для раздачи всем вместо себя...
	var $dbuser		= '';		// db access user
	var $dbpass		= '';		// db access password
	var $dbhost		= '';		// db access host i.e. 127.0.0.1 / localhost / db.dotorgc.org
	var $dbport		= '';		// db access port
	var $dbname		= '';		// default database to use
	var $query		= '';		// query to execute
	var $error		= array();	// last error
	var $qress		= array();	// all query resources
	var $dbres		= false;	// connection resource
	var $qres		= false;	// last query resource
	var $insert_id		= false;	// last inserted ID (from primary field)
	var $affected_rows	= false;	// how many rows affected (update \ delete)
	var $debug_queries	= array();
	var $query_counter	= 0;

/**
 * @desc	class constructor
 * @param	string		$user		db access user
 * @param	string		$pass		db access password
 * @param	string		$type		db we want to use
 * @param	string		$name		default database to use
 * @param	string		$host		db access host i.e. 127.0.0.1 / localhost / db.dotorgc.org
 * @param	string		$port		db access port
 * @param	boolean		$persistent	do we want persistent connection? I hope: NO
 * @param	boolean		$new_one	do we want to create new connection instead of using old instance of db con. 
 */
 
	function dbcon($user, $pass, $name, $host, $port = false, $persistent = false, $new_one = false) {

		if (dbcon::$instance !== false && $new_one === false) return dbcon::$instance;

		$error = array();
		if (empty($user)) $error[] = 'DataBase user: empty';
		if (empty($host)) $error[] = 'DataBase host: empty';
		if (empty($name)) $error[] = 'DataBase name: empty';
		
		$this->dbuser	= $user;
		$this->dbpass	= $pass;
		$this->dbhost	= $host;
		$this->dbport	= $port;
		$this->dbname	= $name;
		
		if (!empty($error)) {
			$this->error(implode("\n", $error));
			return false;
		}

		try
		{
			$connect = 'mysql:host='.$this->dbhost.';port='.$this->dbport.';dbname='.$this->dbname;
			$this->pdo = new PDO($connect, $this->dbuser, $this->dbpass, array(PDO::ATTR_PERSISTENT => $persistent, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8 COLLATE 'utf8_general_ci'"));
		}
		catch (PDOException $e)
		{
			$error[] = $e;
		}

		if (!empty($error)) {
			$this->error(implode("\n", $error));
			return false;
		}

		if ($new_one === false) dbcon::$instance = &$this;
	}


/**
 * @desc	Метод для эмуляции синглетона.
 * @param	boolean	$real_login		Флаг для обозначения - обязан ли пользователь быть залогиненым или нет.
 * @return	object				Ссылку на объект или свежесозданный объект
 */
 
	static function instance() {
		if(dbcon::$instance === false) dbcon::$instance = new dbcon(DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT);
		return dbcon::$instance;
	}


/**
 * @desc	function to send query to the DataBase
 * @param	$query			string		string with the query
 * @return	resource		last query resource ID or 'false' with $this->error
 */
	function query($query = false, $last = false) {


		$num_rows = false;
		$this->insert_id		= false;
		$this->affected_rows		= false;

		if ($query === false)	$query			= $this->query;
		else	$this->query	= $query;
		if (empty($query)) {
			$this->error('db::query: no query to execute');
			return false;
		}

		$query = trim($query);
		$temp_arr = explode(" ", $query, 2);
		$temp_arr[0] = trim(strtoupper($temp_arr[0]));
		
		switch($temp_arr[0]) {
			case 'INSERT' :
				$last = true;
				$query = html_entity_decode ($query);
			case 'UPDATE' :
				$num_rows = true;
				$query = html_entity_decode ($query);
			case 'DELETE' :
				$num_rows = true;
			break;	
		}
		$query = trim($query);

			if ($this->qres = $this->pdo->query($query))
			{
				if ($last !== false) $this->insert_id = $this->pdo->lastInsertId();
				if ($num_rows === true) $this->affected_rows = $this->qres->rowCount();
			}
			else
			{
				$this->error($this->pdo->errorInfo());
				return false;
			}

		$this->qress[] = $this->qres;

//		echo '<pre>',print_r ($this->affected_rows),'</pre>';
		$returner = new dbconQuery($this->qres);
		$returner->insert_id		= $this->insert_id;
		$returner->affected_rows	= $this->affected_rows;

		return $returner;
	}


/**
 * @desc	function to show affected rows after query
 * @param	$res	resource	query resource ID
 * @return			int			number of rows or 'false' with $this->error
 */
	function affected_rows($res = false) {

		if ($res === false) $res = $this->qres;
		if ($res !== true) {
			$this->error('db::affected_rows: gived string is not resource');
			return false;
		}

		$returner = $res->rowCount;

		return $returner;
	}


/**
 * @desc	function to prepare string to use in query
 * @param	$string	string		string to prepare
 * @return			string		escaped string
 */
 
	function escape_string($string) {
		$returner = mysql_real_escape_string($string, $this->dbres);
		return $returner;
	}


/**
 * @desc	function to free all DB results from memory
 * @return				bool		operation result
 */
	function free_all_results() {
		if (empty($this->qress)) return true;
		foreach ($this->qress AS $k => $v) {
			if (gettype($v) != 'boolean') $returner = $v->closeCursor();;
			unset($this->qress[$k]);
		}
	}


/**
 * @desc	function to print queries with time
 */
	function print_debug_queries() {
		foreach ($this->debug_queries AS $query) {
			echo $query['time'].': '.$query['query']."\n\n";
		}
	}


/**
 * @desc	function to create append error and call debugger
 * @param	$error	string	string with error in it
 */
	function error($error) {
		echo '<pre>',$error,'</pre>';
		echo $this -> query;
		exit;
	}

	function show_tables () {
		return new dbconQuery (mysql_list_tables ($this -> name));
	}


/**
 * @desc	function destructor
 */
	function __destruct() {
		$this->free_all_results();
		$returner = false;
		unset($this->dbres);
	}
}

/**
 *  @desc DataBase class - query class
 */


class dbconQuery {

	var $res		= false;	// last query resource
	var $insert_id	= false;	// last inserted ID (from primary field)
	var $affected_rows	= false;	// last inserted ID (from primary field)

/**
 * @desc	class constructor
 * @param	resourse	$res		Ресурс на запрос
 * @param	string		$dbtype		чё за база такая ваще
 */
	function __construct($res) {
		$this->res		= $res;
	}


/**
 * @return			string		возвращает одну переменную
 */
	function fetch_one() {
		return list($returner) = $this->fetch_row();
	}


/**
 * @desc	function to fetch resource into associative array
 * @return			array		fetched query in the array
 */
	function fetch_assoc() {
		return $this->res->fetch(PDO::FETCH_ASSOC);
	}


/**
 * @return			array		fetched query in the array
 */
	function fetch_row() {
		return $this->res->fetch();
	}


/**
 * @return			array		fetched query in the array
 */
	function fetch_object() {
		return $this->res->fetch(PDO::FETCH_OBJ);
	}


/**
 * @desc	function to fetch resource with 'assoc' type
 * @return	array					array from fetched recource
 */
	function fetch_assoc_all() {
		return $this->res->fetchAll(PDO::FETCH_ASSOC);
	}



/**
 * @desc	function to count number of rows in the selected query
 * @return			int			number of rows or 'false' with $this->error
 */
	function num_rows() {
		$returner = mysql_num_rows($this->res);
		return $returner;
	}


/**
 * @desc	function to free DB result from memory
 * @return				bool		operation result
 */
	function free_result() {
		$returner = mysql_free_result($this->res);
		return $returner;
	}


/**
 * @desc	function to get last id
 * @return				intiger		last insert id
 */
	function insert_id() {
		return $this->pdo->lastInsertId();
	}

	function fetch_obj () {
		$returner = array();

		while ($returner[] = mysql_fetch_object ($this->res));
			array_pop($returner);

		return $returner;
	}


/**
 * @desc	function to get affected_rows
 * @return				intiger		number of affected rows
 */
	function affected_rows() {
		return $this->res->rowCount;
	}


/**
 * @desc	function destructor
 */
	function __destruct() {
		//$this->free_result();
		dbcon::$instance = false;
	}
}
?>
