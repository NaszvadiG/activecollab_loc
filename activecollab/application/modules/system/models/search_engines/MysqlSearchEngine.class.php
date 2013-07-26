<?php
//12 April 2012 (SA) Ticket #769: modify ac search results to list active tickets first
  require_once SYSTEM_MODULE_PATH . '/models/search_engines/SearchEngine.class.php';
  
  /**
   * MySQL search engine implementation
   * 
   * This search engine stores data into MyISAM table with FULLTEXT key on 
   * content field. This is default search engine and should be used only for 
   * low load websites
   * 
   * @package activeCollab.modules.system
   * @subpackage models
   */
  class MysqlSearchEngine extends SearchEngine  {
    
    /**
     * Mysql Search
     *
     * @param string $search_for
     * @param string $type
     * @param User $user
     * @param integer $page
     * @param integer $per_page
     * @return array
     */
	//BOF:mod 20120711
	/*
	//EOF:mod 20120711
    function search($search_for, $type, $user, $page = 1, $per_page = 30, $search_object_type = '', $search_under_project_id = '') {
	//BOF:mod 20120711
	*/
	function search($search_for, $type, $user, $page = 1, $per_page = 30, $search_object_type = '', $search_under_project_id = '', $datesort = '') {
	//EOF:mod 20120711
      $page = (integer) $page;
      $per_page = (integer) $per_page;
      
      $search_index_table = TABLE_PREFIX . 'search_index';
      
      $offset = ($page - 1) * $per_page;
      
      // Search in projects
      if($type == 'ProjectObject') {
        $type_filter = ProjectUsers::getVisibleTypesFilter($user, array(PROJECT_STATUS_ACTIVE, PROJECT_STATUS_PAUSED, PROJECT_STATUS_COMPLETED, PROJECT_STATUS_CANCELED));
        if(empty($type_filter)) {
          return array(null, new Pager(1, 0, $per_page));
        }// if
        if (strlen($search_for)<=2){
        	return array(null, new Pager(1, 0, $per_page));
        }
        //BOF:mod 20111102
       // $search_for = str_replace(' ', '% %', $search_for);
        //EOF:mod 20111102
        
        $project_objects_table = TABLE_PREFIX . 'project_objects';
        
        //$total_items = (integer) array_var(db_execute_one("SELECT COUNT($project_objects_table.id) AS 'row_count' FROM $project_objects_table, $search_index_table WHERE $type_filter AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $project_objects_table.id = $search_index_table.object_id AND $search_index_table.type = ? AND state >= ? AND visibility >= ?", $search_for, $type, STATE_VISIBLE, $user->getVisibility()), 'row_count');
        /*if (empty($search_object_type)){
        	$total_items = (integer) array_var(db_execute_one("SELECT COUNT($project_objects_table.id) AS 'row_count' FROM $project_objects_table, $search_index_table WHERE $type_filter AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $project_objects_table.id = $search_index_table.object_id AND $search_index_table.type = ? AND state >= ? AND visibility >= ?", $search_for, $type, STATE_VISIBLE, $user->getVisibility()), 'row_count');
        } else {
        	$total_items = (integer) array_var(db_execute_one("SELECT COUNT($project_objects_table.id) AS 'row_count' FROM $project_objects_table, $search_index_table WHERE $type_filter AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $project_objects_table.id = $search_index_table.object_id AND $search_index_table.type = ? AND state >= ? AND visibility >= ? AND $project_objects_table.type = ?", $search_for, $type, STATE_VISIBLE, $user->getVisibility(), $search_object_type), 'row_count');
        }*/
        $complete_str = '';
        if($_GET['complete']!='1'){
			$complete_str = " and healingcrystals_project_objects.completed_on is null and (healingcrystals_project_objects.completed_by_id is null or healingcrystals_project_objects.completed_by_id='0') and healingcrystals_project_objects.boolean_field_1 is null ";
        }
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
      	$query_main = "(select healingcrystals_sort_order_for_search.sort_order, if(healingcrystals_project_objects.completed_on is null, '0', '1'), '0' as new_order, healingcrystals_project_objects.* 
		  		 from healingcrystals_project_objects " . 
		  		 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") .
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter . $complete_str. " 
				 and (healingcrystals_project_objects.name is not null) 
				 and (healingcrystals_project_objects.name like '%" . addslashes($search_for) . "%') 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .  
				 " )
				 UNION 
				 (select healingcrystals_sort_order_for_search.sort_order, if(healingcrystals_project_objects.completed_on is null, '0', '1'), '0' as new_order, healingcrystals_project_objects.* 
		  		 from healingcrystals_project_objects " .
				 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") . 
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter . $complete_str . " 
				 and (healingcrystals_project_objects.body like '%" . addslashes($search_for) . "%') and 
				 (healingcrystals_project_objects.name is null or healingcrystals_project_objects.name='') 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .
				 " ) ";
      	$count_query_main = "(select * 
		  		 from healingcrystals_project_objects " . 
		  		 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") .
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter . $complete_str . " and (healingcrystals_project_objects.name is not null) 
				 and (healingcrystals_project_objects.name like '%" . addslashes($search_for) . "%') 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .  
				 " )
				 UNION 
				 (select * from healingcrystals_project_objects " .
				 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") . 
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter . $complete_str . " 
				 and (healingcrystals_project_objects.body like '%" . addslashes($search_for) . "%')  and 
				 (healingcrystals_project_objects.name is null or healingcrystals_project_objects.name='') 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .
				 " ) ";
        //BOF-20120216
       
        if(strpos($search_for, ' ')!== false){
        $all_parts_in_name_string = '';
        $all_parts_in_body_string = '';
        $few_parts_in_name_string = '';
        $string_parts = explode(' ', $search_for);        
        $query_parts_name = array();
        $query_parts_description = array();
        foreach($string_parts as $part){
            $query_parts_name[] = "healingcrystals_project_objects.name like '%" . addslashes($part) . "%'";
            $query_parts_description[] = "healingcrystals_project_objects.body like '%" . addslashes($part) . "%'";
        }
        $all_parts_in_name_string = implode(" and ",$query_parts_name);
        $all_parts_in_body_string = implode(" and ",$query_parts_description);
        $few_parts_in_name_string = implode(" or ",$query_parts_name);
        $few_parts_in_body_string = implode(" or ",$query_parts_description);

        $query_main = "(select distinct(healingcrystals_project_objects.id), healingcrystals_sort_order_for_search.sort_order, if(healingcrystals_project_objects.completed_on is null, '0', '1'), if(healingcrystals_project_objects.name like '%" . addslashes($search_for) . "%','1', if(".$all_parts_in_name_string.",'3',if(".$few_parts_in_name_string.",'5','99'))) as new_order, healingcrystals_project_objects.* 
		  		 from healingcrystals_project_objects " . 
		  		 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") .
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter . $complete_str . " 
				 and (healingcrystals_project_objects.name is not null) 
				 and ( (healingcrystals_project_objects.name like '%" . addslashes($search_for) . "%') or ( ".$all_parts_in_name_string." )  or ( ".$few_parts_in_name_string." ) ) 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .  
				 " )
				 UNION 
				 (select distinct(healingcrystals_project_objects.id), healingcrystals_sort_order_for_search.sort_order, if(healingcrystals_project_objects.completed_on is null, '0', '1'), if(healingcrystals_project_objects.body like '%" . addslashes($search_for) . "%','2', if(".$all_parts_in_body_string.",'4',if(".$few_parts_in_body_string.",'6','99'))) as new_order, healingcrystals_project_objects.* 
		  		 from healingcrystals_project_objects " .
				 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") . 
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter . $complete_str . " 
				 and ( (healingcrystals_project_objects.body like '%" . addslashes($search_for) . "%') or ( ".$all_parts_in_body_string." ) or ( ".$few_parts_in_body_string." ) ) 
				 and (healingcrystals_project_objects.name is null or healingcrystals_project_objects.name='') 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .
				 " )";
        $count_query_main = "(select * from healingcrystals_project_objects " . 
		  		 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") .
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter. $complete_str . " and (healingcrystals_project_objects.name is not null) 
				 and ( (healingcrystals_project_objects.name like '%" . addslashes($search_for) . "%' ) or ( ".$all_parts_in_name_string." ) or ( ".$few_parts_in_name_string." ) ) 
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .  
				 " )
				 UNION 
				 (select * from healingcrystals_project_objects " .
				 (!empty($search_under_project_id) ? " inner join healingcrystals_projects on (healingcrystals_project_objects.project_id=healingcrystals_projects.id and  healingcrystals_project_objects.project_id='" . (int)$search_under_project_id . "') " : " ") . 
				 " inner join healingcrystals_search_index on healingcrystals_project_objects.id=healingcrystals_search_index.object_id 
		  		 left join healingcrystals_sort_order_for_search on healingcrystals_sort_order_for_search.type=healingcrystals_project_objects.type 
				 where " . $type_filter. $complete_str . "
				 and ( (healingcrystals_project_objects.body like '%" . addslashes($search_for) . "%' ) or ( ".$all_parts_in_body_string." ) or ( ".$few_parts_in_body_string." ) ) 
				 and (healingcrystals_project_objects.name is null or healingcrystals_project_objects.name='')  
				 and healingcrystals_search_index.type='" . $type . "' 
				 and healingcrystals_project_objects.state='" . STATE_VISIBLE . "' " .  
				 //" and healingcrystals_project_objects.visibility='" . $user->getVisibility() . "' " . 
				 (empty($search_object_type) ? "" : " and healingcrystals_project_objects.type='" . $search_object_type . "' " ) .
				 " )";

        }
				$result = mysql_query($query_main, $link);
				$count_result = mysql_query($count_query_main, $link);
				$count = mysql_num_rows($count_result);
		$total_items = mysql_num_rows($result);
        //mysql_query("insert into testing (content, date_added) values ('" . mysql_real_escape_string($query_main) . "', now())");

        if($total_items) {
        	$rows = array();
        	$items = array();
          //$items = ProjectObjects::findBySQL("SELECT $project_objects_table.* FROM $project_objects_table, $search_index_table WHERE $type_filter AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $project_objects_table.id = $search_index_table.object_id AND $search_index_table.type = ? AND state >= ? AND visibility >= ? LIMIT $offset, $per_page", array($search_for, $type, STATE_VISIBLE, $user->getVisibility()));
          	$ids = array();
          	//BOF:mod 20110706 ticketid222
          	/*
          	//EOF:mod 20110706 ticketid222
          	$query = $query_main . " order by 2, 1 LIMIT " . $offset . " ," . $per_page;
          	//BOF:mod 20110706 ticketid222
          	*/
			//BOF:mod 20120711
			if (empty($datesort)){
			//EOF:mod 20120711
				$query = $query_main . " order by new_order ASC, 2, 1, created_on desc LIMIT " . $offset . " ," . $per_page;
			//BOF:mod 20120711
			} elseif ($datesort=='a'){
				$query = $query_main . " order by created_on, new_order ASC, 2, 1 LIMIT " . $offset . " ," . $per_page;
			} elseif ($datesort=='d'){
				$query = $query_main . " order by created_on desc, new_order ASC, 2, 1 LIMIT " . $offset . " ," . $per_page;
			}
			//EOF:mod 20120711
          	//EOF:mod 20110706 ticketid222
          	$result = mysql_query($query, $link);

          	while($info = mysql_fetch_assoc($result)){
          		$ids[] = $info['id'];
				$rows[] = $info;
          	}
          	foreach($rows as $row){
          		$item_class = array_var($row, 'type');
          
          		$item = new $item_class();
          		$item->loadFromRow($row);

				$add_item = true;
				if($_GET['complete']!='1'){
					if ($item->getParentType()=='Page'){
						$temp_page = new Page($item->getParentId());
						$is_archived = $temp_page->getIsArchived();
						if ($is_archived){
							$add_item = false;
						}
					}
					if ($add_item){
						$temp_obj = new ProjectObject($item->getParentId());
						if ($temp_obj->isCompleted()) $add_item = false;
					}
				}
				if ($add_item) $items[] = $item;
          	}
          if (empty($search_object_type)){
          	//$items = ProjectObjects::findBySQL("SELECT $project_objects_table.* FROM $project_objects_table, $search_index_table WHERE $type_filter AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $project_objects_table.id = $search_index_table.object_id AND $search_index_table.type = ? AND state >= ? AND visibility >= ? LIMIT $offset, $per_page", array($search_for, $type, STATE_VISIBLE, $user->getVisibility()));
          } else {
          	//$items = ProjectObjects::findBySQL("SELECT $project_objects_table.* FROM $project_objects_table, $search_index_table WHERE $type_filter AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $project_objects_table.id = $search_index_table.object_id AND $search_index_table.type = ? AND state >= ? AND visibility >= ? AND $project_objects_table.type = ? LIMIT $offset, $per_page", array($search_for, $type, STATE_VISIBLE, $user->getVisibility(), $search_object_type));
          }
          //mysql_query("insert into healingcrystals_testing (query, fired_at) values ('" . mysql_real_escape_string($query) . "', now())", $link);
          

        } else {
          $items = null;
        } // if
        mysql_close($link);
        return array($items, new Pager($page, $total_items, $per_page), $count);
        
      // Search for projects
      } elseif($type == 'Project') {
        $project_ids = Projects::findProjectIdsByUser($user, null, true);
        if(!is_foreachable($project_ids)) {
          return array(null, new Pager(1, 0, $per_page));
        } // if
        
        $projects_table = TABLE_PREFIX . 'projects';
        
        $total_items = (integer) array_var(db_execute_one("SELECT COUNT($projects_table.id) AS 'row_count' FROM $projects_table, $search_index_table WHERE $projects_table.id IN (?) AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $projects_table.id = $search_index_table.object_id AND $search_index_table.type = ?", $project_ids, $search_for, 'Project'), 'row_count');
        if($total_items) {
          $items = Projects::findBySQL("SELECT * FROM $projects_table, $search_index_table WHERE $projects_table.id IN (?) AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $projects_table.id = $search_index_table.object_id AND $search_index_table.type = ? LIMIT $offset, $per_page", array($project_ids, $search_for, 'Project'));
        } else {
          $items = null;
        } // if
        
        return array($items, new Pager($page, $total_items, $per_page));
        
      // Search for users
      } elseif($type == 'User') {
        $user_ids = $user->visibleUserIds();
        if(!is_foreachable($user_ids)) {
          return array(null, new Pager(1, 0, $per_page));
        } // if
        $users_table = TABLE_PREFIX . 'users';
        
        $total_items = (integer) array_var(db_execute_one("SELECT COUNT($users_table.id) AS 'row_count' FROM $users_table, $search_index_table WHERE $users_table.id IN (?) AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $users_table.id = $search_index_table.object_id AND $search_index_table.type = ?", $user_ids, $search_for, 'User'), 'row_count');
        if($total_items) {
          $items = Users::findBySQL("SELECT * FROM $users_table, $search_index_table WHERE $users_table.id IN (?) AND MATCH ($search_index_table.content) AGAINST (? IN BOOLEAN MODE) AND $users_table.id = $search_index_table.object_id AND $search_index_table.type = ? LIMIT $offset, $per_page", array($user_ids, $search_for, 'User'));
        } else {
          $items = null;
        } // if
        
        return array($items, new Pager($page, $total_items, $per_page));
        
      // Unknown search type
      } else {
        return array(null, new Pager(1, 0, $per_page));
      } // if
    } // search
	
	//BOF:mod 20120822
	function search_attachments($search_for, $user, $page, $per_page, $search_object_type, $search_under_project_id, $datesort){
		$page = (integer) $page;
		$per_page = (integer) $per_page;
		$offset = ($page - 1) * $per_page;
		
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		
		$sql = "select ::PLACE_HOLDER:: from healingcrystals_attachments a inner join healingcrystals_project_objects b on (a.parent_id=b.id" . (!empty($search_under_project_id) ? " and b.project_id='" . $search_under_project_id . "'" : "") . ") where a.name like '%" .  $search_for. "%' ";
		$count_sql = str_replace('::PLACE_HOLDER::', "count(*) as row_count", $sql);
		$result = mysql_query($count_sql, $link);
		$info = mysql_fetch_assoc($result);
		$total_items = $info['row_count'];
		
		if ($total_items){
			if (empty($datesort)){
				$sql =  str_replace('::PLACE_HOLDER::', 'a.id', $sql) . " order by a.name LIMIT " . $offset . " ," . $per_page;
			} elseif ($datesort=='a'){
				$sql = str_replace('::PLACE_HOLDER::', 'a.id', $sql) . " order by a.created_on, a.name LIMIT " . $offset . " ," . $per_page;
			} elseif ($datesort=='d'){
				$sql = str_replace('::PLACE_HOLDER::', 'a.id', $sql) . " order by a.created_on desc, a.name LIMIT " . $offset . " ," . $per_page;
			}

			$items = Attachments::findBySQL($sql);
		} else {
			$items = null;
		}
		mysql_close($link);
		return array($items, new Pager($page, $total_items, $per_page), $total_items);
	}
	//EOF:Mod 20120822
    
    /**
     * Update
     *
     * @param integer $object_id
     * @param string $type
     * @param string $content
     * @param array $atributtes
     * @return null
     */
    function update($object_id, $type, $content, $atributtes = null) {
      $search_index_table = TABLE_PREFIX . 'search_index';
      if(search_index_has($object_id, $type)) {
        return db_execute("UPDATE $search_index_table SET content = ? WHERE object_id = ? AND type = ?", $content, $object_id, $type);
      } else {
        return db_execute("INSERT INTO $search_index_table (object_id, type, content) VALUES (?, ?, ?)", $object_id, $type, $content);
      } // if
    } // update
    
    /**
     * Remove from search index
     *
     * @param mixed $object_id
     * @param string $type
     * @return null
     */
    function remove($object_id, $type) {
      $search_index_table = TABLE_PREFIX . 'search_index';
    	return db_execute("DELETE FROM $search_index_table WHERE object_id IN (?) AND type = ?", $object_id, $type);
    } // remove
    
    /**
     * Returns true if we already have an search index for a given entry
     *
     * @param integer $object_id
     * @param string $type
     * @return boolean
     */
    function hasObject($object_id, $type) {
      $search_index_table = TABLE_PREFIX . 'search_index';
      return (boolean) array_var(db_execute_one("SELECT COUNT(*) AS 'row_count' FROM $search_index_table WHERE object_id = ? AND type = ?", $object_id, $type), 'row_count');
    } // hasObject
    
  } 

?>