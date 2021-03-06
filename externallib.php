<?php
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/weblib.php");
require_once $CFG->dirroot . '/blocks/exaport/lib/lib.php';
require_once $CFG->dirroot . '/lib/filelib.php';

class block_exaport_external extends external_api {


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function get_items_parameters() {
		return new external_function_parameters(
				array('level' => new external_value(PARAM_INT, 'id of level/parent category'))
		);

	}

	/**
	 * Get items
	 * @param int level
	 * @return array of course subjects
	 */
	public static function get_items($level) {
		global $CFG,$DB,$USER;

		$params = self::validate_parameters(self::get_items_parameters(), array('level'=>$level));

		$conditions=array("pid"=>$level,"userid"=>$USER->id);
		$categories = $DB->get_records("block_exaportcate", $conditions);

		$results = array();

		foreach($categories as $category) {
			$result = new stdClass();
			$result->id = $category->id;
			$result->name = $category->name;
			$result->type = "category";

			$results[] = $result;
		}

		$items = $DB->get_records("block_exaportitem", array("userid" => $USER->id,"categoryid" => $level),'','id,name,type');
		$results = array_merge($results,$items);

		return $results;
	}

	/**
	 * Returns desription of method return values
	 * @return external_multiple_structure
	 */
	public static function get_items_returns() {
		return new external_multiple_structure(
				new external_single_structure(
						array(
								'id' => new external_value(PARAM_INT, 'id of item'),
								'name' => new external_value(PARAM_TEXT, 'title of item'),
								'type' => new external_value(PARAM_TEXT, 'title of item (note,file,link,category)')
						)
				)
		);
	}

	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function get_item_parameters() {
		return new external_function_parameters(
				array('itemid' => new external_value(PARAM_INT, 'id of item'))
		);

	}

	/**
	 * Get item
	 * @param int itemid
	 * @return array of course subjects
	 */
	public static function get_item($itemid) {
		global $CFG,$DB,$USER;

		$params = self::validate_parameters(self::get_item_parameters(), array('itemid'=>$itemid));

		$conditions=array("id"=>$itemid,"userid"=>$USER->id);
		$item = $DB->get_record("block_exaportitem", $conditions, 'id,userid,type,categoryid,name,intro,url',MUST_EXIST);
		$category = $DB->get_field("block_exaportcate","name",array("id"=>$item->categoryid));

		if(!$category)
			$category = "Hauptkategorie";

		$item->category = $category;
		$item->file = "";
		$item->isimage = false;
		$item->filename = "";

		if ($item->type == 'file') {
			if ($file = block_exaport_get_item_file($item)) {
				$item->file = ("{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=portfolio/id/".$USER->id."&itemid=".$item->id);
				$item->isimage = $file->is_valid_image();
				$item->filename = $file->get_filename();
			}
		}
			
		return $item;
	}

	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function get_item_returns() {
		return new external_single_structure(
				array(
						'id' => new external_value(PARAM_INT, 'id of item'),
						'name' => new external_value(PARAM_TEXT, 'title of item'),
						'type' => new external_value(PARAM_TEXT, 'type of item (note,file,link,category)'),
						'category' => new external_value(PARAM_TEXT, 'title of category'),
						'url' => new external_value(PARAM_TEXT, 'url'),
						'intro' => new external_value(PARAM_RAW, 'description of item'),
						'filename' => new external_value(PARAM_TEXT, 'title of item'),
						'file' => new external_value(PARAM_URL, 'file url'),
						'isimage' => new external_value(PARAM_BOOL,'true if file is image')
				)
		);
	}

	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function add_item_parameters() {
		return new external_function_parameters(
				array('title' => new external_value(PARAM_TEXT, 'item title'),
						'categoryid' => new external_value(PARAM_INT, 'categoryid'),
						'url' => new external_value(PARAM_URL, 'url'),
						'intro' => new external_value(PARAM_TEXT, 'introduction'),
						'filename' => new external_value(PARAM_TEXT, 'filename, used to look up file and create a new one in the exaport file area'),
						'type' => new external_value(PARAM_TEXT, 'type of item (note,file,link,category)'))
		);

	}
	
