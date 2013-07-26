<?php

  /**
   * System module on_build_menu event handler
   *
   * @package activeCollab.modules.system
   * @subpackage handlers
   */
  
  /**
   * Build menu
   *
   * @param Menu $menu
   * @param User $user
   * @return array
   */
  //BOF:task_1260
  /*
  //EOF:task_1260
  function system_handle_on_build_menu(&$menu, &$user) {
  //BOF:task_1260
  */
  function system_handle_on_build_menu(&$menu, &$user, &$project = null) {
  //EOF:task_1260
    
    // ---------------------------------------------------
    //  Tools
    // ---------------------------------------------------
    //BOF:task_1260
    /*if (is_null($project)){
    	$my_tickets_menu = new MenuItem('today', lang('My Tickets'), assemble_url('user_today_page'), get_image_url('navigation/today.png'));
    } else {
    	$my_tickets_menu = new MenuItem('today', lang('My Tickets'), assemble_url('project_user_today_page', array('project_id' => $project->getId(), 'user_id' => $user->getId())), get_image_url('navigation/today.png'));
    }*/
	//EOF:task_1260 
    
    $menu->addToGroup(array(
      //BOF:mod 20110715 ticketid246
      //new MenuItem('homepage', lang('Home Page'), assemble_url('goto_home_tab'), get_image_url('navigation/home.gif')),
      //EOF:mod 20110715 ticketid246
      //$my_tickets_menu,
      //(!is_null($project) ? new MenuItem('today', lang('My Tickets'), assemble_url('project_user_today_page', array('project_id' => $project->getId(), 'user_id' => $user->getId())), get_image_url('navigation/today.png')) : new MenuItem('today', lang('My Tickets'), assemble_url('user_today_page'), get_image_url('navigation/today.png')) ),         
	  //BOF:mod 20120914
	  /*
	  //EOF:mod 20120914
      new MenuItem('today', lang('My Tickets'), assemble_url('my_tickets'), get_image_url('navigation/today.png')), 
	  //BOF:mod 20120914
	  */
	  new MenuItem('tasks', lang('My Tasks'), assemble_url('goto_user_task_page', array('project_id' => TASK_LIST_PROJECT_ID)) . '&selected_user_id=' . $user->getId(), get_image_url('navigation/today.png')), 
	  //EOF:mod 20120914
      new MenuItem('calendar', lang('Calendar'), Calendar::getDashboardCalendarUrl(), get_image_url('navigation/calendar.gif')),
      //new MenuItem('milestones', lang('My Milestones'), assemble_url('user_assigned_milestones'), get_image_url('navigation/milestones.png')),
      new MenuItem('projects', lang('Projects'), assemble_url('projects'), get_image_url('navigation/projects.gif')),
      new MenuItem('people', lang('People'), assemble_url('people'), get_image_url('navigation/people.gif')),
      //BOF:task_1260
      /*
      //EOF:task_1260
      //new MenuItem('today', lang('My Tickets'), assemble_url('user_today_page'), get_image_url('navigation/today.png')),
      //BOF:task_1260
      */
      //$my_tickets_menu,
      //EOF:task_1260
    ), 'main');
    
    // ---------------------------------------------------
    //  Folders
    // ---------------------------------------------------
    
    $folders = array(
      //new MenuItem('assignments', lang('Assignmt.'), assemble_url('assignments'), get_image_url('navigation/assignments.gif')),
      new MenuItem('search', lang('Search'), assemble_url('quick_search'), get_image_url('navigation/search.gif')),
      new MenuItem('starred_folder', lang('Starred'), assemble_url('starred'), get_image_url('navigation/starred.gif'))
    );
    
    if($user->isAdministrator() || $user->getSystemPermission('manage_trash')) {
      $folders[] = new MenuItem('trash', lang('Trash'), assemble_url('trash'), get_image_url('navigation/trash.gif'));
    } // if
    
    $folders[] = new MenuItem('quick_add', lang('Quick Add'), assemble_url('homepage'), get_image_url('navigation/quick_add.gif'), null, '+');
    
    $menu->addToGroup($folders, 'folders');
  } // system_handle_on_build_menu

?>