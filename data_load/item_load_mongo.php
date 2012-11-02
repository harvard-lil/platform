<?php

namespace org\librarycloud\data_load;

ini_set("memory_limit","900M");

define('LC_HOME', dirname(dirname(__FILE__)).'/' );
require_once LC_HOME . 'lib/SolrPhpClient/Apache/Solr/Service.php';

$config = parse_ini_file(LC_HOME . 'etc/data_load_mongo.ini');

// Call our function that does the heavy lifting
index_items();

// Connect to the mongo datastore and toss things at Solr
function index_items() {

    // Let's report some basic load times
    $start_time = time();

    echo "Throwing a crapload of records at Solr. Hold tight...\n";

    global $config;
    
    $solr = new \Apache_Solr_Service($config['solr_host'], $config['solr_port'], $config['solr_path']);
    if ( ! $solr->ping() ) {
        echo 'Solr service not responding';
        exit;
    } else {
        echo "Connected to Solr...\n";
    }

    $m = new \Mongo($config['mongo_connection']);
    $db = $m->selectDB($config['mongo_db']);
    $collection = $db->selectCollection($config['mongo_collection']);
    $cursor = $collection->find()->batchSize(400);
    
    $count = 0;
    foreach ($cursor as $obj) {

        $count++;

        if ($count % 1000 == 0) {
            echo "now processing document no. $count\n";
        }

        $solr_document = new \Apache_Solr_Document();

        // Process our mapped terms
        // TODO: This has grown insanely verbose. Almost all of this can be handled by two functions (process common term, process common list):
        foreach ($obj['lc'] as $key => $value) {
            if ($key == 'id') {
                parse_id($solr_document, $value);
            }
            if ($key == 'title') {
                parse_title($solr_document, $value);
            }
            if ($key == 'title_sort') {
                parse_title_sort($solr_document, $value);
            }
            if ($key == 'title_link_friendly') {
                parse_title_link_friendly($solr_document, $value);
            }
            if ($key == 'title_sort') {
                parse_title_sort($solr_document, $value);
            }
            if ($key == 'creator') {
                parse_creator($solr_document, $value);
            }
            if ($key == 'publisher') {
                parse_publisher($solr_document, $value);
            }
            if ($key == 'pub_location') {
                parse_pub_location($solr_document, $value);
            }
            if ($key == 'pub_date') {
                parse_pub_date($solr_document, $value);
            }
            if ($key == 'pub_date_numeric') {
                parse_pub_date_numeric($solr_document, $value);
            }
            if ($key == 'format') {
                parse_format($solr_document, $value);
            }
            if ($key == 'language') {
                parse_language($solr_document, $value);
            }
            if ($key == 'pages') {
                parse_pages($solr_document, $value);
            }
            if ($key == 'pages_numeric') {
                parse_pages_numeric($solr_document, $value);
            }
            if ($key == 'height') {
                parse_height($solr_document, $value);
            }
            if ($key == 'height_numeric') {
                parse_height_numeric($solr_document, $value);
            }
            if ($key == 'call_number') {
                parse_call_number($solr_document, $value);
            }
            if ($key == 'lcsh') {
                parse_lcsh($solr_document, $value);
            }
            if ($key == 'id_inst') {
                parse_id_inst($solr_document, $value);
            }
            if ($key == 'id_isbn') {
                parse_id_isbn($solr_document, $value);
            }
            if ($key == 'id_lccn') {
                parse_id_lccn($solr_document, $value);
            }
            if ($key == 'id_oclc') {
                parse_id_oclc($solr_document, $value);
            }
            if ($key == 'online_avail') {
                parse_online_avail($solr_document, $value);
            }
            if ($key == 'ut_id') {
                parse_ut_id($solr_document, $value);
            }
            if ($key == 'ut_count') {
                parse_ut_count($solr_document, $value);
            }
            if ($key == 'loc_call_num_subject') {
                parse_loc_call_num_subject($solr_document, $value);
            }
            if ($key == 'data_source') {
                parse_data_source($solr_document, $value);
            }
            if ($key == 'dataset_tag') {
                parse_dataset_tag($solr_document, $value);
            }
            if ($key == 'shelfrank') {
                parse_shelfrank($solr_document, $value);
            }
            if ($key == 'score_checkouts_undergrad') {
                parse_score_checkouts_undergrad($solr_document, $value);
            }
            if ($key == 'score_checkouts_grad') {
                parse_score_checkouts_grad($solr_document, $value);
            }
            if ($key == 'score_checkouts_fac') {
                parse_score_checkouts_fac($solr_document, $value);
            }
            if ($key == 'score_reserves') {
                parse_score_reserves($solr_document, $value);
            }
            if ($key == 'score_recalls') {
                parse_score_recalls($solr_document, $value);
            }
            if ($key == 'score_course_texts') {
                parse_score_course_texts($solr_document, $value);
            }
            if ($key == 'score_holding_libs') {
                parse_score_holding_libs($solr_document, $value);
            }
            if ($key == 'score_extra_copies') {
                parse_score_extra_copies($solr_document, $value);
            }
            if ($key == 'total_score') {
                parse_total_score($solr_document, $value);
            }
            if ($key == 'note') {
                parse_note($solr_document, $value);
            }
            if ($key == 'holding_libs') {
                parse_holding_libs($solr_document, $value);
            }
            if ($key == 'loc_call_num_sort_order') {
                parse_loc_call_num_sort_order($solr_document, $value);
            }
            if ($key == 'url') {
                parse_url($solr_document, $value);
            }
        }
        
        // Process the local data
        // This is about the ugliest thing I've ever seen.
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
		
    $solr->commit();
    $solr->optimize();

    echo "About " . $collection->count() . " documents indexed in a total of " . (time() - $start_time) . " seconds.\n";
}

/////////
// A whole bunch of helper functions to parse the fields
// TODO: generalize a whole bunch of the mess below
/////////

// Given a id object, add it to a solr doc
function parse_id($document, $id) {
    if (!empty($id) && $id != 'NULL' && $id != 'n/a') {
        $document->addField('id', $id);
        //print "\nid = $id \n";
    }
}

// Given a title object, add it to a solr doc
function parse_title($document, $title) {
    if (!empty($title) && $title != 'NULL' && $title != 'n/a') {
        $document->addField('title', $title);
        //print "\ntitle = $title \n";
    }
}

// Given a title_sort object, add it to a solr doc
function parse_title_sort($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('title_sort', $value);
    }
}

