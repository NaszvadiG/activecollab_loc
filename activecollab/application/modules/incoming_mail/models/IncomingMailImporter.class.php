<?php
  /**
   * Class for importing mail from mailboxes
   *
   * @package activeCollab.modules.project_exporter
   * @subpackage models
   *
   */
  class IncomingMailImporter extends AngieObject {
    /**
     * Open's connection to mailboxes and download emails with limit of $max_emails
     *
     * @param array $mailboxes
     * @param integer $max_emails
     */
    function importEmails (&$mailboxes, $max_emails = 20) {
      use_model('incoming_mail_activity_logs', INCOMING_MAIL_MODULE);

      $import_date = new DateTimeValue();
      $imported_emails_count = 0;
      if (is_foreachable($mailboxes)) {
        foreach ($mailboxes as $mailbox) {
        	$manager = $mailbox->getMailboxManager();
        	// open connection to mailbox
        	$result = $manager->connect();
        	if (!$result || is_error($result)) {
        	  // we didn't connect, so we need to log it
        	  $error_message = '';
        	  if (is_error($result)) {
              $error_message = ': '.$result->getMessage();
        	  } // if
        	  IncomingMailActivityLogs::log($mailbox->getId(), lang('Could Not Connect To Mailbox' . $error_message), null, INCOMING_MAIL_LOG_STATUS_ERROR, $import_date);
            $mailbox->setLastStatus(2);
            $mailbox->save();
        	  continue;
        	} // if

          $mailbox->setLastStatus(1);
          $mailbox->save();

        	$email_count = $manager->countMessages();
        	for ($mid = 1; $mid < ($email_count+1); $mid++) {
        	  if ($imported_emails_count >= $max_emails) {
        	    return true;
        	  } // if
            $current_message_id = 1;

            $email = $manager->getMessage($current_message_id, INCOMING_MAIL_ATTACHMENTS_FOLDER);
            if (!instance_of($email, 'MailboxManagerEmail')) {
              IncomingMailActivityLogs::log($mailbox->getId(), $email->getMessage(), null, INCOMING_MAIL_LOG_STATUS_ERROR, $import_date);
              continue;
            } // if

            $pending_email = IncomingMailImporter::createPendingEmail($email, $mailbox);
            if (!instance_of($pending_email, 'IncomingMail')) {
              IncomingMailActivityLogs::log($mailbox->getId(), $pending_email->getMessage(), $email, INCOMING_MAIL_LOG_STATUS_ERROR, $import_date);
              continue;
            } // if

            $manager->deleteMessage($current_message_id, true);

            $project_object = IncomingMailImporter::importPendingEmail($pending_email, false, $email->getIsReport());
            if (!instance_of($project_object, 'ProjectObject')) {
              IncomingMailActivityLogs::log($mailbox->getId(), $project_object->getMessage(), $pending_email, INCOMING_MAIL_LOG_STATUS_ERROR, $import_date);
              continue;
            } // if

            IncomingMailActivityLogs::log($mailbox->getId(), lang('Imported Successfully'), $project_object, INCOMING_MAIL_LOG_STATUS_OK, $import_date);
            $user = $project_object->getCreatedBy();
            if (instance_of($user, 'User')) {
              $user->setLastActivityOn(new DateTimeValue());
              $user->save();
            } // if
            $pending_email->delete();

            $imported_emails_count ++;
      	  } // for
        } // foreach
      } // if
    } // importEmails

    /**
     * Creates pending incoming email from email message
     *
     * @param MailboxManagerEmail $email
     * @param IncomingMailbox $mailbox
     *
     * @return mixed
     */
    function createPendingEmail(&$email, &$mailbox) {
      if (!instance_of($email, 'MailboxManagerEmail')) {
        return new Error(lang('Email provided is empty'));
      } // if

      $incoming_mail = new IncomingMail();
      $incoming_mail->setProjectId($mailbox->getProjectId());
      $incoming_mail->setIncomingMailboxId($mailbox->getId());
      $incoming_mail->setHeaders($email->getHeaders());

      // object subject
      $subject = $email->getSubject();
      $incoming_mail->setSubject($subject);

      // object body
      $incoming_mail->setBody(incoming_mail_get_body($email));

      // object type and parent id
      $object_type = $mailbox->getObjectType();

      preg_match("/\{ID(.*?)\}(.*)/is", $subject, $results);
      if (count($results) > 0) {
        $parent_id = $results[1];
        $parent = ProjectObjects::findById($parent_id);
        if (instance_of($parent, 'ProjectObject') && $parent->can_have_comments) {
          $object_type = 'comment';
          $incoming_mail->setParentId($parent_id);
        } else {
          $incoming_mail->setParentId(null);
        } // if
        $subject = trim(str_replace($results[0],'',$subject));
        $incoming_mail->setSubject($subject);
      } else {
		  //BOF:mod 20120809
		  //echo $subject . "\n";
		  $temp = explode('-', $subject);
		  if (count($temp)==1 || count($temp)==2){
			list($user_name, $priority) = $temp;
			$user_name = trim($user_name);
			$priority = trim($priority);
			//echo $user_name . ' | ' . $priority . "\n";
			$name_parts = explode(' ', $user_name);
			if (count($name_parts)==1 || count($name_parts)==2){
				list($first_name, $last_name) = $name_parts;
				//echo $first_name . ' | ' . $last_name . "\n";
				$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
				mysql_select_db(DB_NAME, $link);
				$query = "select id from healingcrystals_users where first_name='" . mysql_real_escape_string($first_name) . "' " . (!empty($last_name) ? " and last_name='" . mysql_real_escape_string($last_name) . "' " : "");
				$result = mysql_query($query, $link);
				if (mysql_num_rows($result)){
					$info = mysql_fetch_assoc($result);
					$user_id = $info['id'];
					$incoming_mail->setProjectId(TASK_LIST_PROJECT_ID);
					$object_type = 'task';
					//echo $user_id . ' | ' . $object_type . "\n";
				}
				mysql_close($link);
			}
		  }
		  if (empty($user_id)) {
			$temp_sender = $email->getAddress('from');
			if (is_array($temp_sender)) {
				$temp_email_address = array_var($temp_sender, 'email', null);
				$temp_user = Users::findByEmail($temp_email_address);
				if (instance_of($temp_user,'User')) {
					$incoming_mail->setProjectId(TASK_LIST_PROJECT_ID);
					$incoming_mail->setSubject($incoming_mail->getSubject() . '{' . $temp_user->getId() . '}');
					$object_type = 'task';
				}
			}
		  }
		  //EOF:mod 20120809
	  }
	  //BOF: mod
	  if ($object_type=='comment' && empty($parent_id) ){
      	$object_type = 'ticket';
      }
      //EOF: mod

      $incoming_mail->setObjectType($object_type);

      if ($incoming_mail->getSubject() || $incoming_mail->getBody()) {
        if (!$incoming_mail->getSubject()) {
          $incoming_mail->setSubject(lang('[SUBJECT NOT PROVIDED]'));
        } // if
        if (!$incoming_mail->getBody() && (in_array($incoming_mail->getObjectType(), array('comment', 'discussion')))) {
          $incoming_mail->setBody(lang('[CONTENT NOT PROVIDED]'));
        } // if
      } // if

      $sender = $email->getAddress('from');
      if (!is_array($sender)) {
        return new Error(lang('Sender is unknown'));
      } // if

      // user details
      $email_address = array_var($sender, 'email', null);
      $user = Users::findByEmail($email_address);
      if (!instance_of($user,'User')) {
        $user = new AnonymousUser(array_var($sender, 'name', null) ? array_var($sender, 'name', null) : $email_address, $email_address);
      } // if
      $incoming_mail->setCreatedBy($user);

      // creation time
      $incoming_mail->setCreatedOn(new DateTimeValue());

      $result = $incoming_mail->save();
      if (!$result || is_error($result)) {
        return $result;
      } // if

      // create attachment objects
      $attachments = $email->getAttachments();
      if (is_foreachable($attachments)) {
        foreach ($attachments as $attachment) {
        	$incoming_attachment = new IncomingMailAttachment();
        	$incoming_attachment->setTemporaryFilename(basename(array_var($attachment, 'path', null)));
        	$incoming_attachment->setOriginalFilename(array_var($attachment,'filename', null));
        	$incoming_attachment->setContentType(array_var($attachment, 'content_type', null));
        	$incoming_attachment->setFileSize(array_var($attachment, 'size', null));
        	$incoming_attachment->setMailId($incoming_mail->getId());
        	$attachment_save = $incoming_attachment->save();
        	if (!$attachment_save || is_error($attachment_save)) {
        	  // we couldn't create object in database so we need to remove file from system
        	  //@unlink(array_var($attachment,'path'));
        	} // if
        } // foreach
      } // if

      return $incoming_mail;
    } // createPendingEmail

    /**
     * Use $incoming_mail as a base for creating ProjectObject
     *
     * @param IncomingMail $incoming_mail
     * @return integer
     */
    function importPendingEmail(&$incoming_mail, $skip_permission_checking = false, $is_report = false) {
      $mailbox = IncomingMailboxes::findById($incoming_mail->getIncomingMailboxId());

      if ($is_report) {
        $incoming_mail->setState(INCOMING_MAIL_STATUS_REPORT_EMAIL);
        $incoming_mail->save();
        return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_REPORT_EMAIL));
      } // if

      $project = $incoming_mail->getProject();
      if (!instance_of($project, 'Project')) {
        // project does not exists
        $incoming_mail->setState(INCOMING_MAIL_STATUS_PROJECT_DOES_NOT_EXISTS);
        $incoming_mail_save = $incoming_mail->save();
        return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_PROJECT_DOES_NOT_EXISTS));
      } // if

      $user = $incoming_mail->getCreatedBy();
      if (!$skip_permission_checking) {
        // check additional permissions
        if (instance_of($user, 'User')) { // if it's registered user

          // if object type is not comment and all users cannot create objects and current user cant create object
          if (($incoming_mail->getObjectType() != 'comment') && !$mailbox->getAcceptAllRegistered() && !ProjectObject::canAdd($user, $project,$incoming_mail->getObjectType())) {
            $incoming_mail->setState(INCOMING_MAIL_STATUS_USER_CANNOT_CREATE_OBJECT);
            $incoming_mail_save = $incoming_mail->save();
            return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_USER_CANNOT_CREATE_OBJECT));
          } // if
        } else { // if it's anonymous user
          // if mailbox does not accept anonymous users
          if (!$mailbox->getAcceptAnonymous()) {
            $incoming_mail->setState(INCOMING_MAIL_STATUS_ANONYMOUS_NOT_ALLOWED);
            $incoming_mail_save = $incoming_mail->save();
            return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_ANONYMOUS_NOT_ALLOWED));
          } // if
        } // if
      } // if

      // create new object instance dependable of object type
      switch ($incoming_mail->getObjectType()) {
        case 'discussion':
          $import = & IncomingMailImporter::importPendingEmailAsDiscussion($incoming_mail, $project, $user);
          break;
        case 'ticket':
          $import = & IncomingMailImporter::importPendingEmailAsTicket($incoming_mail, $project, $user);
          break;
        case 'comment':
			//$pos = strpos($incoming_mail->getSubject(), '{ID');
			//if ($pos!==false){
			$parent_id = $incoming_mail->getParentId();
			if (!empty($parent_id)){
				$import = & IncomingMailImporter::importPendingEmailAsComment($incoming_mail, $project, $user, $mailbox);
			} else {
				$import = & IncomingMailImporter::importPendingEmailAsTicket($incoming_mail, $project, $user);
			}
          break;
		//BOF:mod 20120809
		case 'task':
		  //BOF:mod 20120820
		  /*
		  //EOF:mod 20120820
		  $import = & IncomingMailImporter::importPendingEmailToTaskList($incoming_mail, $project, $user);
		  //BOF:mod 20120820
		  */
		  $page_id = IncomingMailImporter::getPageId($incoming_mail, $project, $user);
		  $attachments = $incoming_mail->getAttachments();
		  $comment = null;
		  if (strpos($incoming_mail->getSubject(), '}')!==false || strlen(strip_tags($incoming_mail->getBody()))>150 || count($attachments)){
			$import = & IncomingMailImporter::importPendingEmailAsComment($incoming_mail, $project, $user, $mailbox, $page_id);
		  } else {
			$import = & IncomingMailImporter::importPendingEmailToTaskList($incoming_mail, $project, $user, $page_id, null);
		  }

		  //BOF:mod 20120820
		  break;
		//EOF:mod 20120809
      } // switch

      return $import;
    } // importPendingEmail
	function getPageId(&$incoming_mail, &$project, &$user){
	  $page_id = '';
	  list($user_name, ) = explode('-', $incoming_mail->getSubject());
	  $user_name = trim($user_name);
	  $name_parts = explode(' ', $user_name);
	  list($first_name, $last_name) = $name_parts;

	  $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  mysql_select_db(DB_NAME, $link);
	  $query = "select id from healingcrystals_users where first_name='" . mysql_real_escape_string($first_name) . "' " . (!empty($last_name) ? " and last_name='" . mysql_real_escape_string($last_name) . "' " : "");
	  $result = mysql_query($query, $link);
	  if (mysql_num_rows($result)){
		$info = mysql_fetch_assoc($result);
		$user_id = $info['id'];
	  } elseif ( strpos($incoming_mail->getSubject(), '}')!==false ) {
		$user_id = substr($incoming_mail->getSubject(), strrpos($incoming_mail->getSubject(), '{')+1, -1);
	  }

	  if (!empty($user_id) ){
		$task_meant_for_user = Users::findById($user_id);
		$page_title = $task_meant_for_user->getName() . ' - Task List';
		//echo $page_title . '<br/>';
		$query2 = "select id from healingcrystals_project_objects where type='Page' and project_id='" . TASK_LIST_PROJECT_ID . "' and name='" . mysql_real_escape_string($page_title) . "'";
		//echo $query2 . '<br/>';
		$result2 = mysql_query($query2,$link);
		if (mysql_num_rows($result2)){
			$info2 = mysql_fetch_assoc($result2);
			$page_id = $info2['id'];
		} else {
			$query3 = "select id from healingcrystals_project_objects where type='Category' and module='pages' and project_id='" . TASK_LIST_PROJECT_ID . "' and name='General'";
			//echo $query3 . '<br/>';
			$page_category = mysql_query($query3, $link);
			$page_category_info = mysql_fetch_assoc($page_category);
			$category_id = $page_category_info['id'];
			//echo $category_id . '<br/>';

			$page_data = array(
				'type' => 'Page',
				'module' => 'pages',
				'visibility' => VISIBILITY_NORMAL,
				'name' => $page_title,
				'body' => 'Auto-generated Task List Page',
				'integer_field_1' => '1',
			);
			//print_r($page_data);
			//db_begin_work();
			$page = new Page();
			$page->setAttributes($page_data);
			$page->setProjectId(TASK_LIST_PROJECT_ID);
			$page->setCreatedBy($user);
			$page->setState(STATE_VISIBLE);
			$page->setParentId($category_id);
			$page->save();
			$page->ready();
			$page_id = $page->getId();
		}
		$query = "select * from healingcrystals_project_users where user_id='" . $task_meant_for_user->getId() . "' and project_id='" . TASK_LIST_PROJECT_ID . "'";
		$result = mysql_query($query, $link);
		if (!mysql_num_rows($result)){
			//mysql_query("insert into healingcrystals_project_users (user_id, project_id, role_id, permissions) values ('" . $user->getId() . "', '" . TASK_LIST_PROJECT_ID . "', '" . $user->getRoleId() . "', 'N;')");
			mysql_query("insert into healingcrystals_project_users (user_id, project_id, role_id, permissions) values ('" . $task_meant_for_user->getId() . "', '" . TASK_LIST_PROJECT_ID . "', '7', 'N;')");
		} elseif ($user->getRoleId()=='2'){
			mysql_query("update healingcrystals_project_users set role_id='7' where user_id='" . $task_meant_for_user->getId() . "' and project_id='" . TASK_LIST_PROJECT_ID . "'");
		}
	  }
	  mysql_close($link);
	  return $page_id;
	}
	//BOF:mod 20120809
	//BOF:mod 20120820
	/*
	//EOF:mod 20120820
    function importPendingEmailToTaskList(&$incoming_mail, &$project, &$user) {
	//BOF:mod 20120820
	*/
	function importPendingEmailToTaskList(&$incoming_mail, &$project, &$user, $page_id, $comment) {
	  //$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  //mysql_select_db(DB_NAME, $link);
	  //mysql_query("insert into testing (date_added, content) values (now(), 'in task list adde func:Page " . $page_id . " / comment: " . $comment->getId() . "')");
	  //mysql_query("insert into testing (date_added, content) values (now(), '" . mysql_real_escape_string($incoming_mail->getSubject()) . "')");
	//EOF:mod 20120820
	  list($user_name, $priority) = explode('-', $incoming_mail->getSubject());
	  $user_name = trim($user_name);
	  $priority = trim($priority);
	  $name_parts = explode(' ', $user_name);
	  list($first_name, $last_name) = $name_parts;
	  //mysql_query("insert into testing (date_added, content) values (now(), '" . mysql_real_escape_string($priority) . "')");
	  //BOF:mod 20120820
	  /*
	  //EOF:mod 20120820
	  $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  mysql_select_db(DB_NAME, $link);
	  $query = "select id from healingcrystals_users where first_name='" . mysql_real_escape_string($first_name) . "' " . (!empty($last_name) ? " and last_name='" . mysql_real_escape_string($last_name) . "' " : "");
	  $result = mysql_query($query, $link);
	  if (mysql_num_rows($result)){
		$info = mysql_fetch_assoc($result);
		$user_id = $info['id'];
		$task_meant_for_user = Users::findById($user_id);
		$page_title = $task_meant_for_user->getName() . ' - Task List';
		//echo $page_title . '<br/>';
		$query2 = "select id from healingcrystals_project_objects where type='Page' and project_id='" . TASK_LIST_PROJECT_ID . "' and name='" . mysql_real_escape_string($page_title) . "'";
		//echo $query2 . '<br/>';
		$result2 = mysql_query($query2,$link);
		if (mysql_num_rows($result2)){
			$info2 = mysql_fetch_assoc($result2);
			$page_id = $info2['id'];
		} else {
			$query3 = "select id from healingcrystals_project_objects where type='Category' and module='pages' and project_id='" . TASK_LIST_PROJECT_ID . "' and name='General'";
			//echo $query3 . '<br/>';
			$page_category = mysql_query($query3, $link);
			$page_category_info = mysql_fetch_assoc($page_category);
			$category_id = $page_category_info['id'];
			//echo $category_id . '<br/>';

			$page_data = array(
				'type' => 'Page',
				'module' => 'pages',
				'visibility' => VISIBILITY_NORMAL,
				'name' => $page_title,
				'body' => 'Auto-generated Task List Page',
				'integer_field_1' => '1',
			);
			//print_r($page_data);
			//db_begin_work();
			$page = new Page();
			$page->setAttributes($page_data);
			$page->setProjectId(TASK_LIST_PROJECT_ID);
			$page->setCreatedBy($user);
			$page->setState(STATE_VISIBLE);
			$page->setParentId($category_id);
			$page->save();
			$page->ready();
			$page_id = $page->getId();
		}
	  }
	  mysql_close($link);
	  //BOF:mod 20120820
	  */
	  //EOF:mod 20120820

      $task = new Task();
      $task->setProjectId($project->getId());
	  $task->setParentId($page_id);
	  $task->setParentType('Page');
      $task->setCreatedBy($user);
      $task->setCreatedOn($incoming_mail->getCreatedOn());
      $task->setVisibility(VISIBILITY_NORMAL);
      $task->setState(STATE_VISIBLE);
      $task->setSource(OBJECT_SOURCE_EMAIL);
	  if (!empty($priority)){
		$constant_name = 'PRIORITY_' . strtoupper(str_replace(' ', '', $priority));
		$priority_val = '';
		$priority_val_set = false;
		if (defined($constant_name)){
			switch($constant_name){
				case 'PRIORITY_HOLD':
					$priority_val = '-4';
					$priority_val_set = true;
					break;
				//BOF:mod 20121107
				/*
				//EOF:mod 20121107
				case 'PRIORITY_ONGOING':
					$priority_val = '-3';
					$priority_val_set = true;
					break;
				//BOF:mod 20121107
				*/
				//EOF:mod 20121107
				case 'PRIORITY_LOWEST':
					$priority_val = '-2';
					$priority_val_set = true;
					break;
				case 'PRIORITY_LOW':
					$priority_val = '-1';
					$priority_val_set = true;
					break;
				case 'PRIORITY_NORMAL':
					$priority_val = '0';
					$priority_val_set = true;
					break;
				case 'PRIORITY_HIGH':
					$priority_val = '1';
					$priority_val_set = true;
					break;
				case 'PRIORITY_HIGHEST':
					$priority_val = '2';
					$priority_val_set = true;
					break;
				//BOF:mod 20121107
				case 'PRIORITY_URGENT':
					$priority_val = '3';
					$priority_val_set = true;
					break;
				//EOF:mod 20121107
			}
			if ($priority_val_set) $task->setPriority($priority_val);
		}
	  }

      $task->setName($incoming_mail->getSubject());
	  $amended_body = '';
	  //BOF:mod 20120820
	  if (empty($comment)) {
		if (strpos($incoming_mail->getSubject(), '{')!==false ){
			$amended_body .= substr($incoming_mail->getSubject(), 0, strrpos($incoming_mail->getSubject(), '{') );
		} else {
			$amended_body .= $incoming_mail->getBody();
		}
	  } else {
		if (strpos($incoming_mail->getSubject(), '{')!==false ){
			$parent = $comment->getParent();
			$url = $parent->getViewUrl() . '#comment' . $comment->getId();
			$amended_body .= substr($incoming_mail->getSubject(), 0, strrpos($incoming_mail->getSubject(), '{') ) .
							'<br/><a href="' . $url . '">View Task in Full</a>';
		} else {
			$temp_body = strip_tags($incoming_mail->getBody());
			$chars_len = strlen($temp_body);
			//if ($chars_len > 150){
			if ($chars_len > 525){
				//mysql_query("insert into testing (date_added, content) values (now(), 'greater than 150 block')");
				//$url = $comment->getRealViewUrl();
				$parent = $comment->getParent();
				$url = $parent->getViewUrl() . '#comment' . $comment->getId();
				//mysql_query("insert into testing (date_added, content) values (now(), '" . mysql_real_escape_string($url) . "')");
				//$amended_body .= substr($temp_body, 0, 150) . '.. <br/><a href="' . $url . '">View Task in Full</a>';
				$amended_body .= substr($temp_body, 0, 525) . '.. <br/><a href="' . $url . '">View Task in Full</a>';
			} else {
				$amended_body .= $incoming_mail->getBody();
			}
		}
		//mysql_query("insert into testing (date_added, content) values (now(), '" . mysql_real_escape_string($amended_body) . "')");
		$attachments = $comment->getAttachments();
		if (is_foreachable($attachments)) {
			$amended_body .= '<br/>Attachments:<br/>';
			foreach ($attachments as $attachment) {
				$amended_body .= '<a href="' . $attachment->getViewUrl() . '">' . $attachment->getName() . '</a><br/>';
			}
		}
	  }
	  $task->setBody($amended_body);

      //IncomingMailImporter::attachFilesToProjectObject($incoming_mail, $task);

      $save = $task->save();
      if ($save && !is_error($save)) {
        //$subscibed_users = array($project->getLeaderId());
        //if (instance_of($user, 'User')) {
        // $subscibed_users[] = $user->getId();
        //} // if
        //Subscriptions::subscribeUsers($subscibed_users, $ticket);
        $task->ready();
		//mysql_query("insert into testing (date_added, content) values (now(), 'in task list adde func end: " . $task->getId() . "')");
        return $task;

      } // if

      return $save;
	  //mysql_close($link);
    }
	//EOF:mod 20120809

    /**
     * Import pending email as ticket
     *
     * @param IncomingMail $incoming_mail
     * @param Project $project
     * @param User $user
     * @return Ticket
     */
    function importPendingEmailAsTicket(&$incoming_mail, &$project, &$user) {
      $ticket = new Ticket();
      $ticket->setProjectId($project->getId());
      $ticket->setCreatedBy($user);
      $ticket->setCreatedOn($incoming_mail->getCreatedOn());
      $ticket->setVisibility(VISIBILITY_NORMAL);
      $ticket->setState(STATE_VISIBLE);
      $ticket->setSource(OBJECT_SOURCE_EMAIL);

      $ticket->setName($incoming_mail->getSubject());
      $ticket->setBody($incoming_mail->getBody());

      IncomingMailImporter::attachFilesToProjectObject($incoming_mail, $ticket);

      $save = $ticket->save();
      if ($save && !is_error($save)) {
        $subscibed_users = array($project->getLeaderId());
        if (instance_of($user, 'User')) {
         $subscibed_users[] = $user->getId();
        } // if
        Subscriptions::subscribeUsers($subscibed_users, $ticket);
        $ticket->ready();
        return $ticket;
      } // if
      return $save;
    } // importPendingEmailAsTicket


    /**
     * Import pending email as discussion
     *
     * @param IncomingMail $incoming_mail
     * @param Project $project
     * @param User $user
     * @return Discussion
     */
    function importPendingEmailAsDiscussion(&$incoming_mail, &$project, &$user) {
      $discussion = new Discussion();
      $discussion->setProjectId($project->getId());
      $discussion->setCreatedBy($user);
      $discussion->setCreatedOn($incoming_mail->getCreatedOn());
      $discussion->setVisibility(VISIBILITY_NORMAL);
      $discussion->setState(STATE_VISIBLE);
      $discussion->setSource(OBJECT_SOURCE_EMAIL);

      $discussion->setName($incoming_mail->getSubject());
      $discussion->setBody($incoming_mail->getBody());

      IncomingMailImporter::attachFilesToProjectObject($incoming_mail, $discussion);

      $save = $discussion->save();
      if ($save && !is_error($save)) {
        $subscibed_users = array($project->getLeaderId());
        if (instance_of($user, 'User')) {
         $subscibed_users[] = $user->getId();
        } // if
        Subscriptions::subscribeUsers($subscibed_users, $discussion);
        $discussion->ready();
        return $discussion;
      } // if
      return $save;
    } // importPendingEmailAsDiscussion


    /**
     * Imports pending email as comment to commentable object
     *
     * @param IncomingMail $incoming_mail
     * @param Project $project
     * @param User $user
     * @param IncomingMailbox $mailbox
     * @return Comment
     */
	//BOF:mod 20120820
	/*
	//EOF:mod 20120820
    function importPendingEmailAsComment(&$incoming_mail, &$project, &$user, &$mailbox) {
      $parent = ProjectObjects::findById($incoming_mail->getParentId());
	//BOF:mod 20120820
	*/
	function importPendingEmailAsComment(&$incoming_mail, &$project, &$user, &$mailbox, $page_id = '') {
      $parent = ProjectObjects::findById(!empty($page_id) ? $page_id : $incoming_mail->getParentId());
	//EOF:mod 20120820
      if (!instance_of($parent, 'ProjectObject')) {
        // parent object does not exists
        $incoming_mail->setState(INCOMING_MAIL_STATUS_PARENT_NOT_EXISTS);
        $incoming_mail_save = $incoming_mail->save();
        return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_PARENT_NOT_EXISTS));
      } // if

      if (!$mailbox->getAcceptAllRegistered() && instance_of($user, 'User') && !$parent->canComment($user)) {
        // user cannot create comments to parent object
        $incoming_mail->setState(INCOMING_MAIL_STATUS_USER_CANNOT_CREATE_COMMENT);
        $incoming_mail_save = $incoming_mail->save();
        return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_USER_CANNOT_CREATE_COMMENT));
      } else {
        if(!$parent->can_have_comments || $parent->getIsLocked() || ($parent->getState() < STATE_VISIBLE))  {
          // parent object can't have comments
          $incoming_mail->setState(INCOMING_MAIL_STATUS_USER_CANNOT_CREATE_COMMENT);
          $incoming_mail_save = $incoming_mail->save();
          return new Error(incoming_mail_module_get_status_description(INCOMING_MAIL_STATUS_USER_CANNOT_CREATE_COMMENT));
        } // if
      } // if

      $comment = new Comment();
      $comment->log_activities = false;
      $comment->setCreatedBy($user);
      $comment->setCreatedOn($incoming_mail->getCreatedOn());
      $comment->setProjectId($parent->getProjectId());
      $comment->setState(STATE_VISIBLE);
      $comment->setSource(OBJECT_SOURCE_EMAIL);
      $comment->setVisibility($parent->getVisibility());
      $comment->setParent($parent);
	  $body_content = '';
	  if (stripos($incoming_mail->getBody(), '-- REPLY ABOVE THIS LINE --')!==false){
		$body_content = substr($incoming_mail->getBody(), 0, strpos($incoming_mail->getBody(), '-- REPLY ABOVE THIS LINE --') );
	  } else {
		$body_content = $incoming_mail->getBody();
	  }
      $comment->setBody($body_content);

      IncomingMailImporter::attachFilesToProjectObject($incoming_mail, $comment);

      //$save = $comment->save();
      $save = $comment->save(true);
      if ($save && !is_error($save)) {
        $activity = new NewCommentActivityLog();
        $activity->log($comment, $user);

        if (instance_of($user, 'User')) {
          $parent->subscribe($user);
        } // if
        $comment->ready();
        //BOF:mod 20111110 #493
        preg_match("/\[CID(.*?)\](.*)/is", $incoming_mail->getSubject(), $results);
        if (count($results) > 0) {
            $project = new Project($parent->getProjectId());
            $variables = array('owner_company_name' => get_owner_company(),
                               'project_name'       => $project->getName(),
                               'project_url'        => $project->getOverviewUrl(),
                               'object_type'        => $comment->getVerboseType(),
                               'object_name'        => $comment->getName(),
                               'object_body'        => $comment->getFormattedBody(),
                               'object_url'         => $comment->getViewUrl(),
                               'comment_body'       => $comment->getFormattedBody(),
                               'comment_url'        => $comment->getViewUrl(),
                               'created_by_url'     => $user->getViewUrl(),
                               'created_by_name'    => $user->getDisplayName(),
                               'details_body'       => '',
                               'comment_id'         => $comment->getId(), );
            $emailed_comment_id = $results[1];
            $emailed_comment = new Comment($emailed_comment_id);
            $emailed_comment_creator_id = $emailed_comment->getCreatedById();

			$email_to = array();
			$temp_user_id = $user->getId();
			$temp_comment_id = $comment->getId();
			$rows = db_execute_all("select user_id from " . TABLE_PREFIX . "assignments_action_request where comment_id='" . $emailed_comment_id . "' and marked_for_email='1'");
			foreach($rows as $row){
				if ($row['user_id']!=$temp_user_id){
					$email_to[] = new User($row['user_id']);
					db_execute("insert into " . TABLE_PREFIX . "assignments_action_request (user_id, marked_for_email, selected_by_user_id, comment_id, date_added) values ('" . $row['user_id'] . "', '1', '" . $temp_user_id . "', '" . $temp_comment_id . "', now())");
				}
			}
			$row = db_execute_one("select a.selected_by_user_id from " . TABLE_PREFIX . "assignments_action_request a where a.comment_id='" . $emailed_comment_id . "' and a.marked_for_email='1' and a.selected_by_user_id not in (select b.user_id from " . TABLE_PREFIX . "assignments_action_request b where b.comment_id='" . $emailed_comment_id . "' and b.marked_for_email='1') limit 0, 1");
			if (!empty($row['selected_by_user_id'])){
				if ($row['selected_by_user_id']!=$temp_user_id){
					$email_to[] = new User($row['selected_by_user_id']);
					db_execute("insert into " . TABLE_PREFIX . "assignments_action_request (user_id, marked_for_email, selected_by_user_id, comment_id, date_added) values ('" . $row['selected_by_user_id'] . "', '1', '" . $temp_user_id . "', '" . $temp_comment_id . "', now())");
				}
			}
            //ApplicationMailer::send(array(new User($emailed_comment_creator_id)), 'resources/new_comment', $variables, $parent);
                        $attachments = null;
                        $object_attachments = $comment->getAttachments();
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
			ApplicationMailer::send($email_to, 'resources/new_comment', $variables, $parent, $attachments);
        }
        //EOF:mod 20111110 #493
		if (!empty($page_id)){
	  //$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	  //mysql_select_db(DB_NAME, $link);
	  //mysql_query("insert into testing (date_added, content) values (now(), 'Page_id: " . $page_id . "')");
	  //mysql_close($link);
			$task = & IncomingMailImporter::importPendingEmailToTaskList($incoming_mail, $project, $user, $page_id, $comment);
			return $task;
		} else {
			return $comment;
		}

      } // if
      return $save;
    } // importPendingEmailAsComment

    /**
     * Attach files from incoming mail to $project_object
     *
     * @param IncomingMail $incoming_mail
     * @param ProjectObject $project_object
     * @return null
     */
    function attachFilesToProjectObject(&$incoming_mail, &$project_object) {
      $attachments = $incoming_mail->getAttachments();
      $formated_attachments = array();
      if (is_foreachable($attachments)) {
        foreach ($attachments as $attachment) {
        	$formated_attachments[] = array(
        	 'path' => INCOMING_MAIL_ATTACHMENTS_FOLDER.'/'.$attachment->getTemporaryFilename(),
        	 'filename' => $attachment->getOriginalFilename(),
        	 'type' => strtolower($attachment->getContentType()),
        	);
        } // foreach
        attach_from_array($formated_attachments, $project_object);
      } // if
    } // attachFilesToProjectObject

  } // IncomingMailImporter