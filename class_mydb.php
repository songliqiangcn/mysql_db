<?php

include_once(dirname(__FILE__).'/class_mydb_result.php');

class mydb{
	var $username;
	var $password;
	var $hostname;
	var $database;
	
	var $dbprefix		= '';
	var $char_set		= 'utf8';
	var $dbcollat		= 'utf8_general_ci';
	var $autoinit		= TRUE; // Whether to automatically initialize the DB
	
	var $port			= '';
	var $pconnect		= FALSE;
	var $conn_id		= FALSE;
	var $result_id		= FALSE;
	var $db_debug		= TRUE;
	var $benchmark		= 0;
	var $query_count	= 0;
	var $bind_marker	= '?';
	var $save_queries	= TRUE;
	var $queries		= array();
	var $query_times	= array();
	
	var $trans_enabled	= TRUE;
	var $trans_strict	= TRUE;
	var $_trans_depth	= 0;
	var $_trans_status	= TRUE; // Used with transactions to determine if a rollback should occur				
	var $delete_hack    = TRUE;
	
	var $error_no       = 0;
	var $error_msg      =  '';
	
	// whether SET NAMES must be used to set the character set
	var $use_set_names;
	
	
	/**
	 * Constructor.  Accepts one parameter containing the database
	 * connection settings.
	 *
	 * @param array
	 */
	function __construct($params){
	
		if (is_array($params)){
			foreach ($params as $key => $val){
				$this->$key = $val;
			}
		}
		
		if ($this->autoinit === TRUE){
			$this->initialize();
		}
	}
	
	/**
	 * Execute the query
	 *
	 * Accepts an SQL string as input and returns a result object upon
	 * successful execution of a "read" type query.  Returns boolean TRUE
	 * upon successful execution of a "write" type query. Returns boolean
	 * FALSE upon failure, and if the $db_debug variable is set to TRUE
	 * will raise an error.
	 *
	 * @access	public
	 * @param	string	An SQL query string
	 * @param	array	An array of binding data
	 * @return	mixed
	 */
	function query($sql, $binds = FALSE, $return_object = TRUE){
		if ($sql == ''){
			if ($this->db_debug){
				$this->log_message('error', 'Invalid query: '.$sql);
			}
			return FALSE;
		}
		
		if ($binds !== FALSE){
			$sql = $this->_compile_binds($sql, $binds);
		}
		
		// Save the  query for debugging
		if ($this->save_queries == TRUE){
			$this->queries[] = $sql;
		}
		
		// Start the Query Timer
		$time_start = list($sm, $ss) = explode(' ', microtime());
		
		// Run the Query
		if (FALSE === ($this->result_id = $this->simple_query($sql))){
			if ($this->save_queries == TRUE){
				$this->query_times[] = 0;
			}
		
			// This will trigger a rollback if transactions are being used
			$this->_trans_status = FALSE;
			
			// grab the error number and message now, as we might run some
			// additional queries before displaying the error
			$this->error_no = $this->_error_number();
			$this->error_msg = $this->_error_message();
		
			if ($this->db_debug){
				// We call this function in order to roll-back queries
				// if transactions are enabled.  If we don't call this here
				// the error message will trigger an exit, causing the
				// transactions to remain in limbo.
				$this->trans_complete();
		
				// Log and display errors
				$this->log_message('error', 'Query error: ErrorNo = '.$this->error_no.' ErrorMessage = '.$this->error_msg." Query: $sql");
				return FALSE;
			}		
			return FALSE;
		}
		else{
			$this->error_no = 0;
			$this->error_msg = '';
		}
		
		// Stop and aggregate the query time results
		$time_end = list($em, $es) = explode(' ', microtime());
		$this->benchmark += ($em + $es) - ($sm + $ss);
		
		if ($this->save_queries == TRUE){
			$this->query_times[] = ($em + $es) - ($sm + $ss);
		}
		
		// Increment the query counter
		$this->query_count++;
		
		// Return TRUE if we don't need to create a result object
		// Currently only the Oracle driver uses this when stored
		// procedures are used
		if ($return_object !== TRUE){
			return TRUE;
		}
		
		// Load and instantiate the result driver
		
		$RES = new mydb_result($this->conn_id, $this->result_id);
		//$this->log_message('error', "Query2222222 Query: $sql");
		return $RES;
	}
	
	/**
	 * Simple Query
	 * This is a simplified version of the query() function.  Internally
	 * we only use it when running transaction commands since they do
	 	* not require all the features of the main query() function.
	 *
	 * @access	public
	 * @param	string	the sql query
	 * @return	mixed
	 */
	function simple_query($sql){
		if ( ! $this->conn_id){
			$this->initialize();
		}
	
		return $this->_execute($sql);
	}
		
