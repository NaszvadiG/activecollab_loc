<?php
/*27/3/2012 Ticket #298: Create a page that shows Action Requests that you have Assigned (SA)*/
//12 April 2012 (SA) Ticket #769: modify ac search results to list active tickets first
//27 April 2012 (SA) Ticket #769: modify ac search results to list active tickets first
  /**
   * Dashboard controller
   *
   * @package activeCollab.modules.system
   * @subpackage controllers
   */
  class DashboardController extends ApplicationController {

    /**
     * Show dashboard overview
     *
     * @param void
     * @return null
     */
    function index() {

      // Welcome message, displayed only to administrators
      if($this->logged_user->isAdministrator() && ConfigOptions::getValue('show_welcome_message')) {
        $this->wireframe->addPageAction(lang('Hide Welcome Message'), assemble_url('admin_settings_hide_welcome_message'), null, array(
          'method' => 'post',
          'confirm' => lang('You are about to hide welcome message. If you wish to bring it back later on you can do it from General settings page in Administration. Hide now?'),
        ));

        $this->smarty->assign(array(
          'show_welcome_message' => true,
          'available_modules' => Modules::findNotInstalled(),
        ));
        //BOF: task 05 | AD
        $this->wireframe->addPageAction(lang('View All'), assemble_url('view_projects_info'));
        //EOF: task 05 | AD
      // Regular dashboard
      } else {
        if(Project::canAdd($this->logged_user)) {
          $this->wireframe->addPageAction(lang('New Project'), assemble_url('projects_add'));
          //BOF: task 05 | AD
          $this->wireframe->addPageAction(lang('View All'), assemble_url('view_projects_info'));
          //EOF: task 05 | AD
        } // if

        $this->wireframe->addRssFeed(
          $this->owner_company->getName() . ' - ' . lang('Recent activities'),
          assemble_url('rss', array('token' => $this->logged_user->getToken(true))),
          FEED_RSS
        );

        $pinned_project_ids = PinnedProjects::findProjectIdsByUser($this->logged_user);
        if(is_foreachable($pinned_project_ids)) {
          $pinned_projects = Projects::findByIds($pinned_project_ids);
        } else {
          $pinned_projects = null;
        } // if

        $dashboard_sections = new NamedList();
        event_trigger('on_dashboard_sections', array(&$dashboard_sections, &$this->logged_user));

        $important_items = new NamedList();
        event_trigger('on_dashboard_important_section',array(&$important_items, &$this->logged_user));

        $this->smarty->assign(array(
          'show_welcome_message' => false,
          'important_items' => $important_items,
          'pinned_projects' => $pinned_projects,
          'dashboard_sections' => $dashboard_sections,
          'online_users' => Users::findWhoIsOnline($this->logged_user),
          'grouped_activities' => group_by_date(ActivityLogs::findActiveProjectsActivitiesByUser($this->logged_user, 20), $this->logged_user),
        ));
      } // if
      //BOF:mod 20110623
      $tabs = new NamedList();
      $tabs->add('dashboard', array('text' => 'Active Teams', 'url' => assemble_url('dashboard')));
      $tabs->add('home_page', array('text' => 'Home Page', 'url' => assemble_url('goto_home_tab')));
      $tabs->add('assigned_action_request', array('text' => 'Assigned Action Requests', 'url' => assemble_url('assigned_action_request')));
      $tabs->add('owned_tickets', array('text' => 'Owned Tickets', 'url' => assemble_url('my_tickets')));
      $tabs->add('subscribed_tickets', array('text' => 'Subscribed Tickets', 'url' => assemble_url('my_subscribed_tickets')));
      $this->smarty->assign('page_tabs', $tabs);
      $this->smarty->assign('page_tab', 'dashboard');
      //EOF:mod 20110623
    } // index

    /**
     * Trashed Project Objects
     *
     * @param void
     * @return null
     */
    function trash() {
      $this->wireframe->current_menu_item = 'trash';

      if(!$this->logged_user->isAdministrator() && !$this->logged_user->getSystemPermission('manage_trash')) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      if($this->request->isSubmitted()) {
        $action = $this->request->post('action');
        if(!in_array($action, array('restore', 'delete'))) {
          $this->httpError(HTTP_ERR_BAD_REQUEST, 'Invalid action');
        } // if

        $object_ids = $this->request->post('objects');
        $objects = ProjectObjects::findByIds($object_ids, STATE_DELETED, VISIBILITY_PRIVATE);

        db_begin_work();
        foreach($objects as $object) {
          if($action == 'restore') {
            $object->restoreFromTrash();
          } else {
            $object->delete();
          } // if
        } // foreach
        db_commit();
      } // if

      $per_page = 30;
      $page = (integer) $this->request->get('page');
      if($page < 1) {
        $page = 1;
      } // if

      list($objects, $pagination) = ProjectObjects::paginateTrashed($this->logged_user, $page, $per_page);
    	$this->smarty->assign(array(
    	  'objects' => $objects,
    	  'pagination' => $pagination,
    	));

    	if(is_foreachable($objects)) {
    	  $this->wireframe->addPageAction(lang('Empty Trash'), assemble_url('trash_empty'), null, array(
    	    'method' => 'post',
    	    'confirm' => lang('Are you sure that you want to empty trash?'),
    	  ));
    	} // if
    } // trash

    /**
     * Delete permanently all items that are in trash
     *
     * @param void
     * @return null
     */
    function trash_empty() {
      if(!$this->logged_user->isAdministrator() && !$this->logged_user->getSystemPermission('manage_trash')) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      $operations_performed = 0;

      $objects = ProjectObjects::findTrashed($this->logged_user);
      if(is_foreachable($objects)) {
        db_begin_work();
        foreach($objects as $object) {
          $delete = $object->delete();
          if($delete && !is_error($delete)) {
            $operations_performed++;
          } // if
        } // foreach
        db_commit();

        flash_success(':count objects deleted', array('count' => $operations_performed));
      } else {
        flash_success('Already empty');
      } // if

      $this->redirectTo('trash');
    } // trash_empty

    /**
     * Starred Project Objects
     *
     * @param void
     * @return null
     */
    function starred() {
      $this->wireframe->current_menu_item = 'starred_folder';
      if($this->request->isSubmitted()) {
        $action = $this->request->post('action');
        if(!in_array($action, array('unstar', 'unstar_and_complete', 'trash'))) {
          $this->httpError(HTTP_ERR_BAD_REQUEST, 'Invalid action');
        } // if

        $objects = ProjectObjects::findByIds($this->request->post('objects'), STATE_VISIBLE, $this->logged_user->getVisibility());

        db_begin_work();
        foreach($objects as $object) {

          // Unstar selected object
          if($action == 'unstar') {
            $object->unstar($this->logged_user);

          // Unstar and marked as completed
          } elseif($action == 'unstar_and_complete') {
            $operation = $object->unstar($this->logged_user);
            if($operation && !is_error($operation)) {
              if($object->can_be_completed) {
                $object->complete($this->logged_user);
              } // if
            } // if

          // Move to Trash
          } elseif($action == 'trash') {
            if(!$object->canDelete($this->logged_user)) {
              continue;
            } // if

            $object->moveToTrash();
          } // if

        } // foreach
        db_commit();
      } // if

    	$this->smarty->assign('objects', StarredObjects::findByUser($this->logged_user));
    	$this->smarty->assign('user_pages', StarredObjects::findStarredPagesByUser($this->logged_user->getId(), '2'));

    	if($this->request->get('async')) {
        $this->smarty->display(get_template_path('starred', 'dashboard', SYSTEM_MODULE));
        die();
      } // if
    } // starred

    function get_object_types(){
    	$types = array();
    	$types[] = array('id' => '', 'text' => 'Select Type');
    	$types[] = array('id' => 'Milestone', 'text' => lang('Milestone'));
    	$types[] = array('id' => 'Ticket', 'text' => 'Ticket');
    	$types[] = array('id' => 'Task', 'text' => 'Task');
    	$types[] = array('id' => 'Page', 'text' => 'Page');
    	$types[] = array('id' => 'File', 'text' => 'File');
    	$types[] = array('id' => 'Comment', 'text' => 'Comment');
    	$types[] = array('id' => 'Discussion', 'text' => 'Discussion');
    	$types[] = array('id' => 'Checklist', 'text' => 'Checklist');
    	$types[] = array('id' => 'Category', 'text' => 'Category');
		//BOF:mod 20120822
		$types[] = array('id' => 'Attachment', 'text' => 'Attachment');
		//EOF:mod 20120822
    	return $types;
    }

    /**
     * Search
     *
     * @param void
     * @return null
     */
    function search() {
      //BOF:mod 20110629 search
      if (trim($this->request->get('q'))!=''){
		//$_SESSION['search_string'] = trim($this->request->get('q'));
	  }
      //$this->smarty->assign('search_string', $_SESSION['search_string']);
      //EOF:mod 20110629 search
      $this->wireframe->current_menu_item = 'search';
      $this->smarty->assign('search_url', assemble_url('search'));

      /*$object_types = array();
      $object_types[] = array('id' => '', 'text' => '');
      $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
      mysql_select_db(DB_NAME, $link);
      $query = "select distinct type from healingcrystals_project_objects order by type";
      $result = mysql_query($query);
      while($type = mysql_fetch_assoc($result)){
      	$object_types[] = array('id' => $type['type'], 'text' => $type['type']);
      }
      mysql_close($link);*/
      $object_types = $this->get_object_types();
      $this->smarty->assign('object_types', $object_types);

      $search_projects = array();
      $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
      mysql_select_db(DB_NAME, $link);
      $query = "select distinct a.project_id from healingcrystals_project_users a inner join healingcrystals_projects b on a.project_id=b.id where a.user_id='" . $this->logged_user->getId() . "' order by b.name";
      $result = mysql_query($query);
      while($entry = mysql_fetch_assoc($result)){
		$search_projects[] = new Project($entry['project_id']);
	  }
      mysql_close($link);
      $this->smarty->assign('search_projects', $search_projects);


      $search_for = trim($this->request->get('q'));
      $search_type = $this->request->get('type');
      $search_object = $this->request->get('search_object');
      $search_project_id = $this->request->get('search_project_id');
      $complete = $this->request->get('complete');
	  //BOF:mod 20120711
	  $datesort = $this->request->get('datesort');
	  //EOF:mod 20120711	
      $per_page = 30;

      $page = (integer) $this->request->get('page');
      if($page < 1) {
        $page = 1;
      } // if

      if($search_for && $search_type) {
      	$complete_count = 0;
        // Search inside the project
        if($search_type == 'in_projects') {
		  //BOF:mod 20120711
		  /*
		  //EOF:mod 20120711
          list($results, $pagination, $complete_count) = search_index_search($search_for, 'ProjectObject', $this->logged_user, $page, $per_page, $search_object, $search_project_id);
		  //BOF:mod 20120711
		  */
		  //BOF:mod 20120822
		  if ($search_object=='Attachment'){
			list($results, $pagination, $complete_count) = search_attachments($search_for, $this->logged_user, $page, $per_page, $search_object, $search_project_id, $datesort);
		  } else {
		  //EOF:mod 20120822
			list($results, $pagination, $complete_count) = search_index_search($search_for, 'ProjectObject', $this->logged_user, $page, $per_page, $search_object, $search_project_id, $datesort);
		  //BOF:mod 20120822
		  }
		  //EOF:mod 20120822		  
		  //EOF:mod 20120711
        // Search for people
        } elseif($search_type == 'for_people') {
        	$search_object = '';
          list($results, $pagination) = search_index_search($search_for, 'User', $this->logged_user, $page, $per_page);

        // Search for projects
        } elseif($search_type == 'for_projects') {
        	$search_object = '';
          list($results, $pagination) = search_index_search($search_for, 'Project', $this->logged_user, $page, $per_page);

        // Unknown type
        } else {
          $search_for = '';
          $search_type = null;
          $search_object = '';
          $search_project_id = '';
        } // if

      } else {
        $search_for = '';
        $search_type = null;
        $search_object = '';
        $search_project_id = '';
      } // if

      $this->smarty->assign(array(
        'search_for'     => $search_for,
        'search_type'    => $search_type,
        'search_object' => $search_object,
        'search_results' => $results,
        'pagination'     => $pagination,
        'complete'     => $complete,
        'search_project_id' => $search_project_id,
      	'completedCount' => $complete_count,
		//BOF:mod 20120711
		'datesort' => $datesort,
		//EOF:mod 20120711
      ));
    } // search

    /**
     * Render and process quick search dialog
     *
     * @param void
     * @return null
     */
    function quick_search() {
      if(!$this->request->isAsyncCall()) {
        $this->redirectTo('search');
      } // if

      //BOF:mod 20110629 search
      if (trim($this->request->post('search_for'))!=''){
		//$_SESSION['search_string'] = trim($this->request->post('search_for'));
	  }
      //$this->smarty->assign('search_string', $_SESSION['search_string']);
      //EOF:mod 20110629 search

      /*$object_types = array();
      $object_types[] = array('id' => '', 'text' => '');
      $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
      mysql_select_db(DB_NAME, $link);
      $query = "select distinct type from healingcrystals_project_objects order by type";
      $result = mysql_query($query);
      while($type = mysql_fetch_assoc($result)){
      	$object_types[] = array('id' => $type['type'], 'text' => $type['type']);
      }
      mysql_close($link);*/
	      $search_projects = array();
	      $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	      mysql_select_db(DB_NAME, $link);
	      $query = "select distinct a.project_id from healingcrystals_project_users a inner join healingcrystals_projects b on a.project_id=b.id where a.user_id='" . $this->logged_user->getId() . "' order by b.name";
	      $result = mysql_query($query);
	      while($entry = mysql_fetch_assoc($result)){
			$search_projects[] = new Project($entry['project_id']);
		  }
	      mysql_close($link);
      $object_types = $this->get_object_types();
      if($this->request->isSubmitted()) {
        $search_for = trim($this->request->post('search_for'));
        $search_type = $this->request->post('search_type');
        $search_object_type = $this->request->post('search_object_type');
        $search_project_id = $this->request->post('search_project_id');

        if($search_for == '') {
          die(lang('Nothing to search for'));
        } // if

        $this->smarty->assign(array(
          'search_for' => $search_for,
          'search_type' => $search_type,
          'search_object_type' => $search_object_type,
          'object_types' => $object_types,
          'search_project_id' => $search_project_id,
          'search_projects' => $search_projects
        ));
        $per_page = 5;

        // Search inside the project
        if($search_type == 'in_projects') {
		  //BOF:mod 20120822
		  if ($search_object_type=='Attachment'){
			$template = get_template_path('_quick_search_project_objects', null, SYSTEM_MODULE);
			list($results, $pagination) = search_attachments($search_for, $this->logged_user, 1, $per_page, $search_object_type, $search_project_id);
		  } else {
		  //EOF:mod 20120822
			$template = get_template_path('_quick_search_project_objects', null, SYSTEM_MODULE);
			list($results, $pagination) = search_index_search($search_for, 'ProjectObject', $this->logged_user, 1, $per_page, $search_object_type, $search_project_id);
		  //BOF:mod 20120822
		  }
		  //EOF:mod 20120822
		  
        // Search for people
        } elseif($search_type == 'for_people') {
          $template = get_template_path('_quick_search_users', null, SYSTEM_MODULE);
          list($results, $pagination) = search_index_search($search_for, 'User', $this->logged_user, 1, $per_page);

        // Search for projects
        } elseif($search_type == 'for_projects') {
          $template = get_template_path('_quick_search_projects', null, SYSTEM_MODULE);
          list($results, $pagination) = search_index_search($search_for, 'Project', $this->logged_user, 1, $per_page);

        // Unknown type
        } else {
          die(lang('Unknown search type: :type', array('type' => $search_type)));
        } // if

        $this->smarty->assign(array(
          'results' => $results,
          'pagination' => $pagination,
        ));

        $this->smarty->display($template);
        die();
      } else {
      	$this->smarty->assign('object_types', $object_types);
      	$this->smarty->assign('search_projects', $search_projects);
      }
    } // quick_search

    /**
     * Show recent activities page
     *
     * @param void
     * @return null
     */
    function recent_activities() {
      $this->skip_layout = $this->request->isAsyncCall();

      $this->smarty->assign(array(
        'grouped_activities' => group_by_date(ActivityLogs::findActiveProjectsActivitiesByUser($this->logged_user, 20), $this->logged_user),
      ));
    } // recent_activities

    /**
     * Show active projects list - all projects in a brief view
     *
     * @param void
     * @return null
     */
    function active_projects() {
      if(!$this->request->isAsyncCall()) {
        $this->redirectTo('projects');
      } // if

      $this->skip_layout = true;
      $this->smarty->assign('projects', Projects::findByUser($this->logged_user, array(PROJECT_STATUS_ACTIVE)));
    } // active_projects

    /**
     * Show objects posted since users last visit
     *
     * @param void
     * @return null
     */
    function new_since_last_visit() {
      $this->skip_layout = $this->request->isAsyncCall();

      $page = (integer) $this->request->get('page');
      if($page < 1) {
        $page = 1;
      } // if

      list($objects, $pagination) = ProjectObjects::paginateNew($this->logged_user, $page, 10);

      $this->smarty->assign(array(
        'objects'    => $objects,
        'pagination' => $pagination,
      ));
    } // new_since_last_visit

    /**
     * Mark all items as read (update users last visit timestamp)
     *
     * @param void
     * @return null
     */
    function mark_all_read() {
      if($this->request->isSubmitted()) {
        $this->logged_user->setLastVisitOn(DateTimeValue::now());
        $save = $this->logged_user->save();

        if($save && !is_error($save)) {
          if($this->request->isAsyncCall()) {
            $this->httpOk();
          } else {
            flash_success('All new items are marked as read');
          } // if
        } else {
          $message = lang('Failed to mark new items as read');
          if ($this->request->isAsyncCall()) {
            $this->httpError(HTTP_ERR_OPERATION_FAILED, $message);
            die();
          } else {
            flash_success($message);
          } // if
        } // if
        $this->redirectToReferer(assemble_url('dashboard'));
      } else {
        $this->httpError(HTTP_BAD_REQUEST);
      } // if
    } // mark_all_read

    /**
     * Show objects that are late or scheduled for today for a given user
     *
     * @param void
     * @return null
     */
    function late_today() {
      $page = (integer) $this->request->get('page');
      if($page < 1) {
        $page = 1;
      } // if

      list($objects, $pagination) = ProjectObjects::findLateAndToday($this->logged_user, null, get_completable_project_object_types(), $page, 30);

      $this->smarty->assign(array(
        'objects' => $objects,
        'pagination' => $pagination,
      ));

      if($this->request->isAsyncCall()) {
        $this->smarty->display(get_template_path('late_today', 'dashboard', SYSTEM_MODULE));
        die();
      } // if
    } // late_today

    /**
     * Render recent activities feed
     *
     * @param void
     * @return null
     */
    function rss() {
      require_once ANGIE_PATH . '/classes/feed/init.php';

      $projects = Projects::findNamesByUser($this->logged_user);

      $feed = new Feed($this->owner_company->getName() . ' - ' . lang('Recent activities'), ROOT_URL);
      $feed->setDescription(lang('Recent activities in active projects'));

      $activities = ActivityLogs::findActiveProjectsActivitiesByUser($this->logged_user, 50);
      if(is_foreachable($activities)) {
        foreach($activities as $activity) {
          $object = $activity->getObject();
          $activity_title = $activity_body = $activity->renderHead();
          $activity_title = strip_tags($activity_title);

          if ($activity->has_body && ($body = trim($activity->renderBody()))) {
            $activity_body.=$body;
          } // if

          $item = new FeedItem($activity_title, $object->getViewUrl(), $activity_body, $activity->getCreatedOn());
          $item->setId(extend_url($object->getViewUrl(), array('guid' => $activity->getId())));
          $feed->addItem($item);
        } // foreach
      } // if

      print render_rss_feed($feed);
      die();
    } // rss

    /**
     * Render global iCalendar feed
     *
     * @param void
     * @return null
     */
    function ical() {
    	$filter = ProjectUsers::getVisibleTypesFilter($this->logged_user, array(PROJECT_STATUS_ACTIVE), get_completable_project_object_types());
      if($filter) {
        $objects = ProjectObjects::find(array(
    		  'conditions' => array($filter . ' AND completed_on IS NULL AND state >= ? AND visibility >= ?', STATE_VISIBLE, $this->logged_user->getVisibility()),
    		  'order'      => 'priority DESC',
    		));

    		render_icalendar(lang('Global Calendar'), $objects, true);
    		die();
      } elseif($this->request->get('subscribe')) {
      	flash_error(lang('You are not able to download .ics file because you are not participating in any of the active projects at the moment'));
      	$this->redirectTo('ical_subscribe');
      } else {
      	$this->httpError(HTTP_ERR_NOT_FOUND);
      } // if
    } // ical

    /**
     * Show ical subscribe page
     *
     * @param void
     * @return null
     */
    function ical_subscribe() {
    	$this->wireframe->print_button = false;

      $ical_url = assemble_url('ical', array(
        'token' => $this->logged_user->getToken(true),
      ));

      $ical_subscribe_url = str_replace(array('http://', 'https://'), array('webcal://', 'webcal://'), $ical_url);

      $this->smarty->assign(array(
        'ical_url' => $ical_url . '&subscribe=no',
        'ical_subscribe_url' => $ical_subscribe_url
      ));
    } // ical_subscribe

    /**
     * Show quick add form
     *
     * @param void
     * @return null
     */
    function quick_add() {
      $this->wireframe->current_menu_item = 'quick_add';

      $quick_add_urls = array();
      event_trigger('on_quick_add', array(&$quick_add_urls));
      $all_projects_permissions = array_keys($quick_add_urls);

      $formatted_map = array();
      $projects_roles_map = ProjectUsers::getProjectRolesMap($this->logged_user, array(PROJECT_STATUS_ACTIVE));
      if(!is_foreachable($projects_roles_map)) {
        print lang('There are no active projects that you are involved with');
        die();
      } // if

      if (is_foreachable($projects_roles_map)) {
        foreach ($projects_roles_map as $project_id => $project_role_map) {
        	$formatted_map[$project_id] = array(
        	  'name' => array_var($project_role_map, 'name')
        	);
        	$project_leader = array_var($project_role_map, 'leader');
        	$project_role_permissions = array_var($project_role_map, 'permissions', null);

        	if ($this->logged_user->isAdministrator() || $this->logged_user->isProjectManager() || ($this->logged_user->getId() == $project_leader)) {
          	foreach ($all_projects_permissions as $current_permission) {
          		$formatted_map[$project_id]['permissions'][] = array(
          		  'title' => lang($current_permission),
          		  'name' => $current_permission
          		);
          	} // if
        	} else {
        	  foreach ($all_projects_permissions as $current_permission) {
          	  if (array_var($project_role_permissions, $current_permission, 0) > 1) {
          	    $formatted_map[$project_id]['permissions'][] = array(
            		  'title' => lang($current_permission),
            		  'name' => $current_permission
            		);
          	  } // if
          	} // if
        	} // if
        } // foreach
      } // if

      $this->smarty->assign(array(
        'formatted_map' => $formatted_map,
        'quick_add_url' => $quick_add_url,
        'js_encoded_formatted_map' => do_json_encode($formatted_map),
        'js_encoded_quick_add_urls' => do_json_encode($quick_add_urls)
      ));
    } // quick_add

    /**
     * Show JavaScript disabled page
     *
     * @param void
     * @return null
     */
    function js_disabled() {

    } // js_disabled

    //BOF: task 05 | AD
    function view_projects_info(){
    	$details = array();

      	$link = mysql_connect(DN_HOST, DB_USER, DB_PASS);
      	mysql_select_db(DB_NAME, $link);
      	$query = "select `id`, name from healingcrystals_projects where completed_on is null order by starts_on desc";
      	$result = mysql_query($query);
      	while($project = mysql_fetch_assoc($result)){
      		$details[] = array('project_name' => $project['name'],
      							'project_url' => assemble_url('project_overview', array('project_id' => $project['id'])),
			  					'project_id' => $project['id']);

			$query_1 = "select id, name from healingcrystals_project_objects
						where type='Milestone' and
						project_id='" . $project['id'] . "' and
						completed_on is null order by created_on desc";
			$result_1 = mysql_query($query_1);
			while($milestone = mysql_fetch_assoc($result_1)){
				$details[count($details)-1]['milestones'][] = array('milestone_id' => $milestone['id'],
																	'milestone_url' => assemble_url('project_milestone', array('project_id' => $project['id'], 'milestone_id' => $milestone['id'])),
																	'milestone_name' => $milestone['name']);

				$query_2 = "select id, name, integer_field_1 from healingcrystals_project_objects
							where type='Ticket' and
							project_id='" . $project['id'] . "' and
							milestone_id='" . $milestone['id'] . "' and
							completed_on is null order by created_on desc";
				$result_2 = mysql_query($query_2);
				while($ticket = mysql_fetch_assoc($result_2)){
					$index = count($details[count($details)-1]['milestones']) - 1;
					$details[count($details)-1]['milestones'][$index]['tickets'][] = array('ticket_id' => $ticket['id'],
																							'ticket_url' => assemble_url('project_ticket', array('project_id' => $project['id'], 'ticket_id' => $ticket['integer_field_1'])),
																							'ticket_name' => $ticket['name']);

	          		$query_3 = "select id, body
		  		 				from healingcrystals_project_objects
				 				where parent_id='" . $ticket['id'] . "' and
								parent_type = 'Ticket'
								order by created_on desc";
					$result_3 = mysql_query($query_3);
					while($task = mysql_fetch_assoc($result_3)){
						$index_1 = count($details[count($details)-1]['milestones'][$index]['tickets']) - 1;
						$details[count($details)-1]['milestones'][$index]['tickets'][$index_1]['tasks'][] = array('task_body' => $task['body'],
																											'task_id' => $task['id']);
					}
				}
			}
      	}

      	mysql_close($link);

    	$this->smarty->assign('details', $details);
    }
    //EOF: task 05 | AD

    function get_project_name($project_id, &$link){
    	$resp = 'Unknown';
    	$query = "select name from healingcrystals_projects where id='" . $project_id . "'";
    	$result = mysql_query($query, $link);
    	if (mysql_num_rows($result)){
    		$info = mysql_fetch_assoc($result);
    		$resp = $info['name'];
    	}
    	return $resp;
    }

    function get_object_info($object_string, $project_id, $id, &$link, $is_integer_field_1 = false){
    	$resp = array('type' => 'Unknown', 'name' => 'Unknown');
    	$type = ucfirst(substr($object_string, 0, -1));

    	$query = "select type, name
				  from healingcrystals_project_objects
				  where project_id='" . $project_id . "' and type='" . $type . "' and " . ($is_integer_field_1 ? " integer_field_1='" : " id='") .
				  $id . "'";
		$result = mysql_query($query, $link);
		if (mysql_num_rows($result)){
			$info = mysql_fetch_assoc($result);
			$resp['type'] = $info['type'];
			if ($is_integer_field_1){
				$resp['type'] .= '#' . $id;
			}
			$resp['name'] = $info['name'];
		}

		return $resp;
    }

    function recent_pages(){
    	$project_id = $_GET['project_id'];
    	$active_project = Projects::findById($project_id);

        $tabs = new NamedList();
        $tabs->add('overview', array(
          'text' => str_excerpt($active_project->getName(), 25),
          'url' => $active_project->getOverviewUrl()
        ));

        event_trigger('on_project_tabs', array(&$tabs, &$this->logged_user, &$active_project));

        $tabs->add('people', array(
          'text' => lang('People'),
          'url' => $active_project->getPeopleUrl(),
        ));

        $tabs->add('recent_pages', array(
          'text' => lang('Recent Pages'),
          'url' => assemble_url('recent_pages') . '&project_id=' . $active_project->getId(),
        ));
        $tabs->add('reminders', array(
          'text' => lang('Notifications'),
          'url' => assemble_url('reminders_list', array('project_id' => $active_project->getId())) ,
        ));
	    $tabs->add('calendar', array(
	      'text' => lang('Calendar'),
	      'url' => Calendar::getProjectCalendarUrl($active_project),
	    ));
        js_assign('active_project_id', $active_project->getId());

        $this->smarty->assign('page_tabs', $tabs);

    	$recent_pages = array();
    	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
    	mysql_select_db(DB_NAME);
    	$query = "select * from healingcrystals_user_visited_pages where user_id='" . $this->logged_user->getId() . "' order by access_time desc";
    	$result = mysql_query($query);
    	$count = 0;
    	while($info = mysql_fetch_assoc($result)){
    		$desc = (empty($info['title']) ? $info['page_url'] : $info['title']);
    		//if (empty($desc)){
	    		//$desc = $info['page_url'];
	    		$pos = strpos($desc, 'path_info');
	    		if ($pos!==false){
	    			$desc = str_replace('path_info=', '', substr(str_replace('%2F', '/', $desc), $pos));
	    			$pos = strpos($desc, 'projects');
	    			if ($pos!==false and $pos===0){
	    				$split = explode('/', $desc);
	    				$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	    				mysql_select_db(DB_NAME);
	    				switch(count($split)){
	    					case 2:
	    						$desc = lang('Project: ') . $this->get_project_name($split[1], $link);
	    						break;
	    					case 3:
	    						switch($split[2]){
	    							case 'tickets':
	    							case 'milestones':
	    							case 'people':
	    							case 'checklists':
	    							case 'discussions':
	    							case 'calendar':
	    							case 'files':
	    							case 'pages':
	    								$desc = lang('Project: ') . $this->get_project_name($split[1], $link) . ' | ' . ucfirst($split[2]);
	    								break;
	    						}
	    						break;
	    					case 4:
	    						$pos = strpos($split[3], '&');
	    						if ($pos!==false){

	    						} else {
	    							switch($split[2]){
		    							case 'tickets':
		    							case 'milestones':
		    							case 'checklists':
		    							case 'discussions':
		    							case 'pages':
		    								$resp = $this->get_object_info($split[2], $split[1], $split[3], $link, ($split[2]=='tickets' ? true : false));
		    								$desc = lang($resp['type']) . ': ' . $resp['name'];
		    								break;
	    							}
	    						}
	    						break;
	    					default:
	    						$desc = $info['title'];
	    						break;
	    				}
	    				mysql_close($link);
	    			}
					// else {
	    			//	$desc = $info['page_url'];
	    			//}
	    		}
    		//}
    		$recent_pages[] = array('url' => $info['page_url'],
							'access_time' => date('m-d-Y G:i:s', strtotime($info['access_time'])),
							'count' => ++$count,
							'description' => $desc);
    	}
    	mysql_close($link);
    	$this->smarty->assign('recent_pages', $recent_pages);
    	$this->smarty->assign('page_tab', 'recent_pages');
    }

    function attachments_list(){
    	$project_id = $_GET['project_id'];
    	$active_project = Projects::findById($project_id);

        $tabs = new NamedList();
        $tabs->add('overview', array(
          'text' => str_excerpt($active_project->getName(), 25),
          'url' => $active_project->getOverviewUrl()
        ));

        event_trigger('on_project_tabs', array(&$tabs, &$this->logged_user, &$active_project));

        $tabs->add('people', array(
          'text' => lang('People'),
          'url' => $active_project->getPeopleUrl(),
        ));

        $tabs->add('recent_pages', array(
          'text' => lang('Recent Pages'),
          'url' => assemble_url('recent_pages') . '&project_id=' . $active_project->getId(),
        ));
        $tabs->add('reminders', array(
          'text' => lang('Notifications'),
          'url' => assemble_url('reminders_list', array('project_id' => $active_project->getId())) ,
        ));
	    $tabs->add('calendar', array(
	      'text' => lang('Calendar'),
	      'url' => Calendar::getProjectCalendarUrl($active_project),
	    ));
        js_assign('active_project_id', $active_project->getId());

        $this->smarty->assign('page_tabs', $tabs);

    	/*$attachments = array();
    	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
    	mysql_select_db(DB_NAME);
    	$query = "select id from healingcrystals_project_objects where type='Comment' and project_id='" . $active_project->getId() . "' order by created_on desc";
    	$result = mysql_query($query, $link);
    	while($comment = mysql_fetch_assoc($result)){
    		$comment_id = $comment['id'];
    		$query_1 = "select id from healingcrystals_attachments where parent_id='" . $comment_id . "' and parent_type='Comment' order by name";
    		$result_1 = mysql_query($query_1, $link);
    		while($attachment = mysql_fetch_assoc($result_1)){
    			$attachment_id = $attachment['id'];
    			$attachments[] = new Attachment($attachment_id);
    		}
    	}
    	mysql_close($link);

    	$this->smarty->assign('attachments', $attachments);*/
        $this->smarty->assign('page_tab', 'attachments');
		
        $page = (integer) $this->request->get('page');
        if($page < 1) {
          $page = 1;
        }
		list($attachments, $pagination) = Attachments::paginateByProject($active_project, $this->logged_user, $page);
        $this->smarty->assign(array(
			'attachments' => $attachments, 
			'pagination' => $pagination, 
			'active_project' => $active_project, 
		));
    }
	
    function reminders_list(){
    	$project_id = $_GET['project_id'];
    	$flag = $_GET['flag'];
    	$active_project = Projects::findById($project_id);

        $tabs = new NamedList();
        $tabs->add('overview', array(
          'text' => str_excerpt($active_project->getName(), 25),
          'url' => $active_project->getOverviewUrl()
        ));

        event_trigger('on_project_tabs', array(&$tabs, &$this->logged_user, &$active_project));

        $tabs->add('people', array(
          'text' => lang('People'),
          'url' => $active_project->getPeopleUrl(),
        ));

        $tabs->add('recent_pages', array(
          'text' => lang('Recent Pages'),
          'url' => assemble_url('recent_pages') . '&project_id=' . $active_project->getId(),
        ));
        /*$tabs->add('attachments', array(
          'text' => lang('Attachments'),
          'url' => assemble_url('attachments_list', array('project_id' => $active_project->getId())) ,
        ));*/
        $tabs->add('reminders', array(
          'text' => lang('Notifications'),
          'url' => assemble_url('reminders_list', array('project_id' => $active_project->getId())) ,
        ));
	    $tabs->add('calendar', array(
	      'text' => lang('Calendar'),
	      'url' => Calendar::getProjectCalendarUrl($active_project),
	    ));
        js_assign('active_project_id', $active_project->getId());

        $this->smarty->assign('page_tabs', $tabs);

    	$reminders = array();
    	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
    	mysql_select_db(DB_NAME);
    	if ($flag){
    		$query = "select a.user_id, a.object_id, a.comment, a.created_by_id, a.created_on from healingcrystals_reminders a inner join healingcrystals_project_objects b on a.object_id=b.id where b.project_id='" . $active_project->getId() . "' and a.user_id='" . $this->logged_user->getId() . "' order by a.created_on desc";
    	} else {
    		$query = "select a.user_id, a.object_id, a.comment, a.created_by_id, a.created_on from healingcrystals_reminders a inner join healingcrystals_project_objects b on a.object_id=b.id where b.project_id='" . $active_project->getId() . "' and (a.created_by_id='" . $this->logged_user->getId() . "' or a.user_id='" . $this->logged_user->getId() . "') order by a.created_on desc";
    	}

    	$result = mysql_query($query, $link);
    	while($entry = mysql_fetch_assoc($result)){
    		$object_ref = new ProjectObject($entry['object_id']);
    		$cur_type = $object_ref->getType();
    		$type_ref = new $cur_type($entry['object_id']);
    		$reminders[] = array('sent_to' 	=> new User($entry['user_id']),
								 'object'	=> $type_ref,
								 'comment'	=> $entry['comment'],
								 'sent_by'	=> new User($entry['created_by_id']),
								 'sent_on'	=> date('d-M-Y H:i', strtotime($entry['created_on'])));
    	}
    	mysql_close($link);

    	$this->smarty->assign('reminders', $reminders);
        $this->smarty->assign('page_tab', 'reminders');
        $this->smarty->assign('flag', $flag);
    }

    function fyi_read_all(){
	  $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  mysql_select_db(DB_NAME);
	  $ts = date('Y-m-d H:i:s');
	  $query = "update healingcrystals_assignments_action_request set is_fyi='R', last_modified='" . $ts . "', fyi_marked_read_on='" . $ts . "' where is_fyi='1' and user_id='" . $this->logged_user->getId() . "'";
	  mysql_query($query);
	  mysql_close($link);
    }

    function goto_home_tab(){
        $user_id = $this->request->get('user_id');
        if (empty($user_id)){
            $user_id = $this->request->post('user_id');
            if (empty($user_id)){
                $user_id = $this->logged_user->getId();
            }
        }
        $user_obj = new User($user_id);

        if($this->request->get('async')) {
            $show_summary = $this->request->post('show_summary');
            if ($show_summary){
                //$user_id = $this->logged_user->getId();
                $ar_unvisited = 0;
                $ar_visited = 0;
                $fyi_unvisited = 0;
                $fyi_visited = 0;
                $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
                mysql_select_db(DB_NAME);

                $query = "select distinct a.link_is_visited, count(*) as count
                         from healingcrystals_assignments_action_request a
                         inner join healingcrystals_project_objects b on a.comment_id=b.id
                         inner join healingcrystals_project_objects d on b.parent_id=d.id
                         left outer join healingcrystals_project_objects e on d.milestone_id=e.id
                         inner join healingcrystals_projects c on b.project_id=c.id
                         where b.state='" . STATE_VISIBLE . "' and a.user_id='" . $user_id . "' and a.is_action_request='1'
                         and d.state='" . STATE_VISIBLE . "' and (d.completed_on is null or d.completed_on='')
                         group by a.link_is_visited";
                $result = mysql_query($query, $link);
                while ($entry = mysql_fetch_assoc($result)){
                    if ($entry['link_is_visited']){
                        $ar_visited = $entry['count'];
                    } else {
                        $ar_unvisited = $entry['count'];
                    }
                }

                //BOF:mod 20111019 #448
                $query = "select distinct a.link_is_visited, count(*) as count
                          from healingcrystals_assignments_flag_fyi_actionrequest a
                          inner join healingcrystals_project_objects b on a.object_id=b.id
                          left outer join healingcrystals_project_objects e on b.milestone_id=e.id
                          inner join healingcrystals_projects c on b.project_id=c.id
                          where a.user_id='" . $user_id . "' and flag_actionrequest='1' and b.state='" . STATE_VISIBLE . "'
                          and (b.completed_on is null or b.completed_on='')
                          group by a.link_is_visited";
                $result = mysql_query($query, $link);
                while ($entry = mysql_fetch_assoc($result)){
                    if ($entry['link_is_visited']){
                        $ar_visited += $entry['count'];
                    } else {
                        $ar_unvisited += $entry['count'];
                    }
                }
                //EOF:mod 20111019 #448

                $query = "select distinct a.link_is_visited, count(*) as count
                         from healingcrystals_assignments_action_request a
                         inner join healingcrystals_project_objects b on a.comment_id=b.id
                         inner join healingcrystals_project_objects d on b.parent_id=d.id
                         left outer join healingcrystals_project_objects e on d.milestone_id=e.id
                         inner join healingcrystals_projects c on b.project_id=c.id
                         where b.state='" . STATE_VISIBLE . "' and a.user_id='" . $user_id . "' and a.is_fyi='1'
                         and d.state='" . STATE_VISIBLE . "' and (d.completed_on is null or d.completed_on='')
                         group by a.link_is_visited";
                $result = mysql_query($query, $link);
                while ($entry = mysql_fetch_assoc($result)){
                    if ($entry['link_is_visited']){
                        $fyi_visited = $entry['count'];
                    } else {
                        $fyi_unvisited = $entry['count'];
                    }
                }

                //BOF:mod 20111019 #448
                $query = "select distinct a.link_is_visited, count(*) as count
                          from healingcrystals_assignments_flag_fyi_actionrequest a
                          inner join healingcrystals_project_objects b on a.object_id=b.id
                          left outer join healingcrystals_project_objects e on b.milestone_id=e.id
                          inner join healingcrystals_projects c on b.project_id=c.id
                          where a.user_id='" . $user_id . "' and flag_fyi='1' and b.state='" . STATE_VISIBLE . "'
                          and (b.completed_on is null or b.completed_on='')
                          group by a.link_is_visited";
                $result = mysql_query($query, $link);
                while ($entry = mysql_fetch_assoc($result)){
                    if ($entry['link_is_visited']){
                        $fyi_visited += $entry['count'];
                    } else {
                        $fyi_unvisited += $entry['count'];
                    }
                }
                //EOF:mod 20111019 #448

                mysql_close($link);
                $this->smarty->assign(array('show_summary'                  => '1',
                                            'action_req_links_unvisited'    => $ar_unvisited,
                                            'action_req_links_total'        => ($ar_visited + $ar_unvisited),
                                            'fyi_links_unvisited'           => $fyi_unvisited,
                                            'fyi_links_total'               => ($fyi_visited + $fyi_unvisited),
                                            ));
            } else {
                $comment_id = $this->request->post('comment_id');
                //$user_id = $this->request->post('user_id');
                //BOF:mod 20111019 #448
                $project_id = $this->request->post('project_id');
                $temp_obj = new ProjectObject($comment_id);
                $object_type = $temp_obj->getType();
                //EOF:mod 20111019
                $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
                mysql_select_db(DB_NAME);
                //BOF:mod 20111019 #448
                if (empty($object_type) || $object_type!='Comment'){
                    $query = "select id, type from healingcrystals_project_objects where project_id='" . (int)$project_id . "' and integer_field_1='" . (int)$comment_id . "'";
                    $result = mysql_query($query);
                    $info = mysql_fetch_assoc($result);
                    if ($info['type']=='Ticket' || $info['type']=='Page' || $info['type']=='File' || $info['type']=='TimeRecord'){
                        $comment_id = $info['id'];
                        $object_type = $info['type'];
                    }
                }
                //mysql_query("insert into testing (date_added, content) values (now(), '" . $comment_id . " | " . $object_type . " | " . $project_id .  "')");
                if ($object_type=='Comment'){
                //EOF:mod 20111019 #448
                  mysql_query("update healingcrystals_assignments_action_request set link_is_visited='1' where user_id='" . $user_id . "' and comment_id='" . (int)$comment_id . "'");
                //BOF:mod 20111019 #448
                } else {
                  mysql_query("update healingcrystals_assignments_flag_fyi_actionrequest set link_is_visited='1' where user_id='" . $user_id . "' and object_id='" . (int)$comment_id . "'");
                }
                //EOF:mod 20111019 #448
                mysql_close($link);
                $this->smarty->assign('is_ajax_call', '1');
                //$unvisited_links =  $this->logged_user->get_unvisited_links_count();
                $unvisited_links =  $user_obj->get_unvisited_links_count();
                $this->smarty->assign('action_request_links', $unvisited_links['action_request']);
                $this->smarty->assign('fyi_links', $unvisited_links['fyi']);
            }
        } else {
            //BOF:mod #59_303
            $layout_type = $this->request->post('layout_type');
            $due_flag = $this->request->get('due_flag');

            $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
            mysql_select_db(DB_NAME);
            if (empty($layout_type)){
                //$query = "select setting_value from healingcrystals_user_settings where user_id='" . $this->logged_user->getId() . "' and setting_type='HOMETAB_LAYOUT'";
                $query = "select setting_value from healingcrystals_user_settings where user_id='" . $user_id . "' and setting_type='HOMETAB_LAYOUT'";
                $result = mysql_query($query);
                if (mysql_num_rows($result)){
                    $info = mysql_fetch_assoc($result);
                    $layout_type = $info['setting_value'];
                } else {
                    $layout_type = 'summary';
                }
            } else {
                //$query = "select setting_value from healingcrystals_user_settings where user_id='" . $this->logged_user->getId() . "' and setting_type='HOMETAB_LAYOUT'";
                $query = "select setting_value from healingcrystals_user_settings where user_id='" . $user_id . "' and setting_type='HOMETAB_LAYOUT'";
                $result = mysql_query($query);
                if (mysql_num_rows($result)){
                    //mysql_query("update healingcrystals_user_settings set setting_value='" . $layout_type . "' where user_id='" . $this->logged_user->getId() . "' and setting_type='HOMETAB_LAYOUT'");
                    mysql_query("update healingcrystals_user_settings set setting_value='" . $layout_type . "' where user_id='" . $user_id . "' and setting_type='HOMETAB_LAYOUT'");
                } else {
                    //mysql_query("insert into healingcrystals_user_settings (user_id, setting_type, setting_value) values ('" . $this->logged_user->getId() . "', 'HOMETAB_LAYOUT', '" . $layout_type . "')");
                    mysql_query("insert into healingcrystals_user_settings (user_id, setting_type, setting_value) values ('" . $user_id . "', 'HOMETAB_LAYOUT', '" . $layout_type . "')");
                }
                $this->redirectTo('goto_home_tab', array('user_id' => $user_id));
            }
            mysql_close($link);
            //EOF:mod #59_303

            //$content =  $this->logged_user->getHomeTabContent('', $due_flag);
            $content =  $user_obj->getHomeTabContent($user_id, $due_flag);
            $this->smarty->assign('home_tab_content', $content);
            $this->smarty->assign('current_user', $user_obj);

            $tabs = new NamedList();
            $tabs->add('dashboard', array('text' => 'Active Teams', 'url' => assemble_url('dashboard')));
            $tabs->add('home_page', array('text' => 'Home Page', 'url' => assemble_url('goto_home_tab')));
            $tabs->add('assigned_action_request', array('text' => 'Assigned Action Requests', 'url' => assemble_url('assigned_action_request')));
            $tabs->add('owned_tickets', array('text' => 'Owned Tickets', 'url' => assemble_url('my_tickets')));
            $tabs->add('subscribed_tickets', array('text' => 'Subscribed Tickets', 'url' => assemble_url('my_subscribed_tickets')));
            //BOF:mod 20110715 ticketid246
            $customtabs = $this->logged_user->getCustomTabsInfo();
            $tabs->add('tab1', array('text' => $customtabs[0]['name'], 'url' => $customtabs[0]['url'],  'ondblclick' => "customtab_ondblclick(this, '1')"));
            $tabs->add('tab2', array('text' => $customtabs[1]['name'], 'url' => $customtabs[1]['url'],  'ondblclick' => "customtab_ondblclick(this, '2')"));
            $tabs->add('tab3', array('text' => $customtabs[2]['name'], 'url' => $customtabs[2]['url'], 'ondblclick' => "customtab_ondblclick(this, '3')"));
            //EOF:mod 20110715 ticketid246

            $this->smarty->assign('page_tabs', $tabs);
            $this->smarty->assign('page_tab', 'home_page');
            //BOF:mod #59_303
            $this->smarty->assign('layout_type', $layout_type);
            //EOF:mod #59_303
        }
    }
    function assigned_action_request(){
        $user_id = $this->request->get('user_id');
        if (empty($user_id)){
            $user_id = $this->request->post('user_id');
            if (empty($user_id)){
                $user_id = $this->logged_user->getId();
            }
        }
        $user_obj = new User($user_id);
        $layout_type = $this->request->post('layout_type');
        $due_flag = $this->request->get('due_flag');

            //$content =  $this->logged_user->getHomeTabContent('', $due_flag);
            $content =  $user_obj->getAssignedActionRequestContent($user_id, $due_flag);
            $this->smarty->assign('assigned_ar_content', $content);
            $this->smarty->assign('current_user', $user_obj);

            $tabs = new NamedList();
            $tabs->add('dashboard', array('text' => 'Active Teams', 'url' => assemble_url('dashboard')));
            $tabs->add('home_page', array('text' => 'Home Page', 'url' => assemble_url('goto_home_tab')));
            $tabs->add('assigned_action_request', array('text' => 'Assigned Action Requests', 'url' => assemble_url('assigned_action_request')));
            $tabs->add('owned_tickets', array('text' => 'Owned Tickets', 'url' => assemble_url('my_tickets')));
            $tabs->add('subscribed_tickets', array('text' => 'Subscribed Tickets', 'url' => assemble_url('my_subscribed_tickets')));
            //BOF:mod 20110715 ticketid246
            $customtabs = $this->logged_user->getCustomTabsInfo();
            $tabs->add('tab1', array('text' => $customtabs[0]['name'], 'url' => $customtabs[0]['url'],  'ondblclick' => "customtab_ondblclick(this, '1')"));
            $tabs->add('tab2', array('text' => $customtabs[1]['name'], 'url' => $customtabs[1]['url'],  'ondblclick' => "customtab_ondblclick(this, '2')"));
            $tabs->add('tab3', array('text' => $customtabs[2]['name'], 'url' => $customtabs[2]['url'], 'ondblclick' => "customtab_ondblclick(this, '3')"));
            //EOF:mod 20110715 ticketid246

            $this->smarty->assign('page_tabs', $tabs);
            $this->smarty->assign('page_tab', 'assigned_action_request');
            //BOF:mod #59_303
            $this->smarty->assign('layout_type', $layout_type);
            //EOF:mod #59_303
        
    }

    function my_tickets(){
    	$selected_project   = $_GET['selected_project'];
    	$order_by           = $_GET['order_by'];
    	$sort_order         = $_GET['sort_order'];

        $user = $this->logged_user;

        $entries = $this->logged_user->getOwnedTickets($user->getId(), $selected_project, $order_by, $sort_order);
        $this->smarty->assign(array(
	        	'active_user'       => $user,
	        	'entries'           => $entries,
                        'user_projects'     => $user->getActiveProjects(),
			'selected_project'  => $selected_project,
	        	));
        $tabs = new NamedList();
        $tabs->add('dashboard', array('text' => 'Active Teams', 'url' => assemble_url('dashboard')));
        $tabs->add('home_page', array('text' => 'Home Page', 'url' => assemble_url('goto_home_tab')));
        $tabs->add('assigned_action_request', array('text' => 'Assigned Action Requests', 'url' => assemble_url('assigned_action_request')));
        $tabs->add('owned_tickets', array('text' => 'Owned Tickets', 'url' => assemble_url('my_tickets')));
        $tabs->add('subscribed_tickets', array('text' => 'Subscribed Tickets', 'url' => assemble_url('my_subscribed_tickets')));
        //BOF:mod 20110715 ticketid246
        $customtabs = $this->logged_user->getCustomTabsInfo();
        $tabs->add('tab1', array('text' => $customtabs[0]['name'], 'url' => $customtabs[0]['url'],  'ondblclick' => "customtab_ondblclick(this, '1')"));
        $tabs->add('tab2', array('text' => $customtabs[1]['name'], 'url' => $customtabs[1]['url'],  'ondblclick' => "customtab_ondblclick(this, '2')"));
        $tabs->add('tab3', array('text' => $customtabs[2]['name'], 'url' => $customtabs[2]['url'], 'ondblclick' => "customtab_ondblclick(this, '3')"));
        //EOF:mod 20110715 ticketid246
        $this->smarty->assign('page_tabs', $tabs);
        $this->smarty->assign('page_tab', 'owned_tickets');
    }

    function my_subscribed_tickets(){
    	$selected_project   = $_GET['selected_project'];
    	$order_by           = $_GET['order_by'];
    	$sort_order         = $_GET['sort_order'];

        $user = $this->logged_user;

        $entries = $this->logged_user->getSubscribedTickets($user->getId(), $selected_project, $order_by, $sort_order);
        $this->smarty->assign(array(
	        	'active_user'       => $user,
	        	'entries'           => $entries,
                        'user_projects'     => $user->getActiveProjects(),
			'selected_project'  => $selected_project,
	        	));
        $tabs = new NamedList();
        $tabs->add('dashboard', array('text' => 'Active Teams', 'url' => assemble_url('dashboard')));
        $tabs->add('home_page', array('text' => 'Home Page', 'url' => assemble_url('goto_home_tab')));
        $tabs->add('assigned_action_request', array('text' => 'Assigned Action Requests', 'url' => assemble_url('assigned_action_request')));
        $tabs->add('owned_tickets', array('text' => 'Owned Tickets', 'url' => assemble_url('my_tickets')));
        $tabs->add('subscribed_tickets', array('text' => 'Subscribed Tickets', 'url' => assemble_url('my_subscribed_tickets')));
        //BOF:mod 20110715 ticketid246
        $customtabs = $this->logged_user->getCustomTabsInfo();
        $tabs->add('tab1', array('text' => $customtabs[0]['name'], 'url' => $customtabs[0]['url'],  'ondblclick' => "customtab_ondblclick(this, '1')"));
        $tabs->add('tab2', array('text' => $customtabs[1]['name'], 'url' => $customtabs[1]['url'],  'ondblclick' => "customtab_ondblclick(this, '2')"));
        $tabs->add('tab3', array('text' => $customtabs[2]['name'], 'url' => $customtabs[2]['url'], 'ondblclick' => "customtab_ondblclick(this, '3')"));
        //EOF:mod 20110715 ticketid246
        $this->smarty->assign('page_tabs', $tabs);
        $this->smarty->assign('page_tab', 'subscribed_tickets');
    }

    //BOF:mod 20111019 #448
    function action_request_completed(){
    	$project_id = $_GET['project_id'];
    	$active_project = Projects::findById($project_id);
        $query = "select id, type from healingcrystals_project_objects where project_id='" . (int)$active_project->getId() . "' and integer_field_1='" . (int)$_GET['object_id'] . "'";
        $result = mysql_query($query);
        $info = mysql_fetch_assoc($result);
        if ($info['type']=='Ticket' || $info['type']=='Page' || $info['type']=='File' || $info['type']=='TimeRecord'){
            $object_id = $info['id'];
        } else {
            $object_id = $_GET['object_id'];
        }

        $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
        mysql_select_db(DB_NAME);
        $query = mysql_query("update healingcrystals_assignments_flag_fyi_actionrequest set flag_actionrequest='0' where user_id='" . $this->logged_user->getId() . "' and object_id='" . (int)$object_id . "'");
        mysql_query($query);
        mysql_close($link);
        $this->redirectToUrl(assemble_url('goto_home_tab'));
    }

    function fyi_read(){
    	$project_id = $_GET['project_id'];
    	$active_project = Projects::findById($project_id);
        $query = "select id, type from healingcrystals_project_objects where project_id='" . (int)$active_project->getId() . "' and integer_field_1='" . (int)$_GET['object_id'] . "'";
        $result = mysql_query($query);
        $info = mysql_fetch_assoc($result);
        if ($info['type']=='Ticket' || $info['type']=='Page' || $info['type']=='File' || $info['type']=='TimeRecord'){
            $object_id = $info['id'];
        } else {
            $object_id = $_GET['object_id'];
        }

        $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
        mysql_select_db(DB_NAME);
        $query = mysql_query("update healingcrystals_assignments_flag_fyi_actionrequest set flag_fyi='0' where user_id='" . $this->logged_user->getId() . "' and object_id='" . (int)$object_id . "'");
        mysql_query($query);
        mysql_close($link);
        $this->redirectToUrl(assemble_url('goto_home_tab'));
    }
    //EOF:mod 20111019 #448

    //BOF:mod 20120106
    function render_comments(){
        $response = '<table width="100%" style="border:5px solid #dddddd;">';
        $user_id = $this->request->get('user_id');
        if (empty($user_id)){
            $user_id = $this->request->post('user_id');
            if (empty($user_id)){
                $user_id = $this->logged_user->getId();
            }
        }

        $action_type    = $_GET['action_type'];
        $parent_id      = $_GET['parent_id'];

        $temp_obj = new ProjectObject($parent_id);
        $parenttype = $temp_obj->getType();
        $parentobj = new $parenttype($parent_id);
        $projectobj = new Project($parentobj->getProjectId());
        $milestone_id = $parentobj->getMilestoneId();
        if (!empty($milestone_id)){
            $milestoneobj = new Milestone($milestone_id);
        }
        $assigneesstring = '';
        list($assignees, $owner_id) = $parentobj->getAssignmentData();
        foreach($assignees as $assignee) {
            $assigneeobj = new User($assignee);
            $assigneesstring .= '<a target="_blank" href="' . $assigneeobj->getViewUrl() . '">' . $assigneeobj->getName() . '</a>, ';
            unset($assigneeobj);
        }
        if (!empty($assigneesstring)){
            $assigneesstring = substr($assigneesstring, 0, -2);
        }
        $dueon = date('F d, Y', strtotime($parentobj->getDueOn()));
        if ($dueon=='January 01, 1970'){
            $dueon = '--';
        }
        if ($milestoneobj){
            $priority = $milestoneobj->getPriority();
            if (!empty($priority) || $priority=='0'){
                $priority = $milestoneobj->getFormattedPriority();
            } else {
                $priority = '--';
            }
        } else {
            $priority = '--';
        }

        $response .= '
                <tr><td colspan="2" style="height:20px;">&nbsp;</td></tr>
                    <tr>
                        <td style="width:25%;" valign="top">' . $parenttype . '</td>
                        <td valign="top">
                            <a target="_blank" href="' . $parentobj->getViewUrl() . '"><span class="homepageobject">' . $parentobj->getName() . '</span></a>
                        </td>
                    </tr>
                    <tr>
                        <td valign="top">Team &raquo; Project</td>
                        <td valign="top">
                            <a target="_blank" href="' . $projectobj->getOverviewUrl() . '"><span class="homepageobject">' . $projectobj->getName() . '</span></a> &raquo; ' .  ($milestoneobj ? '<a target="_blank" href="' . $milestoneobj->getViewUrl() . '"><span class="homepageobject">' . $milestoneobj->getName() . '</a></span>' : '--') .
                        '</td>
                    </tr>
                    <tr>
                        <td vlaign="top">Project Priority</td>
                        <td valign="top">' .
                            $priority .
                        '</td>
                    </tr>
                    <tr>
                        <td valign="top">Due on</td>
                        <td valign="top">' .
                            $dueon .
                        '</td>
                    </tr>
                    <tr>
                        <td valign="top">Assignees</td>
                        <td valign="top">' .
                            $assigneesstring .
                        '</td>
                    </tr>
                    <tr><td colspan="2" style="height:20px;">&nbsp;</td></tr>';

      	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
      	mysql_select_db(DB_NAME, $link);
        if ($action_type=='actionrequest'){
            $query = "(select b.id, d.id as parent_ref , a.date_added as date_value, e.priority as prio, c.name as project_name
                     from healingcrystals_assignments_action_request a
                     inner join healingcrystals_project_objects b on a.comment_id=b.id
                     inner join healingcrystals_project_objects d on b.parent_id=d.id
                     left outer join healingcrystals_project_objects e on d.milestone_id=e.id
                     inner join healingcrystals_projects c on b.project_id=c.id
                     where d.id='" . $parent_id . "' and b.state='" . STATE_VISIBLE . "' and a.user_id='" . $user_id . "' and a.is_action_request='1'
                     and d.state='" . STATE_VISIBLE . "'  )
                     union
                     (select '' as id, a.object_id as parent_ref, b.created_on as date_value, e.priority as prio, c.name as project_name
                     from healingcrystals_assignments_flag_fyi_actionrequest a
                     inner join healingcrystals_project_objects b on a.object_id=b.id
                     left outer join healingcrystals_project_objects e on b.milestone_id=e.id
                     inner join healingcrystals_projects c on b.project_id=c.id
                     where a.user_id='" . $user_id . "' and a.object_id='" . $parent_id . "' and flag_actionrequest='1' and b.state='" . STATE_VISIBLE . "'
                      )
                     order by prio desc, project_name, date_value desc";

        } elseif ($action_type=='fyi'){
            $query = "(select b.id, d.id as parent_ref , a.date_added as date_value, e.priority as prio, c.name as project_name
                     from healingcrystals_assignments_action_request a
                     inner join healingcrystals_project_objects b on a.comment_id=b.id
                     inner join healingcrystals_project_objects d on b.parent_id=d.id
                     left outer join healingcrystals_project_objects e on d.milestone_id=e.id
                     inner join healingcrystals_projects c on b.project_id=c.id
                     where d.id='" . $parent_id .  "' and b.state='" . STATE_VISIBLE . "' and a.user_id='" . $user_id . "' and a.is_fyi='1'
                     and d.state='" . STATE_VISIBLE . "' )
                     union
                     (select '' as id, a.object_id as parent_ref, b.created_on as date_value, e.priority as prio, c.name as project_name
                     from healingcrystals_assignments_flag_fyi_actionrequest a
                     inner join healingcrystals_project_objects b on a.object_id=b.id
                     left outer join healingcrystals_project_objects e on b.milestone_id=e.id
                     inner join healingcrystals_projects c on b.project_id=c.id
                     where a.user_id='" . $user_id . "' and a.object_id='" . $parent_id . "' and flag_fyi='1' and b.state='" . STATE_VISIBLE . "'
                      )
                     order by prio desc, project_name, date_value desc";
        }
        $result = mysql_query($query);
        $count = 0;
        while($entry = mysql_fetch_assoc($result)){
            $count++;
            if (!empty($entry['id'])){
                $temp_obj = new Comment($entry['id']);
                $created_by_id = $temp_obj->getCreatedById();
                $created_by_user = new User($created_by_id);
                $created_on = strtotime($temp_obj->getCreatedOn());
                $created_on = date('m-d-y', $created_on);

                $temp = $temp_obj->getFormattedBody(true, true);
                $comment_body = $temp;
                $response .= '
                        <tr>
                            <td valign="top" style="vertical-align:top;">
                                Comment by<br/>' .
                                (!empty($created_by_id) ? '<a target="_blank" href="' . $created_by_user->getViewUrl() . '">' . $created_by_user->getName() . '</a>' : $temp_obj->getCreatedByName()) .
                                '<br/><br/><br/>
                                <a target="_blank" href="' . $temp_obj->getViewUrl() . '">[view comment]</a><br/>&nbsp;&nbsp;&nbsp;' .
                                $created_on .
                                '<br/><br/><br/>' .
                                ($action_type=='actionrequest' ?
                                        '<a class="mark_as_complete" count="' . $count . '" href="' . assemble_url('project_comment_action_request_completed', array('project_id' => $temp_obj->getProjectId(), 'comment_id' => $temp_obj->getId())) . '">Mark Action Request Complete</a>'
                                        : '') .
                                ($action_type=='fyi' ?
                                        '<a class="mark_as_read" count="' . $count . '" href="' . assemble_url('project_comment_fyi_read', array('project_id' => $temp_obj->getProjectId(), 'comment_id' => $temp_obj->getId())) . '">Mark this Notification<br/>as Read</a>'
                                        : '') .
                            '</td>
                            <td valign="top" style="vertical-align:top;">
                                <div style="width:600px;overflow:auto;">' . $comment_body . '</div>' .
                                    ($show_read_link ? '<a target="_blank" href="' . $temp_obj->getViewUrl() . '">Click here to read the rest of this Comment</a>' : '') .
                            '</td>
                        </tr>
                        <tr><td colspan="2" style="height:20px;">&nbsp;</td></tr>';
            } else {
                $response .= '
                        <tr>
                           <td valign="top" style="vertical-align:top;">
                                Created by<br/>' .
                                (!empty($created_by_id) ? '<a target="_blank" href="' . $created_by_user->getViewUrl() . '">' . $created_by_user->getName() . '</a>' : $parentobj->getCreatedByName()) .
                                '<br/><br/><br/>
                                <a target="_blank" href="' . $parentobj->getViewUrl() . '">[view object]</a><br/>&nbsp;&nbsp;&nbsp;' .
                                $created_on .
                                '<br/><br/><br/>' .
                                ($action_type=='action_request' ?
                                        '<a class="mark_as_complete" count="' . $count . '" href="' . assemble_url('project_object_action_request_completed', array('project_id' => $parentobj->getProjectId())) . '&object_id=' . $parentobj->getId() . '&project_id=' . $parentobj->getProjectId() . '">Mark Action Request Complete</a>'
                                        : '') .
                                ($action_type=='fyi' ?
                                        '<a class="mark_as_read" count="' . $count . '" href="' . assemble_url('project_object_fyi_read', array('project_id' => $parentobj->getProjectId())) . '&object_id=' . $parentobj->getId() . '&project_id=' . $parentobj->getProjectId() . '">Mark this Notification<br/>as Read</a>'
                                        : '') .
                            '</td>
                            <td valign="top" style="vertical-align:top;">
                                <div style="width:600px;overflow:auto;">' . $parentobj->getFormattedBody(true, true) . '</div>
                            </td>
                        </tr>
                        <tr><td colspan="2" style="height:20px;">&nbsp;</td></tr>';
            }

        }
        mysql_close($link);
        $response .= '</table>';
        $this->smarty->assign('response', $response);
    }
    //EOF:mod 20120106

	//BOF:mod 20120906
	function get_collection(){
		$parent_id = $this->request->post('parent_id');
		$team_obj = new Project($parent_id);
		$object_type = $this->request->post('object_type');
		
		$listing = array();
		switch($object_type){
			case 'milestone':
				//$listing = Milestones::findByProject($team_obj, $this->logged_user);
				$listing = Milestones::findActiveByProject_custom($team_obj);
				break;
			case 'ticket':
				$listing = Tickets::findOpenByProjectByNameSort($team_obj, STATE_VISIBLE, $this->logged_user->getVisibility());
				break;
			case 'page':
				$categories = Categories::findByModuleSection($team_obj, 'pages', 'pages');
				$listing = Pages::findByCategories($categories, STATE_VISIBLE, $this->logged_user->getVisibility());
				/*foreach($categories as $category){
					$listing = array_merge($listing, Pages::findByCategory($category, STATE_VISIBLE, $this->logged_user->getVisibility()));
				}*/
				break;
		}
		$this->smarty->assign('options', $listing);
	}
	//EOF:mod 20120906
	
  }

?>