<?php
/*{*27 March 2012 Ticket #770: modify Print function for Pages in AC (SA)*}*/
  /**
   * Resources module definition
   *
   * @package activeCollab.modules.resources
   * @subpackage models
   */
  class ResourcesModule extends Module {
    
    /**
     * Plain module name
     *
     * @var string
     */
    var $name = 'resources';
    
    /**
     * Is system module flag
     *
     * @var boolean
     */
    var $is_system = true;
    
    /**
     * Module version
     *
     * @var string
     */
    var $version = '2.0';
    
    // ---------------------------------------------------
    //  Events and Routes
    // ---------------------------------------------------
    
    /**
     * Define module routes
     *
     * @param Router $r
     * @return null
     */
    function defineRoutes(&$router) {
      
      // Assignments
      $router->map('assignments', 'assignments', array('controller' => 'assignments'));
      $router->map('assignments_filter_add', 'assignments/add', array('controller' => 'assignment_filters', 'action' => 'add'));
      $router->map('assignments_filter', 'assignments/:filter_id', array('controller' => 'assignments'), array('filter_id' => '\d+'));
      $router->map('assignments_filter_edit', 'assignments/:filter_id/edit', array('controller' => 'assignment_filters', 'action' => 'edit'), array('filter_id' => '\d+'));
      $router->map('assignments_filter_delete', 'assignments/:filter_id/delete', array('controller' => 'assignment_filters', 'action' => 'delete'), array('filter_id' => '\d+'));
      $router->map('assignments_filter_rss', 'assignments/:filter_id/rss', array('controller' => 'assignment_filters', 'action' => 'rss'), array('filter_id' => '\d+'));
      $router->map('assignments_filter_partial_generator', 'assignments/partial-generator', array('controller' => 'assignment_filters', 'action' => 'partial_generator'));
      //BOF: mod
      $router->map('responsible_status_popup', 'projects/:project_id/tickets/:ticket_id/responsible-status', array('controller' => 'assignments', 'action' => 'set_responsible_status'), array('project_id' => '\d+', 'ticket_id' => '\d+'));
      //EOF: mod
      
      // Comments
      $router->map('project_comments', 'projects/:project_id/comments', array('controller' => 'comments', 'action' => 'index'), array('project_id' => '\d+'));
      $router->map('project_comments_add', 'projects/:project_id/comments/add', array('controller' => 'comments', 'action' => 'add'), array('project_id' => '\d+'));
      $router->map('project_comment', 'projects/:project_id/comments/:comment_id', array('controller' => 'comments', 'action' => 'view'), array('project_id' => '\d+', 'comment_id' => '\d+'));
      $router->map('project_comment_edit', 'projects/:project_id/comments/:comment_id/edit', array('controller' => 'comments', 'action' => 'edit'), array('project_id' => '\d+', 'comment_id' => '\d+'));
      //BOF:task_1260
      $router->map('project_comment_action_request_completed', 'projects/:project_id/comments/:comment_id/actionrequestcompleted', array('controller' => 'comments', 'action' => 'action_request_completed'), array('project_id' => '\d+', 'comment_id' => '\d+'));
      $router->map('project_comment_fyi_read', 'projects/:project_id/comments/:comment_id/fyiread', array('controller' => 'comments', 'action' => 'fyi_read'), array('project_id' => '\d+', 'comment_id' => '\d+'));
      
      //EOF:task_1260
      //BOF:mod 20120217
      $router->map('project_comment_print', 'projects/:project_id/comments/:comment_id/print_comment', array('controller' => 'comments', 'action' => 'print_comment'), array('project_id' => '\d+', 'comment_id' => '\d+'));
      //EOF:mod 20120217
      //
      // Reminders
      $router->map('reminders', '/reminders', array('controller' => 'reminders', 'action' => 'index'));
      $router->map('reminders_add', '/reminders/add', array('controller' => 'reminders', 'action' => 'add'));
      $router->map('reminder_view', 'reminders/:reminder_id', array('controller' => 'reminders', 'action' => 'view'), array('reminder_id' => '\d+'));
      $router->map('reminder_dismiss', 'reminders/:reminder_id/dismiss', array('controller' => 'reminders', 'action' => 'dismiss'), array('reminder_id' => '\d+'));
      
      // Categories
      $router->map('project_categories', 'projects/:project_id/:controller/categories', array('action' => 'categories'), array('project_id' => '\d+'));
      $router->map('project_category', 'projects/:project_id/:controller/categories/:category_id', array('action' => 'view_category'), array('project_id' => '\d+', 'category_id' => '\d+'));
      $router->map('project_category_add', 'projects/:project_id/:controller/categories/add', array('action' => 'add_category'), array('project_id' => '\d+'));
      $router->map('project_category_quick_add', 'projects/:project_id/:controller/categories/quick-add', array('action' => 'quick_add_category'), array('project_id' => '\d+'));
      $router->map('project_category_edit', 'projects/:project_id/:controller/categories/:category_id/edit', array('action' => 'edit_category'), array('project_id' => '\d+', 'category_id' => '\d+'));
      $router->map('project_category_delete', 'projects/:project_id/:controller/categories/:category_id/delete', array('action' => 'delete_category'), array('project_id' => '\d+', 'category_id' => '\d+'));
      
      // Tasks
      $router->map('project_tasks_add', 'projects/:project_id/tasks/add', array('controller' => 'tasks', 'action' => 'add'), array('project_id' => '\d+'));
      $router->map('project_tasks_reorder', 'projects/:project_id/tasks/reorder', array('controller' => 'tasks', 'action' => 'reorder'), array('project_id' => '\d+'));
      $router->map('project_tasks_list_completed', 'projects/:project_id/objects/:parent_id/list-completed-tasks', array('controller' => 'tasks', 'action' => 'list_completed'), array('project_id' => '\d+','parent_id' => '\d+'));
      $router->map('project_task', 'projects/:project_id/tasks/:task_id', array('controller' => 'tasks', 'action' => 'view'), array('project_id' => '\d+', 'task_id' => '\d+'));
      $router->map('project_task_edit', 'projects/:project_id/tasks/:task_id/edit', array('controller' => 'tasks', 'action' => 'edit'), array('project_id' => '\d+', 'task_id' => '\d+'));
      $router->map('project_task_complete', 'projects/:project_id/tasks/:task_id/complete', array('controller' => 'tasks', 'action' => 'complete'), array('project_id' => '\d+', 'task_id' => '\d+'));
      $router->map('project_task_open', 'projects/:project_id/tasks/:task_id/open', array('controller' => 'tasks', 'action' => 'open'), array('project_id' => '\d+', 'task_id' => '\d+'));
      //BOF:mod
      $router->map('project_task_ajax_edit', 'projects/:project_id/tasks/:task_id/ajaxedit', array('controller' => 'tasks', 'action' => 'ajaxedit'), array('project_id' => '\d+', 'task_id' => '\d+'));
      $router->map('project_task_change_priority', 'projects/:project_id/tasks/:task_id/change_priority_tasks', array('controller' => 'tasks', 'action' => 'change_priority_tasks'), array('project_id' => '\d+', 'task_id' => '\d+'));
      $router->map('project_tasks_print', 'projects/:project_id/objects/:parent_id/print_tasks', array('controller' => 'tasks', 'action' => 'print_tasks'), array('project_id' => '\d+', 'parent_id' => '\d+'));
	  $router->map('project_task_snooze', 'projects/:project_id/tasks/:task_id/snooze', array('controller' => 'tasks', 'action' => 'snooze'), array('project_id' => '\d+', 'task_id' => '\d+'));
      //EOF:mod
	  //BOF:mod 20120904
	  $router->map('project_task_move', 'projects/:project_id/tasks/:task_id/move', array('controller' => 'tasks', 'action' => 'move'), array('project_id' => '\d+', 'task_id' => '\d+'));
	  //EOF:mod 20120904
	  //BOF:mod 20120911
	  $router->map('project_task_quickreminder', 'projects/:project_id/tasks/:task_id/quickreminder', array('controller' => 'tasks', 'action' => 'quickreminder'), array('project_id' => '\d+', 'task_id' => '\d+'));
	  //EOF:mod 20120911
	  //BOF:mod 20121126
	  $router->map('project_task_snoozereminder', 'projects/:project_id/tasks/:task_id/snoozereminder', array('controller' => 'tasks', 'action' => 'snoozereminder'), array('project_id' => '\d+', 'task_id' => '\d+'));
	  //EOF:mod 20121126
      
      // Tags
      $router->map('project_tags', 'projects/:project_id/tags', array('controller' => 'tags', 'action' => 'index'), array('project_id' => '\d+'));
      $router->map('project_tag', 'projects/:project_id/tags/:tag', array('controller' => 'tags', 'action' => 'view'), array('project_id' => '\d+', 'tag' => '.*'));
      
      // Subscriptions
      $router->map('project_object_subscriptions', 'projects/:project_id/objects/:object_id/subscriptions', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'subscriptions'), array('project_id' => '\d+', 'object_id' => '\d+'));  
      $router->map('project_object_subscribe', 'projects/:project_id/objects/:object_id/subscribe', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'subscribe'), array('project_id' => '\d+', 'object_id' => '\d+'));  
      $router->map('project_object_unsubscribe', 'projects/:project_id/objects/:object_id/unsubscribe', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'unsubscribe'), array('project_id' => '\d+', 'object_id' => '\d+'));
      $router->map('project_object_unsubscribe_user', 'projects/:project_id/objects/:object_id/unsubscribe-user/:user_id', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'unsubscribe_user'), array('project_id' => '\d+', 'object_id' => '\d+', 'user_id' => '\d+'));
      
      // Visibility
      $router->map('project_object_visibility', 'projects/:project_id/objects/:object_id/visibility', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'visibility'), array('project_id' => '\d+', 'object_id' => '\d+'));  
      
      // Attachments
      $router->map('attachments', 'projects/:project_id/objects/:object_id/attachments', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'attachments'), array('project_id' => '\d+', 'object_id' => '\d+'));
      //BOF-20120228SA
      $router->map('attachments_all', 'projects/:project_id/objects/:object_id/attachments_all', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'attachments_all'), array('project_id' => '\d+', 'object_id' => '\d+'));
      $router->map('comments_all', 'projects/:project_id/objects/:object_id/comments_all', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'comments_all'), array('project_id' => '\d+', 'object_id' => '\d+'));
      //EOF-20120228SA
      $router->map('attachments_mass_update', 'projects/:project_id/objects/:object_id/attachments/mass-update', array('module' => SYSTEM_MODULE, 'controller' => 'project_objects', 'action' => 'attachments_mass_update'), array('project_id' => '\d+', 'object_id' => '\d+'));
      
      $router->map('attachment_view', 'attachments/:attachment_id', array('controller' => 'attachments', 'action' => 'view'), array('project_id' => '\d+', 'attachment_id' => '\d+'));
      $router->map('attachment_edit', 'attachments/:attachment_id/edit', array('controller' => 'attachments', 'action' => 'edit'), array('project_id' => '\d+', 'attachment_id' => '\d+'));
      $router->map('attachment_delete', 'attachments/:attachment_id/delete', array('controller' => 'attachments', 'action' => 'delete'), array('project_id' => '\d+', 'attachment_id' => '\d+'));
      //BOF:mod 20111206
      $router->map('attachment_rename', 'attachments/:attachment_id/rename', array('controller' => 'attachments', 'action' => 'rename'), array('project_id' => '\d+', 'attachment_id' => '\d+'));
      $router->map('attachment_copy_to', 'attachments/:attachment_id/copyto', array('controller' => 'attachments', 'action' => 'copy_to') , array('project_id' => '\d+', 'attachment_id' => '\d+'));
      //EOF:mod 20111206
	  $router->map('attachment_move_to', 'attachments/:attachment_id/moveto', array('controller' => 'attachments', 'action' => 'move_to') , array('project_id' => '\d+', 'attachment_id' => '\d+'));
    } // defineRoutes
    
    /**
     * Define event handlers
     *
     * @param EventsManager $events
     * @return null
     */
    function defineHandlers(&$events) {
      $events->listen('on_get_project_object_types', 'on_get_project_object_types');
      $events->listen('on_get_completable_project_object_types', 'on_get_completable_project_object_types');
      $events->listen('on_email_templates', 'on_email_templates');
      $events->listen('on_comment_added', 'on_comment_added');
      $events->listen('on_comment_deleted', 'on_comment_deleted');
      $events->listen('on_project_object_copied', 'on_project_object_copied');
      $events->listen('on_project_object_moved', 'on_project_object_moved');
      $events->listen('on_project_object_reassigned', 'on_project_object_reassigned');
      $events->listen('on_project_user_removed', 'on_project_user_removed');
      $events->listen('on_object_deleted', 'on_object_deleted');
      $events->listen('on_system_permissions', 'on_system_permissions');
      $events->listen('on_dashboard_important_section', 'on_dashboard_important_section');
    } // defineHandlers
    
    // ---------------------------------------------------
    //  Names
    // ---------------------------------------------------
    
    /**
     * Get module display name
     *
     * @return string
     */
    function getDisplayName() {
      return lang('Resources');
    } // getDisplayName
    
    /**
     * Return module description
     *
     * @param void
     * @return string
     */
    function getDescription() {
      return lang('System level resources support - comments, categories, tags...');
    } // getDescription
    
  }

?>