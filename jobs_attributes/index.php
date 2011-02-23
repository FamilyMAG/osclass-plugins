<?php
/*
Plugin Name: Jobs attributes
Plugin URI: http://www.osclass.org/
Description: This plugin extends a category of items to store jobs attributes such as salary, requirements, timetable, and so on.
Version: 1.0
Author: OSClass
Author URI: http://www.osclass.org/
Short Name: jobs_plugin
Plugin update URI: http://www.osclass.org/files/plugins/jobs_attributes/update.php
*/

// Adds some plugin-specific search conditions
function job_search_conditions($params = '') {
    // we need conditions and search tables (only if we're using our custom tables)
    if($params!='') {
        $has_conditions = false;
        foreach($params as $key => $value) {
            // We may want to  have param-specific searches
            switch($key) {
                case 'relation':
                    Search::newInstance()->addConditions(sprintf("%st_item_job_attr.e_relation = '%s'", DB_TABLE_PREFIX, $value));
                    $has_conditions = true;
                    break;
                case 'companyName':
                    Search::newInstance()->addConditions(sprintf("%st_item_job_attr.s_company_name = '%%%s%%'", DB_TABLE_PREFIX, $value));
                    $has_conditions = true;
                    break;
                case 'positionType':
                    Search::newInstance()->addConditions(sprintf("%st_item_job_attr.e_position_type = '%s'", DB_TABLE_PREFIX, $value));
                    $has_conditions = true;
                    break;
                case 'salaryMin':
                    if($value != 0) {
                        Search::newInstance()->addConditions(sprintf("%st_item_job_attr.i_salary_min >= %d", DB_TABLE_PREFIX, $value));
                        $has_conditions = true;
                    }
                    break;
                case 'salaryMax':
                    if($value > 0) {
                        Search::newInstance()->addConditions(sprintf("%st_item_job_attr.i_salary_max <= %d", DB_TABLE_PREFIX, $value));
                        $has_conditions = true;
                    }
                    break;
                case 'salaryPeriod':
                    Search::newInstance()->addConditions(sprintf("%st_item_job_attr.e_salary_period = '%s'", DB_TABLE_PREFIX, $value));
                    $has_conditions = true;
                    break;
                default:
                    break;
            }
        }

        // Only if we have some values at the params we add our table and link with the ID of the item.
        if($has_conditions) {
            Search::newInstance()->addConditions(sprintf("%st_item_job_attr.fk_i_item_id = %st_item.pk_i_id", DB_TABLE_PREFIX, DB_TABLE_PREFIX));
            Search::newInstance()->addConditions(sprintf("%st_item_job_description_attr.fk_i_item_id = %st_item.pk_i_id", DB_TABLE_PREFIX, DB_TABLE_PREFIX));
            Search::newInstance()->addTable(sprintf("%st_item_job_attr", DB_TABLE_PREFIX));
            Search::newInstance()->addTable(sprintf("%st_item_job_description_attr", DB_TABLE_PREFIX));
        }
    }
}

function job_call_after_install() {
    // Insert here the code you want to execute after the plugin's install
    // for example you might want to create a table or modify some values
	
    // In this case we'll create a table to store the Example attributes
    $conn = getConnection();
    $conn->autocommit(false);
    try {
        $path = osc_plugin_resource('jobs_attributes/struct.sql');
        $sql = file_get_contents($path);
        $conn->osc_dbImportSQL($sql);
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo $e->getMessage();
    }
    $conn->autocommit(true);
}

function job_call_after_uninstall() {
    // Insert here the code you want to execute after the plugin's uninstall
    // for example you might want to drop/remove a table or modify some values

    // In this case we'll remove the table we created to store Example attributes
    $conn = getConnection();
    $conn->autocommit(false);
    try {
        $conn->osc_dbExec("DELETE FROM %st_plugin_category WHERE s_plugin_name = 'jobs_plugin'", DB_TABLE_PREFIX);
        $conn->osc_dbExec('DROP TABLE %st_item_job_description_attr', DB_TABLE_PREFIX);
        $conn->osc_dbExec('DROP TABLE %st_item_job_attr', DB_TABLE_PREFIX);
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo $e->getMessage();
    }
    $conn->autocommit(true);
}

