<?php //if(!defined("BASEPATH")) exit("No direct script access allowed"); // Uncomment this line if you are using CodeIgniter 2 
error_reporting(E_ALL); // To surpress errors, comment this line and `include` the class, vs `require_once`

/*
DON'T BE A DICK PUBLIC LICENSE

                    Version 1, December 2009

 Copyright (C) 2009 Philip Sturgeon <email@philsturgeon.co.uk>
 
 Everyone is permitted to copy and distribute verbatim or modified
 copies of this license document, and changing it is allowed as long
 as the name is changed.

                  DON'T BE A DICK PUBLIC LICENSE
    TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

  1. Do whatever you like with the original work, just don't be a dick.

     Being a dick includes - but is not limited to - the following instances:

 1a. Outright copyright infringement - Don't just copy this and change the name.
 1b. Selling the unmodified original with no work done what-so-ever, that's REALLY being a dick.
 1c. Modifying the original work to contain hidden harmful content. That would make you a PROPER dick.

  2. If you become rich through modifications, related works/services, or supporting the original work,
 share the love. Only a dick would make loads off this work and not buy the original works 
 creator(s) a pint.
 
  3. Code is provided with no warranty. Using somebody else's code and bitching when it goes wrong makes 
 you a DONKEY dick. Fix the problem yourself. A non-dick would submit the fix back.


***Able_Datatables***
* 
* This library is intended to translate your sql query into results that match the formating of the jQuery Datatables.net plugin
* 
* found at http://datatables.net/examples/data_sources/server_side.html
*
* @package    "Framework Independant'
* @@!!dependency php-sql-parser.php found @ http://code.google.com/p/php-sql-parser/
* @version    1.0
* @author     Peter Trerotola <petroz@mac.com>
* @location http://thumpin.tumblr.com
*
* //basic usage
* require_once('able_datatables');
* $datatables = new Able_Datatables();
* $sql = "SELECT `users`.`username`, `users`.`email`, `groups`.`name` FROM `users`,`groups` WHERE `users`.`group_id`=groups`.`group_id` AND `users`.`id` = '$user_id';
* $result = $datatables->generate($sql);
* print_r($result);
*
*/
class Able_Datatables
{	
	public function __construct()
	{	
		//database configuration options
		/*
		 *	This should be the only section you will need to edit
		 */
		$this->config = array(
			"db_name" => "able_datatables",
			"db_host" => "127.0.0.1",
			"db_user" => "able",
			"db_pass" => "able"
		);
	}

	public function generate($sql)
	{
		$db_connection = $this->db();
		//parse the query into an array
		$query_array = $this->parse_query($sql);
		
		//build an array of all the Datatable POST options
		$options = $this->parse_options();
		
		//rebuild each query part to support the options
		$select = $this->build_select($query_array, $options);
		$from = $this->build_from($query_array, $options);
		$where = $this->build_where($query_array, $options); 
		$group_by = $this->build_group_by($query_array, $options);
		$order_by = $this->build_order_by($query_array, $options);
		$limit = $this->build_limit($query_array, $options);
		
		//build queries
		//used for getting totals... ie pagination 
		$base_query = $select.$from.$where.$group_by.$order_by;
		//used for creating data
		$final_query = $select.$from.$where.$group_by.$order_by.$limit;
		
		//build json payload for datatables
		$payload = array(
			"iTotalRecords" => $this->count_total_records($base_query),
			"iTotalDisplayRecords" => $this->count_total_records($base_query),
			"sEcho" => $options['sEcho'],
			"sColumns" => $this->build_columns_string($query_array),
			"aaData" => $this->fetch_data($final_query),
		);
		$payload = json_encode($payload);
		
		print_r($payload);
		//print_r(get_defined_vars());
	}
	
	private function db()
	{
		//database setup
		$db = mysql_connect ($this->config['db_host'], $this->config['db_user'], $this->config['db_pass']) or die ('I cannot connect to the database because: ' . mysql_error());
		mysql_select_db ($this->config['db_name']); 
	}	
	
	private function parse_query($sql)
	{
		//parse the mysql into a array 
		require_once('php-sql-parser.php');
		$parser = new PHPSQLParser($sql);
		
		return $parser->parsed;		
	}
	
	private function parse_options()
	{
		foreach($_POST as $key => $val)
		{
			$options[$key] = $val;
		}
		
		return $options;	
	}
	
	private function build_select($query_array, $options)
	{
		$select = '';
		$select_array = $query_array['SELECT'];
		
		$select .= "SELECT ";
		//loop through each expression and build SELECT string
		foreach($select_array as $select_expression)
		{
			$select .= $select_expression['base_expr'].$select_expression['alias'];
			$select .= ',';
		}
		//remove the last comma
		$select = substr($select, 0, -1); 
		
		return $select;
	}
	
