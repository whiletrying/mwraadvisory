<?php

class parseCSV {

/*

	# Modified by Aryan Duntley for the purposes of the gravity-forms-mass-import wordpress plugin.

	# TAKE NOTE!!!!!!!!   THIS FILE has been modified to suit the purposes of this plugin.  For this reason	

	# if you are attempting to use parseCSV in a manner pertaining to the original code, it will likely not 

	#work as anticipated.



	Class: parseCSV v0.3.2

	http://code.google.com/p/parsecsv-for-php/

	

	

	Fully conforms to the specifications lined out on wikipedia:

	 - http://en.wikipedia.org/wiki/Comma-separated_values

	

	Based on the concept of Ming Hong Ng's CsvFileParser class:

	 - http://minghong.blogspot.com/2006/07/csv-parser-for-php.html

	

	

	

	Copyright (c) 2007 Jim Myhrberg (jim@zydev.info).



	Permission is hereby granted, free of charge, to any person obtaining a copy

	of this software and associated documentation files (the "Software"), to deal

	in the Software without restriction, including without limitation the rights

	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell

	copies of the Software, and to permit persons to whom the Software is

	furnished to do so, subject to the following conditions:



	The above copyright notice and this permission notice shall be included in

	all copies or substantial portions of the Software.



	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR

	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,

	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE

	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER

	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,

	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN

	THE SOFTWARE.

	

	# Modified by Aryan Duntley for the purposes of the gravity-forms-mass-import wordpress plugin.

	# TAKE NOTE!!!!!!!!   THIS FILE has been modified to suit the purposes of this plugin.  For this reason 

	# the above access calls may not work as anticipated.

*/





	/**

	 * Configuration

	 * - set these options with $object->var_name = 'value';

	 */

	

	# use first line/entry as field names

	public $heading = true;

	

	# override field names

	public $fields = array();

	

	# sort entries by this field

	private $sort_by = null;

	private $sort_reverse = false;

	

	# delimiter (comma) and enclosure (double quote)

	private $delimiter = ',';

	private $enclosure = '"';

	

	# basic SQL-like conditions for row matching

	private $conditions = null;

	

	# number of rows to ignore from beginning of data

	private $offset = null;

	

	# limits the number of returned rows to specified amount

	private $limit = null;

	

	# number of rows to analyze when attempting to auto-detect delimiter

	private $auto_depth = 15;

	

	# characters to ignore when attempting to auto-detect delimiter

	private $auto_non_chars = "a-zA-Z0-9\n\r";

	

	# preferred delimiter characters, only used when all filtering method

	# returns multiple possible delimiters (happens very rarely)

	private $auto_preferred = ",;\t.:|";

	

	# character encoding options

	private $convert_encoding = false;

	private $input_encoding = 'ISO-8859-1';

	private $output_encoding = 'ISO-8859-1';

	

	# used by unparse(), save(), and output() functions

	private $linefeed = "\r\n";

	

	# only used by output() private function

	private $output_delimiter = ',';

	private $output_filename = 'data.csv';

	

	# used to check whether user has allowed processing after a caution was given

	public $goAhead = false;

	public $actHeaders = "";

	

	//private $rower_counter = 0;

	/**

	 * Internal variables

	 */

	

	# current file

	private $file;

	

	# loaded file contents

	private $file_data;

	

	# array of field values in data parsed

	public $titles = array();

	

	# two dimentional array of CSV data

	public $data = array();

	

	# last entry id of current form

	public $last_form_id;

	

	# current form being used

	public $form_using;

	

	#usable array for placing entries (no excess)

	public $usable_array;

	

	#count of total rows

	public $total_of_rows;

	

	/**

	 * Constructor

	 * @param   input   CSV file or string

	 * @return  nothing

	 */

	public function parseCSV ($input = null, $offset = null, $limit = null, $conditions = null) {

		if ( $offset !== null ) $this->offset = $offset;

		if ( $limit !== null ) $this->limit = $limit;

		if ( count($conditions) > 0 ) $this->conditions = $conditions;

		if ( !empty($input) ) $this->parse($input);

		
		$this->usable_array=array();

	}

	

	

	// ==============================================

	// ----- [ Main Functions ] ---------------------

	// ==============================================

	

