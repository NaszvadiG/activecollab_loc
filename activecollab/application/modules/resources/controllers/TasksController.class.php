<?php
/*{*27 March 2012 Ticket #770: modify Print function for Pages in AC (SA)*}*/
/*12 April 2012 (SA) Ticket #784: check Recurring Reminder email script in AC*/
  // Projects controller is required
  use_controller('project', SYSTEM_MODULE);

  /**
   * Tasks controller
   *
   * @package activeCollab.modules.resources
   * @subpackage controllers
   */
  class TasksController extends ProjectController {

    /**
     * Active module
     *
     * @var string
     */
    var $active_module = RESOURCES_MODULE;

    /**
     * Selected task
     *
     * @var Task
     */
    var $active_task;

    /**
     * Parent object of active task
     *
     * @var ProjectObject
     */
    var $active_task_parent;

    /**
     * List of available API actions
     *
     * @var array
     */
    var $api_actions = array('view', 'add', 'edit');

    /**
     * Constructor
     *
     * @param Request $request
     * @return TasksController
     */
    function __construct($request) {
      parent::__construct($request);

      $task_id = $this->request->getId('task_id');
      if($task_id) {
        $this->active_task = Tasks::findById($task_id);
      } // if

      if(instance_of($this->active_task, 'Task')) {
        $this->active_task_parent = $this->active_task->getParent();
        if(instance_of($this->active_task_parent, 'ProjectObject')) {
          $this->active_task_parent->prepareProjectSectionBreadcrumb($this->wireframe);
        } // if
      } else {
        $this->active_task = new Task();

        $parent_id = $this->request->getId('parent_id');
        if($parent_id) {
          $parent = ProjectObjects::findById($parent_id);
          if(instance_of($parent, 'ProjectObject')) {
            $this->active_task_parent = $parent;
            $this->active_task_parent->prepareProjectSectionBreadcrumb($this->wireframe);
          } // if
        } // if
      } // if

      if(instance_of($this->active_task_parent, 'ProjectObject')) {
        $this->wireframe->addBreadCrumb($this->active_task_parent->getName(), $this->active_task_parent->getViewUrl());
      } else {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      $this->smarty->assign(array(
        'active_task' => $this->active_task,
        'active_task_parent' => $this->active_task_parent,
        'page_tab' => $this->active_task->getProjectTab()
      ));
    } // __construct

    /**
     * View task URL (redirects to parent object)
     *
     * @param void
     * @return null
     */
    function view() {
      if($this->active_task->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
      } // if

      if(empty($this->active_task_parent)) {
        $this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
      } // if

      if($this->request->isApiCall()) {
        $this->serveData($this->active_task, 'task');
      } else {
        $this->redirectToUrl($this->active_task_parent->getViewUrl() . '#task' . $this->active_task->getId());
      } // if
    } // view

    /**
     * Show and process add task form
     *
     * @param void
     * @return null
     */
    function add() {
      $this->wireframe->print_button = false;

      if(!instance_of($this->active_task_parent, 'ProjectObject')) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      if(!$this->active_task_parent->canSubtask($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      $task_data = $this->request->post('task');
      $this->smarty->assign(array(
        'task_data' => $task_data,
        'page_tab' => $this->active_task_parent->getProjectTab(),
      ));

      if($this->request->isSubmitted()) {
        db_begin_work();

        $this->active_task = new Task(); // just in case...
        $this->active_task->log_activities = false;

        $this->active_task->setAttributes($task_data);
        $this->active_task->setParent($this->active_task_parent);
        $this->active_task->setProjectId($this->active_project->getId());

        if(trim($this->active_task->getCreatedByName()) == '' || trim($this->active_task->getCreatedByEmail()) == '') {
          $this->active_task->setCreatedBy($this->logged_user);
        } // if

        $this->active_task->setState(STATE_VISIBLE);
        $this->active_task->setVisibility($this->active_task_parent->getVisibility());

        $save = $this->active_task->save();
        if($save && !is_error($save)) {
          $subscribers = array($this->logged_user->getId());
          if(is_foreachable(array_var($task_data['assignees'], 0))) {
            $subscribers = array_merge($subscribers, array_var($task_data['assignees'], 0));
          } else {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if

          if(!in_array($this->active_project->getLeaderId(), $subscribers)) {
            $subscribers[] = $this->active_project->getLeaderId();
          } // if

          Subscriptions::subscribeUsers($subscribers, $this->active_task);

          $activity = new NewTaskActivityLog();
          $activity->log($this->active_task, $this->logged_user);

          db_commit();
          $this->active_task->ready();

          //BOF:mod
          	$recurring_flag             = $task_data['recurring_flag'];
          	$recurring_period           = $task_data['recurring_period'];
          	$recurring_period_type      = $task_data['recurring_period_type'];
          	$recurring_period_condition = $task_data['recurring_period_condition'];
          	//$recurring_end_date         = str_replace('/', '-', $task_data['recurring_end_date']);
            ///    $reminder                   = str_replace('/', '-', $task_data['reminder']);
          	 $recurring_end_date         = dateval($task_data['recurring_end_date']);
                /*$reminder                   = dateval($task_data['reminder']);
                $reminderhours              = (int)$task_data['reminderhours'];
                $reminderminutes            = (int)$task_data['reminderminutes'];
                $remindermeridian           = $task_data['remindermeridian'];
                if (!empty($reminder)){
                    if (!empty($remindermeridian) && $remindermeridian=='PM' && $reminderhours<12){
                        $reminderhours += 12;
                    } elseif (!empty($remindermeridian) && $remindermeridian=='AM' && $reminderhours==12){
						$reminderhours = 0;
					}
                    $reminder               = $reminder . ' ' . $reminderhours . ':' . $reminderminutes;
                }*/
                $email_flag                 = empty($task_data['email_flag']) ? '0' : '1';
				if ($email_flag=='1'){
					$email_reminder_period		= (int)$task_data['figure_before_due_date'];
					$email_reminder_unit		= empty($task_data['unit_before_due_date']) ? 'D' : $task_data['unit_before_due_date'];
					$email_reminder_hours		= empty($task_data['reminderhours']) ? '6' : $task_data['reminderhours'];
					$email_reminder_minutes		= (int)$task_data['reminderminutes'];
					$email_reminder_meridian	= empty($task_data['remindermeridian']) ? 'AM' : $task_data['remindermeridian'];
					
					$email_reminder_time = '';
                    if ($email_reminder_meridian=='PM' && $email_reminder_hours<12){
                        $email_reminder_time = ($email_reminder_hours + 12)  . ':';
                    } elseif ($email_reminder_meridian=='AM' && $email_reminder_hours==12){
						$email_reminder_time = '00:';
					} else {
						$email_reminder_time = str_pad($email_reminder_hours, 2, '0', STR_PAD_LEFT) . ':';
					}
					$email_reminder_time .= $email_reminder_minutes;
					
				}

                $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
                mysql_select_db(DB_NAME);
				//if (empty($recurring_flag) && empty($reminder) && empty($email_flag)){
				if (empty($recurring_flag) && empty($email_flag)){
                    $query = "delete from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
                    mysql_query($query, $link);
				} else {
                    if (empty($recurring_flag)){
                        $recurring_period           = '';
                        $recurring_period_type      = '';
                        $recurring_period_condition = '';
                        $recurring_end_date         = '';
                    } else {
                        if (empty($recurring_period) || (int)$recurring_period==0){
                            $recurring_period = '7';
                        }
                    }
                    $query = "select * from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
                    $result = mysql_query($query, $link);
                    if (mysql_num_rows($result)){
                        $query01 = "update healingcrystals_project_object_misc set
                                    recurring_period='" . $recurring_period . "',
                                    recurring_period_type='" . $recurring_period_type . "',
                                    recurring_period_condition='" . $recurring_period_condition . "',
                                    recurring_end_date='" . $recurring_end_date . "', " .
                                    //reminder_date='" . $reminder . "',
                                    "auto_email_status='" . $email_flag . "',
									email_reminder_period=" . ($email_flag=='1' ? "'" . $email_reminder_period . "'" : "null") . ", 
									email_reminder_unit=" . ($email_flag=='1' ? "'" . $email_reminder_unit . "'" : "null") . ", 
									email_reminder_time=" . ($email_flag=='1' ? "'" . $email_reminder_time . "'" : "null") . ", 
                                    last_modified=now() where object_id='" . $this->active_task->getId() . "'";
                        mysql_query($query01, $link);
                    } else {
                        $query01 = "insert into healingcrystals_project_object_misc
                                    (object_id, " .
                                     //reminder_date,
                                     "recurring_period,
                                     recurring_period_type,
                                     recurring_period_condition,
                                     recurring_end_date,
                                     date_added,
                                     auto_email_status, 
									 email_reminder_period, 
									 email_reminder_unit, 
									 email_reminder_time) values
                                     ('" . $this->active_task->getId() . "', " .
                                      //'" . $reminder . "',
                                      "'" . $recurring_period . "',
                                      '" . $recurring_period_type . "',
                                      '" . $recurring_period_condition . "',
                                      '" . $recurring_end_date . "',
                                      now(),
                                      '" . $email_flag . "', 
									  " .  ($email_flag=='1' ? "'" . $email_reminder_period . "'" : "null") . ", 
									  " .  ($email_flag=='1' ? "'" . $email_reminder_unit . "'" : "null") . ", 
									  " .  ($email_flag=='1' ? "'" . $email_reminder_time . "'" : "null") . ")";
                            mysql_query($query01, $link);
                    }
                }
                mysql_close($link);
          //EOF:mod


          if($this->request->isApiCall()) {
            $this->serveData($this->active_task, 'task');
          } elseif($this->request->isAsyncCall()) {
            $this->smarty->assign(array(
              '_object_task' => $this->active_task,
            ));
            print tpl_fetch(get_template_path('_task_opened_row', $this->controller_name, RESOURCES_MODULE));
            die();
          } else {
            //flash_success('Task ":name" has been added', array('name' => str_excerpt($this->active_task->getBody(), 80, '...')), false, false);
            //bof:mod
            flash_success('Task ":name" has been added', array('name' => str_excerpt(strip_tags($this->active_task->getBody()), 80, '...')), false, false);
            //eof:mod
            $this->redirectToUrl($this->active_task_parent->getViewUrl());
          } // if
        } else {
          db_rollback();

          if($this->request->isApiCall() || $this->request->isAsyncCall()) {
            $this->serveData($save);
          } else {
            $this->smarty->assign('errors', $save);
            //$this->smarty->assign('add_content', '');
          } // if
        } // if
      } else {
        if($this->request->isApiCall()) {
          $this->httpError(HTTP_ERR_BAD_REQUEST);
        } // if
      } // if
    } // add

    /**
     * Show and process edit task form
     *
     * @param void
     * @return null
     */
    function edit() {
      $this->wireframe->print_button = false;

      if($this->active_task->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
      } // if

      if(empty($this->active_task_parent)) {
        $this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
      } // if

      if(!$this->active_task->canEdit($this->logged_user) && $this->active_task->getProjectId()!=TASK_LIST_PROJECT_ID ) {
        $this->httpError(HTTP_ERR_FORBIDDEN, null, true, $this->request->isApiCall());
      } // if
      //BOF:mod
      $this->wireframe->addPageAction(lang('Convert Task to a Ticket'), tickets_module_url($this->active_project) . '&task_id=' . $this->active_task->getId());
      //EOF:mod

      $task_data = $this->request->post('task');
      if(!is_array($task_data)) {
        $task_data = array(
          'body' => $this->active_task->getBody(),
          'priority' => $this->active_task->getPriority(),
          'due_on' => $this->active_task->getDueOn(),
          'assignees' => Assignments::findAssignmentDataByObject($this->active_task),
        );

        $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
        mysql_select_db(DB_NAME);
        $query = "select * from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
        $result = mysql_query($query, $link);
        if (mysql_num_rows($result)){
                $info = mysql_fetch_assoc($result);
                $task_data['recurring_flag'] = '1';
                $task_data['recurring_period'] = $info['recurring_period'];
                if (empty($task_data['recurring_period'])){
                    $task_data['recurring_flag'] = '0';
                }
                $task_data['recurring_period_type'] = $info['recurring_period_type'];
                $task_data['recurring_period_condition'] = $info['recurring_period_condition'];
                $task_data['recurring_end_date'] = empty($info['recurring_end_date']) || $info['recurring_end_date']=='0000-00-00' ? '' : dateval($info['recurring_end_date']);
                /*if (!empty($info['reminder_date']) && $info['reminder_date']!='0000-00-00 00:00:00'){
                    list($date, $time) = explode(' ', $info['reminder_date']);
                    list($h, $m, $s) = explode(':', $time);
                    //$date = str_replace('-', '/', $date);
                    $date = dateval($date);
					$task_data['reminder'] = $date;
                } else {*/
					$task_data['reminder'] = '';
				/*}
                $task_data['remindermeridian'] = $h>=12 ? 'PM' : 'AM';
                $task_data['reminderhours'] = $h>12 ? $h-12 : ($h!=0 ? $h : '12');
                $task_data['reminderminutes'] = $m;*/
				//BOF:mod 20120703
				$task_data['auto_email_status'] = $info['auto_email_status'];
				//EOF:mod 20120703
				if ($info['auto_email_status']=='1'){
					$task_data['figure_before_due_date'] = empty($info['email_reminder_period']) ? '0' : $info['email_reminder_period'];
					$task_data['unit_before_due_date'] = empty($info['email_reminder_unit']) ? 'D' : $info['email_reminder_unit'];
					$email_reminder_time = $info['email_reminder_time'];
					if (!empty($email_reminder_time)){
						list($h, $m,) = explode(':', $email_reminder_time);
						$task_data['remindermeridian'] = $h>=12 ? 'PM' : 'AM';
						$task_data['reminderhours'] = $h>12 ? $h-12 : ($h!=0 ? $h : '12');
						$task_data['reminderminutes'] = $m;
					} else {
						$task_data['remindermeridian'] = 'AM';
						$task_data['reminderhours'] = '6';
						$task_data['reminderminutes'] = '0';
					}
				}
        } else {
                $task_data['recurring_flag'] = '0';
                $task_data['recurring_period'] = '';
                $task_data['recurring_period_type'] = 'D';
                $task_data['recurring_period_condition'] = 'after_due_date';
                $task_data['recurring_end_date'] = '';
                $task_data['reminder'] = '';
                $task_data['reminderhours'] = '6';
                $task_data['reminderminutes'] = '0';
                $task_data['remindermeridian'] = 'AM';
				//BOF:mod 20120703
				$task_data['auto_email_status'] = '';
				//EOF:mod 20120703
				$task_data['figure_before_due_date'] = '0';
				$task_data['unit_before_due_date'] = 'D';
        }
        mysql_close($link);

      } // if

      $this->smarty->assign('task_data', $task_data);

      if($this->request->isSubmitted()) {
        if(!isset($task_data['assignees'])) {
          $task_data['assignees'] = array(array(), 0);
        } // if

        db_begin_work();
        $old_name = $this->active_task->getBody();

        $this->active_task->setAttributes($task_data);
        $save = $this->active_task->save();

        if($save && !is_error($save)) {
          db_commit();
          //BOF:mod
          	$recurring_flag 		= $task_data['recurring_flag'];
          	$recurring_period 		= $task_data['recurring_period'];
          	$recurring_period_type 		= $task_data['recurring_period_type'];
          	$recurring_period_condition     = $task_data['recurring_period_condition'];
          	//$recurring_end_date 		= str_replace('/', '-', $task_data['recurring_end_date']);
             //   $reminder                       = str_replace('/', '-', $task_data['reminder']);
          	$recurring_end_date 		= dateval($task_data['recurring_end_date']);
                /*$reminder                       = dateval($task_data['reminder']);
                $reminderhours                  = (int)$task_data['reminderhours'];
                $reminderminutes                = (int)$task_data['reminderminutes'];
                $remindermeridian               = $task_data['remindermeridian'];
                if (!empty($reminder)){
                    if (!empty($remindermeridian) && $remindermeridian=='PM' && $reminderhours<12){
                        $reminderhours += 12;
                    } elseif (!empty($remindermeridian) && $remindermeridian=='AM' && $reminderhours==12){
						$reminderhours = 0;
					}
                    $reminder                   = $reminder . ' ' . $reminderhours . ':' . $reminderminutes;
                }*/
                $email_flag                     = empty($task_data['email_flag']) ? '0' : '1';
				if ($email_flag=='1'){
					$email_reminder_period		= (int)$task_data['figure_before_due_date'];
					$email_reminder_unit		= empty($task_data['unit_before_due_date']) ? 'D' : $task_data['unit_before_due_date'];
					$email_reminder_hours		= empty($task_data['reminderhours']) ? '6' : $task_data['reminderhours'];
					$email_reminder_minutes		= (int)$task_data['reminderminutes'];
					$email_reminder_meridian	= empty($task_data['remindermeridian']) ? 'AM' : $task_data['remindermeridian'];
					
					$email_reminder_time = '';
                    if ($email_reminder_meridian=='PM' && $email_reminder_hours<12){
                        $email_reminder_time = ($email_reminder_hours + 12)  . ':';
                    } elseif ($email_reminder_meridian=='AM' && $email_reminder_hours==12){
						$email_reminder_time = '00:';
					} else {
						$email_reminder_time = str_pad($email_reminder_hours, 2, '0', STR_PAD_LEFT) . ':';
					}
					$email_reminder_time .= $email_reminder_minutes;
					
				}
				
                $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
                mysql_select_db(DB_NAME);
                if (empty($recurring_flag) && empty($reminder) && empty($email_flag)){
                    $query = "delete from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
                    mysql_query($query, $link);
                } else {
                    if (empty($recurring_flag)){
                        $recurring_period           = '';
                        $recurring_period_type      = '';
                        $recurring_period_condition = '';
                        $recurring_end_date         = '';
                    }
                    $query = "select * from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
                    $result = mysql_query($query, $link);
                    if (mysql_num_rows($result)){
                        $query01 = "update healingcrystals_project_object_misc set " .
                                    //reminder_date='" . $reminder . "',
                                    " recurring_period='" . $recurring_period . "',
                                    recurring_period_type='" . $recurring_period_type . "',
                                    recurring_period_condition='" . $recurring_period_condition . "',
                                    recurring_end_date='" . $recurring_end_date . "',
                                    auto_email_status='" . $email_flag . "',
									email_reminder_period=" . ($email_flag=='1' ? "'" . $email_reminder_period . "'" : "null") . ", 
									email_reminder_unit=" . ($email_flag=='1' ? "'" . $email_reminder_unit . "'" : "null") . ", 
									email_reminder_time=" . ($email_flag=='1' ? "'" . $email_reminder_time . "'" : "null") . ", 
                                    last_modified=now() where object_id='" . $this->active_task->getId() . "'";
                        mysql_query($query01, $link);
                    } else {
                        $query01 = "insert into healingcrystals_project_object_misc
                                    (object_id, " .
                                    //reminder_date,
                                    " recurring_period,
                                    recurring_period_type,
                                    recurring_period_condition,
                                    recurring_end_date,
                                    date_added,
                                    auto_email_status, 
									email_reminder_period, 
									 email_reminder_unit, 
									 email_reminder_time) values (
                                    '" . $this->active_task->getId() . "', " .
                                    //'" . $reminder . "',
                                    "'" . $recurring_period . "',
                                    '" . $recurring_period_type . "',
                                    '" . $recurring_period_condition . "',
                                    '" . $recurring_end_date . "',
                                    now(),
                                    '" . $email_flag . "', 
									" .  ($email_flag=='1' ? "'" . $email_reminder_period . "'" : "null") . ", 
									" .  ($email_flag=='1' ? "'" . $email_reminder_unit . "'" : "null") . ", 
									" .  ($email_flag=='1' ? "'" . $email_reminder_time . "'" : "null") . ")";
                        mysql_query($query01, $link);
                    }
                }
                mysql_close($link);
          //EOF:mod

          if($this->request->isApiCall()) {
            $this->serveData($this->active_task, 'task');
          } else {
            //flash_success('Task ":name" has been updated', array('name' => str_excerpt($old_name, 80, '...')), false, false);
            //bof:mod
            flash_success('Task ":name" has been updated', array('name' => str_excerpt(strip_tags($old_name), 80, '...')), false, false);
            //eof:mod
			//BOF:mod 20120904
			/*
			//EOF:mod 20120904
            $this->redirectToUrl($this->active_task_parent->getViewUrl());
			//BOF:mod 20120904
			*/
			$this->redirectToUrl($this->active_task_parent->getViewUrl() . '#task' . $this->active_task->getId());
			//EOF:mod 20120904
          } // if
        } else {
          db_rollback();

          if($this->request->isApiCall()) {
            $this->serveData($save);
          } else {
            $this->smarty->assign('errors', $save);
          } // if
        } // if
      } else {
        if($this->request->isApiCall()) {
          $this->httpError(HTTP_ERR_BAD_REQUEST, null, true, true);
        } // if
      } // if
    } // edit

    /**
     * Complete specific object
     *
     * @param void
     * @return null
     */
    function complete() {
      if($this->active_task->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      if(!$this->active_task->canChangeCompleteStatus($this->logged_user) && $this->active_task->getProjectId()!=TASK_LIST_PROJECT_ID) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      if($this->request->isSubmitted() || isset($_GET['auto'])) {
		//BOF:mod 20110617
		$responsible_assignee = $this->active_task->getResponsibleAssignee();
		$created_by_id = $this->active_task->getCreatedById();
		$project_leader = $this->active_project->getLeaderId();
		$parent_type = $this->active_task->getParentType();
		//BOF:mod 20120824
		/*
		//EOF:mod 20120824
		$ticket_owner_exists = false;
		if ($parent_type=='Ticket'){
			$temp_ticket = new Ticket($this->active_task->getParentId());
			$ticket_owner = $temp_ticket->getResponsibleAssignee();
			if (!empty($ticket_owner)){
				$ticket_owner_exists = true;
			}
		}
		//BOF:mod 20120824
		*/
		$owner_exists = false;
		$parent = new $parent_type($this->active_task->getParentId());
		$owner = $parent->getResponsibleAssignee();
		if (!empty($owner)){
			$owner_exists = true;
		}
		//BOF:mod 20121031
		$user_is_subscriber = false;
		$subscribers = array();
		if ($parent->can_have_subscribers){
			if ($parent->hasSubscribers() ){
				$subscribers = $parent->getSubscribers();
				foreach($subscribers as $subscriber){
					if ($subscriber->getId() == $this->logged_user->getId() ){
						$user_is_subscriber = true;
						break;
					}
				}
			}
		}
		//EOF:mod 20121031
		
		$page_user_id = '';
		if ($parent_type=='Page' && stripos($parent->getName(), 'task list')!==false){
			$temp = explode('-', $parent->getName());
			if (count($temp)){
				$name = trim($temp[0]);
				$name_parts = explode(' ', $name);
				if (count($name_parts)){
					list($first_name, $last_name) = $name_parts;
					$first_name = trim($first_name);
					$last_name = trim($last_name);
					$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
					mysql_select_db(DB_NAME);
					$query = "select id from healingcrystals_users where first_name='" . mysql_real_escape_string($first_name) . "'" . (!empty($last_name) ? " and last_name='" . mysql_real_escape_string($last_name) . "'" : "");
					$result = mysql_query($query, $link);
					if (mysql_num_rows($result)){
						$info = mysql_fetch_assoc($result);
						$page_user_id = $info['id'];
					}
					mysql_close($link);
				}
			}
		}
		//EOF:mod 20120824	
			//BOF:mod 20120917 (reversed by shawn)
			/**/
			//EOF:mod 20120917
		if ( (!is_null($responsible_assignee) && $responsible_assignee->getId()==$this->logged_user->getId())
					|| $created_by_id==$this->logged_user->getId()
					|| $project_leader==$this->logged_user->getId()
					|| $this->logged_user->isAdministrator()
					//BOF:mod 20120824
					//EOF:mod 20120824
					//|| ($ticket_owner_exists && $ticket_owner==$this->logged_user->getId()) ){
					//BOF:mod 20120824
					//BOF:mod 20121031
					|| $user_is_subscriber 
					//EOF:mod 20121031
					|| (!empty($page_user_id) && $page_user_id==$this->logged_user->getId() )
					|| ($owner_exists && $owner==$this->logged_user->getId()) ){
					//BOF:mod 20120824
			$warning = '';
		} else {
                        $temp = new User($created_by_id);
                        $warning = 'Message: Task "' . $this->active_task->getName() . '" cannot be closed at this time. Please send message to ' . $temp->getName() . ' to close this task.';
                        unset($temp);
		}
		if (!empty($warning)){
			$this->smarty->assign(array('warning' => $warning,));
			print tpl_fetch(get_template_path('_task_completed_row', $this->controller_name, RESOURCES_MODULE));
			die();
		}
			//BOF:mod 20120917 (reversed by shawn)
			/**/
			//EOF:mod 20120917
		//EOF:mod 20110617
		db_begin_work();
		$_SESSION['new_recurring_task_id'] = null;
        $action = $this->active_task->complete($this->logged_user);
        if($action && !is_error($action)) {
          db_commit();

          if($this->request->getFormat() == FORMAT_HTML) {
            if($this->request->get('async')) {
              $this->smarty->assign(array(
                '_object_task' => $this->active_task,
				'new_recurring_task_id' => $_SESSION['new_recurring_task_id'], 
              ));
              print tpl_fetch(get_template_path('_task_completed_row', $this->controller_name, RESOURCES_MODULE));
			  if (!empty($_SESSION['new_recurring_task_id'])){
				  $this->smarty->assign(array(
					'_object_task' => new Task($_SESSION['new_recurring_task_id']),
				  ));
				  print tpl_fetch(get_template_path('_task_opened_row', $this->controller_name, RESOURCES_MODULE));
			  }
			  unset($_SESSION['new_recurring_task_id']);
              die();
            } else {
              //flash_success('Task ":name" has been completed', array('name' => str_excerpt($this->active_task->getName(), 80, '...')));
              //bof:mod
              flash_success('Task ":name" has been completed', array('name' => str_excerpt(strip_tags($this->active_task->getName()), 80, '...')));
              //eof:mod
			  if (isset($_GET['auto'])){
			    $this->redirectToUrl($this->active_task->getViewUrl());
			  } else {
				$this->redirectToReferer($this->active_task->getViewUrl());
			  }
            } // if
          } else {
            $this->serveData($this->active_task);
          } // if
        } else {
          db_rollback();

          if($this->request->getFormat() == FORMAT_HTML) {
            if($this->request->get('async')) {
              $this->serveData($action);
            } else {
              flash_error('Failed to complete task ":name"', array('name' => str_excerpt($this->active_task->getName(), 80, '...')));
              $this->redirectToReferer($this->active_task->getViewUrl());
            } // if
          } else {
            $this->httpError(HTTP_ERR_OPERATION_FAILED);
          } // if
        } // if
      } else {
        $this->httpError(HTTP_ERR_BAD_REQUEST);
      } // if
    } // complete

    /**
     * Reopen specific object
     *
     * @param void
     * @return null
     */
    function open() {
      if($this->active_task->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      if(!$this->active_task->canChangeCompleteStatus($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      if($this->request->isSubmitted()) {
        db_begin_work();

        $action = $this->active_task->open($this->logged_user);
        if($action && !is_error($action)) {
          db_commit();

          if($this->request->getFormat() == FORMAT_HTML) {
            if($this->request->get('async')) {
              $this->smarty->assign(array(
                '_object_task' => $this->active_task,
              ));
              print tpl_fetch(get_template_path('_task_opened_row', $this->controller_name, RESOURCES_MODULE));
              die();
            } else {
              //flash_success('Task ":name" has been opened', array('name' => str_excerpt($this->active_task->getName(), 80, '...')));
              //bof:mod
              flash_success('Task ":name" has been opened', array('name' => str_excerpt(strip_tags($this->active_task->getName()), 80, '...')));
              //eof:mod
              $this->redirectToReferer($this->active_task->getViewUrl());
            } // if
          } else {
            $this->serveData($this->active_task);
          } // if
        } else {
          db_rollback();

          if($this->request->getFormat() == FORMAT_HTML) {
            if($this->request->get('async')) {
              $this->serveData($action);
            } else {
              flash_error('Failed to open task ":name"', array('name' => str_excerpt($this->active_task->getName(), 80, '...')));
              $this->redirectToReferer($this->active_task->getViewUrl());
            } // if
          } else {
            $this->httpError(HTTP_ERR_OPERATION_FAILED);
          } // if
        } // if
      } else {
        $this->httpError(HTTP_ERR_BAD_REQUEST);
      } // if
    } // open

    /**
     * Show and process reorder task form
     *
     * @param void
     * @return null
     */
    function reorder() {
      $this->wireframe->print_button = false;

      if(!instance_of($this->active_task_parent, 'ProjectObject')) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      if(!$this->active_task_parent->canSubtask($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      $order_data = $this->request->post('task');
      $ids = array_keys($order_data);
      if (is_foreachable($order_data)) {
      	$x = 1;
        foreach ($order_data as $key=>$value) {
        	$order_data[$key] = $x;
        	$x++;
        } // foreach
      } // if

      $tasks = Tasks::findByIds($ids, STATE_VISIBLE, $this->logged_user->getVisibility());
      if (is_foreachable($tasks)) {
        foreach ($tasks as $task) {
          $task->setParent($this->active_task_parent);
          $task->setProjectId($this->active_task_parent->getProjectId());
          $task->setVisibility($this->active_task_parent->getVisibility());
          $task->setPosition(array_var($order_data, $task->getId()));
          $task->save();
        } // foreach
      } // if
      $this->httpOk();
    } // reorder

    /**
     * Function used only via ajax to return all completed tasks
     *
     * @param void
     * @return null
     */
    function list_completed() {
      if (!$this->request->isAsyncCall()) {
        $this->redirectToReferer('dashboard');
      } // if

      $completed_tasks = $this->active_task_parent->getCompletedTasks();
      $this->smarty->assign(array(
        'completed_tasks' => $completed_tasks
      ));
    } // list_completed

    //BOF:mod
	function ajaxedit(){
      if(!$this->active_task->canEdit($this->logged_user) && $this->active_task->getProjectId()!=TASK_LIST_PROJECT_ID) {
        $this->httpError(HTTP_ERR_FORBIDDEN, null, true, $this->request->isApiCall());
      } // if

      $task_summary = $this->request->post('task_summary');
      if(!is_array($task_data)) {
        $task_data = array(
          'body' => (empty($task_summary) ? $this->active_task->getBody() : $task_summary),
          'priority' => $this->active_task->getPriority(),
          'due_on' => $this->active_task->getDueOn(),
          'assignees' => Assignments::findAssignmentDataByObject($this->active_task),
        );
      } // if

      $this->smarty->assign('task_summary', $task_data['body']);

      if($this->request->isSubmitted()) {
        if(!isset($task_data['assignees'])) {
          $task_data['assignees'] = array(array(), 0);
        } // if

        db_begin_work();
        $old_name = $this->active_task->getBody();

        $this->active_task->setAttributes($task_data);
        $save = $this->active_task->save();

        if($save && !is_error($save)) {
          db_commit();
        } else {
          db_rollback();
        } // if
      }
	}
    //EOF:mod
        function change_priority_tasks(){
			if($this->request->post('priority')=='0'){
				$query = "update " . TABLE_PREFIX . "project_objects set priority='0', updated_on='" . new DateTimeValue() . "', updated_by_id='" . $this->logged_user->getId() . "' where id='" . $this->active_task->getId() . "'";
				db_execute($query);
			} else {
				$this->active_task->setPriority($this->request->post('priority'));
				$save = $this->active_task->save();
			}
        }

    function print_tasks() {
        $open_tasks = $this->active_task_parent->getOpenTasks();
        $this->smarty->assign(array(
            '_open_tasks' =>  $open_tasks,
            //BOF:mod 20120716
            'obj' => $this->active_task_parent,
            //EOF:mod 20120716
        ));
    } // print_tasks

	//BOF:mod 20120904
	function move(){
		$new_parent_id = $this->request->post('new_parent_id');
		$new_parent_type = $this->request->post('new_parent_type');
		$new_parent_url = '';
		$move_mode = false;
		if (!empty($new_parent_id) && !empty($new_parent_type)){
			$move_mode = true;
			$parent_obj = $sql_part = null;
			switch($new_parent_type){
				case 'milestone':
					$parent_obj = new MileStone($new_parent_id);
					break;
				case 'ticket':
					$parent_obj = new Ticket($new_parent_id);
					break;
				case 'page':
					$parent_obj = new Page($new_parent_id);
					break;
			}
			if ($parent_obj){
				$body = $this->active_task->getBody();
				$doc = new DOMDocument();
				if ($doc->loadHTML($body)){
					$anc_tags = $doc->getElementsByTagName('a');
					$new_parent_url = '';
					foreach($anc_tags as $anc){
						if ($anc->nodeValue == 'View Task in Full'){
							$href = $anc->getAttribute('href');
							$fragment = substr($href, strpos($href, '#'));
							$anc->setAttribute('href', $parent_obj->getViewUrl() . $fragment);
							break;
						}
					}
					if (!empty($fragment)){
						$body_tag = $doc->getElementsByTagName('body');
						$body = $doc->saveXML($body_tag->item(0)->firstChild);
						$comment_id = str_replace('#comment', '', $fragment);
					}
				}
			
				$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
				mysql_select_db(DB_NAME);
				$query = "update 
							healingcrystals_project_objects 
						  set 
							project_id='" . $parent_obj->getProjectId() . "', 
							milestone_id='" . $parent_obj->getMilestoneId() . "', 
							parent_id='" . $parent_obj->getId() . "', 
							parent_type='" . $parent_obj->getType() . "', 
							body = '" . mysql_real_escape_string($body) . "' 
						  where	id='" . $this->active_task->getId() . "'";
				mysql_query($query, $link);
				
				if (!empty($comment_id)){
						$comment_query = "update 
							healingcrystals_project_objects 
						  set 
							project_id='" . $parent_obj->getProjectId() . "', 
							milestone_id='" . $parent_obj->getMilestoneId() . "', 
							parent_id='" . $parent_obj->getId() . "', 
							parent_type='" . $parent_obj->getType() . "', 
							position=null
						  where	id='" . $comment_id . "'";
						  mysql_query($comment_query, $link);
				}
				
				mysql_close($link);
				$new_parent_url = $parent_obj->getViewUrl() . '#task' . $this->active_task->getId();
				
				$cache_id = TABLE_PREFIX . 'project_objects_id_' . $this->active_task->getId();
				$cache_obj = cache_get($cache_id);
				if ($cache_obj) cache_remove($cache_id);
			}
		} else {
			$listing = array();
			switch($this->active_task_parent->getType()){
				case 'Milestone':
					//$listing = Milestones::findByProject($this->active_project, $this->logged_user);
					$listing = Milestones::findActiveByProject_custom($this->active_project);
					break;
				case 'Ticket':
					$listing = Tickets::findOpenByProjectByNameSort($this->active_project, STATE_VISIBLE, $this->logged_user->getVisibility());
					break;
				case 'Page':
					$categories = Categories::findByModuleSection($this->active_project, 'pages', 'pages');
					$listing = Pages::findByCategories($categories, STATE_VISIBLE, $this->logged_user->getVisibility());
					/*foreach($categories as $category){
						$listing = array_merge($listing, Pages::findByCategory($category, STATE_VISIBLE, $this->logged_user->getVisibility()));
					}*/
					break;
			}
			
			$this->smarty->assign(array(
				'teams' => Projects::findNamesByUser($this->logged_user), 
				'listing' => $listing, 
				'task_parent_id' => $this->active_task_parent->getId(), 
			));
		}
		$this->smarty->assign('new_parent_url', $new_parent_url);
		$this->smarty->assign('move_mode', $move_mode);
	}
	//EOF:mod 20120904
	
	//BOF:mod 20120911
	function quickreminder(){
		if($this->active_task->isNew()) {
			$this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
		}
		if(empty($this->active_task_parent)) {
			$this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
		}
		if(!$this->active_task->canEdit($this->logged_user) && $this->active_task->getProjectId()!=TASK_LIST_PROJECT_ID) {
			$this->httpError(HTTP_ERR_FORBIDDEN, null, true, $this->request->isApiCall());
		}
		
		$task_data = $this->request->post('taskquick');
		
		if(!is_array($task_data)) {
			$task_data = array(
				'body' 		=> $this->active_task->getBody(),
				'priority' 	=> $this->active_task->getPriority(),
				'due_on' 	=> $this->active_task->getDueOn(),
				'assignees' => Assignments::findAssignmentDataByObject($this->active_task),
			);

			$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
			mysql_select_db(DB_NAME);
			$query = "select * from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
			$result = mysql_query($query, $link);
			if (mysql_num_rows($result)){
				$info = mysql_fetch_assoc($result);
                //$task_data['recurring_flag'] = '1';
                //$task_data['recurring_period'] = $info['recurring_period'];
                //if (empty($task_data['recurring_period'])){
                //   $task_data['recurring_flag'] = '0';
                //}
                //$task_data['recurring_period_type'] = $info['recurring_period_type'];
                //$task_data['recurring_period_condition'] = $info['recurring_period_condition'];
                //$task_data['recurring_end_date'] = empty($info['recurring_end_date']) || $info['recurring_end_date']=='0000-00-00' ? '' : dateval($info['recurring_end_date']);
                if (!empty($info['reminder_date']) && $info['reminder_date']!='0000-00-00 00:00:00'){
                    list($date, $time) = explode(' ', $info['reminder_date']);
                    list($h, $m, $s) = explode(':', $time);
                    $date = dateval($date);
                }
                $task_data['reminder'] = $date;
                $task_data['remindermeridian'] = $h>=12 ? 'PM' : 'AM';
                $task_data['reminderhours'] = $h>12 ? $h-12 : ($h!=0 ? $h : '12');
                $task_data['reminderminutes'] = $m;
				$task_data['auto_email_status'] = $info['auto_email_status'];
			} else {
                //$task_data['recurring_flag'] = '0';
                //$task_data['recurring_period'] = '';
                //$task_data['recurring_period_type'] = 'D';
                //$task_data['recurring_period_condition'] = 'after_due_date';
                //$task_data['recurring_end_date'] = '';
                $task_data['reminder'] = '';
                $task_data['reminderhours'] = '';
                $task_data['reminderminutes'] = '';
                $task_data['remindermeridian'] = '';
				$task_data['auto_email_status'] = '';
			}
			mysql_close($link);
		}

		$this->smarty->assign('task_data', $task_data);
		$refresh_task_content_mode = false;
		if($this->request->isSubmitted()) {
			if(!isset($task_data['assignees'])) {
				$task_data['assignees'] = array(array(), 0);
			}

			db_begin_work();
			$old_name = $this->active_task->getBody();

			$this->active_task->setAttributes($task_data);
			$save = $this->active_task->save();
			if($save && !is_error($save)) {
				db_commit();
                $reminder			= dateval($task_data['reminder']);
                $reminderhours		= (int)$task_data['reminderhours'];
                $reminderminutes	= (int)$task_data['reminderminutes'];
                $remindermeridian	= $task_data['remindermeridian'];
                if (!empty($reminder)){
                    if (!empty($remindermeridian) && $remindermeridian=='PM' && $reminderhours<12){
                        $reminderhours += 12;
                    } elseif (!empty($remindermeridian) && $remindermeridian=='AM' && $reminderhours==12){
						$reminderhours = 0;
					}
                    $reminder		= $reminder . ' ' . $reminderhours . ':' . $reminderminutes;
                }
                $email_flag			= empty($task_data['email_flag']) ? '0' : '1';

                $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
                mysql_select_db(DB_NAME);
				$query = "select * from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
                $result = mysql_query($query, $link);
				if (mysql_num_rows($result)){
					$query01 = "update healingcrystals_project_object_misc set reminder_date='" . $reminder . "', auto_email_status='" . $email_flag . "', last_modified=now() where object_id='" . $this->active_task->getId() . "'";
					mysql_query($query01, $link);
				} else {
                        $query01 = "insert into healingcrystals_project_object_misc
                                    (object_id,
                                     reminder_date,
                                     recurring_period,
                                     recurring_period_type,
                                     recurring_period_condition,
                                     recurring_end_date,
                                     date_added,
                                     auto_email_status) values
                                     ('" . $this->active_task->getId() . "',
                                      '" . $reminder . "',
                                      null,
                                      null,
                                      null,
                                      null,
                                      now(),
                                      '" . $email_flag . "')";
                            mysql_query($query01, $link);
				}
                mysql_close($link);

				/*if($this->request->isApiCall()) {
					$this->serveData($this->active_task, 'task');
				} else {
					flash_success('Task ":name" has been updated', array('name' => str_excerpt(strip_tags($old_name), 80, '...')), false, false);
					$this->redirectToUrl($this->active_task_parent->getViewUrl() . '#task' . $this->active_task->getId());
				}*/
				$refresh_task_content_mode = true;
			} else {
				db_rollback();

				if($this->request->isApiCall()) {
					$this->serveData($save);
				} else {
					$this->smarty->assign('errors', $save);
				}
			}
		} else {
			if($this->request->isApiCall()) {
				$this->httpError(HTTP_ERR_BAD_REQUEST, null, true, true);
			}
		}
		$this->smarty->assign('refresh_task_content_mode', $refresh_task_content_mode);
	}
	
	//BOF:mod 20121126
	function snoozereminder(){
		$snooze = $this->request->post('snooze');
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		if ($snooze){
			/*mysql_query("update healingcrystals_project_object_misc set snooze_duration='" . (int)$snooze['duration'] . "', snooze_unit='" . $snooze['unit'] . "' where object_id='" . $this->active_task->getId() . "'");
			$query = "select reminder_date from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'";
			$result = mysql_query($query);
			if (mysql_num_rows($result)){
				$info = mysql_fetch_assoc($result);
				if (!empty($info['reminder_date']) && $info['reminder_date']!='0000-00-00 00:00:00'){
					$base_date = strtotime($info['reminder_date']);
					$modified_date = '';
					if ($snooze['duration']>0){
						switch($snooze['unit']){
							case 'H':
								$modified_date = strtotime('+ ' . $snooze['duration'] . ' hours', $base_date);
								break;
							case 'D':
								$modified_date = strtotime('+ ' . $snooze['duration'] . ' days', $base_date);
								break;
							case 'W':
								$modified_date = strtotime('+ ' . $snooze['duration'] . ' weeks', $base_date);
								break;
							case 'M':
								$modified_date = strtotime('+ ' . $snooze['duration'] . ' months', $base_date);
								break;
						}
						if (!empty($modified_date)){
							mysql_query("update healingcrystals_project_object_misc set reminder_date='" . date('Y-m-d H:i:s', $modified_date) . "' where object_id='" . $this->active_task->getId() . "'");
						}
					}
				}
			}*/
			//$query = mysql_query("select now() as cur_time");
			//$info = mysql_fetch_assoc($query);
			//$base_date = strtotime($info['cur_time']);
			$base_date = new DateTimeValue();
			$base_date->advance(get_system_gmt_offset($this->logged_user), true);
			//$base_date->advance(get_user_gmt_offset($this->logged_user), true);
			$modified_date = '';
			if ($snooze['duration']>0){
				switch($snooze['unit']){
					case 'I':
						$modified_date = strtotime('+ ' . $snooze['duration'] . ' minutes', $base_date->getTimestamp());
						break;
					case 'H':
						$modified_date = strtotime('+ ' . $snooze['duration'] . ' hours', $base_date->getTimestamp());
						break;
					case 'D':
						$modified_date = strtotime('+ ' . $snooze['duration'] . ' days', $base_date->getTimestamp());
						break;
					case 'W':
						$modified_date = strtotime('+ ' . $snooze['duration'] . ' weeks', $base_date->getTimestamp());
						break;
					case 'M':
						$modified_date = strtotime('+ ' . $snooze['duration'] . ' months', $base_date->getTimestamp());
						break;
				}
				if (!empty($modified_date)){
					$sql = "select object_id from " . TABLE_PREFIX ."project_object_misc where object_id='" . $this->active_task->getId() . "'";
					$result = mysql_query($sql);
					if(mysql_num_rows($result)){
						mysql_query("update healingcrystals_project_object_misc set last_modified=now(),  snooze_datetime='" . date('Y-m-d H:i:s', $modified_date) . "' where object_id='" . $this->active_task->getId() . "'");
					} else {
						mysql_query("insert into " . TABLE_PREFIX . "project_object_misc (object_id, date_added, snooze_datetime) values ('" . $this->active_task->getId() . "', now(), '" . date('Y-m-d H:i:s', $modified_date) . "')");
					}
				}
			}
			$this->redirectToUrl($this->active_task->getViewUrl() . '#task' . $this->active_task->getId());
		} else {
			/*$snooze = array('duration' => '', 'unit' => '');
			$result = mysql_query("select snooze_duration, snooze_unit from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'");
			if (mysql_num_rows($result)){
				$info = mysql_fetch_assoc($result);
				if (!empty($info['snooze_duration'])) $snooze['duration'] = $info['snooze_duration'];
				if (!empty($info['snooze_unit'])) $snooze['unit'] = $info['snooze_unit'];
			}*/
			$snooze_date = 'Not Set';
			$result = mysql_query("select snooze_datetime from healingcrystals_project_object_misc where object_id='" . $this->active_task->getId() . "'");
			if (mysql_num_rows($result)){
				$info = mysql_fetch_assoc($result);
				if (!empty($info['snooze_datetime'])){
					$snooze_date = date('m-d-Y H:i', strtotime($info['snooze_datetime']));
				}
			}
		}
		mysql_close($link);
		$this->smarty->assign(array(
			'form_action_url' => assemble_url('project_task_snoozereminder', array('project_id' => $this->active_project->getId(), 'task_id' => $this->active_task->getId())), 
			//'snooze_duration' => $snooze['duration'], 
			//'snooze_unit' => $snooze['unit'], 
			'snooze_date' => $snooze_date, 
		));
	}
	//EOF:mod 20121126
  } // TasksController

?>