// Given a title_link_friendly object, add it to a solr doc
function parse_title_link_friendly($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('title_link_friendly', $value);
    }
}

// Given a sub_title object, add it to a solr doc
function parse_sub_title($document, $value) {
    if (!empty($value) && $title != 'NULL' && $value != 'n/a') {
        $document->addField('sub_title', $value);
    }
}

// Given a creator object, add it to a solr doc
function parse_creator($document, $creators) {
    foreach ($creators as $creator) {
        $document->addField('creator', $creator);
        //print "\ncreator = $name \n\n";
    }
}

// Given a publisher object, add it to a solr doc
function parse_publisher($document, $publisher) {
    if (!empty($publisher) && $publisher != 'NULL' && $publisher != 'n/a') {
        $document->addField('publisher', $publisher);
        //print "\npublisher = $publisher \n";
    }
}

// Given a parse_pub_location object, add it to a solr doc
function parse_pub_location($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('pub_location', $value);
    }
}

// Given a pub_date object, add it to a solr doc
function parse_pub_date($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('pub_date', $value);
    }
}

// Given a pub_date_numeric object, add it to a solr doc
function parse_pub_date_numeric($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('pub_date_numeric', $value);
    }
}

// Given a format object, add it to a solr doc
function parse_format($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('format', $value);
    }
}

// Given a language object, add it to a solr doc
function parse_language($document, $language) {
    if (!empty($language) && $language != 'NULL' && $language != 'n/a') {
        $document->addField('language', $language);
        //print "\nlanguage = $language \n";
    }
}

// Given a pages object, add it to a solr doc
function parse_pages($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('pages', $value);
    }
}

// Given a pages_numeric object, add it to a solr doc
function parse_pages_numeric($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('pages_numeric', $value);
    }
}

// Given a height object, add it to a solr doc
function parse_height($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('height', $value);
    }
}

// Given a pages_height object, add it to a solr doc
function parse_height_numeric($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('height_numeric', $value);
    }
}

