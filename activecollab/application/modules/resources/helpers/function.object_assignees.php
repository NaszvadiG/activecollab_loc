<?php

  /**
   * object_assignees helper
   *
   * @package activeCollab.modules.resources
   * @subpackage helpers
   */
  
  /**
   * Render object assignees list
   *
   * @param array $params
   * @param Smarty $smarty
   * @return string
   */
  function smarty_function_object_assignees($params, &$smarty) {
    $object = array_var($params, 'object');
    if(!instance_of($object, 'ProjectObject')) {
      return new InvalidParamError('object', $object, '$object is expected to be an instance of ProjectObject class', true);
    } // if
    
    $language = array_var($params, 'language', $smarty->get_template_vars('current_language')); // maybe we need to print this in a specific language?
	//BOF:mod 20121122
	/*
	//EOF:mod 20121122
    if(instance_of($language, 'Language')) {
      $cache_id = 'object_assignments_' . $object->getId() . '_rendered_' . $language->getId();
      $cached_value = cache_get($cache_id);
    } else {
	//BOF:mod 20121122
	*/
	//EOF:mod 20121122
      $cache_id = null;
      $cached_value = null;
	//BOF:mod 20121122
	/*
	//EOF:mod 20121122
    } // if
	//BOF:mod 20121122
	*/
	//EOF:mod 20121122
    
    //if($cached_value) {
    // return $cached_value;
    //} else {
      $users_table = TABLE_PREFIX . 'users';
      $assignments_table = TABLE_PREFIX . 'assignments';
      
      $rows = db_execute_all("SELECT $assignments_table.is_owner AS is_assignment_owner, $users_table.id AS user_id, $users_table.company_id, $users_table.first_name, $users_table.last_name, $users_table.email FROM $users_table, $assignments_table WHERE $users_table.id = $assignments_table.user_id AND $assignments_table.object_id = ? ORDER BY $assignments_table.is_owner DESC", $object->getId());
      if(is_foreachable($rows)) {
        $owner = null;
        $other_assignees = array();
        $users_dropdown_for_tickets = '';
        foreach($rows as $row) {
          if(empty($row['first_name']) && empty($row['last_name'])) {
            //$user_link = '<a href="' . assemble_url('people_company', array('company_id' => $row['company_id'])) . '#user' . $row['user_id'] . '">' . clean($row['email'])  . '</a>';
            $user_link = '<a href="' . assemble_url('project_people', array('project_id' => $object->getProjectId())) . '">' . clean($row['email'])  . '</a>';
          } else {
            //$user_link = '<a href="' . assemble_url('people_company', array('company_id' => $row['company_id'])) . '#user' . $row['user_id'] . '">' . clean($row['first_name'] . ' ' . $row['last_name'])  . '</a>';
            $user_link = '<a href="' . assemble_url('project_people', array('project_id' => $object->getProjectId())) . '">' . clean($row['first_name'] . ' ' . $row['last_name'])  . '</a>';
          } // if
          
          if($row['is_assignment_owner']) {
          	//if(!instance_of($object, 'Ticket')) {
          		$owner = $user_link;
          	//}
          } else {
            $other_assignees[] = $user_link;
          } // if
          /*
          if(instance_of($object, 'Ticket')) {
	        if (empty($users_dropdown_for_tickets)){
	        	$users_dropdown_for_tickets = '<select onchange="modify_responsible_status(this);">';
	        }
	        $users_dropdown_for_tickets .= '<option value="' . $row['user_id'] . '"' . ($row['is_assignment_owner'] ? ' selected ' : '') . '>';
          	if(empty($row['first_name']) && empty($row['last_name'])) {
            	$users_dropdown_for_tickets .= clean($row['email']);
          	} else {
            	$users_dropdown_for_tickets .= clean($row['first_name'] . ' ' . $row['last_name']);
          	}
	        $users_dropdown_for_tickets .= '</option>';
          }
          */
        } // foreach
        /*
        if (!empty($users_dropdown_for_tickets)){
        	$users_dropdown_for_tickets .= '</select>';
        	$owner = $users_dropdown_for_tickets;
        }
        */
        if($owner) {
        	if(instance_of($object, 'Ticket')) {
        	  $popup_url = assemble_url('responsible_status_popup', array('project_id' => $object->getProjectId(), 'ticket_id' => $object->getTicketId()));
	          if(count($other_assignees) > 0) {
	            //$cached_value = $owner . ' is <a href="' . $popup_url . '" id="is_responsible" href_="' . assemble_url('project_ticket_edit', array('project_id' => $object->getProjectId(), 'ticket_id' => $object->getTicketId())) . '">responsible</a>. ' . lang('Other assignees', null, true, $language) . ': ' . implode(', ', $other_assignees) . '.';
	            //$cached_value = 'Owner: ' .  $owner . '<br/>' . lang('Assignees: ', null, true, $language) . ': ' . implode(', ', $other_assignees);
	            $cached_value = $owner;
	            if (!empty($cached_value)){
	            	$cached_value .= ', ';
	            }
	            $cached_value .= implode(', ', $other_assignees);
	          } else {
	            //$cached_value = $owner . ' is <a href="' . $popup_url . '" id="is_responsible" href_="' . assemble_url('project_ticket_edit', array('project_id' => $object->getProjectId(), 'ticket_id' => $object->getTicketId())) . '">responsible</a>.';
	            //$cached_value = 'Owner: ' . $owner;
	            $cached_value = $owner;
	          } // if
        	} else {
	          if(count($other_assignees) > 0) {
	            $cached_value = $owner . ' ' . lang('is responsible', null, true, $language) . '. ' . lang('Other assignees', null, true, $language) . ': ' . implode(', ', $other_assignees) . '.';
	          } else {
	            $cached_value = $owner . ' ' . lang('is responsible', null, true, $language) . '.';
	          } // if
        	}
        } // if
      } // if

		//BOF:mod 20121122
		/*
		//EOF:mod 20121122      
      if(empty($cached_value)) {
        $cached_value = lang('Anyone can pick and complete this task', null, true, $language);
      } // if
		//BOF:mod 20121122
		*/
		//EOF:mod 20121122
      
      if(instance_of($language, 'Language') && $cache_id) {
        cache_set($cache_id, $cached_value); // cache if we don't have language parameter set
      } // if
      
      return $cached_value;
    //} // if
  } // smarty_function_object_assignees

?>