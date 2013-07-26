<?php
function smarty_function_get_custom_comment_body($params, &$smarty) {
	$comment = array_var($params, 'comment');
	$parent_type = $comment->getParentType();
	$parent_id = $comment->getParentId();
	
	$parent = new $parent_type($parent_id);
	$body = $comment->getFormattedBody();
	
	$subscribers = $parent->getSubscribers();
	$subscribers_info = array();
	foreach($subscribers as $subscriber){
		$subscribers_info[$subscriber->getId()] = $subscriber->getName();
	}
	
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
	mysql_select_db(DB_NAME);
	
	$pos_action_request_1 = stripos($body, 'Action Request emailed to:');//still checking for backward compatibility
        $pos_action_request_2 = stripos($body, 'Action Request marked to:');

	if ($pos_action_request_1!==false || $pos_action_request_2!==false){
                if ($pos_action_request_1!==false){
                    $pos_action_request = $pos_action_request_1;
                } elseif ($pos_action_request_2!==false){
                    $pos_action_request = $pos_action_request_2;
                }
		$action_request_string = substr($body, $pos_action_request);
		$pos_br = stripos($action_request_string, '<br />');
		if ($pos_br!==false){
			$action_request_string = substr($action_request_string, 0, $pos_br);
		}
		$length_action_request = strlen($action_request_string);
                if ($pos_action_request_1!==false){
                    $temp = str_ireplace('Action Request emailed to:', '', strip_tags($action_request_string));
                } elseif ($pos_action_request_2!==false){
                    $temp = str_ireplace('Action Request marked to:', '', strip_tags($action_request_string));
                }
                
		$names = explode(',', $temp);
		foreach($names as $name){
			$name = trim($name);
			$id = array_search($name, $subscribers_info);
			if ($id!==false){
				$sql = mysql_query("select is_action_request from healingcrystals_assignments_action_request where comment_id='" . $comment->getId() . "' and user_id='" . $id . "'");
				if (mysql_num_rows($sql)){
					$info = mysql_fetch_assoc($sql);
					$flag = $info['is_action_request'];
					if ($flag=='1'){
						$action_request_string = str_ireplace($name, '<span style="color:red;">' . $name . '</span>', $action_request_string);
					} else {
						$action_request_string = str_ireplace($name, '<span style="color:black;">' . $name . '</span>', $action_request_string);
					}
				}
			}
		}
	}
	 
	$pos_fyi_1 = stripos($body, 'FYI Comment sent to:');//still checking for backward compatibility
        $pos_fyi_2 = stripos($body, 'FYI Comment marked to:');
	if ($pos_fyi_1!==false || $pos_fyi_2!==false){
                if ($pos_fyi_1!==false){
                    $pos_fyi = $pos_fyi_1;
                } elseif ($pos_fyi_2!==false){
                    $pos_fyi = $pos_fyi_2;
                }
		$fyi_string = substr($body, $pos_fyi);
		$pos_br = stripos($fyi_string, '<br />');
		if ($pos_br!==false){
			$fyi_string = substr($fyi_string, 0, $pos_br);
		}
                if ($pos_fyi_1!==false){
                    $temp = str_ireplace('FYI Comment sent to:', '', strip_tags($fyi_string));
                } elseif ($pos_fyi_2!==false){
                    $temp = str_ireplace('FYI Comment marked to:', '', strip_tags($fyi_string));
                }
                
		$length_fyi = strlen($fyi_string);
		 
		$names = explode(',', $temp);
		foreach($names as $name){
			$name = trim($name);
			$id = array_search($name, $subscribers_info);
			if ($id!==false){
				$sql = mysql_query("select is_fyi from healingcrystals_assignments_action_request where comment_id='" . $comment->getId() . "' and user_id='" . $id . "'");
				if (mysql_num_rows($sql)){
					$info = mysql_fetch_assoc($sql);
					$flag = $info['is_fyi'];
					if ($flag=='1'){
						$fyi_string = str_ireplace($name, '<span style="color:red;">' . $name . '</span>', $fyi_string);
					} else {
						$fyi_string = str_ireplace($name, '<span style="color:black;">' . $name . '</span>', $fyi_string);
					}
				}
			}
		}
	}
        
        $pos_email = stripos($body, 'Email sent to:');
        if ($pos_email!==false){
            $email_string = substr($body, $pos_email);
            
        }
        
	mysql_close($link);
	
	if ($pos_action_request){
		$body = substr($body, 0, $pos_action_request);
	} elseif ($pos_fyi){
		$body = substr($body, 0, $pos_fyi);
	} elseif ($pos_email){
		$body = substr($body, 0, $pos_email);
	}
	
	
	return $body . $action_request_string . ($pos_action_request!==false ? '<br/>' : '') .  $fyi_string . ($pos_action_request_1!==false || $pos_fyi!==false ? '<br/>' : '') . $email_string;
}
?>