	private function build_from($query_array, $options)
	{
		$from = '';
		$from_array = $query_array['FROM'][0];
		
		$from .= "FROM ";
		$from .= $from_array['table'];
		$from .= " ";
		
		return $from;
	}
	
	private function build_where($query_array, $options)
	{
		//add WHERE starting poing
		$where = '';		
		
		if(!empty($query_array['WHERE']))
		{
			//build where array
			$where_array = $query_array['WHERE'];	
	
			//start the where
			$where .= 'WHERE ';
			 
			//get columns array
			$columns_array = $this->build_columns_array($query_array);

			//if there is a search string			
			if(!empty($options['sSearch']))
			{
				//check for enabled columns
				$i = 0;
				$columns_length = count($columns_array);
				for($i; $i < intval($columns_length); $i++)
				{
					//create the options boolean array
					$searchable_columns['bSearchable_'.$i] = $options['bSearchable_'.$i];
				}
				
				//loop through searchable_columns for true values
				foreach($searchable_columns as $searchable_column_key => $searchable_column_val)
				{
					if($searchable_column_val == true)
					{
						//get an integer from the searchable_column key
						$column_id = preg_replace("/[^0-9]/", '', $searchable_column_key);
						
						//lookup column name by index
						foreach($columns_array as $columns_array_key => $columns_array_val)
						{
							//if the $columns_array_key matches the $column_id
							if($columns_array_key == $column_id)
							{
								//loop to build where foreach base expression
								$i = 0;
								$where_length = count($where_array);
								for($i; $i < intval($where_length); $i++)
								{
									//append the existing WHERE Expressions
									$where .= $where_array[$i]['base_expr'];
								} 								
								
								//append the LIKE '%$options['sSearch'])%'
								$where .= ' AND '.$columns_array_val." LIKE '%".$options['sSearch']."%' OR ";
							}
						}	
					}
				}
				//remove the last OR
				$where = substr_replace($where, "", -3);									
			}
			else
			{
				//loop to build where
				$i = 0;
				$where_length = count($where_array);
				for($i; $i < intval($where_length); $i++)
				{
					$where .= $where_array[$i]['base_expr'];
				} 
			}			 
		}
		
		//print_r($where_length);
		return $where;
	}
	
	private function build_group_by($query_array, $options)
	{
		$group_by = '';
		
		if(!empty($query_array['GROUP']))
		{
			$group_by_array = $query_array['GROUP'][0]; 
			$group_by .= ' GROUP BY ';
			$group_by .= $group_by_array['base_expr'];
			$group_by .= ' ';
		}
		
		return $group_by;
	}
	
	private function build_order_by($query_array, $options)
	{
		$order_by = '';
		
		//check if sorting is set
		if(!empty($options['iSortingCols']))
		{
			$order_by .= " ORDER BY ";
			//get columns
			$columns_array = $this->build_columns_array($query_array);	
		
			$i = 0;
			$sort_length = intval($options['iSortingCols']);
			for($i; $i < $sort_length; $i++)
			{
				$order_by .= $columns_array[intval($options['iSortCol_'.$i])]." ".strtoupper($options['sSortDir_'.$i]).",";
			}
			
			$order_by = substr_replace($order_by, "", -1);
		}
		
		return $order_by;		
	}
	
	private function build_limit($query_array, $options)
	{
		$iDisplayStart = intval($options['iDisplayStart']);
		$iDisplayLength = intval($options['iDisplayLength']);
		
		//offset
		if(!empty($iDisplayStart))
		{
			$offset = $iDisplayStart;
		}
		else
		{
			$offset = 0;
		}		
		
		//limit
		if(!empty($iDisplayLength))
		{
			$limit = " LIMIT ".$offset.",".$iDisplayLength;
		}
		else
		{
			$limit = " LIMIT ".$offset.",10";	
		}
		
		return $limit;	
	}
	
	private function build_columns_array($query_array)
	{
		$columns_array = array();
		foreach($query_array['SELECT'] as $column)
		{
			$columns_array[] = $column['base_expr'].$column['alias'];
		}
		
		return $columns_array;		
	}
	
	private function build_columns_string($query_array)
	{
		foreach($query_array['SELECT'] as $column)
		{
			$columns_array[] = $column['alias'];
		}	
		$columns_string = '';
		$columns_string .= implode(',', $columns_array);
		$columns_string = str_replace('`', "", $columns_string);			
		return $columns_string;			
	}
	
	private function count_total_records($base_query)
	{
		$query = mysql_query($base_query) or die(mysql_error());
		$count = mysql_num_rows($query);
		
		return $count;
	}
	
	private function fetch_data($final_query)
	{
		$data = array();
		$query = mysql_query($final_query) or die(mysql_error());
		if(mysql_num_rows($query) > 0)
		{
			while($row = mysql_fetch_array($query, MYSQL_ASSOC))
			{
				$data[] = array_values($row);
			}			
			
		}
		
		return $data;
	}
/*end of class*/	
}
/*end of file*/