function job_form($catId = null) {
    // We received the categoryID
    if($catId!="") {
        // We check if the category is the same as our plugin
        if(osc_is_this_category('jobs_plugin', $catId)) {
            require_once 'form.php';
        }
    }
}

function job_search_form($catId = null) {
	// We received the categoryID
	if($catId!=null) {
		// We check if the category is the same as our plugin
        foreach($catId as $id) {
    		if(osc_is_this_category('jobs_plugin', $id)) {
	    		include_once 'search_form.php';
	    		break;
	    	}
        }
	}
}

function job_form_post($catId = null, $item_id = null)  {
    // We received the categoryID and the Item ID
    $conn = getConnection();
    if($catId!="") {
        // We check if the category is the same as our plugin
        if(osc_is_this_category('jobs_plugin', $catId) && $item_id!=null) {
                // Insert the data in our plugin's table
                $conn->osc_dbExec("INSERT INTO %st_item_job_attr (fk_i_item_id, e_relation, s_company_name, e_position_type, i_salary_min, i_salary_max, e_salary_period) VALUES (%d, '%s', '%s', '%s', %d, %d, '%s')", DB_TABLE_PREFIX, $item_id, Params::getParam('relation'), Params::getParam('companyName'), Params::getParam('positionType'), Params::getParam('salaryMin'), Params::getParam('salaryMax'), Params::getParam('salaryPeriod') );
                // prepare locales
                $dataItem = array();
                $request = Params::getParamsAsArray();
                foreach ($request as $k => $v) {
                    if (preg_match('|(.+?)#(.+)|', $k, $m)) {
                        $dataItem[$m[1]][$m[2]] = $v;
                    }
                }

                // insert locales
                foreach ($dataItem as $k => $_data) {
                    $conn->osc_dbExec("INSERT INTO %st_item_job_description_attr (fk_i_item_id, fk_c_locale_code, s_desired_exp, s_studies, s_minimum_requirements, s_desired_requirements, s_contract, s_company_description) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s')", DB_TABLE_PREFIX, $item_id, $k, $_data['desired_exp'], $_data['studies'], $_data['min_reqs'], $_data['desired_reqs'], $_data['contract'], $_data['company_desc'] );
                }
        }
    }
}

// Self-explanatory
function job_item_detail() {
    if(osc_is_this_category('jobs_plugin', osc_item_category_id())) {
        $conn = getConnection();
        $detail = $conn->osc_dbFetchResult("SELECT * FROM %st_item_job_attr WHERE fk_i_item_id = %d", DB_TABLE_PREFIX, osc_item_id());

        $descriptions = $conn->osc_dbFetchResults('SELECT * FROM %st_item_job_description_attr WHERE fk_i_item_id = %d', DB_TABLE_PREFIX, osc_item_id());
        $detail['locale'] = array();
        foreach ($descriptions as $desc) {
            $detail['locale'][$desc['fk_c_locale_code']] = $desc;
        }
        require_once 'item_detail.php';
    }
}

// Self-explanatory
function job_item_edit() {
    if(osc_is_this_category('jobs_plugin', osc_item_category_id())) {
        $conn = getConnection();
        $detail = $conn->osc_dbFetchResult("SELECT * FROM %st_item_job_attr WHERE fk_i_item_id = %d", DB_TABLE_PREFIX, osc_item_id());

        $descriptions = $conn->osc_dbFetchResults('SELECT * FROM %st_item_job_description_attr WHERE fk_i_item_id = %d', DB_TABLE_PREFIX, osc_item_id());
        $detail['locale'] = array();
        foreach ($descriptions as $desc) {
            $detail['locale'][$desc['fk_c_locale_code']] = $desc;
        }
        require_once 'item_edit.php';
    }
}