	/**
	 * Add item
	 * @param int itemid
	 * @return array of course subjects
	 */
	public static function add_item($title,$categoryid,$url,$intro,$filename,$type) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::add_item_parameters(), array('title'=>$title,'categoryid'=>$categoryid,'url'=>$url,'intro'=>$intro,'filename'=>$filename,'type'=>$type));
	
		$itemid = $DB->insert_record("block_exaportitem", array('userid'=>$USER->id,'name'=>$title,'categoryid'=>$categoryid,'url'=>$url,'intro'=>$intro,'type'=>$type,'timemodified'=>time()));
		
		//if a file is added we need to copy the file from the user/private filearea to block_exaport/item_file with the itemid from above	
		if($type == "file") {
			$context = context_user::instance($USER->id);
			$fs = get_file_storage();
			$old = $fs->get_file($context->id, "user", "private", 0, "/", $filename);
		
			$file_record = array('contextid'=>$context->id, 'component'=>'block_exaport', 'filearea'=>'item_file',
					'itemid'=>$itemid, 'filepath'=>'/', 'filename'=>$old->get_filename(),
					'timecreated'=>time(), 'timemodified'=>time());
			$fs->create_file_from_storedfile($file_record, $old->get_id());
		}
		
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function add_item_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function get_views_parameters() {
		return new external_function_parameters(
				array()
		);
	
	}
	
	/**
	 * Get views
	 * @return array of e-Portfolio views
	 */
	public static function get_views() {
		global $CFG,$DB,$USER;
	
		$conditions=array("userid"=>$USER->id);
		$views = $DB->get_records("block_exaportview", $conditions);
	
		$results = array();
	
		foreach($views as $view) {
			$result = new stdClass();
			$result->id = $view->id;
			$result->name = $view->name;
			$result->description = $view->description;
			$results[] = $result;
		}

		return $results;
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_multiple_structure
	 */
	public static function get_views_returns() {
		return new external_multiple_structure(
				new external_single_structure(
						array(
								'id' => new external_value(PARAM_INT, 'id of view'),
								'name' => new external_value(PARAM_TEXT, 'title of view'),
								'description' => new external_value(PARAM_RAW, 'description of view')
						)
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function get_view_parameters() {
		return new external_function_parameters(
				array('id' => new external_value(PARAM_INT, 'view id'))
		);
	}
	
	/**
	 * Get view
	 * @param int id
	 * @return detailed view including list of items
	 */
	public static function get_view($id) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::get_view_parameters(), array('id'=>$id));
		
		$conditions=array("id"=>$id);
		$view = $DB->get_record("block_exaportview", $conditions);
	
		$result->id = $view->id;
		$result->name = $view->name;
		$result->description = $view->description;
		
		$conditions = array("viewid"=>$id);
		$items = $DB->get_records("block_exaportviewblock", $conditions);
		
		$result->items = array();
		foreach($items as $item) {
			if($item->type == "item"){
				$conditions = array("id"=>$item->itemid);
				$itemdb = $DB->get_record("block_exaportitem", $conditions);
			
				$resultitem = new stdClass();
				$resultitem->id = $itemdb->id;
				$resultitem->name = $itemdb->name;
				$resultitem->type = $itemdb->type;
				$result->items[] = $resultitem;
			}
		}
	
		return $result;
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function get_view_returns() {
		return new external_single_structure(
				array(
					'id' => new external_value(PARAM_INT, 'id of view'),
					'name' => new external_value(PARAM_TEXT, 'title of view'),
					'description' => new external_value(PARAM_RAW, 'description of view'),
					'items'=> new external_multiple_structure(
								new external_single_structure(
									array(
										'id' => new external_value(PARAM_INT, 'id of item'),
										'name' => new external_value(PARAM_TEXT, 'title of item'),
										'type' => new external_value(PARAM_TEXT, 'title of item (note,file,link,category)')
									)
								)
							)
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function add_view_parameters() {
		return new external_function_parameters(
				array(
					'name' => new external_value(PARAM_TEXT, 'view title'),
					'description' => new external_value(PARAM_TEXT, 'description')
				)
		);
	}
	
	/**
	 * Add view
	 * @param String name, String description
	 * @return success
	 */
	public static function add_view($name,$description) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::add_view_parameters(), array('name'=>$name,'description'=>$description));
	
		$viewid = $DB->insert_record("block_exaportview", array('userid'=>$USER->id,'name'=>$name,'description'=>$description, 'timemodified'=>time()));
	
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function add_view_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function update_view_parameters() {
		return new external_function_parameters(
				array(
						'id' => new external_value(PARAM_INT, 'view id'),
						'name' => new external_value(PARAM_TEXT, 'view title'),
						'description' => new external_value(PARAM_TEXT, 'description')
				)
		);
	}
	
	/**
	 * Update view
	 * @param int id, String name, String description
	 * @return success
	 */
	public static function update_view($id, $name,$description) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::update_view_parameters(), array('id'=>$id,'name'=>$name,'description'=>$description));
	
		$record = new stdClass();
		$record->id = $id;
		$record->name = $name;
		$record->description = $description;
		$DB->update_record("block_exaportview", $record);
		
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function update_view_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function delete_view_parameters() {
		return new external_function_parameters(
				array(
						'id' => new external_value(PARAM_INT, 'view id')
				)
		);
	}
	
	/**
	 * Delete view
	 * @param int id
	 * @return success
	 */
	public static function delete_view($id) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::delete_view_parameters(), array('id'=>$id));
	
		$DB->delete_records("block_exaportview", array("id"=>$id));
	
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function delete_view_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function get_all_items_parameters() {
		return new external_function_parameters(
				array(
				)
		);
	}
	
	/**
	 * Get all items
	 * @return all items available
	 */
	public static function get_all_items() {
		global $CFG,$DB,$USER;
		
		$categories = $DB->get_records("block_exaportcate", array("userid"=>$USER->id));
	
		$itemstree = array();
		$maincategory = $DB->get_records("block_exaportitem", array("userid"=>$USER->id, "categoryid"=>0));
	
		$itemstree[0] = new stdClass();
		$items_temp = array();
		foreach($maincategory as $item){
			$itemstree[0]->id = 0;
			$itemstree[0]->name = "Hauptkategorie";
			$item_temp = new stdClass();
			$item_temp->id = $item->id;
			$item_temp->name = $item->name;
			$items_temp[] = $item_temp;
		}
		$itemstree[0]->items = $items_temp;
		foreach($categories as $category){
			$categoryitems = $DB->get_records("block_exaportitem", array("userid"=>$USER->id, "categoryid"=>$category->id));
	
			$itemstree[$category->id] = new stdClass();
			$items_temp = array();
			foreach($categoryitems as $item){
				$itemstree[$category->id]->id = $category->id;
				$itemstree[$category->id]->name = $category->name;
				$item_temp = new stdClass();
				$item_temp->id = $item->id;
				$item_temp->name = $item->name;
				$items_temp[] = $item_temp;
			}
			$itemstree[$category->id]->items = $items_temp;
		}
	
		return $itemstree;
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_multiple_structure
	 */
	public static function get_all_items_returns() {
		return new external_multiple_structure(
				new external_single_structure(
						array(
								'id' => new external_value(PARAM_INT, 'id of category'),
								'name' => new external_value(PARAM_TEXT, 'title of category'),
								'items' => new external_multiple_structure(
											new external_single_structure(
												array(
													'id' => new external_value(PARAM_INT, 'id of item'),
													'name' => new external_value(PARAM_TEXT, 'name of item')
												)
											)
										)
						)
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function add_view_item_parameters() {
		return new external_function_parameters(
				array(
						'viewid' => new external_value(PARAM_INT, 'view id'),
						'itemid' => new external_value(PARAM_INT, 'item id')
				)
		);
	}
	
	/**
	 * Add item to view
	 * @param int viewid, itemid
	 * @return success
	 */
	public static function add_view_item($viewid, $itemid) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::add_view_item_parameters(), array('viewid'=>$viewid, 'itemid'=>$itemid));
		
		$query = "SELECT MAX(positiony) from {block_exaportviewblock} WHERE viewid=?";
		$max = $DB->get_field_sql($query, array($viewid));
		$ycoord = intval($max)+1;
		
		$blockid = $DB->insert_record("block_exaportviewblock", array("viewid"=>$viewid, "itemid"=>$itemid, "positionx"=>1, "positiony"=>$ycoord, "type"=>"item"));
		
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function add_view_item_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function delete_view_item_parameters() {
		return new external_function_parameters(
				array(
						'viewid' => new external_value(PARAM_INT, 'view id'),
						'itemid' => new external_value(PARAM_INT, 'item id')
				)
		);
	}
	
	/**
	 * Remove item from view
	 * @param int viewid, itemid
	 * @return success
	 */
	public static function delete_view_item($viewid, $itemid) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::delete_view_item_parameters(), array('viewid'=>$viewid, 'itemid'=>$itemid));
		$query = "SELECT MAX(positiony) from {block_exaportviewblock} WHERE viewid=? AND itemid=?";
		$max = $DB->get_field_sql($query, array($viewid, $itemid));
		$ycoord = intval($max);
		$DB->delete_records("block_exaportviewblock", array("viewid"=>$viewid, "itemid"=>$itemid, "positiony"=>$ycoord));
	
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function delete_view_item_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function view_grant_external_access_parameters() {
		return new external_function_parameters(
				array(
						'id' => new external_value(PARAM_INT, 'view id'),
						'val' => new external_value(PARAM_INT, '1 for check, 0 for uncheck')
				)
		);
	}
	
	/**
	 * Grant external acces to view
	 * @param int id, val 
	 * @return success
	 */
	public static function view_grant_external_access($id, $val) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::view_grant_external_access_parameters(), array('id'=>$id, 'val'=>$val));
		
		$record = new stdClass();
		$record->id = $id;
		
		if($val == 0)
			$record->externaccess = 0;
		else 
			$record->externaccess = 1;
		
		$record->externcomment = 0;
		$DB->update_record("block_exaportview", $record);
		
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function view_grant_external_access_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function view_get_available_users_parameters() {
		return new external_function_parameters(
				array()
		);
	}
	
	/**
	 * Get all available users for sharing view
	 * @return all items available
	 */
	public static function view_get_available_users() {
		global $CFG,$DB,$USER;
	
		$mycourses = enrol_get_users_courses($USER->id, true);
		
		$usersincontext = array();
		foreach($mycourses as $course){
			$enrolledusers = get_enrolled_users(context_course::instance($course->id));
			foreach($enrolledusers as $user){
				if(!in_array($user, $usersincontext)){
					$usersincontext[] = $user;
				}
			}
		}
		
		$users = array();
		foreach($usersincontext as $user){
			$user_temp = new stdClass();
			$user_temp->id = $user->id;
			$user_temp->firstname = $user->firstname;
			$user_temp->lastname = $user->lastname;
			$users[] = $user_temp;
		}
		
		return $users;
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_multiple_structure
	 */
	public static function view_get_available_users_returns() {
		return new external_multiple_structure(
				new external_single_structure(
						array(
								'id' => new external_value(PARAM_INT, 'id of user'),
								'firstname' => new external_value(PARAM_TEXT, 'firstname of user'),
								'lastname' => new external_value(PARAM_TEXT, 'lastname of user'),
						)
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function view_grant_internal_access_all_parameters() {
		return new external_function_parameters(
				array(
						'id' => new external_value(PARAM_INT, 'view id'),
						'val' => new external_value(PARAM_INT, '1 for check, 0 for uncheck')
				)
		);
	}
	
	/**
	 * Grant internal acces to view to all users
	 * @param int id, val
	 * @return success
	 */
	public static function view_grant_internal_access_all($id, $val) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::view_grant_internal_access_all_parameters(), array('id'=>$id, 'val'=>$val));
	
		$record = new stdClass();
		$record->id = $id;
	
		if($val == 0)
			$record->shareall = 0;
		else
			$record->shareall = 1;
	
		$record->externcomment = 0;
		$DB->update_record("block_exaportview", $record);
	
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function view_grant_internal_access_all_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function view_grant_internal_access_parameters() {
		return new external_function_parameters(
				array(
						'viewid' => new external_value(PARAM_INT, 'view id'),
						'userid' => new external_value(PARAM_INT, 'user id'),
						'val' => new external_value(PARAM_INT, '1 for check, 0 for uncheck')
				)
		);
	}
	
	/**
	 * Grant internal acces to view to one user
	 * @param int viewid, userid, val
	 * @return success
	 */
	public static function view_grant_internal_access($viewid, $userid, $val) {
		global $CFG,$DB,$USER;
	
		$params = self::validate_parameters(self::view_grant_internal_access_parameters(), array('viewid'=>$viewid, 'userid'=>$userid, 'val'=>$val));
	
		if($val == 1)
			$blockid = $DB->insert_record("block_exaportviewshar", array("viewid"=>$viewid, "userid"=>$userid));
		if($val == 0)
			$DB->delete_records("block_exaportviewshar", array("viewid"=>$viewid, "userid"=>$userid));
	
		return array("success"=>true);
	}
	
	/**
	 * Returns desription of method return values
	 * @return external_single_structure
	 */
	public static function view_grant_internal_access_returns() {
		return new external_single_structure(
				array(
						'success' => new external_value(PARAM_BOOL, 'status')
				)
		);
	}
}

?>