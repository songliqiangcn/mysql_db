<?php
include_once(dirname(__FILE__).'/class_mydb.php');


$db = new mydb (array('hostname' => DB_HOST, 'username' => DB_USER, 'password' => DB_PASSWORD, 'database' => DB_NAME));

//get user by ID
$user_info = array();

if (FALSE === ($res = $db->query('SELECT uid, username, dob, mobile, address FROM register_user WHERE uid = ?', array( (int) $uid )))){
	error_log(__METHOD__." Failed to get user info with error = ".$db->error_msg);
}
else{
	$user_info = $res->first_row('array');
	$res->free_result();	
}

print_r($user_info);


//get users 
$users = array();

if (FALSE === ($res = $db->query('SELECT uid, username, dob, mobile, address FROM register_user WHERE uid = ?', array( (int) $uid )))){
	error_log(__METHOD__." Failed to get user info with error = ".$db->error_msg);
}
else{
	$users = $res->result('array');
	$res->free_result();
}

print_r($users);
