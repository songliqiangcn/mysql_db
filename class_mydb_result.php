<?php
class mydb_result{
	var $conn_id				= NULL;
	var $result_id				= NULL;
	var $result_array			= array();
	var $result_object			= array();
	var $custom_result_object	= array();
	var $current_row			= 0;	
	var $row_data				= NULL;
	
	
	function __construct($conn_id = NULL, $result_id = NULL){
		$this->conn_id   = $conn_id;
		$this->result_id = $result_id;
			
	}
	
	/**
	 * Query result.  Acts as a wrapper function for the following functions.
	 *
	 * @access	public
	 * @param	string	can be "object" or "array"
	 * @return	mixed	either a result object or array
	 */
	public function result($type = 'object'){
		if ($type == 'array') return $this->result_array();
		else if ($type == 'object') return $this->result_object();
		else return $this->custom_result_object($type);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Custom query result.
	 *
	 * @param class_name A string that represents the type of object you want back
	 * @return array of objects
	 */
	public function custom_result_object($class_name){
		if (array_key_exists($class_name, $this->custom_result_object)){
			return $this->custom_result_object[$class_name];
		}
	
		if ($this->result_id === FALSE OR $this->num_rows() == 0){
			return array();
		}
	
		// add the data to the object
		$this->_data_seek(0);
		$result_object = array();
	
		while ($row = $this->_fetch_object()){
			$object = new $class_name();
	
			foreach ($row as $key => $value){
				$object->$key = $value;
			}
	
			$result_object[] = $object;
		}
	
		// return the array
		return $this->custom_result_object[$class_name] = $result_object;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Query result.  "object" version.
	 *
	 * @access	public
	 * @return	object
	 */
	public function result_object(){
		if (count($this->result_object) > 0){
			return $this->result_object;
		}
	
		// In the event that query caching is on the result_id variable
		// will return FALSE since there isn't a valid SQL resource so
		// we'll simply return an empty array.
		if ($this->result_id === FALSE OR $this->num_rows() == 0){
			return array();
		}
	
		$this->_data_seek(0);
		while ($row = $this->_fetch_object()){
			$this->result_object[] = $row;
		}
	
		return $this->result_object;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Query result.  "array" version.
	 *
	 * @access	public
	 * @return	array
	 */
	public function result_array(){
		if (count($this->result_array) > 0){
			return $this->result_array;
		}
	
		// In the event that query caching is on the result_id variable
		// will return FALSE since there isn't a valid SQL resource so
		// we'll simply return an empty array.
		if ($this->result_id === FALSE OR $this->num_rows() == 0){
			return array();
		}
	
		$this->_data_seek(0);
		while ($row = $this->_fetch_assoc()){
			$this->result_array[] = $row;
		}
	
		return $this->result_array;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Free the result
	 *
	 * @return	null
	 */
	function free_result(){
		if (is_object($this->result_id)){
			mysqli_free_result($this->result_id);
			$this->result_id = FALSE;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Query result.  Acts as a wrapper function for the following functions.
	 *
	 * @access	public
	 * @param	string
	 * @param	string	can be "object" or "array"
	 * @return	mixed	either a result object or array
	 */
	public function row($n = 0, $type = 'object'){
		if ( ! is_numeric($n)){
			// We cache the row data for subsequent uses
			if ( ! is_array($this->row_data)){
				$this->row_data = $this->row_array(0);
			}
	
			// array_key_exists() instead of isset() to allow for MySQL NULL values
			if (array_key_exists($n, $this->row_data)){
				return $this->row_data[$n];
			}
			// reset the $n variable if the result was not achieved
			$n = 0;
		}
	
		if ($type == 'object') return $this->row_object($n);
		else if ($type == 'array') return $this->row_array($n);
		else return $this->custom_row_object($n, $type);
	}
		
	// --------------------------------------------------------------------
	
	/**
	 * Assigns an item into a particular column slot
	 *
	 * @access	public
	 * @return	object
	 */
	public function set_row($key, $value = NULL){
		// We cache the row data for subsequent uses
		if ( ! is_array($this->row_data)){
			$this->row_data = $this->row_array(0);
		}
	
		if (is_array($key)){
			foreach ($key as $k => $v){
				$this->row_data[$k] = $v;
			}
	
			return;
		}
	
		if ($key != '' AND ! is_null($value)){
			$this->row_data[$key] = $value;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns a single result row - custom object version
	 *
	 * @access	public
	 * @return	object
	 */
	public function custom_row_object($n, $type){
		$result = $this->custom_result_object($type);
	
		if (count($result) == 0){
			return $result;
		}
	
		if ($n != $this->current_row AND isset($result[$n])){
			$this->current_row = $n;
		}
	
		return $result[$this->current_row];
	}
	
	/**
	 * Returns a single result row - object version
	 *
	 * @access	public
	 * @return	object
	 */
	public function row_object($n = 0){
		$result = $this->result_object();
	
		if (count($result) == 0){
			return $result;
		}
	
		if ($n != $this->current_row AND isset($result[$n])){
			$this->current_row = $n;
		}
	
		return $result[$this->current_row];
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns a single result row - array version
	 *
	 * @access	public
	 * @return	array
	 */
	public function row_array($n = 0){
		$result = $this->result_array();
	
		if (count($result) == 0){
			return $result;
		}
	
		if ($n != $this->current_row AND isset($result[$n])){
			$this->current_row = $n;
		}
	
		return $result[$this->current_row];
	}
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns the "first" row
	 *
	 * @access	public
	 * @return	object
	 */
	public function first_row($type = 'object'){
		$result = $this->result($type);
	
		if (count($result) == 0){
			return $result;
		}
		return $result[0];
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns the "last" row
	 *
	 * @access	public
	 * @return	object
	 */
	public function last_row($type = 'object'){
		$result = $this->result($type);
	
		if (count($result) == 0){
			return $result;
		}
		return $result[count($result) -1];
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns the "next" row
	 *
	 * @access	public
	 * @return	object
	 */
	public function next_row($type = 'object'){
		$result = $this->result($type);
	
		if (count($result) == 0){
			return $result;
		}
	
		if (isset($result[$this->current_row + 1])){
			++$this->current_row;
		}
	
		return $result[$this->current_row];
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns the "previous" row
	 *
	 * @access	public
	 * @return	object
	 */
	public function previous_row($type = 'object'){
		$result = $this->result($type);
	
		if (count($result) == 0){
			return $result;
		}
	
		if (isset($result[$this->current_row - 1])){
			--$this->current_row;
		}
		return $result[$this->current_row];
	}
	
	// --------------------------------------------------------------------
		
	/**
	 * Number of rows in the result set
	 *
	 * @access	public
	 * @return	integer
	 */
	function num_rows(){
		return @mysqli_num_rows($this->result_id);
	}
	
	/**
	 * The affect records number
	 *
	 * @access	public
	 * @return	integer
	 */
	function affected_rows(){
		if ( ! $this->conn_id){
			return 0;
		}
	
		return @mysqli_affected_rows($this->conn_id);
	}
	
	/**
	 * Insert ID
	 *
	 * @access	public
	 * @return	integer
	 */
	function insert_id(){
		if ( ! $this->conn_id){
			return 0;
		}
		
		return @mysqli_insert_id($this->conn_id);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Number of fields in the result set
	 *
	 * @access	public
	 * @return	integer
	 */
	function num_fields(){
		return @mysqli_num_fields($this->result_id);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Fetch Field Names
	 *
	 * Generates an array of column names
	 *
	 * @access	public
	 * @return	array
	 */
	function list_fields(){
		$field_names = array();
		while ($field = mysqli_fetch_field($this->result_id)){
			$field_names[] = $field->name;
		}
	
		return $field_names;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Field data
	 *
	 * Generates an array of objects containing field meta-data
	 *
	 * @access	public
	 * @return	array
	 */
	function field_data(){
		$retval = array();
		while ($field = mysqli_fetch_object($this->result_id)){
			preg_match('/([a-zA-Z]+)(\(\d+\))?/', $field->Type, $matches);
	
			$type = (array_key_exists(1, $matches)) ? $matches[1] : NULL;
			$length = (array_key_exists(2, $matches)) ? preg_replace('/[^\d]/', '', $matches[2]) : NULL;
	
			$F				= new stdClass();
			$F->name		= $field->Field;
			$F->type		= $type;
			$F->default		= $field->Default;
			$F->max_length	= $length;
			$F->primary_key = ( $field->Key == 'PRI' ? 1 : 0 );
	
			$retval[] = $F;
		}
	
		return $retval;
	}
	
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Data Seek
	 *
	 * Moves the internal pointer to the desired offset.  We call
	 * this internally before fetching results to make sure the
	 * result set starts at zero
	 *
	 * @access	private
	 * @return	array
	 */
	private function _data_seek($n = 0){
		return mysqli_data_seek($this->result_id, $n);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Result - associative array
	 *
	 * Returns the result set as an array
	 *
	 * @access	private
	 * @return	array
	 */
	private function _fetch_assoc(){
		return mysqli_fetch_assoc($this->result_id);
	}
	
		
	// --------------------------------------------------------------------
	
	/**
	 * Result - object
	 *
	 * Returns the result set as an object
	 *
	 * @access	private
	 * @return	object
	 */
	private function _fetch_object(){
		return mysqli_fetch_object($this->result_id);
	}
	
}
