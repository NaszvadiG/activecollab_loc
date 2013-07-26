<?php
/*
 * 18 April 2012 (SA) Ticket #791: modify action request and fyi notification checkboxes to load faster
 */
  // We need projects controller
  use_controller('project', SYSTEM_MODULE);

  /**
   * Comments controller
   *
   * @package activeCollab.modules.resources
   * @subpackage controllers
   */
  class CommentsController extends ProjectController {

    /**
     * Active module
     *
     * @var string
     */
    var $active_module = RESOURCES_MODULE;

    /**
     * Active comment
     *
     * @var Comment
     */
    var $active_comment;

    /**
     * API actions
     *
     * @var array
     */
    var $api_actions = array('view', 'add', 'edit');

    /**
     * Construct comments controller
     *
     * @param Request $request
     * @return CommentsController
     */
    function __construct($request) {
      parent::__construct($request);

      $comment_id = $this->request->getId('comment_id');
      if($comment_id > 0) {
        $this->active_comment = ProjectObjects::findById($comment_id);
      } // if

      if(!instance_of($this->active_comment, 'Comment')) {
        $this->active_comment = new Comment();
      } // if

      $this->smarty->assign(array(
        'active_comment' => $this->active_comment,
        'page_tab' => $this->active_comment->getProjectTab()
      ));
    } // __construct

    /**
     * View single comment
     *
     * @param void
     * @return null
     */
    function view() {
      if($this->active_comment->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
      } // if

      if(!$this->active_comment->canView($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN, null, true, $this->request->isApiCall());
      } // if

      if($this->request->isApiCall()) {
        $this->serveData($this->active_comment, 'comment');
      } else {
        $this->redirectToUrl($this->active_comment->getRealViewUrl());
      } // if
    } // view

    /**
     * Create new comment
     *
     * @param void
     * @return null
     */
    function add() {
        $this->wireframe->print_button = false;

        $active_object = ProjectObjects::findById($this->request->getId('parent_id'));
        if(!instance_of($active_object, 'ProjectObject')) {
            $this->httpError(HTTP_ERR_NOT_FOUND, null, true, $this->request->isApiCall());
        } // if

        if(!$active_object->canComment($this->logged_user)) {
            $this->httpError(HTTP_ERR_FORBIDDEN, null, true, $this->request->isApiCall());
        } // if

        $active_object->prepareProjectSectionBreadcrumb($this->wireframe);
        $this->wireframe->addBreadCrumb($active_object->getName(), $active_object->getViewUrl());

        if(!$active_object->canComment($this->logged_user)) {
            if($this->request->isApiCall()) {
                $this->httpError(HTTP_ERR_FORBIDDEN, null, true, true);
            } else {
                flash_error('Parent object not found');
                $this->redirectToReferer($this->active_project->getOverviewUrl());
            } // if
        } // if

        $comment_data = $this->request->post('comment');

        $this->smarty->assign(array(
            'active_object'   => $active_object,
            'page_tab'        => $active_object->getProjectTab(),
            'comment_data'    => $comment_data,
            'recent_comments' => Comments::findRecentObject($active_object, 5, STATE_VISIBLE, $this->logged_user->getVisibility()),
        ));

        if($this->request->isSubmitted()) {
            db_begin_work();

            $complete_parent_object = (boolean) array_var($comment_data, 'complete_parent_object');

            $this->active_comment = new Comment();
            $this->active_comment->log_activities = false;

            if($complete_parent_object) {
                $this->active_comment->send_notification = false;
            } // if

            attach_from_files($this->active_comment, $this->logged_user);

            $this->active_comment->setAttributes($comment_data);
            $this->active_comment->setParent($active_object);
            $this->active_comment->setProjectId($this->active_project->getId());
            $this->active_comment->setState(STATE_VISIBLE);
            $this->active_comment->setVisibility($active_object->getVisibility());

            if(trim($this->active_comment->getCreatedByName()) == '' || trim($this->active_comment->getCreatedByEmail()) == '') {
                $this->active_comment->setCreatedBy($this->logged_user);
            } // if

            $save = $this->active_comment->save();
            if($save && !is_error($save)) {
				$active_object->subscribe($this->logged_user);

                $activity = new NewCommentActivityLog();
                $activity->log($this->active_comment, $this->logged_user);

                if($complete_parent_object && $active_object->canChangeCompleteStatus($this->logged_user)) {
                    $active_object->complete($this->logged_user, $this->active_comment->getFormattedBody(true));
                } // if

                db_commit();
                $this->active_comment->ready();

                //BOF: mod
                $subscribers_to_notify = array_var($comment_data, 'subscribers_to_notify');
                $action_request_user_id = array_var($comment_data, 'action_request');
                //$priority_actionrequest = array_var($comment_data, 'priority_actionrequest');
                //BOF:mod 20110517
                if ($complete_parent_object){
                    $subscribers_to_notify = array();
                    $action_request_user_id = array();
                }
				//EOF:mod 20110517
                //BOF:mod 20110719
                /*
                //EOF:mod 20110719
                if (!empty($action_request_user_id)){
                    $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
                    mysql_select_db(DB_NAME);
                    foreach ($action_request_user_id as $id){
                        $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $id . "'";
						$result = mysql_query($query);
						if (mysql_num_rows($result)){
                            $query = "update healingcrystals_assignments_action_request set is_action_request='1' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $id . "'";
                            mysql_query($query);
						} else {
                            $query = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, selected_by_user_id, comment_id, date_added) values ('" . $id . "', '1', '0', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now())";
                            mysql_query($query);
                        }
                    }

                    foreach($priority_actionrequest as $val){
                        $temp = explode('_', $val);
						list($temp_user_id, $priority) = $temp;
						if (in_array($temp_user_id, $action_request_user_id)){
                            $query = "update healingcrystals_assignments_action_request set priority_actionrequest='" . $priority . "' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $temp_user_id . "'";
                            mysql_query($query);
						}
                    }
                    mysql_close($link);
                }
                //BOF:mod 20110719
                */
                //EOF:mod 20110719

                //BOF:mod 20110719
                //$action_request_user_id = array();
                //if (!empty($priority_actionrequest)){
				$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
				mysql_select_db(DB_NAME);
                if (!empty($action_request_user_id)){
                    //foreach($priority_actionrequest as $val){
                    foreach($action_request_user_id as $val){
                        //$temp = explode('_', $val);
                        //list($temp_user_id, $priority) = $temp;
                        $temp_user_id = $val;
                        $priority = '0';
                        //if ((int)$priority>-10){
                            $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $temp_user_id . "'";
                            $result = mysql_query($query, $link);
                            if (mysql_num_rows($result)){
                                $query1 = "update healingcrystals_assignments_action_request set is_action_request='1', priority_actionrequest='" . $priority . "' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $temp_user_id . "'";
                                mysql_query($query1, $link);
                            } else {
                                $query1 = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, selected_by_user_id, comment_id, date_added, priority_actionrequest) values ('" . $temp_user_id . "', '1', '0', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now(), '" . $priority . "')";
                                mysql_query($query1, $link);
                            }
                            //$action_request_user_id[] = $temp_user_id;
							$task = new Task();
							$task->setProjectId(TASK_LIST_PROJECT_ID);
							$task->setParentId(Page::getTaskPageIdForUser($val));

							$task->setParentType('Page');
							$task->setCreatedBy($this->logged_user);
							$task->setVisibility(VISIBILITY_NORMAL);
							$task->setState(STATE_VISIBLE);

							$task_body = '';
							$parent = $this->active_comment->getParent();
							$url = $parent->getViewUrl() . '#comment' . $this->active_comment->getId();
							$comment_body = $this->active_comment->getBody();
							$comment_body = strip_tags($comment_body);
							//$task_body = substr($comment_body, 0, 10) . '.. <br/><a href="' . $url . '">View Task in Full</a>';
							if (strlen($comment_body)>525){
								$task_body .= substr($comment_body, 0, 525) . '..';
							} else {
								$task_body .= $comment_body;
							}
							$task_body .= '<br/><a href="' . $url . '">View Task in Full</a>';
							$attachments = $this->active_comment->getAttachments();
							if (is_foreachable($attachments)) {
								$task_body .= '<br/>Attachments:<br/>';
								foreach ($attachments as $attachment) {
									$task_body .= '<a href="' . $attachment->getViewUrl() . '">' . $attachment->getName() . '</a><br/>';
								}
							}

							$task->setBody($task_body);
							$savetask = $task->save();
							if ($savetask && !is_error($savetask)) {
								$task->ready();
								mysql_query("insert into actionrequests_to_tasklist (comment_id, user_id, type, object_id) values ('" . $this->active_comment->getId() . "', '" . $temp_user_id . "', 'Task', '" . $task->getId() . "')");
							}
                        //}
                    }
                }
                //EOF:mod 20110719

                if (!empty($subscribers_to_notify)){
                    //BOF:task_1260
                    /*
                    //EOF:task_1260
                    mysql_query("update healingcrystals_assignments_action_request set is_fyi='0' where object_id='" . $active_object->getId() . "'");
					if (!empty($subscribers_to_notify)){
						$temp = $subscribers_to_notify;
						foreach($temp as $id){
							$query = "select * from healingcrystals_assignments_action_request where object_id='" . $active_object->getId() . "' and user_id='" . $id . "'";
							$result = mysql_query($query, $link);
							if (mysql_num_rows($result)){
								mysql_query("update healingcrystals_assignments_action_request set is_fyi='1' where user_id='" . $id . "' and object_id='" . $active_object->getId() . "'");
							} else {
								mysql_query("insert into healingcrystals_assignments_action_request (user_id, object_id, is_fyi) values ('" . $id . "', '" . $active_object->getId() . "', '1')");
							}
						}
					}
					mysql_query("delete from healingcrystals_assignments_action_request where object_id='" . $active_object->getId() . "' and is_action_request='0' and is_fyi='0'");
					//BOF:task_1260
					*/
					foreach ($subscribers_to_notify as $id){
                        $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $id . "'";
                        $result = mysql_query($query);
                        if (mysql_num_rows($result)){
							$query = "update healingcrystals_assignments_action_request set is_fyi='1' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $id . "'";
							mysql_query($query);
                        } else {
							$query = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, selected_by_user_id, comment_id, date_added) values ('" . $id . "', '0', '1', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now())";
							mysql_query($query);
                        }
					}
					//EOF:task_1260
				}

				//shawn wants to fire emails for only action request users and not for FYI users
				// for this, $subscribers_to_notify is set to $action_request_user_id, which will
				// take care of any assignments that were made above the code : 22-MAR-2011
				//BOF:mod 20110623
				$fyi_users = $subscribers_to_notify;
				$fyi_to = '';
				//EOF:mod 20110623
				$subscribers_to_notify = $action_request_user_id;
				//BOF:mod
				$email_to_user_ids = array_var($comment_data, 'email');
				$emailed_to = '';

				foreach($email_to_user_ids as $user_id){
					$temp_user = new User($user_id);
          //BOF:mod 20130429
          /*
          //EOF:mod 20130429
					$emailed_to .= $temp_user->getName() . ', ';
          //BOF:mod 20130429
          */
          //EOF:mod 20130429

					$query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $user_id . "'";
					$result = mysql_query($query);
                    if (mysql_num_rows($result)){
						$query = "update healingcrystals_assignments_action_request set marked_for_email='1' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $user_id . "'";
						mysql_query($query);
					} else {
						$query = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, marked_for_email, selected_by_user_id, comment_id, date_added) values ('" . $user_id . "', '0', '0', '1', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now())";
						mysql_query($query);
					}
				}
				reset($email_to_user_ids);
				//EOF:mod
				if (!empty($subscribers_to_notify)){
					//$subscribers_to_notify = implode(',', $subscribers_to_notify);
					//mysql_query("insert into healingcrystals_testing (query, fired_at) values ('" . $subscribers_to_notify . "', now())");
                    $notified_to = '';
					//$subscribers = explode(',', $subscribers_to_notify);
					$subscribers = $subscribers_to_notify;
					$all_subscribers = $active_object->getSubscribers();
					$excluded = array();
					$included = array();
					//$excluded_temp = array();
					//$included_temp = array();
					$subscribers_name = '';
					foreach($all_subscribers as $reg_subscriber){
						$subscribers_name .= $reg_subscriber->getName() . "<br/>";
                        $subscriber_excluded = true;
                        //if ($this->logged_user->getId()!=$reg_subscriber->getId()){
                        foreach($subscribers as $subscriber_id){
							$subscriber_id = trim($subscriber_id);
                            if ($reg_subscriber->getId()==$subscriber_id){
								$included[] = $reg_subscriber;
                //BOF:mod 20130429
                /*
                //EOF:mod 20130429
								$notified_to .= $reg_subscriber->getName() . ', ';
                //BOF:mod 20130429
                */
                //EOF:mod 20130429
								//$included_temp[] = $reg_subscriber->getId();
								$subscriber_excluded = false;
								//$subscribers_name .= $reg_subscriber->getName() . "<br/>";
								break;
							}
						}
						//BOF:mod 20110623
						foreach($fyi_users as $fyi_user_id){
							$fyi_user_id = trim($fyi_user_id);
              if ($reg_subscriber->getId()==$fyi_user_id){
                //BOF:mod 20130429
                /*
                //EOF:mod 20130429
								$fyi_to .= $reg_subscriber->getName() . ', ';
                //BOF:mod 20130429
                */
                //EOF:mod 20130429
								break;
              }
						}
						//EOF:mod 20110623
						//}
                        if ($subscriber_excluded){
							$excluded[] = $reg_subscriber->getId();
							//$excluded_temp[] = $reg_subscriber->getId();
						}
					}
					//$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
					//mysql_select_db(DB_NAME);
					//mysql_query("insert into healingcrystals_testing (query, fired_at) values ('" . implode('|', $included_temp) . ' = ' . implode('|', $excluded_temp) . "', now())");
					//mysql_close($link);
					//BOF:mod 20110517
                    //if (count($included)){
                    if (!$complete_parent_object && count($included)){
						//EOF:mod 20110517
                        //BOF:mod 20110623
                        //$notified_to = '<br/><br/>Notification emailed to: ' . substr($notified_to, 0, -2);
                        //$this->active_comment->setBody($this->active_comment->getBody() . $notified_to . $fyi_to);
                //BOF:mod 20130429
                      /*
                //EOF:mod 20130429
                        if (!empty($notified_to)){
							$notified_to = '<br/><br/>Action Request marked to: ' . substr($notified_to, 0, -2);
                        }
                        if (!empty($fyi_to)){
							$fyi_to = (empty($notified_to) ? '<br/><br/>' : '<br/>') . 'FYI Comment marked to: ' . substr($fyi_to, 0, -2);
                        }
                        if (!empty($emailed_to)){
							$emailed_to = (empty($notified_to) && empty($fyi_to) ? '<br/><br/>' : '<br/>') . 'Email sent to: ' . substr($emailed_to, 0, -2);
                        }
                        $this->active_comment->setBody($this->active_comment->getBody() . $notified_to . $fyi_to . $emailed_to);
                        //EOF:mod 20110623
                        $this->active_comment->save();
                //BOF:mod 20130429
                        */
                //EOF:mod 20130429
                        //BOF:mod 20110720 ticketid246
                        /*
						//EOF:mod 20110720 ticketid246
						$created_by = $this->active_comment->getCreatedBy();
						$parent = $active_object;
						$parent->sendToSubscribers('resources/new_comment', array(
									'comment_body' => $this->active_comment->getFormattedBody(),
									'comment_url' => $this->active_comment->getViewUrl(),
									'created_by_url' => $created_by->getViewUrl(),
									'created_by_name' => $created_by->getDisplayName(),
									'subscribers_name' => "<br/><br/>-- SET NOTIFICATIONS --<br/>" . $subscribers_name . "<br/><br/>",
									'comment_id' => $this->active_comment->getId(),
									), $excluded, $parent);
						//BOF:mod 20110720 ticketid246
						*/
						//EOF:mod 20110720 ticketid246
						/*$created_by = $this->active_comment->getCreatedBy();
						$variables = array('owner_company_name' => get_owner_company(),
								'project_name'       => $this->active_project->getName(),
								'project_url'        => $this->active_project->getOverviewUrl(),
								'object_type'        => $this->active_comment->getVerboseType(),
								'object_name'        => $this->active_comment->getName(),
								'comment_body' => $this->active_comment->getFormattedBody(),
								'comment_url' => $this->active_comment->getViewUrl(),
								'created_by_url' => $created_by->getViewUrl(),
								'created_by_name' => $created_by->getDisplayName(),);
						ApplicationMailer::send($users, 'resources/new_comment', $variables, $this->active_milestone);*/
                    }
                }
                //BOF:mod 20110623
                elseif (!empty($fyi_users)) {
					$all_subscribers = $active_object->getSubscribers();
					foreach($all_subscribers as $reg_subscriber){
						foreach($fyi_users as $fyi_user_id){
							$fyi_user_id = trim($fyi_user_id);
							if ($reg_subscriber->getId()==$fyi_user_id){
								$fyi_to .= $reg_subscriber->getName() . ', ';
                                break;
							}
						}
					}
					/*$fyi_to = '<br/><br/>FYI Comment marked to: ' . substr($fyi_to, 0, -2);
					if (!empty($emailed_to)){
						$emailed_to = (empty($fyi_to) ? '<br/><br/>' : '<br/>') . 'Email sent to: ' . substr($emailed_to, 0, -2);
                    }
					$this->active_comment->setBody($this->active_comment->getBody() . $fyi_to . $emailed_to);
					$this->active_comment->save();*/
				}
                //EOF:20110623
                elseif (!empty($email_to_user_ids)){
					/*$emailed_to = '<br/><br/>Email sent to: ' . substr($emailed_to, 0, -2);
					$this->active_comment->setBody($this->active_comment->getBody() . $emailed_to);
					$this->active_comment->save();*/
				}

                    if (count($email_to_user_ids)){
                        $users = array();
                        foreach($email_to_user_ids as $user_id){
							if ($user_id!=$this->logged_user->getId()){
								$users[] = new User($user_id);
							}
                        }
                        $created_by = $this->active_comment->getCreatedBy();
                        $variables = array('owner_company_name' => get_owner_company(),
                                           'project_name'       => $this->active_project->getName(),
                                           'project_url'        => $this->active_project->getOverviewUrl(),
                                           'object_type'        => $this->active_comment->getVerboseType(),
                                           'object_name'        => $this->active_comment->getName(),
                                           'object_body'        => $this->active_comment->getFormattedBody(),
                                           'object_url'         => $this->active_comment->getViewUrl(),
                                           'comment_body'       => $this->active_comment->getFormattedBody(),
                                           'comment_url'        => $this->active_comment->getViewUrl(),
                                           'created_by_url'     => $created_by->getViewUrl(),
                                           'created_by_name'    => $created_by->getDisplayName(),
                                           'details_body'       => '',
                                           'comment_id'         => $this->active_comment->getId(), );
                        //BOF:mod 20111101
                        /*
                        //EOF:mod 20111101
                        ApplicationMailer::send($users, 'resources/new_comment', $variables, $this->active_milestone);
                        //BOF:mod 20111101
                        */
                        $parent_id = $this->active_comment->getParentId();
                        $parent_type = $this->active_comment->getParentType();
                        $parent_obj = new $parent_type($parent_id);
                        $attachments = null;
                        $object_attachments = $this->active_comment->getAttachments();
                        if ($object_attachments){
                            $attachments = array();
                            foreach($object_attachments as $object_attachment){
                                $attachments[] = array(
                                    'path' => $object_attachment->getFilePath(),
                                    'name' => $object_attachment->getName(),
                                    'mime_type' => $object_attachment->getMimeType(),
                                );
                            }
                        }
                        ApplicationMailer::send($users, 'resources/new_comment', $variables, $parent_obj, $attachments);
                        //EOF:mod 20111101
                    }
					//BOF:mod 20121030
					$modify_comments_sorting = false;
					$reply_to_comment_id = $this->request->post('reply_to_comment_id');
					if (!empty($reply_to_comment_id)){
						$sql_data = array('integer_field_2' => $reply_to_comment_id);
						Comments::update($sql_data, "id='" . $this->active_comment->getId() . "'", TABLE_PREFIX . 'project_objects');
						//$modify_comments_sorting = true;
					}
					//$count = 0;
					/*$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
					mysql_select_db(DB_NAME);
					$sql = "select * from " . TABLE_PREFIX . "project_objects where parent_id='" . $this->active_comment->getParentId() . "' and parent_type='" . $this->active_comment->getParentType() . "' and type='Comment' and (position is null or position='0')";
					$result = mysql_query($sql, $link);
					if (!mysql_num_rows($result) ){
						$sql = "select max(position) as count from " . TABLE_PREFIX . "project_objects where parent_id='" . $this->active_comment->getParentId() . "' and parent_type='" . $this->active_comment->getParentType() . "' and type='Comment'";
						$result = mysql_query($sql, $link);
						$info = mysql_fetch_assoc($result);
						$count = $info['count'];
						$sql_data = array('position' => ++$count);
						Comments::update($sql_data, "id='" . $this->active_comment->getId() . "'", TABLE_PREFIX . 'project_objects');
					} else {
						$modify_comments_sorting = true;
					}
					mysql_close($link);*/
					//if ($modify_comments_sorting) $this->modify_comments_sorting($count);
					//EOF:mod 20121030
                    if($this->request->isApiCall()) {
                        $this->serveData($this->active_comment, 'comment');
                    } else {
                        flash_success('Comment successfully posted');
                        //$this->redirectToUrl($this->active_comment->getRealViewUrl());
						$this->redirectToUrl($this->active_comment->getParent()->getViewUrl());
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
    } // add
	//BOF:mod 20121030
	/*function modify_comments_sorting(&$count = 0, $lookup_id = ''){
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		if (empty($lookup_id) ){
			$query = "select id from " . TABLE_PREFIX . "project_objects where parent_id='" . $this->active_comment->getParentId() . "' and parent_type='" . $this->active_comment->getParentType() . "' and type='Comment' and (integer_field_2 is null or integer_field_2='0') order by created_on asc";
		} else {
			$query = "select id from " . TABLE_PREFIX . "project_objects where parent_id='" . $this->active_comment->getParentId() . "' and parent_type='" . $this->active_comment->getParentType() . "' and type='Comment' and integer_field_2='" . $lookup_id . "' order by created_on asc";
		}
		$result = mysql_query($query, $link);
		while ($entry = mysql_fetch_assoc($result) ){
			$this->modify_comments_sorting($count, $entry['id']);
			$sql_data = array('position' => ++$count);
			Comments::update($sql_data, "id='" . $entry['id'] . "'", TABLE_PREFIX . 'project_objects');
		}
		mysql_close($link);
	}*/
	//EOF:mod 20121030

    /**
     * Update an existing comment
     *
     * @param void
     * @return null
     */
    function edit() {
      $this->wireframe->print_button = false;

      if($this->active_comment->isNew()) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      if(!$this->active_comment->canEdit($this->logged_user)) {
        $this->httpError(HTTP_ERR_FORBIDDEN);
      } // if

      $parent = $this->active_comment->getParent();
      if(!instance_of($parent, 'ProjectObject')) {
        $this->httpError(HTTP_ERR_NOT_FOUND);
      } // if

      $parent->prepareProjectSectionBreadcrumb($this->wireframe);
      $this->wireframe->addBreadCrumb($parent->getName(), $parent->getViewUrl());

      $comment_data = $this->request->post('comment');
      if(!is_array($comment_data)) {
        $comment_data = array(
          'body' => $this->active_comment->getBody(),
        );
      } // if

      $this->smarty->assign('comment_data', $comment_data);
      //BOF:task_1260
      $active_object = ProjectObjects::findById($this->active_comment->getParentId());
      $this->smarty->assign('subscribers', $active_object->getSubscribers());
	  $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  mysql_select_db(DB_NAME);
	  //$query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "'";
	  $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "'";
	  $request = mysql_query($query);
	  $fyi_users = array();
	  $action_request_users = array();
	  while($entry = mysql_fetch_array($request)){
      //BOF:mod 20130429
      /*
      //EOF:mod 20130429
	  	if ($entry['is_action_request']=='1'){
      //BOF:mod 20130429
      */
      if (!empty($entry['is_action_request'])){
      //EOF:mod 20130429
	  		$action_request_users[] = $entry['user_id'];
	  	}
      //BOF:mod 20130429
      /*
      //EOF:mod 20130429
	  	if ($entry['is_fyi']=='1'){
	  		$fyi_users[] = $entry['user_id'];
	  	}
      //BOF:mod 20130429
      */
      //EOF:mod 20130429
	  }
	  $this->smarty->assign('fyi_users', $fyi_users);
	  $this->smarty->assign('action_request_users', $action_request_users);
	  $this->smarty->assign('logged_user', $this->logged_user);
      //EOF:task_1260

      if($this->request->isSubmitted()) {
        $this->active_comment->setAttributes($comment_data);
        $save = $this->active_comment->save();
        if($save && !is_error($save)) {
          //BOF:task_1260
          //$subscribers_to_notify = array_var($comment_data, 'subscribers_to_notify');
          $action_request_user_id = array_var($comment_data, 'action_request');
          //mysql_query("update healingcrystals_assignments_action_request set is_action_request='0', is_fyi='0' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and is_action_request<>'-1' and is_fyi<>'-1'");
          //mysql_query("update healingcrystals_assignments_action_request set is_action_request='0', is_fyi='0' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and is_action_request<>'-1' and is_fyi<>'-1'");
          /*if (!empty($subscribers_to_notify)){
            foreach ($subscribers_to_notify as $id){
                $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $id . "'";
                $result = mysql_query($query);
                if (mysql_num_rows($result)){
                    $query = "update healingcrystals_assignments_action_request set is_fyi='1' where comment_id='" . $this->active_comment->getId() . "' and selected_by_user_id='" . $this->logged_user->getId() . "' and user_id='" . $id . "'";
                    mysql_query($query);
                } else {
                    $query = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, selected_by_user_id, comment_id, date_added) values ('" . $id . "', '0', '1', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now())";
                    mysql_query($query);
                }
            }
          }*/
		  $existing_ar_users = array();
		  $new_ar_users = array();
          if (!empty($action_request_user_id)){
            foreach ($action_request_user_id as $id){
                $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $id . "'";
                $result = mysql_query($query);
                if (mysql_num_rows($result)){
					$info = mysql_fetch_assoc($result);
					if ($info['is_action_request']=='1'){
						$existing_ar_users[] = $id;
					} else {
						$query = "update healingcrystals_assignments_action_request set is_action_request='1' where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $id . "'";
						mysql_query($query);
						$new_ar_users[] = $id;
					}
                } else {
                    $query = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, selected_by_user_id, comment_id, date_added) values ('" . $id . "', '1', '0', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now())";
                    mysql_query($query);
					         $new_ar_users[] = $id;
                }
            }
			     $query = "update healingcrystals_assignments_action_request set is_action_request='0' where comment_id='" . $this->active_comment->getId() . "' and user_id not in (" . implode(', ', $action_request_user_id)  . ")";
			     mysql_query($query);
          } else {
			 $query = "update healingcrystals_assignments_action_request set is_action_request='0' where comment_id='" . $this->active_comment->getId() . "'";
			 mysql_query($query);
          }
		  mysql_query("delete from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and is_action_request='0' and is_fyi='0' and marked_for_email='0'");
		  //EOF:task_1260
		foreach($action_request_users as $id){
			if (!in_array($id, $existing_ar_users)){
				//unassign
				$query = "select object_id from actionrequests_to_tasklist where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $id . "' and type='Task'";
				$result = mysql_query($query);
				if (mysql_num_rows($result)){
					$info = mysql_fetch_assoc($result);
					$task = new Task($info['object_id']);
					$task->delete();
					mysql_query("delete from actionrequests_to_tasklist where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $id . "' and type='Task'");
				}
			}
		}
		foreach($new_ar_users as $id){
			//assign
      //BOF:mod 20130429
      /*
      //EOF:mod 20130429
			$priority = '0';
            $query = "select * from healingcrystals_assignments_action_request where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $id . "'";
			$result = mysql_query($query, $link);
            if (mysql_num_rows($result)){
				$query1 = "update healingcrystals_assignments_action_request set is_action_request='1', priority_actionrequest='" . $priority . "' where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $id . "'";
				mysql_query($query1, $link);
			} else {
				$query1 = "insert into healingcrystals_assignments_action_request (user_id, is_action_request, is_fyi, selected_by_user_id, comment_id, date_added, priority_actionrequest) values ('" . $id . "', '1', '0', '" . $this->logged_user->getId() . "', '" . $this->active_comment->getId() . "', now(), '" . $priority . "')";
				mysql_query($query1, $link);
			}
      //BOF:mod 20130429
      */
      //EOF:mod 20130429
			$task = new Task();
			$task->setProjectId(TASK_LIST_PROJECT_ID);
			$task->setParentId(Page::getTaskPageIdForUser($id));

			$task->setParentType('Page');
			$task->setCreatedBy($this->logged_user);
			$task->setVisibility(VISIBILITY_NORMAL);
			$task->setState(STATE_VISIBLE);

			$task_body = '';
			$parent = $this->active_comment->getParent();
			$url = $parent->getViewUrl() . '#comment' . $this->active_comment->getId();
			$comment_body = $this->active_comment->getBody();
			$comment_body = strip_tags($comment_body);
			if (strlen($comment_body)>525){
				$task_body .= substr($comment_body, 0, 525) . '..';
			} else {
				$task_body .= $comment_body;
			}
			$task_body .= '<br/><a href="' . $url . '">View Task in Full</a>';
			$attachments = $this->active_comment->getAttachments();
			if (is_foreachable($attachments)) {
				$task_body .= '<br/>Attachments:<br/>';
				foreach ($attachments as $attachment) {
					$task_body .= '<a href="' . $attachment->getViewUrl() . '">' . $attachment->getName() . '</a><br/>';
				}
			}

			$task->setBody($task_body);
			$savetask = $task->save();
			if ($savetask && !is_error($savetask)) {
				$task->ready();
				mysql_query("insert into actionrequests_to_tasklist (comment_id, user_id, type, object_id) values ('" . $this->active_comment->getId() . "', '" . $id . "', 'Task', '" . $task->getId() . "')");
			}
		}

          if($this->request->getFormat() == FORMAT_HTML) {
            flash_success('Comment has been updated');
            $this->redirectToUrl($this->active_comment->getRealViewUrl());
          } else {
            $this->serveData($this->active_comment, 'comment');
          } // if
        } else {
          if($this->request->getFormat() == FORMAT_HTML) {
            $this->smarty->assign('errors', $save);
          } else {
            $this->serveData($save);
          } // if
        } // if
      } // if
      //BOF:task_1260
      //mysql_close($link);
      //EOF:task_1260
    } // edit

    /**
     * Print an existing comment
     *
     * @param void
     * @return null
     */
    function print_comment() {
      $this->wireframe->print_button = false;
      $parent = $this->active_comment->getParent();
      $parent->prepareProjectSectionBreadcrumb($this->wireframe);
      $this->wireframe->addBreadCrumb($parent->getName(), $parent->getViewUrl());
      $comment_data = $this->request->post('comment');
      if(!is_array($comment_data)) {
        $comment_data = array(
          'ticketName' => $parent->getName(),
          'userName' => $this->active_comment->getCreatedByName(),
          'date' => $this->active_comment->getCreatedOn(),
		  //BOF:mod 20121101
		  /*
		  //EOF:mod 20121101
		  'body' => $this->active_comment->getBody(),
		  //BOF:mod 20121101
		  */
		  'body' => preg_replace('/\n/', '', $this->active_comment->getBody()),
		  //EOF:mod 20121101
        );
      } // if

      $this->smarty->assign('comment_data', $comment_data);
    } // print

    //BOF:task_1260
	function action_request_completed(){
		$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
		mysql_select_db(DB_NAME);
		$query = "update healingcrystals_assignments_action_request set is_action_request='-1', last_modified=now() where is_action_request='1' and user_id='" . $this->logged_user->getId() . "' and comment_id='" . $this->active_comment->getId() . "'";
		mysql_query($query);

		$query = "select object_id from actionrequests_to_tasklist where comment_id='" . $this->active_comment->getId() . "' and user_id='" . $this->logged_user->getId() . "' and type='Task'";
		$result = mysql_query($query);
		if (mysql_num_rows($result)){
			$info = mysql_fetch_assoc($result);
			$task = new Task($info['object_id']);
			$task->complete($this->logged_user);
			$task->save();
		}

		mysql_close($link);
        //$this->redirectToUrl(assemble_url('goto_home_tab'));
    }

    function fyi_read(){
	  $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  mysql_select_db(DB_NAME);
	  $ts = date('Y-m-d H:i:s');
	  $query = "update healingcrystals_assignments_action_request set is_fyi='R', last_modified='" . $ts . "', fyi_marked_read_on='" . $ts . "' where is_fyi='1' and user_id='" . $this->logged_user->getId() . "' and comment_id='" . $this->active_comment->getId() . "'";
	  mysql_query($query);
	  mysql_close($link);
         // $this->redirectToUrl(assemble_url('goto_home_tab'));
    }
    //EOF:task_1260
  }

?>