// Given a call number object, add it to a solr doc
function parse_call_number($document, $call_num) {
    foreach ($call_num as $key => $value) {
        foreach ($value as $type => $type_value) {
            if ($type_value == 'value' && !empty($type_value) && $type_value != 'NULL' && $type_value != 'n/a') {
                $document->addField('call_num', $type_value);
                //print "\ncall_num = $type_value \n\n";
            }
        }
    }
}

// Given a subject array, add it to a solr doc
function parse_lcsh($document, $subjects) {
    foreach ($subjects as $subject) {
        if (!empty($subject) && $subject != 'NULL' && $subject != 'n/a') {
            $document->addField('lcsh', $subject);
            //print "\nsubject = $subject \n";
        }
    }
}

// Given an id_inst object, add it to a solr doc
function parse_id_inst($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('id_inst', $value);
    }
}

// Given an id_isbn array, add it to a solr doc
function parse_id_isbn($document, $values) {
    foreach ($values as $value) {
        if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
            $document->addField('id_isbn', $value);
            //print "\nsubject = $subject \n";
        }
    }
}

// Given an id_lccn object, add it to a solr doc
function parse_id_lccn($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('id_lccn', $value);
    }
}

// Given an id_oclc object, add it to a solr doc
function parse_id_oclc($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('id_oclc', $value);
    }
}

// Given an online_avail object, add it to a solr doc
function parse_online_avail($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('online_avail', $value);
    }
}

// Given an ut_id object, add it to a solr doc
function parse_ut_id($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('ut_id', $value);
    }
}

// Given an ut_count object, add it to a solr doc
function parse_ut_count($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('ut_count', $value);
    }
}
                
// Given an loc_call_num_subject object, add it to a solr doc
function parse_loc_call_num_subject($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('loc_call_num_subject', $value);
    }
}

// Given an data_source object, add it to a solr doc
function parse_data_source($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('data_source', $value);
    }
}

// Given an dataset_tag object, add it to a solr doc
function parse_dataset_tag($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('dataset_tag', $value);
    }
}

// Given an shelfrank object, add it to a solr doc
function parse_shelfrank($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('shelfrank', $value);
    }
}

// Given an score_checkouts_undergrad object, add it to a solr doc
function parse_score_checkouts_undergrad($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_checkouts_undergrad', $value);
    }
}

// Given an score_checkouts_grad object, add it to a solr doc
function parse_score_checkouts_grad($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_checkouts_grad', $value);
    }
}

// Given an score_checkouts_fac object, add it to a solr doc
function parse_score_checkouts_fac($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_checkouts_fac', $value);
    }
}

// Given an score_reserves object, add it to a solr doc
function parse_score_reserves($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_reserves', $value);
    }
}

// Given an score_recalls object, add it to a solr doc
function parse_score_recalls($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_recalls', $value);
    }
}

// Given an score_course_texts object, add it to a solr doc
function parse_score_course_texts($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_course_texts', $value);
    }
}

// Given an score_holding_libs object, add it to a solr doc
function parse_score_holding_libs($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_holding_libs', $value);
    }
}

// Given an score_extra_copies object, add it to a solr doc
function parse_score_extra_copies($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('score_extra_copies', $value);
    }
}

// Given an total_score object, add it to a solr doc
function parse_total_score($document, $value) {
    if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
        $document->addField('total_score', $value);
    }
}

// Given a note array, add it to a solr doc
function parse_note($document, $values) {
    foreach ($values as $value) {
        if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
            $document->addField('note', $value);
        }
    }
}

// Given a holding_libs array, add it to a solr doc
function parse_holding_libs($document, $values) {
    foreach ($values as $value) {
        if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
            $document->addField('holding_libs', $value);
        }
    }
}

// Given a loc_call_num_sort_order array, add it to a solr doc
function parse_loc_call_num_sort_order($document, $values) {
    foreach ($values as $value) {
        if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
            $document->addField('loc_call_num_sort_order', $value);
        }
    }
}

// Given a url array, add it to a solr doc
function parse_url($document, $values) {
    foreach ($values as $value) {
        if (!empty($value) && $value != 'NULL' && $value != 'n/a') {
            $document->addField('url', $value);
        }
    }
}

?>