	/**

	 * Parse CSV file or string

	 * @param   input   CSV file or string

	 * @return  nothing

	 */

	public function parse ($input = null, $offset = null, $limit = null, $conditions = null) {

		if ( !empty($input) ) {

			if ( $offset !== null ) $this->offset = $offset;

			if ( $limit !== null ) $this->limit = $limit;

			if ( count($conditions) > 0 ) $this->conditions = $conditions;

			if ( is_readable($input) ) {

				$this->data = $this->parse_file($input);

			} else {

				$this->file_data = &$input;

				$this->data = $this->parse_string();

			}

			if ( $this->data === false ) return false;

		}

		return true;

	}

	

	/**

	 * Generate CSV based string for output

	 * @param   output      if true, prints headers and strings to browser

	 * @param   filename    filename sent to browser in headers if output is true

	 * @param   data        2D array with data

	 * @param   fields      field names

	 * @param   delimiter   delimiter used to separate data

	 * @return  CSV data using delimiter of choice, or default

	 */

	public function output ($output = true, $filename = null, $data = array(), $fields = array(), $delimiter = null) {

		if ( empty($filename) ) $filename = $this->output_filename;

		if ( $delimiter === null ) $delimiter = $this->output_delimiter;

		$data = $this->unparse($data, $fields, null, null, $delimiter);

		if ( $output ) {

			header('Content-type: application/csv');

			header('Content-Disposition: inline; filename="'.$filename.'"');

			echo $data;

		}

		return $data;

	}

	

	/**

	 * Convert character encoding

	 * @param   input    input character encoding, uses default if left blank

	 * @param   output   output character encoding, uses default if left blank

	 * @return  nothing

	 */

	public function encoding ($input = null, $output = null) {

		$this->convert_encoding = true;

		if ( $input !== null ) $this->input_encoding = $input;

		if ( $output !== null ) $this->output_encoding = $output;

	}

	

	/**

	 * Auto-Detect Delimiter: Find delimiter by analyzing a specific number of

	 * rows to determine most probable delimiter character

	 * @param   file           local CSV file

	 * @param   parse          true/false parse file directly

	 * @param   search_depth   number of rows to analyze

	 * @param   preferred      preferred delimiter characters

	 * @param   enclosure      enclosure character, default is double quote (").

	 * @return  delimiter character

	 */

	public function auto ($file = null, $parse = true, $search_depth = null, $preferred = null, $enclosure = null) {

		

		if ( $file === null ) $file = $this->file;

		if ( empty($search_depth) ) $search_depth = $this->auto_depth;

		if ( $enclosure === null ) $enclosure = $this->enclosure;

		

		if ( $preferred === null ) $preferred = $this->auto_preferred;

		

		if ( empty($this->file_data) ) {

			if ( $this->_check_data($file) ) {

				$data = &$this->file_data;

			} else return false;

		} else {

			$data = &$this->file_data;

		}

		

		$chars = array();

		$strlen = strlen($data);

		$enclosed = false;

		$n = 1;

		$to_end = true;

		

		// walk specific depth finding posssible delimiter characters

		for ( $i=0; $i < $strlen; $i++ ) {

			$ch = $data{$i};

			$nch = ( isset($data{$i+1}) ) ? $data{$i+1} : false ;

			$pch = ( isset($data{$i-1}) ) ? $data{$i-1} : false ;

			

			// open and closing quotes

			if ( $ch == $enclosure && (!$enclosed || $nch != $enclosure) ) {

				$enclosed = ( $enclosed ) ? false : true ;

			

			// inline quotes	

			} elseif ( $ch == $enclosure && $enclosed ) {

				$i++;



			// end of row

			} elseif ( ($ch == "\n" && $pch != "\r" || $ch == "\r") && !$enclosed ) {

				if ( $n >= $search_depth ) {

					$strlen = 0;

					$to_end = false;

				} else {

					$n++;

				}

				

			// count character

			} elseif (!$enclosed) {

				if ( !preg_match('/['.preg_quote($this->auto_non_chars, '/').']/i', $ch) ) {

					if ( !isset($chars[$ch][$n]) ) {

						$chars[$ch][$n] = 1;

					} else {

						$chars[$ch][$n]++;

					}

				}

			}

		}

		

		// filtering

		$depth = ( $to_end ) ? $n-1 : $n ;

		$filtered = array();

		foreach( $chars as $char => $value ) {

			if ( $match = $this->_check_count($char, $value, $depth, $preferred) ) {

				$filtered[$match] = $char;

			}

		}

		

		// capture most probable delimiter

		ksort($filtered);

		$delimiter = reset($filtered);

		$this->delimiter = $delimiter;

		

		// parse data

		if ( $parse ) {

		//echo $this->parse_string();

		$this->data = $this->parse_string();

		

		}

		//return $delimiter;

		return $this->data;

		

	}

	

	

