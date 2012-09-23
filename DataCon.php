<?php
	/**
	*
	*	Data access class using PDO so we can swap out the DB backend.
	*
	**/
	class benchmark_DataCon
	{
		/**
		*
		*	Private vars
		*
		**/
		private $_connection = "";
		private $_dbType = "";
		private $_database = "";
		private $_user = "";
		private $_pswd = "";
		private $_host = "";
		
		/**
		*
		*	Public vars
		*
		**/
		public $Connected = false;
		public $RecordCount = 0;
		
		/**
		*
		*	Public Constructor Method
		*	Sets initial values
		*
		*	@param $dbType		string	// Database engine were connecting to
		*	@param $host		string	// Where the db is hosted
		*	@param $database	string	// Database to select
		*	@param $user		string	// Username that has access
		*	@param $pswd		string	// Password for the account
		*
		**/
		public function __construct($dbType, $host, $database, $user, $pswd){
			$this->_dbType = $dbType;
			$this->_database = $database;
			$this->_host = $host;
			$this->_user = $user;
			$this->_pswd = $pswd;
			
			// Create the global connection
			$this->getCon();
		}
		
		/**
		*
		*	Public getCon Method
		*	Creates a connection to the DB if theres not already one
		*	Could definitly find a way to handle the connection strings better
		*
		**/
		public function getCon() {
			if (!$this->_connection){
					switch($this->_dbType){
						case "mysql":
							try{
								$this->_connection = new PDO("mysql:host=$this->_host;dbname=$this->_database", $this->_user, $this->_pswd );
							}catch(PDOException $e){
								echo $e->getMessage();
							}
							break;
						case "pgsql":	
							try{
								$this->_connection = new PDO("pgsql:dbname=$this->_database;host=$this->_host", $this->_user, $this->_pswd );
							}catch(PDOException $e){
								echo $e->getMessage();
							}
							break;
						case "mssql":	
							try{
								$this->_connection = new PDO("odbc:Driver={SQL Native Client};Server=$this->_host;Database=$this->_database; Uid=$this->_user;Pwd=$this->_pswd;Trusted_Connection=yes;"); 
							}catch(PDOException $e){
								echo $e->getMessage();
							}
							break;	
						default:
							echo "Sorry that database type is not currently supported";
							return false;
							break;
					}
					
					$this->_connection-> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				}
				
			return $this->_connection;
		}

		
		/**
		*
		*	Public execute Method
		*	executes a generic query for the database
		*
		**/
		public function execute($query){
			$this->getCon();
			$stmnt = $this->_connection->prepare($query);
			
			try{
				$stmnt->execute();
			}catch(PDOException $e){
				echo $e->getMessage();
			}
			$this->close();
		}
		
		/**
		*
		*	Public insert Method
		*	executes an insert on the database
		*
		*	@param $dbTable			string	 
		*	@param $fieldList		array	
		**/
		public function insert($dbTable, $fieldList){
			$this->getCon();
			
			// Build insert field and value list
			$fields = array_keys($fieldList);  
			$fieldVals = array_values($fieldList);
			$fieldCount = count($fields);

			//join fields
			$fields = implode(",", $fields);
			
			$params = array();
			
			// Create the ? param list
			for($i = 0; $i < $fieldCount; $i++){
				$params[] =  '?';
			}
			
			// Join the param list
			$params = implode(',', $params);
			
			// Create and execute the query
			$query = "INSERT INTO $dbTable ($fields) VALUES($params)";
			$stmnt = $this->_connection->prepare($query);

			// Bind the parameters.
			for($i = 0; $i < $fieldCount; $i++){
				$stmnt->bindParam($i+1, $fieldVals[$i]);
			}
			
			try{
				$stmnt->execute();
			}catch(PDOException $e){
				echo $e->getMessage();
			}
			$this->close();
		}
		
		/**
		*
		*	Public update Method
		*	executes an insert on the database
		*
		*	@param $dbTable			string	 
		*	@param $updateFieldList	array	('fieldName' => fieldVal)
		*	@param $conFieldList	array	('fieldName' => fieldVal)
		**/
		public function update($dbTable, $updateFieldList, $conFieldList){
			$this->getCon();
			
			$fields = array_keys($updateFieldList);  
			$conFields = array_keys($conFieldList);  
			
			$fieldParams = array();
			$conditionParams = array();
			
			// Create the ? field param list
			for($i = 0; $i < count($fields); $i++){
				$fieldParams[] =  "$fields[$i] = ?";
			}
			
			// Join the param list
			$fieldParams = implode(',', $fieldParams);

			// Set the conditions parameter list
			for($i = 0; $i < count($conFields); $i++){
				$conditionParams[] = "$conFields[$i] = ?";
			}
			
			// Join the param list
			$conditionParams = implode(',', $conditionParams);
	
			// Build the update query
			$query = "UPDATE $dbTable SET $fieldParams WHERE $conditionParams";
			
			$stmnt = $this->_connection->prepare($query);
			
			// Bind the parameters
			for($i = 0; $i < count($fields); $i++){
				$stmnt->bindParam($i+1, $updateFieldList[$i]);
			}

			// Bind the conditional parameters.
			for($i = count($fields); $i < count($fields) + count($conFields); $i++){
				$stmnt->bindParam($i+1, $conFieldList[$i - count($fields)]);
			}

			try{
				$stmnt->execute();
			}catch(PDOException $e){
				echo $e->getMessage();
			}
			
			$this->close();
		}
		
		/**
		*
		*	Public storedProc Method
		*	executes an insert on the database
		*
		*	@param $spName			string	 
		*	@param $inParams		array ('paramName' => paramVal)
		*	@param $outParams		array ('paramName' => paramVal)	
		**/
		public function storedProc($spName, $inParams, $outParams){
			$this->getCon();
			
			$inParamCount = 0;
			$outParamCount = 0;
			
			// params
			$inParamNames = array();
			$inParamVals = array();
			
			$outParamNames = array();
			$outParamVals = array();
			
			// Check if we have in or out params
			if($inParams != ""){
				$inParamNames = array_keys($inParams);  
				$inParamVals = array_values($inParams);
				
				$inParamCount = count($inParamNames);
			}
			
			if($outParams != ""){			
				$outParamNames = array_keys($inParams);  
				$outParamVals = array_values($inParams);	

				$outParamCount = count($outParamNames);				
			}

			// Build insert field and value list
			$paramPlaceholders = array();
			
			// Create the param list from input Params passed
			for($i = 0; $i < $inParamCount; $i++){
				$paramPlaceholders[] = ':' . $inParamNames[$i];
			}
			
			// Create the param list from output Params passed
			for($i = 0; $i < $outParamCount; $i++){
				$paramPlaceholders[] = ':' . $outParamNames[$i];
			}
			
			// Join the param list
			$paramPlaceholders = implode(',', $paramPlaceholders);
			
			// Create and execute the query
			$query = "call $spName($paramPlaceholders)";
			echo $query;
			
			$stmnt = $this->_connection->prepare($query);
			
			// Bind the inParameters
			for($i = 0; $i < $inParamCount; $i++){
				$stmnt->bindParam(':' . $inParamNames[$i], $inParamVals[$i]);
			}
			
			// Bind the outParameters
			for($i = 0; $i < $outParamCount; $i++){
				$stmnt->bindParam(':' . $outParamNames[$i], $outParamVals[$i], PDO::PARAM_INPUT_OUTPUT);
			}
			
			try{
				$stmnt->execute();
			}catch(PDOException $e){
				echo $e->getMessage();
			}
			$this->close();
		}
		
		/**
		*
		*	Public close Method
		*	close the connection to the database
		*
		**/
		public function close(){
			$this->_connection = null;
			$this->Connected = false;
		}
	}
