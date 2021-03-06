<?php
/*{*27 March 2012 Ticket #770: modify Print function for Pages in AC (SA)*}*/
/*12 April 2012 (SA) Ticket #784: check Recurring Reminder email script in AC*/
  // We need projects controller
  use_controller('project', SYSTEM_MODULE);

  /**
   * Tickets controller
   *
   * @package activeCollab.modules.tickets
   * @subpackage controllers
   */
  class TicketsController extends ProjectController {
    
    /**
     * Active module
     *
     * @var string
     */
    var $active_module = TICKETS_MODULE;
    
    /**
     * Active ticket
     *
     * @var Ticket
     */
    var $active_ticket;
    
    /**
     * Enable categories support for this controller
     *
     * @var boolean
     */
    var $enable_categories = true;
    
    /**
     * Actions that are exposed through API
     *
     * @var array
     */
    var $api_actions = array('index', 'archive', 'view', 'add', 'edit', 'categories');
    
    var $options = array();
  
    /**
     * Constructor
     *
     * @param Request $request
     * @return TicketsController
     */
    function __construct($request) {
      parent::__construct($request);
      
      if($this->logged_user->getProjectPermission('ticket', $this->active_project) < PROJECT_PERMISSION_ACCESS) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      $tickets_url = tickets_module_url($this->active_project);
      $archive_url = assemble_url('project_tickets_archive', array('project_id' => $this->active_project->getId()));
      
      $this->wireframe->addBreadCrumb(lang('Tickets'), $tickets_url);
      
      /*$_options = new NamedList();
      $_options->add('_category', array('url' => $tickets_url, 'text' => lang('Category')));
      $_options->add('_star', array('url' => $tickets_url, 'text' => lang('Star')));
      $_options->add('_priority', array('url' => $tickets_url, 'text' => lang('Priority')));
      $_options->add('_name', array('url' => $tickets_url, 'text' => lang('Name')));
      $_options->add('_owner', array('url' => $tickets_url, 'text' => lang('Owner')));
      $this->wireframe->addPageAction(lang('Sort by'), $tickets_url, $_options, null, '0');*/
      
      $add_ticket_url = false;
      if(Ticket::canAdd($this->logged_user, $this->active_project)) {
        $params = null;
        if($this->active_category->isLoaded()) {
          $params = array('category_id' => $this->active_category->getId());
        } // if
        $add_ticket_url = tickets_module_add_ticket_url($this->active_project, $params);
        
        $this->wireframe->addPageAction(lang('New Ticket'), $add_ticket_url, null, '1');
        //$this->wireframe->addPageAction(lang('New Ticket'), $add_ticket_url);
      } // if
      
      $ticket_id = $this->request->getId('ticket_id');
      if($ticket_id) {
        $this->active_ticket = Tickets::findByTicketId($this->active_project, $ticket_id);
      } // if      
      if(instance_of($this->active_category, 'Category') && $this->active_category->isLoaded()) {
        $this->wireframe->addBreadCrumb($this->active_category->getName(), $this->active_category->getViewUrl());
      } // if
      
      if(instance_of($this->active_ticket, 'Ticket')) {
        if($this->active_ticket->isCompleted()) {
          $this->wireframe->addBreadCrumb(lang('Archive'), $archive_url);
        } // if
        $this->wireframe->addBreadCrumb($this->active_ticket->getName(), $this->active_ticket->getViewUrl());
      } else {
        $this->active_ticket = new Ticket();
      } // if
      
      $this->smarty->assign(array(
        'tickets_url'           => $tickets_url,
        'tickets_archive_url'   => $archive_url,
        'add_ticket_url'        => $add_ticket_url,
        'active_ticket'         => $this->active_ticket,
        'page_tab'              => 'tickets',
        'mass_edit_tickets_url' => assemble_url('project_tickets_mass_edit', array('project_id' => $this->active_project->getId())),
      ));
    } // __construct
    
    /**
     * Show tickets index page
     *
     * @param void
     * @return null
     */
    function index() {
        /*
    	$selected_milestone_id = $_GET['milestone_id'];
		$milestones = Milestones::findByProject($this->active_project, $this->logged_user);
		//$milestones = Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility());
		
		if (empty($selected_milestone_id)){
			//$tickets = Tickets::findOpenByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility());
          	$tickets = Milestones::groupByMilestone(Tickets::findOpenByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility()), STATE_VISIBLE, $this->logged_user->getVisibility());
		} else {
			$milestone = new Milestone($selected_milestone_id);
			$tickets_in_milestone = Tickets::findByMilestone($milestone, STATE_VISIBLE, $this->logged_user->getVisibility());
			$tickets[$milestone->getId()] = array('milestone' => $milestone, 'objects' => $tickets_in_milestone);
		}
		
		$this->smarty->assign(array('milestones' => $milestones, 
								    'selected_milestone_id' => $selected_milestone_id, 
          							'can_add_ticket' => Ticket::canAdd($this->logged_user, $this->active_project),
          							'can_manage_categories' => $this->logged_user->isProjectLeader($this->active_project) || $this->logged_user->isProjectManager(),
          							'tickets' => $tickets,
									'categories' => Categories::findByModuleSection($this->active_project, TICKETS_MODULE, 'tickets'),
									'tickets_count' => (!empty($selected_milestone_id) ? count($tickets[$milestone->getId()]['objects']) : '-1')
									));
                */
    	
      if($this->request->isApiCall()) {
        if($this->active_category->isLoaded()) {
          $this->serveData(Tickets::findOpenByCategory($this->active_category, STATE_VISIBLE, $this->logged_user->getVisibility()), 'tickets');
        } else {
          $this->serveData(Tickets::findOpenByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility()), 'tickets');
        } // if
      } else {
		$tickets_url = tickets_module_url($this->active_project);
	    $options_sort = new NamedList();
	    $options_sort->add('category', array('url' => $tickets_url . '&sort=category', 'text' => lang('by Category')));
	    $options_sort->add('star', array('url' => $tickets_url . '&sort=star', 'text' => lang('by Star')));
	    $options_sort->add('priority', array('url' => $tickets_url . '&sort=priority', 'text' => lang('by Priority')));
	    $options_sort->add('name', array('url' => $tickets_url . '&sort=name', 'text' => lang('by Name')));
	    $options_sort->add('owner', array('url' => $tickets_url . '&sort=owner', 'text' => lang('by Owner')));
            //BOF:mod #59_266
            $options_sort->add('project', array('url' => $tickets_url . '&sort=milestone', 'text' => 'by Milestone'));
            //EOF:mod #59_266
	    $this->wireframe->addPageAction(lang('Sort'), '#', $options_sort->data);
      
      	$task_id = $_GET['task_id'];
      	if (!empty($task_id)){
            $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
            mysql_select_db(DB_NAME);
            $query = "select type, body from healingcrystals_project_objects where id='" . (int)$task_id . "'";
            $result = mysql_query($query);
            $info = mysql_fetch_assoc($result);
            if (strtolower($info['type'])=='task'){
                    $body_wo_tags = strip_tags($info['body']);
                    $query = "select max(integer_field_1) as max_count from healingcrystals_project_objects where project_id='" . $this->active_project->getId() . "'";
                    $result = mysql_query($query);
                    $info = mysql_fetch_assoc($result);
                    $integer_field_1 = $info['max_count']+1;

                    $query = "update healingcrystals_project_objects set 
                                     type='Ticket', 
                                     module='tickets', 
                                     parent_id='0', 
                                     parent_type=null, 
                                     name='" . str_replace("'", "''", $body_wo_tags) . "', 
                                     body=null, 
                                     updated_on=now(), 
                                     updated_by_id='" . $this->logged_user->getId() . "', 
                                     integer_field_1='" . $integer_field_1 . "' where id='" . (int)$task_id . "'";
                    mysql_query($query);
                    $edit_url = assemble_url('project_ticket_edit', array('project_id' => $this->active_project->getId(), 'ticket_id' => $integer_field_1));
                    header('Location: ' . $edit_url);
            }
            mysql_close($link);
      	}
      	$cur_department = $_GET['department_id'];
      	$mID = $_GET['mID'];
      	$page = (int)$_GET['page'];
      	$page = $page<=0 ? 1 : $page;
      	$limit = (int)$_GET['limit'];
      	//$limit = $limit<=0 ? 50 : $limit;
		$limit = $limit<=0 ? 1000 : $limit;
      	$offset = ($page - 1) * $limit;
      	$sort_by = trim(strtolower($_GET['sort']));
      	$sort_by = empty($sort_by) ? 'category' : $sort_by;
        $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
        mysql_select_db(DB_NAME);
        $tickets = array();
        /*if($this->active_category->isLoaded()) {
          $tickets = Milestones::groupByMilestone(
            Tickets::findOpenByCategory($this->active_category, STATE_VISIBLE, $this->logged_user->getVisibility()), 
            STATE_VISIBLE, $this->logged_user->getVisibility()
          );
        } else {
          $tickets = Milestones::groupByMilestone(
            Tickets::findOpenByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility()), 
            STATE_VISIBLE, $this->logged_user->getVisibility()
          );*/
        if (empty($cur_department)){
            //BOF:mod #59_266
            /*
            //EOF:mod #59_266
            $query = "select a.id as ticket_id, b.id as milestone_id, ifnull(a.priority, '0') from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id " ;
            //BOF:mod #59_266
            */
            $query = "select distinct a.id as ticket_id, b.id as milestone_id, cast(ifnull(a.priority, '0') as signed integer) as priority ::CONTENT_PLACE_HOLDER:: from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id " ;
            //EOF:mod #59_266
			//BOF:mod 20120911
			/*
			//EOF:mod 20120911
            $query_condition = " where a.project_id='" . $this->active_project->getId() . "' and a.type='Ticket' and a.state='" . STATE_VISIBLE . "' and a.visibility='" . VISIBILITY_NORMAL . "' and a.completed_on is null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
			//BOF:mod 20120911
			*/
			$query_condition = " where a.project_id='" . $this->active_project->getId() . "' and a.type='Ticket' and a.state='" . STATE_VISIBLE . "' and a.visibility>='" . $this->logged_user->getVisibility() . "' and a.completed_on is null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
			//EOF:mod 20120911
        } else {
            if ($cur_department=='-1'){
                //BOF:mod #59_266
                /*
                //EOF:mod #59_266
                $query = "select a.id as ticket_id, b.id as milestone_id, ifnull(a.priority, '0') from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id " ;
                //BOF:mod #59_266
                */
                $query = "select distinct a.id as ticket_id, b.id as milestone_id, cast(ifnull(a.priority, '0') as signed integer) as priority ::CONTENT_PLACE_HOLDER:: from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id " ;
                //EOF:mod #59_266
				//BOF:mod 20120911
				/*
				//EOF:mod 20120911
                $query_condition = " where not exists(select * from healingcrystals_project_object_categories obj where obj.object_id=a.id) and a.project_id='" . $this->active_project->getId() . "' and a.type='Ticket' and a.state='" . STATE_VISIBLE . "' and a.visibility='" . VISIBILITY_NORMAL . "' and a.completed_on is null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
				//BOF:mod 20120911
				*/
				$query_condition = " where not exists(select * from healingcrystals_project_object_categories obj where obj.object_id=a.id) and a.project_id='" . $this->active_project->getId() . "' and a.type='Ticket' and a.state='" . STATE_VISIBLE . "' and a.visibility>='" . $this->logged_user->getVisibility() . "' and a.completed_on is null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
				//EOF:mod 20120911
            } elseif ($cur_department=='-99'){
                //BOF:mod #59_266
                /*
                //EOF:mod #59_266
                $query = "select a.id as ticket_id, b.id as milestone_id, ifnull(a.priority, '0') from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id left join healingcrystals_project_milestone_categories dep on a.project_id=dep.project_id left join healingcrystals_project_object_categories obj on (dep.id=obj.category_id and a.id=obj.object_id) " ;
                //BOF:mod #59_266
                */
                $query = "select distinct a.id as ticket_id, b.id as milestone_id, cast(ifnull(a.priority, '0') as signed integer) as priority ::CONTENT_PLACE_HOLDER:: from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id left join healingcrystals_project_milestone_categories dep on a.project_id=dep.project_id left join healingcrystals_project_object_categories obj on (dep.id=obj.category_id and a.id=obj.object_id) " ;
                //EOF:mod #59_266
                $query_condition = " where a.project_id='" . $this->active_project->getId() . "' and a.type='Ticket' and a.completed_on is not null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
            } else {
                //BOF:mod #59_266
                /*
                //EOF:mod #59_266
                $query = "select a.id as ticket_id, b.id as milestone_id, ifnull(a.priority, '0') from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id inner join healingcrystals_project_milestone_categories dep on a.project_id=dep.project_id inner join healingcrystals_project_object_categories obj on (dep.id=obj.category_id and a.id=obj.object_id) " ;
                //BOF:mod #59_266
                */
                $query = "select distinct a.id as ticket_id, b.id as milestone_id, cast(ifnull(a.priority, '0') as signed integer) as priority ::CONTENT_PLACE_HOLDER:: from healingcrystals_project_objects a left join healingcrystals_project_objects b on a.milestone_id=b.id inner join healingcrystals_project_milestone_categories dep on a.project_id=dep.project_id inner join healingcrystals_project_object_categories obj on (dep.id=obj.category_id and a.id=obj.object_id) " ;
                //EOF:mod #59_266
				//BOF:mod 20120911
				/*
				//EOF:mod 20120911
                $query_condition = " where a.project_id='" . $this->active_project->getId() . "' and dep.id='" . $cur_department . "' and a.type='Ticket' and a.state='" . STATE_VISIBLE . "' and a.visibility='" . VISIBILITY_NORMAL . "' and a.completed_on is null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
				//BOF:mod 20120911
				*/
				$query_condition = " where a.project_id='" . $this->active_project->getId() . "' and dep.id='" . $cur_department . "' and a.type='Ticket' and a.state='" . STATE_VISIBLE . "' and a.visibility>='" . $this->logged_user->getVisibility() . "' and a.completed_on is null " . ($this->active_category && $this->active_category->isLoaded() ? " and a.parent_id='" . $this->active_category->getId() . "' " : "") . (!empty($mID) ? " and b.id='" . $mID . "'" : "");
				//EOF:mod 20120911
            }
        }
          	
        //BOF:mod #59_266
        /*
        //EOF:mod #59_266
        $result = mysql_query($query . $query_condition);
        $total_tickets = mysql_num_rows($result);
        $total_pages = ceil($total_tickets / $limit);
        if ($sort_by=='category'){
        //BOF:mod #59_266
        */
        if ($sort_by=='category' || $sort_by=='milestone'){
            if ($sort_by=='category'){
                $query_1 = str_replace('::CONTENT_PLACE_HOLDER::', '', $query) . $query_condition;
            } else {
                $query_1 = str_replace('::CONTENT_PLACE_HOLDER::', ", (select max(created_on) from healingcrystals_project_objects c where c.type='Comment' and c.parent_id=a.id) as comment_date", $query) . $query_condition;
            }
            $result_temp = mysql_query($query_1);
            $total_tickets = mysql_num_rows($result_temp);
            $total_pages = ceil($total_tickets / $limit);
            if ($sort_by=='category'){
                //$query_1 .= " order by isnull(b.id), b.name, a.position, priority desc limit " . $offset . ", " . $limit;
                $query_1 .= " order by isnull(b.id), b.name, priority desc, a.position limit " . $offset . ", " . $limit;
            } else {
                $query_1 .= " order by isnull(b.id), b.name, priority desc, comment_date desc limit " . $offset . ", " . $limit;
            }
        /*
        //EOF:mod #59_266
            $query_1 = $query . $query_condition . " order by isnull(b.id), b.name, a.priority desc limit " . $offset . ", " . $limit;
        //BOF:mod #59_266
        */
        //EOF:mod #59_266
            $result = mysql_query($query_1, $link);
            $cur_milestone_id = -1;
            while($ticket = mysql_fetch_assoc($result)){
                if ($cur_milestone_id==-1 || $cur_milestone_id!=(int)$ticket['milestone_id']){
                    $cur_milestone_id = (int)$ticket['milestone_id'];
                        if ($cur_milestone_id==0){
                            $cur_milestone_ref = null;
                        } else {
                            $cur_milestone_ref = new Milestone($ticket['milestone_id']);
                        }
                        $tickets[] = array('milestone' => $cur_milestone_ref, 
                                           'objects'    => array());
                }

                $tickets[count($tickets)-1]['objects'][] = new Ticket($ticket['ticket_id']);
            }
        } elseif ($sort_by=='star' || $sort_by=='priority' || $sort_by=='name' ||  $sort_by=='owner'){
            //BOF:mod #59_266
            $query = str_replace('::CONTENT_PLACE_HOLDER::', '', $query);
            $result_temp = mysql_query($query . $query_condition);
            $total_tickets = mysql_num_rows($result_temp);
            $total_pages = ceil($total_tickets / $limit);
            //EOF:mod #59_266
            switch($sort_by){
                    case 'star':
                            $query_1 = $query . " left join healingcrystals_starred_objects c on (a.id=c.object_id and c.user_id='" . $this->logged_user->getId() . "') left join healingcrystals_assignments d on (a.id=d.object_id and d.is_owner='1') left join healingcrystals_users e on d.user_id=e.id " . $query_condition . " order by isnull(c.object_id), 3 desc, isnull(e.first_name), e.first_name limit " . $offset . ", " . $limit;
                            break;
                    case 'priority':
                            $query_1 = $query . " left join healingcrystals_assignments d on (a.id=d.object_id and d.is_owner='1') left join healingcrystals_users e on d.user_id=e.id " . $query_condition . " order by 3 desc, isnull(e.first_name), e.first_name, a.name limit " . $offset . ", " . $limit;
                            break;
                    case 'name':
                            $query_1 = $query . $query_condition . " order by a.name limit " . $offset . ", " . $limit;
                            break;
                    case 'owner':
                            $query_1 = $query . " left join healingcrystals_assignments d on (a.id=d.object_id and d.is_owner='1') left join healingcrystals_users e on d.user_id=e.id " . $query_condition . " order by isnull(e.first_name), e.first_name, 3 desc, a.name limit " . $offset . ", " . $limit;
                            break;
            }
            $result = mysql_query($query_1, $link);
            while($ticket = mysql_fetch_assoc($result)){
                $cur_milestone_id = (int)$ticket['milestone_id'];
                if ($cur_milestone_id==0){
                    $cur_milestone_ref = null;
                } else {
                    $cur_milestone_ref = new Milestone($ticket['milestone_id']);
                }
                $tickets[]= array('milestone'   => $cur_milestone_ref, 
                                  'ticket'      => new Ticket($ticket['ticket_id']));
            }
        }
        //} // if
        $all_milestones = array();
		//BOF:mod 20120911
		/*
		//EOF:mod 20120911
        $query = "select id, name from healingcrystals_project_objects where project_id='" . $this->active_project->getId() . "' and type='Milestone' and state='" . STATE_VISIBLE . "' and visibility='" . VISIBILITY_NORMAL . "' and completed_on is null order by name";
		//BOF:mod 20120911
		*/
		$query = "select id, name from healingcrystals_project_objects where project_id='" . $this->active_project->getId() . "' and type='Milestone' and state='" . STATE_VISIBLE . "' and visibility>='" . $this->logged_user->getVisibility() . "' and completed_on is null order by name";
		//EOF:mod 20120911
        $result = mysql_query($query, $link);
        while($milestone = mysql_fetch_assoc($result)){
        	$all_milestones[] = array('id' => $milestone['id'], 'name' => $milestone['name']);
        }
        
        $departments = array();
        $query = "select id, category_name from healingcrystals_project_milestone_categories where project_id='" . $this->active_project->getId() . "' order by category_name";
      	$result = mysql_query(($query));
      	while($department = mysql_fetch_assoc($result)){
      		$departments[] = array('department_id' 	=> $department['id'], 
				  					  'department_name'	=> $department['category_name']);
		}
  		$departments[] = array('department_id' 	=> '-1', 
			  					  'department_name'	=> 'Uncategorized');
        mysql_close($link);
        $this->smarty->assign(array(
          'categories' => Categories::findByModuleSection($this->active_project, TICKETS_MODULE, 'tickets'),
          'groupped_tickets' => $tickets,
          'milestones' => Milestones::findActiveByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility()),
          'can_add_ticket' => Ticket::canAdd($this->logged_user, $this->active_project),
          'can_manage_categories' => $this->logged_user->isProjectLeader($this->active_project) || $this->logged_user->isProjectManager(),
		  'departments_url' => assemble_url('project_manage_milestone_categories', array('project_id' => $this->active_project->getId())), 
		  'total_tickets' =>  $total_tickets, 
		  'total_pages' => $total_pages, 
		  'current_page' => $page, 
		  'all_milestones' => $all_milestones, 
		  'current_milestone' => $mID, 
		  'sort_by' => $sort_by, 
		  'departments' => $departments, 
		  'current_department_id' => $cur_department, 
        ));
        
        js_assign('can_manage_tickets', Ticket::canManage($this->logged_user, $this->active_project));
      } // if
      
    } // index
    
    /**
     * Override view category page
     *
     * @param void
     * @return null
     */
    function view_category() {
      $this->redirectTo('project_tickets', array(
        'project_id' => $this->active_project->getId(),
        'category_id' => $this->request->getId('category_id')
      ));
    } // view_category
    
    /**
     * Show completed tickets
     *
     * @param void
     * @return null
     */
    function archive() {
      if($this->request->isApiCall()) {
        $this->serveData(Tickets::findCompletedByProject($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility()), 'tickets');
      } else {
        $this->wireframe->addBreadCrumb(lang('Archive'), assemble_url('project_tickets_archive', array('project_id' => $this->active_project->getId())));
      
        $per_page = 50;
        $page = (integer) $this->request->get('page');
        if($page < 1) {
          $page = 1;
        } // if
        
        if($this->active_category->isLoaded()) {
          list($tickets, $pagination) = Tickets::paginateCompletedByCategory($this->active_category, $page, $per_page, STATE_VISIBLE, $this->logged_user->getVisibility());
        } else {
          list($tickets, $pagination) = Tickets::paginateCompletedByProject($this->active_project, $page, $per_page, STATE_VISIBLE, $this->logged_user->getVisibility());
        } // if
        
        $this->smarty->assign(array(
          'tickets' => $tickets,
          'pagination' => $pagination,
          'categories' => Categories::findByModuleSection($this->active_project, TICKETS_MODULE, 'tickets'),
        ));
      } // if
    } // archive
    
    /**
     * Show single ticket
     *
     * @param void
     * @return null
     */
    function view() {
      if($this->active_ticket->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if
      
      if(!$this->active_ticket->canView($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      if($this->request->isApiCall()) {
        $this->serveData($this->active_ticket, 'ticket', array(
          'describe_comments'    => true, 
          'describe_tasks'       => true, 
          'describe_attachments' => true,
          'describe_assignees'   => true,
        ));
      } else {
        ProjectObjectViews::log($this->active_ticket, $this->logged_user);
        
        $page = (integer) $this->request->get('page');
        if($page < 1) {
          $page = 1;
        } // if
        
		//$show_all = $this->request->get('show_all');
		//if (!empty($show_all) && $show_all=='1' && !isset($_GET['comment_id'])){
		//	$comments = null;
		//	$pagination = null;
		//} else {
		//	$show_all = '';
        list($comments, $pagination) = $this->active_ticket->paginateComments($page, $this->active_ticket->comments_per_page, $this->logged_user->getVisibility());
		//}
		$comments_only_mode = $this->request->get('comments_only_mode');
		if ($comments_only_mode){
			$this->smarty->assign(array(
				'_object_comments_comments' => $comments,
				'pagination' => $pagination,
				'_counter' => ($page - 1) * $this->active_ticket->comments_per_page,
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
		
		$parent_id = $this->active_ticket->getParentId();
		$parent_category_name = 'Not Set';
		if (!empty($parent_id)){
			$cat = new Category($parent_id);
			if (instance_of($cat, 'Category')){
				$parent_category_name = $cat->getName();
			}
		}
		
		$is_responsible = $_GET['is_responsible'];
		if (!empty($is_responsible) && is_numeric($is_responsible)){
			$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
			mysql_select_db(DB_NAME);
			$query = "select * from healingcrystals_assignments where object_id='" . $this->active_ticket->getId() . "' and user_id='" . (int)$is_responsible . "'";
			$result = mysql_query($query, $link);
			if (mysql_num_rows($result)){
				mysql_query("update healingcrystals_assignments set is_owner='0' where object_id='" . $this->active_ticket->getId() . "'");
				mysql_query("update healingcrystals_assignments set is_owner='1' where object_id='" . $this->active_ticket->getId() . "' and user_id='" . (int)$is_responsible . "'");
			}
			mysql_close($link);
		}
		$priority = $_GET['priority'];
		if (is_numeric($priority)){
			/*$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
			mysql_select_db(DB_NAME);
			mysql_query("update healingcrystals_project_objects set priority='" . $priority . "' where id='" . $this->active_ticket->getId() . "'");
			mysql_close($link);*/
			$this->active_ticket->setPriority($priority);
			$this->active_ticket->save();
		}
		
		$action_request_user = $_GET['action_request_user'];
		if (!empty($action_request_user)){
			$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
			mysql_select_db(DB_NAME);
			if ($action_request_user==-1){
				$query = "select * from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "' and is_action_request='1'";
				$result = mysql_query($query, $link);
				if (mysql_num_rows($result)){
					$info = mysql_fetch_assoc($result);
					if (!$info['is_fyi']){
						mysql_query("delete from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "' and user_id='" . $info['user_id'] . "'");
					} else {
						mysql_query("update healingcrystals_assignments_action_request set is_action_request='0' where user_id='" . $info['user_id'] . "' and object_id='" . $this->active_ticket->getId() . "'");
					}
				}
			} else {
				if ($action_request_user>0){
					$query = "select * from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "' and is_action_request='1'";
					$result = mysql_query($query, $link);
					if (mysql_num_rows($result)){
						$info = mysql_fetch_assoc($result);
						if (!$info['is_fyi']){
							mysql_query("delete from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "' and user_id='" . $info['user_id'] . "'");
						} else {
							mysql_query("update healingcrystals_assignments_action_request set is_action_request='0' where user_id='" . $info['user_id'] . "' and object_id='" . $this->active_ticket->getId() . "'");
						}
					}
					
					$query = "select * from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "' and user_id='" . $action_request_user . "'";
					$result = mysql_query($query, $link);
					if (mysql_num_rows($result)){
						mysql_query("update healingcrystals_assignments_action_request set is_action_request='1' where user_id='" . $action_request_user . "' and object_id='" . $this->active_ticket->getId() . "'");
					} else {
						mysql_query("insert into healingcrystals_assignments_action_request (user_id, object_id, is_action_request) values ('" . $action_request_user . "', '" . $this->active_ticket->getId() . "', '1')");
					}
				}
			}
			/*if ($action_request_user==-1){
				mysql_query("delete from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "'");
			} elseif ((int)$action_request_user > 0) {
				$query = "select * from healingcrystals_assignments_action_request where object_id='" . $this->active_ticket->getId() . "'";
				$result = mysql_query($query, $link);
				if (mysql_num_rows($result)){
					mysql_query("update healingcrystals_assignments_action_request set user_id='" . $action_request_user . "' where object_id='" . $this->active_ticket->getId() . "'");
				} else {
					mysql_query("insert into healingcrystals_assignments_action_request (user_id, object_id) values ('" . $action_request_user . "', '" . $this->active_ticket->getId() . "')");
				}
			}*/
			mysql_close($link);
		} 
		
		$dID = $_GET['dID'];
		if (!empty($dID)){
			$this->active_ticket->changeDepartmentTo($dID);
		}
		
		$pID = $_GET['pID'];
		if (!empty($pID)){
			//$this->active_ticket->changeMilestoneTo($pID);
			$this->active_ticket->setMilestoneId($pID);
			$this->active_ticket->save();
		}
		
        $this->smarty->assign(array(
          'comments' => $comments,
          'pagination' => $pagination,
          'counter' => ($page - 1) * $this->active_ticket->comments_per_page,
          'parent_category_name' => $parent_category_name, 
          'subscribers' => $this->active_ticket->getSubscribers(), 
          'current_user_id' => $this->logged_user->getId(), 
          //BOF:mod 20110615
          'object_id' => $this->active_ticket->getId(), 
          //EOF:mod 20110615
		  'scroll_to_comment' => $scroll_to_comment, 
		  'show_all' => $show_all,
        ));
      } // if
    } // view
    
    /**
     * Create a new ticket
     *
     * @param void
     * @return null
     */
    function add() {
      $this->wireframe->print_button = false;
      
      if($this->request->isApiCall() && !$this->request->isSubmitted()) {
        $this->httpError(HTTP_ERR_BAD_REQUEST);
      } // ifs
      
      if(!Ticket::canAdd($this->logged_user, $this->active_project)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      $ticket_data = $this->request->post('ticket');
      if(!is_array($ticket_data)) {
        $ticket_data = array(
          'visibility'   => $this->active_project->getDefaultVisibility(),
          'milestone_id' => $this->request->get('milestone_id'),
          'parent_id'    => $this->request->get('category_id'),
        );
      } // if
      
      $this->smarty->assign('ticket_data', $ticket_data);
      
      if($this->request->isSubmitted()) {
        db_begin_work();
               
        $this->active_ticket = new Ticket();
        
        attach_from_files($this->active_ticket, $this->logged_user);
        
        $this->active_ticket->setAttributes($ticket_data);
        $this->active_ticket->setProjectId($this->active_project->getId());
        
        if(trim($this->active_ticket->getCreatedByName()) == '' || trim($this->active_ticket->getCreatedByEmail()) == '') {
          $this->active_ticket->setCreatedBy($this->logged_user);
        } // if
        
        $this->active_ticket->setState(STATE_VISIBLE);
        
        $save = $this->active_ticket->save();
        
        if($save && !is_error($save)) {
          $subscribers = array($this->logged_user->getId());
          if(is_foreachable(array_var($ticket_data['assignees'], 0))) {
            $subscribers = array_merge($subscribers, array_var($ticket_data['assignees'], 0));
          } else {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if
          
          if(!in_array($this->active_project->getLeaderId(), $subscribers)) {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if
          
          Subscriptions::subscribeUsers($subscribers, $this->active_ticket);
          
          db_commit();
          $this->active_ticket->ready();
          
          //BOF: mod
          $this->active_ticket->register_departments((!empty($ticket_data['departments']) ? $ticket_data['departments'] : array()));
          $this->register_reminder_info($this->active_ticket->getId(), array('date' => $ticket_data['reminder'], 'period' => $ticket_data['recurring_period'], 'type' => $ticket_data['recurring_period_type']));
          //EOF: mod
          $assignees_flag_data = $this->request->post('assignee');
          $this->active_ticket->register_assignees_flag($assignees_flag_data, true);
          //BOF:mod 13052011
          //if ($ticket_data['flag_fyi'] || $ticket_data['flag_actionrequest']){
		  //	$this->register_flag_fyi_actionrequest($ticket_data['flag_fyi'], $ticket_data['flag_actionrequest'], true);
		  //}
          //EOF:mod 13052011
          
          if($this->request->getFormat() == FORMAT_HTML) {
            flash_success('Ticket #:ticket_id has been added', array('ticket_id' => $this->active_ticket->getTicketId()));
            $this->redirectToUrl($this->active_ticket->getViewUrl());
          } else {
            $this->serveData($this->active_ticket, 'ticket');
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
     * Quick add ticket
     *
     * @param void
     * @return null
     */
    function quick_add() {
      if(!Ticket::canAdd($this->logged_user, $this->active_project)) {
      	$this->httpError(HTTP_ERR_FORBIDDEN, lang("You don't have permission for this action"), true, true);
      } // if
      
      $this->skip_layout = true;
           
      $ticket_data = $this->request->post('ticket');
      if(!is_array($ticket_data)) {
        $ticket_data = array(
          'visibility'   => $this->active_project->getDefaultVisibility(),
        );
      } // if
      
      $this->smarty->assign(array(
        'ticket_data' => $ticket_data,
        'quick_add_url' => assemble_url('project_tickets_quick_add', array('project_id' => $this->active_project->getId())),
      ));
      
      if ($this->request->isSubmitted()) {
        db_begin_work();
        
        $this->active_ticket = new Ticket();
        
        if (count($_FILES > 0)) {
          attach_from_files($this->active_ticket, $this->logged_user);  
        } // if
        
        $this->active_ticket->setAttributes($ticket_data);
        $this->active_ticket->setBody(clean(array_var($ticket_data, 'body', null)));
        if(!isset($ticket_data['priority'])) {
          $this->active_ticket->setPriority(PRIORITY_NORMAL);
        } // if
        $this->active_ticket->setProjectId($this->active_project->getId());
        $this->active_ticket->setCreatedBy($this->logged_user);
        $this->active_ticket->setState(STATE_VISIBLE);
        
        $save = $this->active_ticket->save();
        if($save && !is_error($save)) {
          $subscribers = array($this->logged_user->getId());
          if(is_foreachable(array_var($ticket_data['assignees'], 0))) {
            $subscribers = array_merge($subscribers, array_var($ticket_data['assignees'], 0));
          } else {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if
          Subscriptions::subscribeUsers($subscribers, $this->active_ticket);
          
          db_commit();
          $this->active_ticket->ready(); // ready
          
          $this->smarty->assign(array(
            'ticket_data' => array('visibility' => $this->active_project->getDefaultVisibility()),
            'active_ticket' => $this->active_ticket,
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
     * Update existing ticket
     *
     * @param void
     * @return null
     */
    function edit() {
	  $mode = $_GET['mode'];
	  if (!empty($mode) and $mode=='reminder_only_update_mode'){
	  		$this->register_reminder_info($this->active_ticket->getId(), array('date' => dateval($_POST['reminder']), 'period' => $_POST['recurring_period'], 'type' => $_POST['recurring_period_type']));
	  		$this->redirectToUrl($this->active_ticket->getViewUrl());
	  } else {
	      $this->wireframe->print_button = false;
	      
	      if($this->request->isApiCall() && !$this->request->isSubmitted()) {
	        $this->httpError(HTTP_ERR_BAD_REQUEST);
	      } // ifs
	      
	      if($this->active_ticket->isNew()) {
	        $this->httpError(HTTP_ERR_NOT_FOUND);
	      } // if
	      
	      if(!$this->active_ticket->canEdit($this->logged_user)) {
	        $this->httpError(HTTP_ERR_FORBIDDEN);
	      } // if
	      
	      $ticket_data = $this->request->post('ticket');
	      if(!is_array($ticket_data)) {
	        $ticket_data = array(
	          'name' => $this->active_ticket->getName(),
	          'body' => $this->active_ticket->getBody(),
	          'visibility' => $this->active_ticket->getVisibility(),
	          'parent_id' => $this->active_ticket->getParentId(),
	          'milestone_id' => $this->active_ticket->getMilestoneId(),
	          'priority' => $this->active_ticket->getPriority(),
	          'assignees' => Assignments::findAssignmentDataByObject($this->active_ticket),
	          'tags' => $this->active_ticket->getTags(),
	          'due_on' => $this->active_ticket->getDueOn(),
	        );
	      } // if
		//BOF:mod 20121116
		$options = array();
		$options[] = array('url' => 'javascript:convert_object_to_milestone(\'' . $this->active_ticket->getProjectId() . '\', \'' . $this->active_ticket->getId() . '\', \'' . $this->active_ticket->getType() . '\');', 'text' => 'Milestone');
		$options[] = array('url' => 'javascript:convert_object_to_page(\'' . $this->active_ticket->getProjectId() . '\', \'' . $this->active_ticket->getId() . '\', \'' . $this->active_ticket->getType() . '\');', 'text' => 'Page');
		$this->wireframe->addPageAction(lang('Convert To'), 'javascript://', $options );
		//EOF:mod 20121116
	      $this->smarty->assign('ticket_data', $ticket_data);
	      $this->smarty->assign('reminder', $this->get_reminder_info($this->active_ticket->getId()));
		  //BOF:mod 13052011
		  $this->smarty->assign('ticket_id', $this->active_ticket->getId());
          //EOF:mod 13052011
	      
	      if($this->request->isSubmitted()) {
	        if(!isset($ticket_data['assignees'])) {
	          $ticket_data['assignees'] = array(array(), 0);
	        } // if
	        
	        db_begin_work();
	        $this->active_ticket->setAttributes($ticket_data);
	        $save = $this->active_ticket->save();
	        
	        if($save && !is_error($save)) {
	          db_commit();
	          
	          //BOF: mod
	          $this->active_ticket->register_departments((!empty($ticket_data['departments']) ? $ticket_data['departments'] : array()));
	          $this->register_reminder_info($this->active_ticket->getId(), array('date' => dateval($ticket_data['reminder']), 'period' => $ticket_data['recurring_period'], 'type' => $ticket_data['recurring_period_type']));
	          //EOF: mod
	          $assignees_flag_data = $this->request->post('assignee');
	          $this->active_ticket->register_assignees_flag($assignees_flag_data);
	          //BOF:mod 13052011
	          //if ($ticket_data['flag_fyi'] || $ticket_data['flag_actionrequest']){
			  //	$this->register_flag_fyi_actionrequest($ticket_data['flag_fyi'], $ticket_data['flag_actionrequest']);
			  //}
	          //EOF:mod 13052011
	          
	          if ($ticket_data['new_team_id']!=$this->active_project->getId()){
				$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
				mysql_select_db(DB_NAME);
				$query = "select max(integer_field_1) as count from healingcrystals_project_objects where project_id='" . $ticket_data['new_team_id'] . "' and type='Ticket'";
				$result = mysql_query($query, $link);
				$cur_ticket_id = '1';
				if (mysql_num_rows($result)){
					$info = mysql_fetch_assoc($result);
					if ($info['count']){
						$cur_ticket_id = (int)$info['count'] + 1;
					}
				}
				$query = "update healingcrystals_project_objects set project_id='" . $ticket_data['new_team_id'] . "', milestone_id=null, integer_field_1='" . $cur_ticket_id . "', updated_on=now(), updated_by_id='" . $this->logged_user->getId() . "' where id='" . $this->active_ticket->getId() . "' and project_id='" . $this->active_project->getId() . "'";
				mysql_query($query);
				$query = "update healingcrystals_project_objects set project_id='" . $ticket_data['new_team_id'] . "', milestone_id=null, updated_on=now(), updated_by_id='" . $this->logged_user->getId() . "' where parent_id='" . $this->active_ticket->getId() . "' and project_id='" . $this->active_project->getId() . "'";
				mysql_query($query);
				mysql_close($link);
				$this->redirectToUrl(assemble_url('project_ticket', array('project_id' => $ticket_data['new_team_id'], 'ticket_id' => $cur_ticket_id)));
	          }
          
	          if($this->request->getFormat() == FORMAT_HTML) {
	            flash_success('Ticket #:ticket_id has been updated', array('ticket_id' => $this->active_ticket->getTicketId()));
	            $this->redirectToUrl($this->active_ticket->getViewUrl());
	          } else {
	            $this->serveData($this->active_ticket, 'ticket');
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
	  }	
    } // edit
    
    //BOF:mod
    function get_reminder_info($object_id){
    	$resp = array('date_value' => '', 'period' => '', 'type' => '');
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		$query = "select * from healingcrystals_project_object_misc where object_id='" . (int)$object_id . "'";
		$result = mysql_query($query, $link);
		if (mysql_num_rows($result)){
			$info = mysql_fetch_assoc($result);
			$resp['date_value'] = dateval($info['reminder_date']);
			$resp['period'] = $info['recurring_period'];
			$resp['type'] = $info['recurring_period_type'];
		}
		mysql_close($link);
		return $resp;
    }
    
    function register_reminder_info($object_id, $reminder_info){
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		$query = "select * from healingcrystals_project_object_misc where object_id='" . (int)$object_id . "'";
		$result = mysql_query($query, $link);
		if (mysql_num_rows($result)){
			if (empty($reminder_info['date']) && empty($reminder_info['period'])){
				$query = "delete from healingcrystals_project_object_misc where object_id='" . (int)$object_id . "'";
				mysql_query($query, $link);
			} else {
				$query = "update healingcrystals_project_object_misc set reminder_date=" . (empty($reminder_info['date']) ? "null" : "'" . dateval($reminder_info['date']) . "'") . ", recurring_period=" . (empty($reminder_info['period']) ? "null" : "'" . $reminder_info['period'] . "'") . ", recurring_period_type=" . (empty($reminder_info['period']) ? "null" : "'" . $reminder_info['type'] . "'") . ", last_modified=now() where object_id='" . (int)$object_id . "'";
				mysql_query($query, $link);
			}
		} else {
			if (!empty($reminder_info['date']) || !empty($reminder_info['period'])){
				$query = "insert into healingcrystals_project_object_misc (object_id, reminder_date, recurring_period, recurring_period_type, date_added) values ('" . $object_id . "', " . (empty($reminder_info['date']) ? "null" : "'" . dateval($reminder_info['date']) . "'") . ", " . (empty($reminder_info['period']) ? "null" : "'" . $reminder_info['period'] . "'") . ", " . (empty($reminder_info['period']) ? "null" : "'" . $reminder_info['type'] . "'") . ", now())";
				mysql_query($query, $link);
			}
		}
		mysql_close($link);
    }
    //EOF:mod
    
    /**
     * Update multiple tickets
     *
     * @param void
     * @return null
     */
    function mass_update() {
      if($this->request->isSubmitted()) {
        $action = $this->request->post('with_selected');
        if(trim($action) == '') {
          flash_error('Please select what you want to do with selected tickets');
          $this->redirectToReferer($this->smarty->get_template_vars('tickets_url'));
        } // if
        
        $ticket_ids = $this->request->post('tickets');
        $tickets = Tickets::findByIds($ticket_ids, STATE_VISIBLE, $this->logged_user->getVisibility());
        
        $updated = 0;
        if(is_foreachable($tickets)) {
          
          // Complete selected tickets
          if($action == 'complete') {
            $message = lang(':count tickets completed');
    		//BOF:mod 20110617
			//BOF:mod 20120917 (reversed by shawn)
			/*
			//EOF:mod 20120917
    		$warning = '';
    		foreach($tickets as $ticket) {
				if($ticket->isOpen() && $ticket->canChangeCompleteStatus($this->logged_user)) {
      				$responsible_assignee = $ticket->getResponsibleAssignee();
      				$created_by_id = $ticket->getCreatedById();
      				$project_leader = $this->active_project->getLeaderId();
      				if ( (!is_null($responsible_assignee) && $responsible_assignee->getId()==$this->logged_user->getId()) 
								|| $created_by_id==$this->logged_user->getId() 
								|| $project_leader==$this->logged_user->getId() 
								|| $this->logged_user->isAdministrator() ){
						$warning .= '';
					} else {
						$warning .= '"' . $ticket->getName() . '", ';
					}
				}
			}
			if (!empty($warning)){
                            $temp = new User(!empty($created_by_id) ? $created_by_id : $project_leader);
                            $warning = 'Ticket ' . substr($warning, 0, -2) . ' cannot be closed at this time. Please send message to ' . $temp->getName() . ' to close this ticket.';
                            unset($temp);
          		flash_error($warning, null, true);
          		$this->redirectToReferer($this->smarty->get_template_vars('tickets_url'));
			} else {
			//BOF:mod 20120917 (reversed by shawn)
			*/
			//EOF:mod 20120917
			//EOF:mod 20110617
    			foreach($tickets as $ticket) {
      				if($ticket->isOpen() && $ticket->canChangeCompleteStatus($this->logged_user)) {
        				$complete = $ticket->complete($this->logged_user);
        				if($complete && !is_error($complete)) {
          					$updated++;
        				} // if
      				} // if
    			} // foreach
    		//BOF:mod 20110617
			//BOF:mod 20120917 (reversed by shawn)
			/*
			//EOF:mod 20120917
    		}
			//BOF:mod 20120917 (reversed by shawn)
			*/
			//EOF:mod 20120917
    		//EOF:mod 20110617
          // Open selected tickets
          } elseif($action == 'open') {
            $message = lang(':count tickets opened');
            foreach($tickets as $ticket) {
              if($ticket->isCompleted() && $ticket->canChangeCompleteStatus($this->logged_user)) {
                $open = $ticket->open($this->logged_user);
                if($open && !is_error($open)) {
                  $updated++;
                } // if
              } // if
            } // foreach
            
          // Mark object as starred
          } elseif($action == 'star') {
            $message = lang(':count tickets starred');
            foreach($tickets as $ticket) {
              $star = $ticket->star($this->logged_user);
              if($star && !is_error($star)) {
                $updated++;
              } // if
            } // foreach
            
          // Unstar objects
          } elseif($action == 'unstar') {
            $message = lang(':count tickets unstarred');
            foreach($tickets as $ticket) {
              $unstar = $ticket->unstar($this->logged_user);
              if($unstar && !is_error($unstar)) {
                $updated++;
              } // if
            } // foreach
            
          // Move selected objects to Trash
          } elseif($action == 'trash') {
            $message = lang(':count tickets moved to Trash');
            foreach($tickets as $ticket) {
              if($ticket->canDelete($this->logged_user)) {
                $delete = $ticket->moveToTrash();
                if($delete && !is_error($delete)) {
                  $updated++;
                } // if
              } // if
            } // foreach
            
          // Set a selected priority
          } elseif(str_starts_with($action, 'set_priority')) {
            $priority = (integer) substr($action, 13);
            $message = lang(':count tickets updated');
            foreach($tickets as $ticket) {
              if($ticket->canEdit($this->logged_user)) {
                $ticket->setPriority($priority);
                $save = $ticket->save();
                if($save && !is_error($save)) {
                  $updated++;
                } // if
              } // if
            } // foreach
            
          // Set visibility
          } elseif(str_starts_with($action, 'set_visibility')) {
            $visibility = (integer) substr($action, 15);
            $message = lang(':count tickets updated');
            foreach($tickets as $ticket) {
              if($ticket->canEdit($this->logged_user)) {
                $ticket->setVisibility($visibility);
                $save = $ticket->save();
                if($save && !is_error($save)) {
                  $updated++;
                } // if
              } // if
            } // foreach
            
          // Move this ticket to a given milestone
          } elseif(str_starts_with($action, 'move_to_milestone')) {
            if($action == 'move_to_milestone') {
              $milestone_id = null;
            } else {
              $milestone_id = (integer) substr($action, 18);
            } // if
            
            $message = lang(':count tickets updated');
            foreach($tickets as $ticket) {
              if($ticket->canEdit($this->logged_user)) {
                $ticket->setMilestoneId($milestone_id);
                $save = $ticket->save();
                if($save && !is_error($save)) {
                  $updated++;
                } // if
              } // if
            } // foreach
            
          // Move selected tickets to selected category
          } elseif(str_starts_with($action, 'move_to_category')) {
            if($action == 'move_to_category') {
              $category_id = null;
            } else {
              $category_id = (integer) substr($action, 17);
            } // if
            
            $category = $category_id ? Categories::findById($category_id) : null;
            
            $message = lang(':count tickets updated');
            foreach($tickets as $ticket) {
              if($ticket->canEdit($this->logged_user)) {
                $ticket->setParent($category, false);
                $save = $ticket->save();
                if($save && !is_error($save)) {
                  $updated++;
                } // if
              } // if
            } // foreach
            
          } else {
            $this->httpError(HTTP_ERR_BAD_REQUEST);
          } // if
          
          flash_success($message, array('count' => $updated));
          $this->redirectToReferer($this->smarty->get_template_vars('tickets_url'));
        } else {
          flash_error('Please select tickets that you would like to update');
          $this->redirectToReferer($this->smarty->get_template_vars('tickets_url'));
        } // if
      } else {
        $this->httpError(HTTP_ERR_BAD_REQUEST);
      } // if
    } // mass_update
    
    /**
     * Show ticket changes
     *
     * @param void
     * @return null
     */
    function changes() {
      if($this->active_ticket->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if
      
      if(!$this->active_ticket->canView($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if
      
      $this->skip_layout = $this->request->isApiCall() || $this->request->isAsyncCall();
      
      $this->smarty->assign('changes', $this->active_ticket->getChanges());
    } // changes
    
    /**
     * Export tickets
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
      
      $module_categories = Categories::findByModuleSection($this->active_project, $this->active_module, $this->active_module);
      $module_objects = Tickets::findByProject($this->active_project, null , STATE_VISIBLE, $object_visibility);

      $output_builder->setFileTemplate($this->active_module, $this->controller_name, 'index');
      $output_builder->smarty->assign('categories',$module_categories);
      $output_builder->smarty->assign('objects', $module_objects);
      $output_builder->outputToFile('index');
                 
      // export tickets by categories
      if (is_foreachable($module_categories)) {
        foreach ($module_categories as $module_category) {
          if (instance_of($module_category,'Category')) {
            $output_builder->smarty->assign(array(
              'current_category' => $module_category,
              'objects' => Tickets::findByProject($this->active_project, $module_category, STATE_VISIBLE, $object_visibility),
            ));
            $output_builder->outputToFile('category_'.$module_category->getId());
          } // if
        } // foreach
      } // if
      
      // export tickets
      if (is_foreachable($module_objects)) {
        $output_builder->setFileTemplate($this->active_module, $this->controller_name, 'object');
        foreach ($module_objects as $module_object) {
          if (instance_of($module_object,'Ticket')) {
            $output_builder->outputAttachments($module_object->getAttachments());
            
            $comments = $module_object->getComments($object_visibility);
            $output_builder->outputObjectsAttachments($comments);
            
            if (module_loaded('timetracking')) {
              $timerecords = TimeRecords::findByParent($module_object, null, STATE_VISIBLE, $object_visibility);
              $total_time = TimeRecords::calculateTime($timerecords);
            } else {
              $timerecords = null;
              $total_time = 0;
            } // if
            
            $output_builder->smarty->assign(array(
              'timerecords' => $timerecords,
              'total_time'  => $total_time,
            	'object' => $module_object,
            	'comments' => $comments,
            ));
            $output_builder->outputToFile('ticket_'.$module_object->getId());
          } // if
        } // foreach
      } // if
      
      $this->serveData($output_builder->execution_log, 'execution_log', null, FORMAT_JSON);
    } // export
    
    
    
    /**
     * Show and process reorder task form
     *
     * @param void
     * @return null
     */
    function reorder_tickets() {
      $this->wireframe->print_button = false;
      
      $milestone = Milestones::findById($this->request->get('milestone_id'));
      if (instance_of($milestone, 'Milestone')) {
        $milestone_id = $milestone->getId();
      } else {
        $milestone_id = null;
      } // if
      
      if (!$this->request->isSubmitted()) {
        $this->httpError(HTTP_ERR_BAD_REQUEST, null, true, true);
      } // if
      
      if (!Ticket::canManage($this->logged_user, $this->active_project)) {
        $this->httpError(HTTP_ERR_FORBIDDEN, null, true, true);
      } // if     
      
      $order_data = $this->request->post('reorder_ticket');
      $ids = array_keys($order_data);
      if (is_foreachable($order_data)) {
      	$x = 1;
        foreach ($order_data as $key=>$value) {
        	$order_data[$key] = $x;
        	$x++;
        } // foreach
      } // if
      
      $tickets = Tickets::findByIds($ids, STATE_VISIBLE, $this->logged_user->getVisibility());
      if (is_foreachable($tickets)) {
        foreach ($tickets as $ticket) {
          $ticket->setMilestoneId($milestone_id);
          $ticket->setPosition(array_var($order_data, $ticket->getId()));
          $ticket->save();
        } // foreach
      } // if
      $this->httpOk();
    } // reorder
    
    //BOF:mod 13052011
    /*function register_flag_fyi_actionrequest($flag_fyi_users = array(), $flag_actionrequest_users = array(), $is_new_ticket = false){
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		if (!$is_new_ticket){
			$query = "delete from healingcrystals_assignments_flag_fyi_actionrequest where object_id='" . $this->active_ticket->getId() . "'";
			mysql_query($query, $link);
		}
		$users = array();
		foreach($flag_fyi_users as $user_id){
			if (!array_key_exists((string)$user_id, $users)){
				$users[(string)$user_id] = array('flag_fyi' => '0', 'flag_actionrequest' => '0');
			}
			$users[(string)$user_id]['flag_fyi'] = '1';
		}
		foreach($flag_actionrequest_users as $user_id){
			if (!array_key_exists((string)$user_id, $users)){
				$users[(string)$user_id] = array('flag_fyi' => '0', 'flag_actionrequest' => '0');
			}
			$users[(string)$user_id]['flag_actionrequest'] = '1';
		}
		
		foreach($users as $user_id => $flags){
			$query = "insert into healingcrystals_assignments_flag_fyi_actionrequest (user_id, object_id, flag_fyi, flag_actionrequest) values ('" . $user_id . "', '" . $this->active_ticket->getId() . "', '" . $flags['flag_fyi'] . "', '" . $flags['flag_actionrequest'] . "')";
			mysql_query($query, $link);
		}

		mysql_close($link);
	}*/
    //EOF:mod 13052011
    
	function change_priority(){
		$this->active_ticket->setPriority($this->request->post('priority'));
		$save = $this->active_ticket->save();
	}
    
  
  } // TicketsController

?>