	/**
	 * Complete Transaction
	 *
	 * @access	public
	 * @return	bool
	 */
	function trans_complete(){
		if ( ! $this->trans_enabled){
			return FALSE;
		}
	
		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 1){
			$this->_trans_depth -= 1;
			return TRUE;
		}
	
		// The query() function will set this flag to FALSE in the event that a query failed
		if ($this->_trans_status === FALSE){
			$this->trans_rollback();
	
			// If we are NOT running in strict mode, we will reset
			// the _trans_status flag so that subsequent groups of transactions
			// will be permitted.
			if ($this->trans_strict === FALSE){
				$this->_trans_status = TRUE;
			}
	
			$this->log_message('debug', 'DB Transaction Failure');
			return FALSE;
		}
	
		$this->trans_commit();
		return TRUE;
	}
	
	/**
	 * Commit Transaction
	 *
	 * @access	public
	 * @return	bool
	 */
	function trans_commit(){
		if ( ! $this->trans_enabled){
			return TRUE;
		}
	
		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0){
			return TRUE;
		}
	
		$this->simple_query('COMMIT');
		$this->simple_query('SET AUTOCOMMIT=1');
		return TRUE;
	}
	
	/**
	 * Rollback Transaction
	 *
	 * @access	public
	 * @return	bool
	 */
	function trans_rollback(){
		if ( ! $this->trans_enabled){
			return TRUE;
		}
	
		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0){
			return TRUE;
		}
	
		$this->simple_query('ROLLBACK');
		$this->simple_query('SET AUTOCOMMIT=1');
		return TRUE;
	}
		
	/**
	 * "Smart" Escape String
	 *
	 * Escapes data based on type
	 * Sets boolean and null types
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed
	 */
	function escape($str){
		if (is_string($str)){
			$str = "'".$this->escape_str($str)."'";
		}
		elseif (is_bool($str)){
			$str = ($str === FALSE) ? 0 : 1;
		}
		elseif (is_null($str)){
			$str = 'NULL';
		}
	
		return $str;
	}
	
	/**
	 * Escape String
	 *
	 * @access	public
	 * @param	string
	 * @param	bool	whether or not the string will be used in a LIKE condition
	 * @return	string
	 */
	function escape_str($str, $like = FALSE){
		if (is_array($str)){
			foreach ($str as $key => $val){
				$str[$key] = $this->escape_str($val, $like);
			}	
			return $str;
		}
	
		if (function_exists('mysqli_real_escape_string') AND is_object($this->conn_id)){
			$str = mysqli_real_escape_string($this->conn_id, $str);
		}
		elseif (function_exists('mysql_escape_string')){
			$str = mysql_escape_string($str);
		}
		else{
			$str = addslashes($str);
		}
	
		// escape LIKE condition wildcards
		if ($like === TRUE){
			$str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
		}
	
		return $str;
	}
	
	/**
	 * Initialize Database Settings
	 *
	 * @access	private Called by the constructor
	 * @param	mixed
	 * @return	void
	 */
	function initialize(){
		// If an existing connection resource is available
		// there is no need to connect and select the database
		if (is_resource($this->conn_id) OR is_object($this->conn_id)){
			return TRUE;
		}
	
		// ----------------------------------------------------------------
	
		// Connect to the database and set the connection ID
		$this->conn_id = ($this->pconnect == FALSE) ? $this->db_connect() : $this->db_pconnect();
	
		// No connection resource?  Throw an error
		if ( ! $this->conn_id){
			$this->log_message('error', 'Unable to connect to the database');
			return FALSE;
		}
	
		// ----------------------------------------------------------------
	
		// Select the DB... assuming a database name is specified in the config file
		if ($this->database != ''){
			if ( ! $this->db_select()){
				$this->log_message('error', 'Unable to select database: '.$this->database);	
				return FALSE;
			}
			else{
				// We've selected the DB. Now we set the character set
				if ( ! $this->db_set_charset($this->char_set, $this->dbcollat)){
					return FALSE;
				}	
				return TRUE;
			}
		}
	
		return TRUE;
	}
	
	
	/**
	 * Persistent database connection
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */
	function db_pconnect(){
		return $this->db_connect();
	}
	
	/**
	 * Non-persistent database connection
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */
	function db_connect(){
		if ($this->port != ''){
			return @mysqli_connect($this->hostname, $this->username, $this->password, $this->database, $this->port);
		}
		else{
			return @mysqli_connect($this->hostname, $this->username, $this->password, $this->database);
		}	
	}
	
	/**
	 * Reconnect
	 *
	 * Keep / reestablish the db connection if no queries have been
	 * sent for a length of time exceeding the server's idle timeout
	 *
	 * @access	public
	 * @return	void
	 */
	function reconnect(){
		if (is_resource($this->conn_id) OR is_object($this->conn_id)){
			if (mysqli_ping($this->conn_id) === FALSE){
				$this->conn_id = FALSE;
			}	
		}		
	}
	
	/**
	 * Select the database
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */
	function db_select(){
		return @mysqli_select_db($this->conn_id, $this->database);
	}
		
	/**
	 * Set client character set
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	resource
	 */
	function db_set_charset($charset, $collation){
		if ( ! $this->_db_set_charset($this->char_set, $this->dbcollat)){
			$this->log_message('error', 'Unable to set database connection charset: '.$this->char_set);
			return FALSE;
		}
		return TRUE;
	}
	
	
	
	/**
	 * Close DB Connection
	 *
	 * @access	public
	 * @param	resource
	 * @return	void
	 */
	function close(){
		if (is_resource($this->conn_id) OR is_object($this->conn_id)){
			@mysqli_close($this->conn_id);
		}
		$this->conn_id = FALSE;
	}
	
	
	function log_message($type, $error){
		$t     = microtime(true);
		$micro = sprintf("%06d",($t - floor($t)) * 1000000);
		$d     = new DateTime( date('Y-m-d H:i:s.'.$micro,$t) );
		
		error_log('['.$d->format("Y-m-d H:i:s.u").'] '.strtoupper($type).' : '.$error);
	}
	
	
	// --------------------------------------------------------------------
	
	/**
	 * The error message number
	 *
	 * @access	private
	 * @return	integer
	 */
	private function _error_number(){
		return mysqli_errno($this->conn_id);
	}
		
	/**
	 * Execute the query
	 *
	 * @access	private called by the base class
	 * @param	string	an SQL query
	 * @return	resource
	 */
	private function _execute($sql){
		$sql    = $this->_prep_query($sql);
		$result = @mysqli_query($this->conn_id, $sql);
		return $result;
	}
	
	/**
	 * Prep the query
	 *
	 * If needed, each database adapter can prep the query string
	 *
	 * @access	private called by execute()
	 * @param	string	an SQL query
	 * @return	string
	 */
	private function _prep_query($sql){
		// "DELETE FROM TABLE" returns 0 affected rows This hack modifies
		// the query so that it returns the number of affected rows
		if ($this->delete_hack === TRUE){
			if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql)){
				$sql = preg_replace("/^\s*DELETE\s+FROM\s+(\S+)\s*$/", "DELETE FROM \\1 WHERE 1=1", $sql);
			}
		}
	
		return $sql;
	}
	
	/**
	 * The error message string
	 *
	 * @access	private
	 * @return	string
	 */
	private function _error_message(){
		return mysqli_error($this->conn_id);
	}
	
	/**
	 * Compile Bindings
	 *
	 * @access	private
	 * @param	string	the sql statement
	 * @param	array	an array of bind data
	 * @return	string
	 */
	private function _compile_binds($sql, $binds){
		if (strpos($sql, $this->bind_marker) === FALSE){
			return $sql;
		}
	
		if ( ! is_array($binds)){
			$binds = array($binds);
		}
	
		// Get the sql segments around the bind markers
		$segments = explode($this->bind_marker, $sql);
	
		// The count of bind should be 1 less then the count of segments
		// If there are more bind arguments trim it down
		if (count($binds) >= count($segments)) {
			$binds = array_slice($binds, 0, count($segments)-1);
		}
	
		// Construct the binded query
		$result = $segments[0];
		$i = 0;
		foreach ($binds as $bind){
			$result .= $this->escape($bind);
			$result .= $segments[++$i];
		}
	
		return $result;
	}
	
	/**
	 * Set client character set
	 *
	 * @access	private
	 * @param	string
	 * @param	string
	 * @return	resource
	 */
	private function _db_set_charset($charset, $collation){
		if ( ! isset($this->use_set_names)){
			// mysqli_set_charset() requires MySQL >= 5.0.7, use SET NAMES as fallback
			$this->use_set_names = (version_compare(mysqli_get_server_info($this->conn_id), '5.0.7', '>=')) ? FALSE : TRUE;
		}
	
		if ($this->use_set_names === TRUE){
			return @mysqli_query($this->conn_id, "SET NAMES '".$this->escape_str($charset)."' COLLATE '".$this->escape_str($collation)."'");
		}
		else{
			return @mysqli_set_charset($this->conn_id, $charset);
		}
	}
	
}