	// ==============================================

	// ----- [ Core Functions ] ---------------------

	// ==============================================

	

	/**

	 * Read file to string and call parse_string()

	 * @param   file   local CSV file

	 * @return  2D array with CSV data, or false on failure

	 */

	public function parse_file ($file = null) {

		if ( $file === null ) $file = $this->file;

		if ( empty($this->file_data) ) $this->load_data($file);

		return ( !empty($this->file_data) ) ? $this->parse_string() : false ;

	}

	

	/**

	 * Parse CSV strings to arrays

	 * @param   data   CSV string

	 * @return  2D array with CSV data, or false on failure

	 */

	public function parse_string ($data = null) {

	global $wpdb;

		if ( empty($data) ) {

			if ( $this->_check_data() ) {

				$data = &$this->file_data;

			} else return false;

		}
                 
		
		$rows = array();

		$row = array();

		$row_count = 0;

		$current = '';

		$head = ( !empty($this->fields) ) ? $this->fields : array() ;

		$col = 0;

		$enclosed = false;

		$was_enclosed = false;
                                                                                                        
		if( substr($data, 0,3) == pack("CCC",0xef,0xbb,0xbf) ) { $data = substr($data, 3); }
                                                                                                        
		$strlen = strlen($data);

		

		//  walk through each character

		for ( $i=0; $i < $strlen; $i++ ) {

			$ch = $data{$i};

			$nch = ( isset($data{$i+1}) ) ? $data{$i+1} : false ;

			$pch = ( isset($data{$i-1}) ) ? $data{$i-1} : false ;

			

			// open and closing quotes

			if ( $ch == $this->enclosure && (!$enclosed || $nch != $this->enclosure) ) {

				$enclosed = ( $enclosed ) ? false : true ;

				if ( $enclosed ) $was_enclosed = true;

			

			// inline quotes	

			} elseif ( $ch == $this->enclosure && $enclosed ) {

				$current .= $ch;

				$i++;



			// end of field/row

			} elseif ( ($ch == $this->delimiter || ($ch == "\n" && $pch != "\r") || $ch == "\r") && !$enclosed ) {

				if ( !$was_enclosed ) $current = trim($current);

				$key = ( !empty($head[$col]) ) ? $head[$col] : $col ;

				$row[$key] = $current;

				$current = '';

				$col++;

			

				// end of row

				if ( $ch == "\n" || $ch == "\r" ) {

					if ( $this->_validate_offset($row_count) && $this->_validate_row_conditions($row, $this->conditions) ) {

						if ( $this->heading && empty($head) ) {//$this->goAhead = false; can use this to bypass.

							$head = $row;
                                                       
							$diffs = $this->check_all_headers($head);
							
                                                        
							if((sizeof($diffs) > 0)){
								

								$errHTML = '<div><p>You have some invalid headers: </p></div><div id="errorReport" style="margin-top:15px"><p><ul>';

								for($lu=0;$lu<sizeof($diffs);$lu++){

									$errHTML .= '<li style="background-color:#FFFFC7;">' . $diffs[$lu] . '</li>';

									

								}

								$errHTML .= '</ul></p></div>';

								//Notify user that there are headers in their files that don't match.  Show them the ones that don't match and reference the correct headers above.  Ask users to adjust file accordingly.  Later, give option to modify file itself and then retry (adjust string then call this function again with new string.  Or adjust $head externally and call function again, but it won't check if $head is not empty.

								

								$this->goAhead = false;

								return $errHTML;

							}

							else{$this->goAhead = true;}

							/*

							* Check all headers here return error or caution codes.  Use goAhead variable to determine whether to bypass cautions.

							* Verify that header names match those processed by previous code.  Give errors if not, warnings if ommissions or extra.

							* Any headers from db that are not on csv must be added to the head array or new, complete array.

							* 

							* if($this->goAhead == false){}

							*/

							

						} 
                                                elseif ( empty($this->fields) || (!empty($this->fields) && (($this->heading && $row_count > 0) || !$this->heading)) ) {

							if ( !empty($this->sort_by) && !empty($row[$this->sort_by]) ) {

								if ( isset($rows[$row[$this->sort_by]]) ) {

									$rows[$row[$this->sort_by].'_0'] = &$rows[$row[$this->sort_by]];

									unset($rows[$row[$this->sort_by]]);

									for ( $sn=1; isset($rows[$row[$this->sort_by].'_'.$sn]); $sn++ ) {}

									$rows[$row[$this->sort_by].'_'.$sn] = $row;

								} else $rows[$row[$this->sort_by]] = $row;

							} 

							else {

							

									//Check sizeof($row) against sizeof($this->usable_array)?

									if($this->goAhead){

									$current_user = wp_get_current_user();

									$this->last_form_id = $this->last_form_id+1;

									//$nuall = mysql_real_escape_string('NULL');

									

									//$wpdb->flush();

									$entry_tracking_table =  $wpdb->prefix . "rg_lead";

									$entry_standard_stuff =  $wpdb->prefix . "rg_lead_meta";

									$actual_entry_data =  $wpdb->prefix . "rg_lead_detail";

									$actual_entry_data_long =  $wpdb->prefix . "rg_lead_detail_long";

									//Create a new rg_lead database entry:								

									$_id = $this->last_form_id;

									$_form_id = $this->form_using;

									$_ip = $this->getRealIpAddr();

									$_source_url =  get_bloginfo('wpurl');

									$_user_agent = $_SERVER['HTTP_USER_AGENT'];

									//$_currency = 'USD';

									//payment_status = NULL;

									//payment_date = NULL;

									//payment_amount = NULL;

									//transaction_id = NULL;

									//is_fulfilled = NULL;

									$_created_by = $current_user->ID;

									//transaction_type = NULL;

									//$_status = 'active';

									

							/*$wpdb->query( $wpdb->prepare("INSERT INTO $entry_tracking_table (id, form_id, date_created, ip, source_url, user_agent, currency, created_by, status) VALUES (%d,%d,utc_timestamp(),%s,%s,%s,%s,{$_created_by},%s)",$_id, $_form_id, $_ip, $_source_url, $_user_agent, 'USD', 'active'));*/

							

							$daterow = $row['actualPostDate'];

                            if($daterow){$_created_date = gmdate("Y-m-d H:i:s", strtotime($daterow));}else{$_created_date = current_time('mysql');}
  
 
							 
                            $wpdb->query( $wpdb->prepare("INSERT INTO $entry_tracking_table (id, form_id, date_created, ip, source_url, user_agent, currency, created_by, status) VALUES (%d,%d,%s,%s,%s,%s,%s,{$_created_by},%s)",$_id, $_form_id, $_created_date, $_ip, $_source_url, $_user_agent, 'USD', 'active'));

			   $wpdb->query( $wpdb->prepare("INSERT INTO $entry_standard_stuff(lead_id, meta_key, meta_value) VALUES(%d, %s, %s)", $this->last_form_id, 'gform_product_info', 'a:2:{s:8:"products";a:0:{}s:8:"shipping";a:2:{s:4:"name";s:0:"";s:5:"price";b:0;}}' ) );	

									

									//multiselect, list, checkbox

									//list types are input as json array.

									//multiselect types are input as a,b,c etc...

									//checkbox types are imput as (field id =x) x.1 = value, x.2 = value, etc...

									//LOOP THROUGH $row...
									
								        
                                                                        
                                                                        foreach($this->usable_array as $temp_value){
                                                                            $temp_label = $temp_value['label'];
                                                                             $temp_type = $temp_value['type'];
                                                                            
                                                                           
                                                                            $increment= 0;
                                                                            foreach($row as $temp_key => $values){
                                                                                
                                                                                
                                                                            if($temp_label == reset(explode(' ',$temp_key)) && $temp_type == 'list' && is_numeric(end(explode(' ',$temp_key)))){
                                                                                $increment++;
                                                                                 unset($row[$temp_label.' '.$increment]);
                                                                                $row[$temp_label][$increment] = $values;
                                                                               
                                                                            }
                                                                            
                                                                            }
                                                                           
                                                                        }
                                                                        
                                                                       //echo '<pre>';
                                                                       //print_r($row);
                                                                       //print_r($this->usable_array);
								       //exit;
                                                                        

									for ($ua=0;$ua<sizeof($this->usable_array);$ua++){

											$thlb = $this->usable_array[$ua]['label'];

											if(array_key_exists($thlb, $row)){

												$wpdb->show_errors();

												$toArr = array();

												$_valu = "";

												
																	
												
												switch($this->usable_array[$ua]['type']){

													case 'list':
                                                                                                            
                                                                                                           $thcl= $this->usable_array[$ua]['choices'];
                                                                                                           
                                                                                                       
														$_field_num = $this->usable_array[$ua]['id'];
                                                                                                                
                                                                                                                if(count($row[$thlb])!= 0){
                                                                                                                foreach($row[$thlb] as $key => $temp){
														
                                                                                                                    if(strrpos('|',$temp) != -1){
                                                                                                                     if(count($thcl) == count(explode('|', $temp)) && $temp != "" ){   
                                                                                                                 $toArr[] = array_combine($thcl, explode('|', $temp));
                                                                                                                    }else{
                                                                                                                        
                                                                                                                        //var_dump($temp).'</br>';
                                                                                                                     //print_r($thcl);
                                                                                                                    }
                                                                                                                    }
                                                                                                                 }
                                                                                                          
                                                                                                            }
														$_valu = maybe_serialize($toArr);

														//$_valu = mysql_real_escape_string($_valu);

														$wpdb->show_errors(); 
                                                                                                       
														$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data (lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_field_num,$_valu) );
//echo $wpdb->prepare("INSERT INTO $actual_entry_data (lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_field_num,$_valu);

													break;

													case 'multiselect':

														$_field_num = $this->usable_array[$ua]['id'];

														$_valu = $row[$thlb];

														//$_valu = mysql_real_escape_string($_valu);

														$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data(lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_field_num,$_valu) );

													break;

													case 'checkbox':

														$_field_num = $this->usable_array[$ua]['id'];

														$toArr = explode('|', $row[$thlb]);

														for ($doti=0;$doti<sizeof($toArr);$doti++){

																if($toArr[$doti] == null || $toArr[$doti] == undefined){$_valu = "";}else{

																$_valu = $toArr[$doti];}					

																//$_valu = mysql_real_escape_string($_valu);

																$strNum = (string)$_field_num;

																/*if(strstr($strNum, '0') === "0"){

																$strNum = str_replace("0", "1", $strNum);

																$strNum = $strNum . '.' . ($doti+1);}

																else{$strNum = $strNum . '.' . ($doti+1);}*/
																
																$strNum = $strNum . '.' . ($doti+1);

																/*$a = 0.999999;

																$b = (float)$strNum;

																bcscale(20);

																$ab = bcmul($a,$b);

																$strNum = (string)$ab;*/

																$_new_num = floatval($strNum);

																$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data(lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_new_num,$_valu) );

															}

													break;

													case 'address':

														$_field_num = $this->usable_array[$ua]['id'];

														$toArr = explode(',', $row[$thlb]);

														for ($doti=0;$doti<sizeof($toArr);$doti++){

																if($toArr[$doti] == null || $toArr[$doti] == undefined){$_valu = "";}else{

																$_valu = $toArr[$doti];}

																//$_valu = mysql_real_escape_string($_valu);

																$strNum = (string)$_field_num;

																/*if(strstr($strNum, '0') === "0"){

																$strNum = str_replace("0", "1", $strNum);

																$strNum = $strNum . '.' . ($doti+1);}

																else{$strNum = $strNum . '.' . ($doti+1);}*/
																
																$strNum = $strNum . '.' . ($doti+1);

																/*$a = 0.999999;

																$b = (float)$strNum;

																bcscale(20);

																$ab = bcmul($a,$b);

																$strNum = (string)$ab;*/

																$_new_num = floatval($strNum); 

																$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data(lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_new_num,$_valu) );

															}

													break;

													case 'name':

														$_field_num = $this->usable_array[$ua]['id'];

														$toArr = explode(',', $row[$thlb]);

														if($toArr[0] == null || $toArr[0] == undefined){$firstValname = "";}else{$firstValname = $toArr[0];}

														if($toArr[1] == null || $toArr[1] == undefined){$lastValname = "";}else{$lastValname = $toArr[1];}

														for ($doti=0;$doti<2;$doti++){

																$strNum = (string)$_field_num;

																if($doti == 0){

																$_valu = $firstValname;

																//$_valu = mysql_real_escape_string($_valu);

																$strNum = $strNum . '.' . '3';}

																else{

																$_valu = $lastValname;

																$strNum = $strNum . '.' . '6';

																}

																/*$a = 0.999999;

																$b = (float)$strNum;

																bcscale(20);

																$ab = bcmul($a,$b);

																$strNum = (string)$ab;*/

																$_new_num = floatval($strNum); 

																$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data(lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_new_num,$_valu) );

															}

													break;

													default:

														$_field_num = $this->usable_array[$ua]['id'];

														$_valu = $row[$thlb];
                                                                                                                

														 $_valu = trim($_valu);
                                                                                                                

														 //$_valu = mysql_real_escape_string($_valu);
                                                                                                                

														$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data(lead_id, form_id, field_number, value) VALUES(%d, %d, %f, %s)", $_id, $_form_id, $_field_num,$_valu) );

														//Long data db entry provided by Will Fairhurst will@tic.co

														if (strlen($_valu)>200) {

															$last_lead_detail_id = $wpdb->insert_id;

															$wpdb->query( $wpdb->prepare("INSERT INTO $actual_entry_data_long(lead_detail_id, value) VALUES(%d, %s)", $last_lead_detail_id,$_valu) );

														}	

													}	

												}

										}
                                                                               

									}

								}

						}

					}

					$row = array();

					$col = 0;

					$row_count++;

					if ( $this->sort_by === null && $this->limit !== null && count($rows) == $this->limit ) {

						$i = $strlen;

					}

				}

				

			// append character to current field

			} else {

				$current .= $ch;

			}

		}
        
		$this->titles = $head;

		// Don't need these below.

		/*

		if ( !empty($this->sort_by) ) {

			( $this->sort_reverse ) ? krsort($rows) : ksort($rows) ;

			if ( $this->offset !== null || $this->limit !== null ) {

				$rows = array_slice($rows, ($this->offset === null ? 0 : $this->offset) , $this->limit, true);

			}

		}

		*/

		$this->total_of_rows = $row_count-1;

		return $this->total_of_rows . " rows of data have been imported.  Please check Forms->Entries to verify your data was imported correctly.";

		//return $rows;

		// Return row_count and complete message here.

	}

	/**

	 * Return the headers alone.

	 * Returns an array if headers exist or false if not.

	 */

	/*public function getHeads (){

		if (sizeof($this->titles) > 0){return $this->titles;}else{return false;}

		}*/

	/*public function getSizer (){

		return $this->rower_counter++;

		}*/

	public function check_all_headers($hdrs){

            //$fledarr = array();
	//$frmarr = RGFormsModel::get_form_meta_by_id('2');/*Changed from get_forms_by_id as noted by Redpik https://wordpress.org/support/profile/redpik*/
	//$fldobj = $frmarr[0]['fields'];
            
            //echo '<pre>';
            //print_r($fldobj);
            //exit;
            
            
               //echo '<pre>';
               //print_r($this->actHeaders);
              // print_r($hdrs);
            // exit;
            
          $advance_field_arr = array('list','checkbox');
		$diffarr = array();

		for($hdr=0;$hdr<sizeof($hdrs);$hdr++){

			$thsElm = $hdrs[$hdr];
                        $field_array = array('list','checkbox','html');
                                                                                                        
		for($actH=0;$actH<sizeof($this->actHeaders);$actH++){				

                                  if($this->actHeaders[$actH]['type'] != 'html'){                                                                      
                                 if($this->actHeaders[$actH]['type'] == 'list' && $hdrs[$hdr] != $this->actHeaders[$actH]['label'] ){   
                                     
                                     if($this->actHeaders[$actH]['label'] == reset(explode(' ',$thsElm)) && is_numeric(end(explode(' ',$thsElm)))  ){
                                  
                                         
                                     $this->usable_array[]=$this->actHeaders[$actH];continue (2);   
                                    
                                     }
                                 }
                                 else{ 
                                   
				if ($hdrs[$hdr] == $this->actHeaders[$actH]['label']  ){
                                                                                             
				$this->usable_array[]=$this->actHeaders[$actH];continue (2);
                                
                                }
				else if($actH == (sizeof($this->actHeaders)-1) && !in_array($this->actHeaders[$actH]['type'],$field_array) ){
                                    
						$diffarr[] = $thsElm;
					}

                                        
                                 }
                                 
                                  }
                                 
			}

		}
              
		return $diffarr;	

	}

	public function getRealIpAddr(){

		if (!empty($_SERVER['HTTP_CLIENT_IP'])){$ip=$_SERVER['HTTP_CLIENT_IP'];}

		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){ $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];}

		else{$ip=$_SERVER['REMOTE_ADDR'];}

		return $ip;

	}

	

	/**

	 * Load local file or string

	 * @param   input   local CSV file

	 * @return  true or false

	 */

	public function load_data ($input = null) {

		$data = null;

		$file = null;

		if ( $input === null ) {

			$file = $this->file;

		} elseif ( file_exists($input) ) {

			$file = $input;

		} else {

			$data = $input;

		}

		if ( !empty($data) || $data = $this->_rfile($file) ) {

			if ( $this->file != $file ) $this->file = $file;

			if ( preg_match('/\.php$/i', $file) && preg_match('/<\?.*?\?>(.*)/ims', $data, $strip) ) {

				$data = ltrim($strip[1]);

			}

			if ( $this->convert_encoding ) $data = iconv($this->input_encoding, $this->output_encoding, $data);

			if ( substr($data, -1) != "\n" ) $data .= "\n";

			$this->file_data = &$data;

			return true;

		}

		return false;

	}

	

	

	// ==============================================

	// ----- [ Internal Functions ] -----------------

	// ==============================================

	

	/**

	 * Validate a row against specified conditions

	 * @param   row          array with values from a row

	 * @param   conditions   specified conditions that the row must match 

	 * @return  true of false

	 */

	public function _validate_row_conditions ($row = array(), $conditions = null) {

		if ( !empty($row) ) {

			if ( !empty($conditions) ) {

				$conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions) ;

				$or = '';

				foreach( $conditions as $key => $value ) {

					if ( strpos($value, ' AND ') !== false ) {

						$value = explode(' AND ', $value);

						$and = '';

						foreach( $value as $k => $v ) {

							$and .= $this->_validate_row_condition($row, $v);

						}

						$or .= (strpos($and, '0') !== false) ? '0' : '1' ;

					} else {

						$or .= $this->_validate_row_condition($row, $value);

					}

				}

				return (strpos($or, '1') !== false) ? true : false ;

			}

			return true;

		}

		return false;

	}

	

	/**

	 * Validate a row against a single condition

	 * @param   row          array with values from a row

	 * @param   condition   specified condition that the row must match 

	 * @return  true of false

	 */

	public function _validate_row_condition ($row, $condition) {

		$operators = array(

			'=', 'equals', 'is',

			'!=', 'is not',

			'<', 'is less than',

			'>', 'is greater than',

			'<=', 'is less than or equals',

			'>=', 'is greater than or equals',

			'contains',

			'does not contain',

		);

		$operators_regex = array();

		foreach( $operators as $value ) {

			$operators_regex[] = preg_quote($value, '/');

		}

		$operators_regex = implode('|', $operators_regex);

		if ( preg_match('/^(.+) ('.$operators_regex.') (.+)$/i', trim($condition), $capture) ) {

			$field = $capture[1];

			$op = $capture[2];

			$value = $capture[3];

			if ( preg_match('/^([\'\"]{1})(.*)([\'\"]{1})$/i', $value, $capture) ) {

				if ( $capture[1] == $capture[3] ) {

					$value = $capture[2];

					$value = str_replace("\\n", "\n", $value);

					$value = str_replace("\\r", "\r", $value);

					$value = str_replace("\\t", "\t", $value);

					$value = stripslashes($value);

				}

			}

			if ( array_key_exists($field, $row) ) {

				if ( ($op == '=' || $op == 'equals' || $op == 'is') && $row[$field] == $value ) {

					return '1';

				} elseif ( ($op == '!=' || $op == 'is not') && $row[$field] != $value ) {

					return '1';

				} elseif ( ($op == '<' || $op == 'is less than' ) && $row[$field] < $value ) {

					return '1';

				} elseif ( ($op == '>' || $op == 'is greater than') && $row[$field] > $value ) {

					return '1';

				} elseif ( ($op == '<=' || $op == 'is less than or equals' ) && $row[$field] <= $value ) {

					return '1';

				} elseif ( ($op == '>=' || $op == 'is greater than or equals') && $row[$field] >= $value ) {

					return '1';

				} elseif ( $op == 'contains' && preg_match('/'.preg_quote($value, '/').'/i', $row[$field]) ) {

					return '1';

				} elseif ( $op == 'does not contain' && !preg_match('/'.preg_quote($value, '/').'/i', $row[$field]) ) {

					return '1';

				} else {

					return '0';

				}

			}

		}

		return '1';

	}

	

	/**

	 * Validates if the row is within the offset or not if sorting is disabled

	 * @param   current_row   the current row number being processed

	 * @return  true of false

	 */

	public function _validate_offset ($current_row) {

		if ( $this->sort_by === null && $this->offset !== null && $current_row < $this->offset ) return false;

		return true;

	}

	

	/**

	 * Enclose values if needed

	 *  - only used by unparse()

	 * @param   value   string to process

	 * @return  Processed value

	 */

	public function _enclose_value ($value = null) {

		if ( $value !== null && $value != '' ) {

			$delimiter = preg_quote($this->delimiter, '/');

			$enclosure = preg_quote($this->enclosure, '/');

			if ( preg_match("/".$delimiter."|".$enclosure."|\n|\r/i", $value) || ($value{0} == ' ' || substr($value, -1) == ' ') ) {

				$value = str_replace($this->enclosure, $this->enclosure.$this->enclosure, $value);

				$value = $this->enclosure.$value.$this->enclosure;

			}

		}

		return $value;

	}

	

	/**

	 * Check file data

	 * @param   file   local filename

	 * @return  true or false

	 */

	public function _check_data ($file = null) {

		if ( empty($this->file_data) ) {

			if ( $file === null ) $file = $this->file;

			return $this->load_data($file);

		}

		return true;

	}

	

	

	/**

	 * Check if passed info might be delimiter

	 *  - only used by find_delimiter()

	 * @return  special string used for delimiter selection, or false

	 */

	public function _check_count ($char, $array, $depth, $preferred) {

		if ( $depth == count($array) ) {

			$first = null;

			$equal = null;

			$almost = false;

			foreach( $array as $key => $value ) {

				if ( $first == null ) {

					$first = $value;

				} elseif ( $value == $first && $equal !== false) {

					$equal = true;

				} elseif ( $value == $first+1 && $equal !== false ) {

					$equal = true;

					$almost = true;

				} else {

					$equal = false;

				}

			}

			if ( $equal ) {

				$match = ( $almost ) ? 2 : 1 ;

				$pref = strpos($preferred, $char);

				$pref = ( $pref !== false ) ? str_pad($pref, 3, '0', STR_PAD_LEFT) : '999' ;

				return $pref.$match.'.'.(99999 - str_pad($first, 5, '0', STR_PAD_LEFT));

			} else return false;

		}

	}

	

	/**

	 * Read local file

	 * @param   file   local filename

	 * @return  Data from file, or false on failure

	 */

	public function _rfile ($file = null) {

		if ( is_readable($file) ) {

			if ( !($fh = fopen($file, 'r')) ) return false;

			$data = fread($fh, filesize($file));

			fclose($fh);

			return $data;

		}

		return false;

	}



	/**

	 * Write to local file

	 * @param   file     local filename

	 * @param   string   data to write to file

	 * @param   mode     fopen() mode

	 * @param   lock     flock() mode

	 * @return  true or false

	 */

	public function _wfile ($file, $string = '', $mode = 'wb', $lock = 2) {

		if ( $fp = fopen($file, $mode) ) {

			flock($fp, $lock);

			$re = fwrite($fp, $string);

			$re2 = fclose($fp);

			if ( $re != false && $re2 != false ) return true;

		}

		return false;

	}

	

}



?>