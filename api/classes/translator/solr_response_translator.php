<?php

namespace org\librarycloud\api\translator;

/**
* Logic handle the transformation of a Solr response to a response we'll
* pass to one of our renderers
*
* @author     Matt Phillips <mphillips@law.harvard.edu>
* @license    http://www.gnu.org/licenses/lgpl.txt GNU Lesser Public License
*/

use org\librarycloud\api\utils as utils;

class solr_response_translator {

    // The solr response object
    public $solr_response;
    // This is our return object that gets rendered as json, xml, ...
    public $results;
    public $lc_config;

    /**
     * We'll need some data in this class. Get it here
     *
     * @param Apache_Solr_Response $data_store_response our results from Solr (as they come out of the SolrPHPClient library)
     * @param lc_config $lc_config a container holding the details of our config
     */
    function __construct($data_store_response, $lc_config, $http_request) {
        $this->solr_response = $data_store_response;
        $this->results = array();
        $this->lc_config = $lc_config;
        $this->http_request = $http_request;
    }

    /**
     * We'll repackage the Solr response into something more friendly and robust
     */
    function translate() {
        // Our main response object that will be passed to the renderer,
        // ultimately written the user
        $this->results['num_found'] = $this->solr_response->response->numFound;
        $this->results['start'] = $this->solr_response->responseHeader->params->start;
        $this->results['limit'] = $this->solr_response->responseHeader->params->rows;
        $this->results['sort'] = $this->solr_response->responseHeader->params->sort;
        if (!empty($this->solr_response->responseHeader->params->fq)){
            $this->results['filter'] = $this->solr_response->responseHeader->params->fq;
        }
        
        // Build the docs array
        $docs = array();

        // If a multi-valued field only contains one result, solr treats it as a scalar
        // this makes client code even more irritating to write. Let's make sure we always
        // write arrays when the user expects them
        $multivalued_fields = array();
        if (!empty($this->lc_config['mulval_fields'])) {
            $multivalued_fields = $this->lc_config['mulval_fields'];
        }

        $controlled_fields = array();
        if (!empty($this->lc_config['valid_params'])) {
            $controlled_fields = $this->lc_config['valid_params'];
        }

        // If the user supplied a key, see if they're authorized
        $authorized = false;
        if (!empty($this->http_request->params['key'][0])) {
            $supplied_key = $this->http_request->params['key'][0];

            if ($supplied_key == $this->lc_config['access_key']) {
                $authorized = true;
            }
        }

        $auth_only_fields = array();
        if (!empty($this->lc_config['auth_only_fields'])) {
            $auth_only_fields = $this->lc_config['auth_only_fields'];
        }

        foreach ($this->solr_response->response->docs as $solr_doc) {
            // Each record becomes a doc
            $doc = array();
	    // Put any source fields in this array
	    $source_record = array();

            foreach ($solr_doc->getFieldNames() as $field_name) {

                // We hide some fields if the user didn't supply a valid key.
                // if the user isn't authorized and the field is in our auth_only_fields list,
                // let's skip this field (continue onto the next field in the foreach
                if (!$authorized) {
                    if (in_array($field_name, $auth_only_fields)) {
                        continue;
                    }
                }

                // If we we detect a scalar that should be an array, cast it as an array
		$value;
                if (in_array($field_name, $multivalued_fields)) {
                    $value  = (array) $solr_doc->$field_name;
                } else {
                    $value = $solr_doc->$field_name;
                }

		if (in_array($field_name, $controlled_fields)) {
		    $doc[$field_name] = $value;
		}
		else {
		    $source_record[$field_name] = $value;
		}

            }
	    $doc['source_record'] = $source_record;
            $docs[] = $doc;
        }
        $this->results['docs'] = $docs;

        // Build the facets array
        $facets = array();
        // TODO: find a better way to evaluate an empty facet_fields. This is ugly/
        if (!empty($this->solr_response->facet_counts->facet_fields) && 
            count((array)$this->solr_response->facet_counts->facet_fields) > 0 && 
            $this->solr_response->response->numFound > 0) {
            foreach ($this->solr_response->facet_counts->facet_fields as $key => $value) {
                $facet_values = array();

                foreach ($value as $n_key => $n_value) {
                    $facet_values[$n_key] = $n_value;
                }
                $facets[$key] = $facet_values;
            }
            $facets[$key] = $facet_values;
        }
        $this->results['facets'] = $facets;

        // Handle facet queries. These are nested under the facet array, under the 
        // key of facet_queries
        $facet_queries = array();
        if (count((array)$this->solr_response->facet_counts) > 0 && $this->solr_response->response->numFound > 0) {
            $facet_queries = $this->solr_response->facet_counts->facet_queries;
        }
        $this->results['facet_queries'] = $facet_queries;

        // Build the stats array
        if ($this->solr_response->stats != null && $this->solr_response->response->numFound > 0) {
            $stats = array();
            foreach ($this->solr_response->stats->stats_fields as $key => $value) {
                $stats_values = array();

                foreach ($value as $n_key => $n_value) {
                    $stats_values[$n_key] = $n_value;
                }
                $stats[$key] = $stats_values;
            }
            $this->results['stats'] = $stats;
        }
    }
}
?>
