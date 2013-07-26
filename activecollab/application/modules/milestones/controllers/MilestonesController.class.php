<?php

  // We need ProjectController
  use_controller('project', SYSTEM_MODULE);

  /**
   * Milestones controller
   *
   * @package activeCollab.modules.milestones
   * @subpackage models
   */
  class MilestonesController extends ProjectController {
    
    /**
     * Active module
     *
     * @var string
     */
    var $active_module = MILESTONES_MODULE;
    
    /**
     * Selected milestone
     *
     * @var Milestone
     */
    var $active_milestone;
    
    /**
     * Actions available through API
     *
     * @var array
     */
    var $api_actions = array('index', 'archive', 'view', 'add', 'edit');
    //BOF: task 03 | AD
    //var $order_by = 'due_on';
    var $order_by = 'name';
    var $sort_order = 'asc';
  	//EOF: task 03 | AD
    /**
     * Constructor
     *
     * @param Request $request
     * @return MilestonesController
     */
    function __construct($request) {
      parent::__construct($request);
      
      if($this->logged_user->getProjectPermission('milestone', $this->active_project) < PROJECT_PERMISSION_ACCESS) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      $milestones_url = milestones_module_url($this->active_project);
      $add_milestone_url = milestones_module_add_url($this->active_project);
      
      $this->wireframe->addBreadCrumb(lang('Milestones'), $milestones_url);

      if(Milestone::canAdd($this->logged_user, $this->active_project)) {
        $this->wireframe->addPageAction(lang('New Milestone'), $add_milestone_url);
      } // if
      //$this->wireframe->addPageAction(lang('Today'), assemble_url('project_user_today_page', array('project_id' => $this->active_project->getId(), 'user_id' => $this->logged_user->getId())));
      
      $milestone_id = $this->request->getId('milestone_id');
      if($milestone_id) {
        $this->active_milestone = ProjectObjects::findById($milestone_id);
      } // if
      //BOF: task 03 | AD
      $order_by_val = $_GET['order_by'];
      if (!empty($order_by_val)){
      	$this->order_by = $order_by_val;
      }
      $sort_order_val = $_GET['sort_order'];
      if (!empty($sort_order_val)){
      	$this->sort_order = $sort_order_val;
      }
      //EOF: task 03 | AD
      if(instance_of($this->active_milestone, 'Milestone')) {
        if($this->active_milestone->getCompletedOn()) {
          $this->wireframe->addBreadCrumb(lang('Archive'), assemble_url('project_milestones_archive', array(
            'project_id' => $this->active_project->getId(),
          )));
        } // if
        
        $this->wireframe->addBreadCrumb($this->active_milestone->getName(), $this->active_milestone->getViewUrl());
      } else {
        $this->active_milestone = new Milestone();
      } // if
      
      $this->smarty->assign(array(
        'active_milestone'  => $this->active_milestone,
        'milestones_url'    => $milestones_url,
        'add_milestone_url' => $add_milestone_url,
        'page_tab'          => 'milestones',
        'mass_edit_milestones_url' => assemble_url('project_milestones_mass_edit', array('project_id' => $this->active_project->getId())),
      ));
    } // __construct
    
    /**
     * Show milestones index page
     *
     * @param void
     * @return null
     */
    function index() {
      require_once SMARTY_PATH . '/plugins/modifier.datetime.php';
      //BOF: task 03 | AD
      /*
      $milestones = Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility());
      //EOF: task 03 | AD
      //BOF: task 03 | AD
      */
      //BOF: task 04 | AD
      /*
      //EOF: task 04 | AD
      $milestones = Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order);
      //BOF: task 04 | AD
      */
      //EOF: task 04 | AD
      //EOF: task 03 | AD
      if($this->request->isApiCall()) {
      	//BOF: task 04 | AD
      	$milestones = Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order);
      	//EOF: task 04 | AD
        $this->serveData($milestones, 'milestones');
      } else {
      	//BOF: task 04 | AD
      	/*
      	//EOF: task 04 | AD
        $this->smarty->assign('milestones', $milestones);
      	//BOF: task 04 | AD
      	*/
      	$selected_category_id = $_GET['category_id'];
      	$selected_category_name = 'All';
      	$project_id = $this->active_project->getId();
      	$milestones = array();
      	$categories_all = array();

      	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
      	mysql_select_db(DB_NAME);
      	$query = "select id, category_name from healingcrystals_project_milestone_categories where project_id='" . $project_id . "' order by category_name";
      	$result = mysql_query(($query));
      	while($category = mysql_fetch_assoc($result)){
			//BOF:mod 20121108
			/*
			//EOF:mod 20121108
      		$categories_all[] = array('category_id' 	=> $category['id'], 
				  					  'category_name'	=> $category['category_name']);
			//BOF:mod 20121108
			*/
      		$categories_all[] = array('id' 	=> $category['id'], 
								   'name'=> $category['category_name'], 
								   'url' => assemble_url('project_milestones', array('project_id' => $project_id) ) . '&category_id=' . $category['id'], 
								   );
			//EOF:mod 20121108
    		/*$query_1 = "select distinct a.id 
						from healingcrystals_project_objects a 
						inner join healingcrystals_project_object_categories b on a.id=b.object_id 
						where a.project_id='" . $this->active_project->getId() . "' and 
						type='Milestone' and 
						completed_on is null and b.category_id='" . $category['id'] . "'";
			//mysql_query("insert into healingcrystals_testing (query, fired_at) values ('" . mysql_real_escape_string($query_1) . "', now())");
			$result_1 = mysql_query($query_1, $link);
			while($id = mysql_fetch_assoc($result_1)){
				$ids[] = $info['id'];
			}
			$temp = Milestones::findByIds($ids, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order);*/
			$temp = Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order, $category['id']);
			if (count($temp)){
	      		$milestones[] = array('category_id' 	=> $category['id'], 
				  					  'category_name'	=> $category['category_name'],
									  'milestones_'		=> $temp, 
									  'category_url' 	=> assemble_url('project_milestones', array('project_id' => $project_id, 'category_id' => $category['id'])));

				if (!empty($selected_category_id) && $selected_category_id==$category['id']){
					$selected_category_name = $category['category_name'];
				}
			}
      	}
		//BOF:mod 20121108
		$uncategorized_entries_exist = false;
		//EOF:mod 20121108
		$temp = Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order, '');
		if (count($temp)){
	   		$milestones[] = array('category_id' 	=> '-1', 
			  					  'category_name'	=> 'Uncategorized Projects', 
								  'milestones_'		=> $temp, 
								  'category_url' 	=> assemble_url('project_milestones', array('project_id' => $project_id, 'category_id' => '-1')));
			if (!empty($selected_category_id) && $selected_category_id=='-1'){
				$selected_category_name = 'Uncategorized';
			}
			//BOF:mod 20121108
			$uncategorized_entries_exist = true;
			//EOF:mod 20121108
		}
     	mysql_close($link);
     	//$this->smarty->assign('milestones', $milestones);
     	$this->smarty->assign(array('milestones' => $milestones,
		 							'selected_category_id' => $selected_category_id, 
		 							'selected_category_name' => $selected_category_name, 
		 							'categories_all' => $categories_all, 
		 							'categories_url' => assemble_url('project_manage_milestone_categories', array('project_id' => $project_id)), 
									//BOF:mod 20121108
									'uncategorized_entries_exist' => $uncategorized_entries_exist, 
									//EOF:mod 20121108
		 				));
      	//EOF: task 04 | AD
      } // if
    } // index
    
    /**
     * Show completed milestones
     *
     * @param void
     * @return null
     */
    function archive() {
	//BOF: task 03 | AD
	/*
	//EOF: task 03 | AD
      $milestones = Milestones::findCompletedByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility());
	//BOF: task 03 | AD
	*/
	//BOF: task 04 | AD
	/*
	//EOF: task 04 | AD
	$milestones = Milestones::findCompletedByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order);
	//BOF: task 04 | AD
	*/
	//EOF: task 04 | AD
	//EOF: task 03 | AD
      if($this->request->isApiCall()) {
      	//BOF: task 04 | AD
      	$milestones = Milestones::findCompletedByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order);
        //EOF: task 04 | AD
		$this->serveData($milestones, 'milestones');
      } else {
      	//BOF: task 04 | AD
      	/*
      	//EOF: task 04 | AD
        $this->smarty->assign('milestones', $milestones);
        //BOF: task 04 | AD
        */
      	$selected_category_id = $_GET['category_id'];
      	$selected_category_name = 'All';
      	$project_id = $this->active_project->getId();
      	$milestones = array();
      	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
      	mysql_select_db(DB_NAME);
      	$query = "select id, category_name from healingcrystals_project_milestone_categories where project_id='" . $project_id . "' order by category_name";
      	$result = mysql_query(($query));
      	while($category = mysql_fetch_assoc($result)){
			$temp = Milestones::findCompletedByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order, $category['id']);
			if (count($temp)){
	      		$milestones[] = array('category_id' 	=> $category['id'], 
				  					  'category_name'	=> $category['category_name'], 
									  'milestones_'		=> $temp, 
									  'category_url' 	=> assemble_url('project_milestones_archive', array('project_id' => $project_id, 'category_id' => $category['id'])));
				if (!empty($selected_category_id) && $selected_category_id==$category['id']){
					$selected_category_name = $category['category_name'];
				}
			}
      	}
		$temp = Milestones::findCompletedByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility(), $this->order_by, $this->sort_order, '');
		if (count($temp)){
	   		$milestones[] = array('category_id' 	=> '-1', 
			  					  'category_name'	=> 'Uncategorized', 
								  'milestones_'		=> $temp, 
								  'category_url' 	=> assemble_url('project_milestones_archive', array('project_id' => $project_id, 'category_id' => '-1')));
			if (!empty($selected_category_id) && $selected_category_id=='-1'){
				$selected_category_name = 'Uncategorized';
			}
		}
     	mysql_close($link);
     	//$this->smarty->assign('milestones', $milestones);
     	$this->smarty->assign(array('milestones' => $milestones,
		 							'selected_category_id' => $selected_category_id, 
		 							'selected_category_name' => $selected_category_name
		 				));
        //EOF: task 04 | AD
        
      } // if
    } // archive
    
    /**
     * Show single milestone
     *
     * @param void
     * @return null
     */
    function view() {
    	$show_archived_pages = '0';
    	$show_completed_tkts = '0';
      if($this->active_milestone->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if
      
      if(!$this->active_milestone->canView($this->logged_user)) {
      	$this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      ProjectObjectViews::log($this->active_milestone, $this->logged_user);
      
      if($this->request->isApiCall()) {
        $this->serveData($this->active_milestone, 'milestone', array(
          'describe_assignees' => true,
        ));
      } else {
        $page = (integer) $this->request->get('page');
        if($page < 1) {
          $page = 1;
        } // if
		$show_all = $this->request->get('show_all');
		if (!empty($show_all) && $show_all=='1' && !isset($_GET['comment_id'])){
			$comments = null;
			$pagination = null;
		} else {
			$show_all = '';
			list($comments, $pagination) = $this->active_milestone->paginateComments($page, $this->active_milestone->comments_per_page, $this->logged_user->getVisibility());
		}
		$comments_only_mode = $this->request->get('comments_only_mode');
		if ($comments_only_mode){
			$this->smarty->assign(array(
				'_object_comments_comments' => $comments,
				'pagination' => $pagination,
				'_counter' => ($page - 1) * $this->active_milestone->comments_per_page,
			));
			echo $this->smarty->fetch(get_template_path('_object_comments_ajax', 'comments', RESOURCES_MODULE));
			exit();
		}
		$scroll_to_comment = $this->request->get('comment_id');
		/*if (!empty($scroll_to_comment)){
			for($i=0; $i<count($comments); $i++){
				if ($comments[$i]->getId()==$scroll_to_comment){
					$scroll_to_comment = '';
					break;
				}
			}
		}*/
		
      	if (isset($_GET['archived_pages'])){
      		$show_archived_pages = $_GET['archived_pages'];
      	}
      	if (isset($_GET['completed_tkts'])){
      		$show_completed_tkts = $_GET['completed_tkts'];
      	}
        $total_objects = 0;
        $objects = $this->active_milestone->getObjects($this->logged_user);
        if(is_foreachable($objects)) {
          foreach($objects as $objects_by_module) {
            $total_objects += count($objects_by_module);
          } // foreach
        } // if
        
        // ---------------------------------------------------
        //  Prepare add suboject links
        // ---------------------------------------------------
        
        $links_code = '';
        
        $links = array();
        event_trigger('on_milestone_add_links', array($this->active_milestone, $this->logged_user, &$links));
        
        if(is_foreachable($links)) {
          $total_links = count($links);
          $counter = 1;
          foreach($links as $k => $v) {
            $links_code .= open_html_tag('a', array('href' => $v)) . $k . '</a>';
            
            if($counter < ($total_links - 1)) {
              $links_code .= ', ';
            } elseif($counter == ($total_links - 1)) {
              $links_code .= ' ' . lang('or') . ' ';
            } // if
            
            $counter++;
          } // foreach
        } // if
        
		$dID = $_GET['dID'];
		if (!empty($dID)){
			$this->active_milestone->changeDepartmentTo($dID, array('Page'));
		}
        $this->smarty->assign(array(
          'total_objects' => $total_objects,
          'milestone_objects' => $objects,
          'milestone_add_links_code' => $links_code,
          'subscribers' => $this->active_milestone->getSubscribers(), 
          'current_user_id' => $this->logged_user->getId(), 
          'show_archived_pages' => $show_archived_pages, 
          'show_completed_tkts' => $show_completed_tkts, 
          //BOF:mod 20110615
          'object_id' => $this->active_milestone->getId(), 
          //EOF:mod 20110615
          'comments' => $comments,
          'pagination' => $pagination,
          'counter' => ($page - 1) * $this->active_milestone->comments_per_page, 
		  'scroll_to_comment' => $scroll_to_comment, 
		  'show_all' => $show_all,
        ));
      } // if
    } // view
    
    /**
     * Create a new milestone
     *
     * @param void
     * @return null
     */
    function add() {
      $this->wireframe->print_button = false;
      
      if(!Milestone::canAdd($this->logged_user, $this->active_project)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      $milestone_data = $this->request->post('milestone');
	  if(!$this->request->isSubmitted()) {
		$milestone_data['visibility'] = VISIBILITY_NORMAL;
	  }
      $this->smarty->assign('milestone_data', $milestone_data);
      
      if($this->request->isSubmitted()) {
        db_begin_work();
        
        $this->active_milestone = new Milestone();
        
        $this->active_milestone->setAttributes($milestone_data);
        $this->active_milestone->setProjectId($this->active_project->getId());
        
        if(trim($this->active_milestone->getCreatedByName()) == '' || trim($this->active_milestone->getCreatedByEmail()) == '') {
          $this->active_milestone->setCreatedBy($this->logged_user);
        } // if
        
        $this->active_milestone->setState(STATE_VISIBLE);
        //$this->active_milestone->setVisibility(VISIBILITY_NORMAL);
        
        $save = $this->active_milestone->save();
        
        if($save && !is_error($save)) {
          $subscribers = array($this->logged_user->getId());
          if(is_foreachable(array_var($milestone_data['assignees'], 0))) {
            $subscribers = array_merge($subscribers, array_var($milestone_data['assignees'], 0));
          } else {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if
          
          if(!in_array($this->active_project->getLeaderId(), $subscribers)) {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if
          
          Subscriptions::subscribeUsers($subscribers, $this->active_milestone);
            
          db_commit();
          $this->active_milestone->ready();
          
          //BOF: mod
          $this->active_milestone->register_departments((!empty($milestone_data['departments']) ? $milestone_data['departments'] : array()));
          //EOF: mod
		  //BOF:mod 20110614
          $assignees_flag_data = $this->request->post('assignee');
          $this->active_milestone->register_assignees_flag($assignees_flag_data, true);
          //EOF:mod 20110614
          
          if($this->request->getFormat() == FORMAT_HTML) {
            //flash_success('Milestone ":name" has been created', array('name' => $this->active_milestone->getName()), false, true);
			flash_success('Project ":name" has been created', array('name' => $this->active_milestone->getName()), false, true);
            $this->redirectToUrl($this->active_milestone->getViewUrl());
          } else {
            $this->serveData($this->active_milestone, 'milestone');
          } // if
        } else {
          db_rollback();
          
          if($this->request->getFormat() == FORMAT_HTML) {
            $this->smarty->assign('errors', $save);
          } else {
            $this->serveData($save);
          } // if
        } // if
      } // if
    } // add
    
    /**
     * Quick add milestone
     *
     * @param void
     * @return null
     */
    function quick_add() {
      if(!Milestone::canAdd($this->logged_user, $this->active_project)) {
      	$this->httpError(HTTP_ERR_FORBIDDEN, lang("You don't have permission for this action"), true, true);
      } // if
      
      $this->skip_layout = true;
            
      $milestone_data = $this->request->post('milestone');
      if (!is_array($milestone_data)) {
        $milestone_data = array(
          'visibility' => $this->active_project->getDefaultVisibility()
        );
      } // if
      
      $this->smarty->assign(array(
        'milestone_data' => $milestone_data,
        'quick_add_url' => assemble_url('project_milestones_quick_add', array('project_id' => $this->active_project->getId())),
      ));
      
      if($this->request->isSubmitted()) {
        db_begin_work();
        
        $this->active_milestone = new Milestone();
        
        $this->active_milestone->setAttributes($milestone_data);
        if(!isset($milestone_data['priority'])) {
          $this->active_milestone->setPriority(PRIORITY_NORMAL);
        } // if
        $this->active_milestone->setProjectId($this->active_project->getId());
        $this->active_milestone->setCreatedBy($this->logged_user);
        $this->active_milestone->setState(STATE_VISIBLE);
        $this->active_milestone->setVisibility(VISIBILITY_NORMAL);
        
        $save = $this->active_milestone->save();
        if($save && !is_error($save)) {
          $subscribers = array($this->logged_user->getId());
          if(is_foreachable(array_var($milestone_data['assignees'], 0))) {
            $subscribers = array_merge($subscribers, array_var($milestone_data['assignees'], 0));
          } else {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if
          Subscriptions::subscribeUsers($subscribers, $this->active_milestone);
          
          db_commit();
          $this->active_milestone->ready();
          
          $this->smarty->assign(array(
            'active_milestone' => $this->active_milestone,
            'milestone_data' => array('visibility' => $this->active_project->getDefaultVisibility()),
            'project_id' => $this->active_project->getId()
          ));
          
          $this->skip_layout = true;
        } else {
          db_rollback();
          $this->httpError(HTTP_ERR_OPERATION_FAILED, $save->getErrorsAsString(), true, true);
        } // if
      } // if
    } // quick_add
    
    /**
     * Edit specific milestone
     *
     * @param void
     * @return null
     */
    function edit() {
      $this->wireframe->print_button = false;
      
      if($this->active_milestone->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if
      
      if(!$this->active_milestone->canEdit($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      //$this->wireframe->addPageMessage(lang('<a href=":url">Click here</a> if you wish to reschedule this milestone', array('url' => $this->active_milestone->getRescheduleUrl())), 'info');
      $this->wireframe->addPageMessage(lang('<a href=":url">Click here</a> if you wish to reschedule this project', array('url' => $this->active_milestone->getRescheduleUrl())), 'info');
	  
      $milestone_data = $this->request->post('milestone');
      if(!is_array($milestone_data)) {
        $milestone_data = array(
          'name' => $this->active_milestone->getName(),
          'body' => $this->active_milestone->getBody(),
          'start_on' => $this->active_milestone->getStartOn(),
          'due_on' => $this->active_milestone->getDueOn(),
          'priority' => $this->active_milestone->getPriority(),
          'assignees' => Assignments::findAssignmentDataByObject($this->active_milestone),
          'tags' => $this->active_milestone->getTags(),
          //BOF: task 04 | AD
          //'category_id' => $this->active_milestone->getCategoryId(),
          //EOF: task 04 | AD
		  //BOF: task 07 | AD 
          'project_id' => $this->active_milestone->getProjectId(),
          //EOF: task 07 | AD
		  'visibility' => $this->active_milestone->getVisibility(),
        );
      } // if
		//BOF:mod 20121116
		$options = array();
		$options[] = array('url' => 'javascript:convert_object_to_ticket(\'' . $this->active_milestone->getProjectId() . '\', \'' . $this->active_milestone->getId() . '\', \'' . $this->active_milestone->getType() . '\');', 'text' => 'Ticket');
		$options[] = array('url' => 'javascript:convert_object_to_page(\'' . $this->active_milestone->getProjectId() . '\', \'' . $this->active_milestone->getId() . '\', \'' . $this->active_milestone->getType() . '\');', 'text' => 'Page');
		$this->wireframe->addPageAction(lang('Convert To'), 'javascript://', $options );
		//EOF:mod 20121116
      $this->smarty->assign('milestone_data', $milestone_data);
      //BOF: task 07 | AD
	  $this->smarty->assign('is_edit_mode', '1');
	  //EOF: task 07 | AD
      
      if($this->request->isSubmitted()) {
        if(!isset($milestone_data['assignees'])) {
          $milestone_data['assignees'] = array(array(), 0);
        } // if
        
        db_begin_work();
        
        $old_name = $this->active_milestone->getName();
        //BOF: task 07 | AD
        $old_project_id = $this->active_milestone->getProjectId();
        //EOF: task 07 | AD
        $this->active_milestone->setAttributes($milestone_data);
        $save = $this->active_milestone->save();
        if($save && !is_error($save)) {
          db_commit();
          	//BOF: task 07 | AD
	          //BOF: mod
	          $this->active_milestone->register_departments((!empty($milestone_data['departments']) ? $milestone_data['departments'] : array()), implode(',', $milestone_data['departments']));
	          //EOF: mod
		  //BOF:mod 20110614
          $assignees_flag_data = $this->request->post('assignee');
          $this->active_milestone->register_assignees_flag($assignees_flag_data);
          //EOF:mod 20110614
	        if ($old_project_id!=$this->active_milestone->getProjectId()){
	        	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	        	mysql_select_db(DB_NAME, $link);
	        	
				$query = "select id, integer_field_1 
							from healingcrystals_project_objects where 
							milestone_id ='" . $this->active_milestone->getId() . "' and 
							project_id='" . $old_project_id . "' and type='Ticket' and integer_field_1 is not null";
				$result = mysql_query($query);
				$next_id = Tickets::findNextTicketIdByProject($this->active_milestone->getProjectId());
				while($ticket = mysql_fetch_assoc($result)){
					mysql_query("update healingcrystals_project_objects 
									set integer_field_1='" . $next_id . "'  
									where id='" . $ticket['id'] . "'");
					$next_id++;
				}

	        	$query = "select updated_on, updated_by_id, updated_by_name, integer_field_1 from healingcrystals_project_objects where id='" . $this->active_milestone->getId() . "'";
	        	$result = mysql_query($query, $link);
	        	$info = mysql_fetch_assoc($result);
	        	$query = "update healingcrystals_project_objects set 
							project_id ='" . $this->active_milestone->getProjectId() . "', 
							updated_on =" 	. (empty($info['updated_on']) 		? "null" : "'" . $info['updated_on'] . "'") . ", 
							updated_by_id =" . (empty($info['updated_by_id']) 	? "null" : "'" . $info['updated_by_id'] . "'") . ", 
							updated_by_name =" . (empty($info['updated_by_name']) 	? "null" : "'" . mysql_real_escape_string($info['updated_by_name']) . "'") . ", 
							updated_by_email =" . (empty($info['updated_by_email']) ? "null" : "'" . $info['updated_by_email'] . "'") . " 
							where milestone_id ='" . $this->active_milestone->getId() . "' and project_id='" . $old_project_id . "'";
	        	mysql_query($query);
				$query = "update healingcrystals_project_objects set category_id=null where id='" . $this->active_milestone->getId() . "'";
				mysql_query($query);
	        	mysql_close($link);
	        }
	        //EOF: task 07 | AD
          if($this->request->getFormat() == FORMAT_HTML) {
            //flash_success('Milestone ":name" has been updated', array('name' => $old_name), false, true);
			flash_success('Project ":name" has been updated', array('name' => $old_name), false, true);
            $this->redirectToUrl($this->active_milestone->getViewUrl());
          } else {
            $this->serveData($this->active_milestone, 'milestone');
          } // if
        } else {
          db_rollback();
          
          if($this->request->getFormat() == FORMAT_HTML) {
            $this->smarty->assign('errors', $save);
          } else {
            $this->serveData($save);
          } // if
        } // if
      } // if
    } // edit
    
    /**
     * Reschedule selected milestone
     *
     * @param void
     * @return null
     */
    function reschedule() {
      if($this->active_milestone->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if
      
      if(!$this->active_milestone->canEdit($this->logged_user)) {
      	$this->httpError($this->logged_user);
      } // if
      
      $milestone_data = $this->request->post('milestone');
      if(!is_array($milestone_data)) {
        $milestone_data = array(
          'start_on' => $this->active_milestone->getStartOn(),
          'due_on' => $this->active_milestone->getDueOn(),
          'reschedule_milstone_objects' => false,
        );
      } // if
      $this->smarty->assign('milestone_data', $milestone_data);
      
      if($this->request->isSubmitted()) {
        db_begin_work();
        
        $old_due_on = new DateValue($this->active_milestone->getDueOn());
        
        $new_start_on = new DateValue(array_var($milestone_data, 'start_on'));
        $new_due_on = new DateValue(array_var($milestone_data, 'due_on'));
        $reschedule_tasks = (boolean) array_var($milestone_data, 'reschedule_milstone_objects');
        
        $successive_milestones = Milestones::findSuccessiveByMilestone($this->active_milestone, STATE_VISIBLE, $this->logged_user->getVisibility()); // before we update timestamp
        
        $reschedule = $this->active_milestone->reschedule($new_start_on, $new_due_on, $reschedule_tasks);
        if($reschedule && !is_error($reschedule)) {
        	//if (instance_of($new_due_on, 'DateValue')){
          if($new_due_on->getTimestamp() != $old_due_on->getTimestamp()) {
            $with_successive = array_var($milestone_data, 'with_sucessive');
            
            $to_move = null;
            switch(array_var($with_successive, 'action')) {
              case 'move_all':
                $to_move = $successive_milestones;
                break;
              case 'move_selected':
                $selected_milestones = array_var($with_successive, 'milestones');
                if(is_foreachable($selected_milestones)) {
                  $to_move = Milestones::findByIds($selected_milestones, STATE_VISIBLE, $this->logged_user->getVisibility());
                } // if
                break;
            } // switch
            
            if(is_foreachable($to_move)) {
              $diff = $new_due_on->getTimestamp() - $old_due_on->getTimestamp();
              foreach($to_move as $to_move_milestone) {
                $milestone_start_on = $to_move_milestone->getStartOn();
                $milestone_due_on = $to_move_milestone->getDueOn();
                
                $new_milestone_start_on = $milestone_start_on->advance($diff, false);
                $new_milestone_due_on = $milestone_due_on->advance($diff, false);
                
                $to_move_milestone->reschedule($new_milestone_start_on, $new_milestone_due_on, $reschedule_tasks);
              } // foreach
            } // if
          } // if
          
          db_commit();
          
          if($this->request->getFormat() == FORMAT_HTML) {
            //flash_success('Milestone ":name" has been updated', array('name' => $this->active_milestone->getName()), false, true);
			flash_success('Project ":name" has been updated', array('name' => $this->active_milestone->getName()), false, true);
            $this->redirectToUrl($this->active_milestone->getViewUrl());
          } else {
            $this->serveData($this->active_milestone);
          } // if
          //}
        } else {
          db_rollback();
          
          if($this->request->getFormat() == FORMAT_HTML) {
            $this->smarty->assign('errors', $reschedule);
          } else {
            $this->serveData($save);
          } // if
        } // if
      } // if
    } // edit
    
    /**
     * Export project milestones
     *
     * @param void
     * @return null
     */
    function export() {
      $object_visibility = array_var($_GET, 'visibility', VISIBILITY_NORMAL);
      $exportable_modules = explode(',', array_var($_GET,'modules', null));
      if (!is_foreachable($exportable_modules)) {
        $exportable_modules = null;
      } // if
      
      require_once(PROJECT_EXPORTER_MODULE_PATH.'/models/ProjectExporterOutputBuilder.class.php');
      $output_builder = new ProjectExporterOutputBuilder($this->active_project, $this->smarty, $this->active_module, $exportable_modules);
      if (!$output_builder->createOutputFolder()) {
        $this->serveData($output_builder->execution_log, 'execution_log', null, FORMAT_JSON);
      } // if
      $output_builder->createAttachmentsFolder();
      
      $active_milestones = array();
      $completed_milestones = array();
      
      $all_milestones = Milestones::findAllByProject($this->active_project, $object_visibility);
      if(is_foreachable($all_milestones)) {
        $output_builder->setFileTemplate(MILESTONES_MODULE, 'milestones', 'object');
        foreach($all_milestones as $milestone) {
          if ($milestone->isCompleted()) {
            $completed_milestones[] = $milestone;
          } else {
            $active_milestones[] = $milestone;
          } // if
          
          // Build milestone details page
          
          $objects = array();
          event_trigger('on_milestone_objects_by_visibility', array(&$milestone, &$objects, $object_visibility));
        	  
      	  $total_objects = 0;
      	  if (is_foreachable($objects)) {
      	    foreach ($objects as $objects_per_module) {
      	    	$total_objects += count($objects_per_module);
      	    } // foreach
      	  } // if
      	  
      	  $output_builder->smarty->assign(array(
      	    'active_milestone' => $milestone,
      	    'active_milestone_objects' => $objects,
      	    'total_objects' => $total_objects,
      	  ));
      	  
      	  $output_builder->outputToFile('milestone_'.$milestone->getId());
        } // foreach
      } // if
            
      // export milestones front page
      $output_builder->setFileTemplate(MILESTONES_MODULE, 'milestones', 'index');
      $output_builder->smarty->assign('active_milestones', $active_milestones);
      $output_builder->smarty->assign('completed_milestones', $completed_milestones);
      $output_builder->outputToFile('index');

      $this->serveData($output_builder->execution_log, 'execution_log', null, FORMAT_JSON);
    } // export
    
    function mass_update(){
    	if($this->request->isSubmitted()) {
    		$action = $this->request->post('with_selected');
    		
	        if(trim($action) == '') {
	          flash_error('Please select what you want to do with selected milestones');
	          $this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
	        } // if
	        
	        $milestone_ids = $this->request->post('milestones');
        	$milestones = Milestones::findByIds($milestone_ids, STATE_VISIBLE, $this->logged_user->getVisibility());
        	
        	$updated = 0;
        	if(is_foreachable($milestones)) {
          		// Complete selected milestones
          		if($action == 'complete') {
            		$message = lang(':count milestones completed');
            		//BOF:mod 20110617
			//BOF:mod 20120917 (reversed by shawn)
			/*
			//EOF:mod 20120917
            		$warning = '';
            		foreach($milestones as $milestone) {
						if($milestone->isOpen() && $milestone->canChangeCompleteStatus($this->logged_user)) {
              				$responsible_assignee = $milestone->getResponsibleAssignee();
              				$created_by_id = $milestone->getCreatedById();
              				$project_leader = $this->active_project->getLeaderId();
              				if ( (!is_null($responsible_assignee) && $responsible_assignee->getId()==$this->logged_user->getId()) 
									|| $created_by_id==$this->logged_user->getId() 
									|| $project_leader==$this->logged_user->getId() 
									|| $this->logged_user->isAdministrator() ){
								$warning .= '';
							} else {
								$warning .= '"' . $milestone->getName() . '", ';
							}
						}
					}
					if (!empty($warning)){
                                        $temp = new User(!empty($created_by_id) ? $created_by_id : $project_leader);
                                        $warning = 'Project ' . substr($warning, 0, -2) . ' cannot be closed at this time. Please send message to ' . $temp->getName() . ' to close this ticket.';
                                        unset($temp);
		          		flash_error($warning, null, true);
		          		$this->redirectToReferer($this->smarty->get_template_vars('milestones_url'));
					} else {
			//BOF:mod 20120917 (reversed by shawn)
			*/
			//EOF:mod 20120917
					//EOF:mod 20110617
	            		foreach($milestones as $milestone) {
	              			if($milestone->isOpen() && $milestone->canChangeCompleteStatus($this->logged_user)) {
	                			$complete = $milestone->complete($this->logged_user);
	                			if($complete && !is_error($complete)) {
	                  				$updated++;
	                			} // if
	              			} // if
	            		} // foreach
	            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
					//BOF:mod 20110617
			//BOF:mod 20120917 (reversed by shawn)
			/*
			//EOF:mod 20120917
					}
			//BOF:mod 20120917 (reversed by shawn)
			*/
			//EOF:mod 20120917
            		//EOF:mod 20110617            			
          		// Open selected milestones
          		} elseif($action == 'open') {
            		$message = lang(':count milestones opened');
            		foreach($milestones as $milestone) {
              			if($milestone->isCompleted() && $milestone->canChangeCompleteStatus($this->logged_user)) {
                			$open = $milestone->open($this->logged_user);
                			if($open && !is_error($open)) {
                  				$updated++;
                			} // if
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
          		// Mark object as starred
          		} elseif($action == 'star') {
            		$message = lang(':count milestones starred');
            		foreach($milestones as $milestone) {
              			$star = $milestone->star($this->logged_user);
              			if($star && !is_error($star)) {
                			$updated++;
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
          		// Unstar objects
          		} elseif($action == 'unstar') {
            		$message = lang(':count milestones unstarred');
            		foreach($milestones as $milestone) {
              			$unstar = $milestone->unstar($this->logged_user);
              			if($unstar && !is_error($unstar)) {
                			$updated++;
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
          		// Move selected objects to Trash
          		} elseif($action == 'trash') {
            		$message = lang(':count milestones moved to Trash');
            		foreach($milestones as $milestone) {
              			if($milestone->canDelete($this->logged_user)) {
                			$delete = $milestone->moveToTrash();
                			if($delete && !is_error($delete)) {
                  				$updated++;
                			} // if
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
          		// Set a selected priority
          		} elseif(str_starts_with($action, 'set_priority')) {
            		$priority = (integer) substr($action, 13);
            		$message = lang(':count milestones updated');
            		foreach($milestones as $milestone) {
              			if($milestone->canEdit($this->logged_user)) {
                			$milestone->setPriority($priority);
                			$save = $milestone->save();
                			if($save && !is_error($save)) {
                  				$updated++;
                			} // if
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
          		// Set visibility
          		} elseif(str_starts_with($action, 'set_visibility')) {
            		$visibility = (integer) substr($action, 15);
            		$message = lang(':count milestones updated');
            		foreach($milestones as $milestone) {
              			if($milestone->canEdit($this->logged_user)) {
                			$milestone->setVisibility($visibility);
                			$save = $milestone->save();
                			if($save && !is_error($save)) {
                  				$updated++;
                			} // if
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
         		// Move selected milestones to selected category
          		} elseif(str_starts_with($action, 'move_to_category')) {
            		if($action == 'move_to_category') {
              			$category_id = null;
            		} else {
              			$category_id = (integer) substr($action, 17);
            		} // if
            
            		//$category = $category_id ? Categories::findById($category_id) : null;
            
            		$message = lang(':count milestones updated');
            		foreach($milestones as $milestone) {
              			if($milestone->canEdit($this->logged_user)) {
                			$milestone->setCategoryId($category_id);
                			$save = $milestone->save();
                			if($save && !is_error($save)) {
                  				$updated++;
                			} // if
              			} // if
            		} // foreach
            		$this->redirectToReferer($this->smarty->get_template_vars('milestoness_url'));
          		} else {
            		$this->httpError(HTTP_ERR_BAD_REQUEST);
          		} // if
        	} else {
          		flash_error('Please select milestones that you would like to update');
          		$this->redirectToReferer($this->smarty->get_template_vars('milestones_url'));
        	} // if
    	} else {
    		$this->httpError(HTTP_ERR_BAD_REQUEST);
    	}
    	
    }
  
  }

?>