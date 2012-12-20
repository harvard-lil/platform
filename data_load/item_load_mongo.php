<?php

namespace org\librarycloud\data_load;

ini_set("memory_limit","900M");

define('LC_HOME', dirname(dirname(__FILE__)).'/' );
require_once LC_HOME . 'lib/SolrPhpClient/Apache/Solr/Service.php';

$config = parse_ini_file(LC_HOME . 'etc/data_load_mongo.ini');
$mongo_db = $argv[1];
$mongo_collection = $argv[2];

// Call our function that does the heavy lifting
index_items($mongo_db, $mongo_collection);

// Connect to the mongo datastore and toss things at Solr
function index_items($mongo_db, $mongo_collection) {

    // Let's report some basic load times
    $start_time = time();

    echo "Throwing a crapload of records from $mongo_db.$mongo_collection at Solr. Hold tight ...\n";

    global $config;
    
    $solr = new \Apache_Solr_Service($config['solr_host'], $config['solr_port'], $config['solr_path']);
    if ( ! $solr->ping() ) {
        echo 'Solr service not responding';
        exit;
    } else {
        echo "Connected to Solr...\n";
    }

    $m = new \Mongo($config['mongo_connection']);
    
    // Fetch mongo config from command-line args instead of ini file
    // $db = $m->selectDB($config['mongo_db']);
    // $collection = $db->selectCollection($config['mongo_collection']);
    $db = $m->selectDB($mongo_db);
    $collection = $db->selectCollection($mongo_collection);

    try {

	    $cursor = $collection->find()->immortal(true)->batchSize(100)->timeout(-1);
	
	    
	    $count = 0;
	    foreach ($cursor as $obj) {
	    		/*
	    		$cursor_count = count($cursor);
	    		$object_count = count($obj);
	    		echo "cursor_count: [$cursor_count]\n";
	    		echo "object_count: [$object_count]\n";
	    		print_r($obj);
					*/
					
	        $count++;
	
	        if ($count % 1000 == 0) {
							$date_time = date("l dS F Y h:i:s A");
	            echo "$date_time: now processing document no. $count\n";
	        }
	
	        $solr_document = new \Apache_Solr_Document();
	
	        // Process our mapped terms -- they come in either as scalars or arrays
	        $scalars = array(
	        'id',
	        'title_link_friendly',
	        'sub_title',
	        'publisher',
	        'pub_location',
	        'pub_date',
	        'pub_date_numeric',
	        'format',
	        'language',
	        'pages',
	        'pages_numeric',
	        'height',
	        'height_numeric',
	        'id_inst',
	        'id_lccn',
	        'id_oclc',
	        'call_num',	        
	        'online_avail',
	        'ut_id',
	        'ut_count',
	        'loc_call_num_subject',
	        'data_source',
	        'dataset_tag',
	        'collection',
	        'shelfrank',
	        'score_checkouts_undergrad',
	        'score_checkouts_grad',
	        'score_checkouts_fac',
	        'score_reserves',
	        'score_recalls',
	        'score_course_texts',
	        'score_holding_libs',
	        'score_extra_copies',
	        'score_total'
	        );
	        $arrays = array(
	        'title',
	        'title_sort',
	        'creator',
	        'lcsh',
	        'id_isbn',
	        'note',
	        'holding_libs',
	        'loc_call_num_sort_order',
	        'url',
	        'toc',
	        'rsrc_key',
	        'rsrc_value',
	        'wp_categories'
	        );
	        $inner_count = 0;
	        foreach ($obj['lc'] as $key => $value) 
	        {
	        	$inner_count++;
	        	if (in_array($key, $scalars)) 
	        	{
	        		parse_scalar($solr_document, $value, $key);
	        	}
	        	else
	        	{
	        		parse_array($solr_document, $value, $key);
	        	}
	        }
	        
	        // Process the local data
	        // This is about the ugliest thing I've ever seen.
					if (!empty($obj['local'])) {
						foreach ($obj['local'] as $local_key => $local_value) {
							if (gettype($local_value) == 'array'){
								foreach ($local_value as $field_name => $field_ja){
									foreach ($field_ja as $a => $b) {
										if (!empty($b['subfields']) && gettype($b['subfields']) == 'array') {
											foreach ($b['subfields'] as $subfield_det) {
												foreach($subfield_det as $code => $det) {
													$solr_document->addField("$a$code", $det);
												}
											}
										}
									}
								}
							}
						}
					}
	
	      $solr_documents[] = $solr_document;
				// Send the docs to Solr
		    try {
		        $solr->addDocuments($solr_documents);
		    } catch (\Exception $e) {
		        echo $e->getMessage();
		        exit();
		    }
		    
		    // Release memory
	        unset ($solr_documents);
	    }
    }
    catch (MongoCursorException $e) {
        echo "error message: ".$e->getMessage()."\n";
        echo "error code: ".$e->getCode()."\n";
    }
	
    $solr->commit();
    $solr->optimize();

    echo "About " . $collection->count() . " documents indexed in a total of " . (time() - $start_time) . " seconds.\n";
}

function parse_scalar($document, $value, $key) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField($key, $value);
     		// echo "parse_scalar -- key: [$key] and value: [$value]\n";
		}
}

function parse_array($document, $values, $key) {
	if (!empty($values))
	{
    foreach ($values as $value) {
        if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
            $document->addField($key, $value);
     		// echo "parse_array -- key: [$key] and value: [$value]\n";            
        }
    }
  }
}

?>