function job_item_edit_post($catId = null, $item_id = null) {
    // We received the categoryID and the Item ID
    if($catId!=null) {
        // We check if the category is the same as our plugin
        if(osc_is_this_category('jobs_plugin', $catId)) {
            $conn = getConnection() ;
            $conn->osc_dbExec("REPLACE INTO %st_item_job_attr (fk_i_item_id, e_relation, s_company_name, e_position_type, i_salary_min, i_salary_max, e_salary_period) VALUES (%d, '%s', '%s', '%s', %d, %d, '%s')", DB_TABLE_PREFIX, $item_id, Params::getParam('relation'), Params::getParam('companyName'), Params::getParam('positionType'), Params::getParam('salaryMin'), Params::getParam('salaryMax'), Params::getParam('salaryPeriod') );
            // prepare locales
            $dataItem = array();
            $request = Params::getParamsAsArray();
            foreach ($request as $k => $v) {
                if (preg_match('|(.+?)#(.+)|', $k, $m)) {
                    $dataItem[$m[1]][$m[2]] = $v;
                }
            }

            // insert locales
            foreach ($dataItem as $k => $_data) {
                $conn->osc_dbExec("REPLACE INTO %st_item_job_description_attr (fk_i_item_id, fk_c_locale_code, s_desired_exp, s_studies, s_minimum_requirements, s_desired_requirements, s_contract, s_company_description) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s')", DB_TABLE_PREFIX, $item_id, $k, $_data['desired_exp'], $_data['studies'], $_data['min_reqs'], $_data['desired_reqs'], $_data['contract'], $_data['company_desc'] );
            }
        }
    }
}

function job_delete_locale($locale) {
    $conn = getConnection();
    $conn->osc_dbExec("DELETE FROM %st_item_job_description_attr WHERE fk_c_locale_code = '" . $locale . "'", DB_TABLE_PREFIX);
}

function job_delete_item($item) {
    $conn = getConnection();
    $conn->osc_dbExec("DELETE FROM %st_item_job_attr WHERE fk_i_item_id = '" . $item . "'", DB_TABLE_PREFIX);
    $conn->osc_dbExec("DELETE FROM %st_item_job_description_attr WHERE fk_i_item_id = '" . $item . "'", DB_TABLE_PREFIX);
}


function job_admin_configuration() {
    // Standard configuration page for plugin which extend item's attributes
    osc_plugin_configure_view(__FILE__);
}

// This is needed in order to be able to activate the plugin
osc_register_plugin(__FILE__, 'job_call_after_install');
// This is a hack to show a Configure link at plugins table (you could also use some other hook to show a custom option panel)
osc_add_hook(__FILE__."_configure", 'job_admin_configuration');
// This is a hack to show a Uninstall link at plugins table (you could also use some other hook to show a custom option panel)
osc_add_hook(__FILE__."_uninstall", 'job_call_after_uninstall');

// When publishing an item we show an extra form with more attributes
osc_add_hook('item_form', 'job_form');
// To add that new information to our custom table
osc_add_hook('item_form_post', 'job_form_post');

// When searching, display an extra form with our plugin's fields
osc_add_hook('search_form', 'job_search_form');
// When searching, add some conditions
osc_add_hook('search_conditions', 'job_search_conditions');

// Show an item special attributes
osc_add_hook('item_detail', 'job_item_detail');

// Edit an item special attributes
osc_add_hook('item_edit', 'job_item_edit');
// Edit an item special attributes POST
osc_add_hook('item_edit_post', 'job_item_edit_post');

//Delete locale
osc_add_hook('delete_locale', 'job_delete_locale');
//Delete item
osc_add_hook('delete_item', 'job_delete_item');


?>
