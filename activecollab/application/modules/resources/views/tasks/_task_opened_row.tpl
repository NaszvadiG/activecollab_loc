                <li class="{cycle values='odd,even'} sort"id="task{$_object_task->getId()}">
				  {*}BOF:mod 20120904{*}<a name="task{$_object_task->getId()}" style="display:none;">&nbsp;</a>{*}EOF:mod 20120904{*}
                  <span class="task">
                    <span class="left_options">
                      <span class="option star">{object_star object=$_object_task user=$logged_user}</span>
                      <span class="option checkbox">
                        {if $_object_task->getProjectId()=='62' || $_object_task->canChangeCompleteStatus($logged_user)}
                          {link href=$_object_task->getCompleteUrl(true) class=complete_task}<img src="{image_url name=icons/not-checked.gif}" alt="toggle" />{/link}
                        {else}
                          <img src="{image_url name=icons/not-checked.gif}" alt="toggle" />
                        {/if}
                      </span>
                      <!--span class="option">{object_priority object=$_object_task}</span>
                      <span class="option">                          
                          {select_priority_images name=$_object_task->getId() value=$_object_task->getPriority() url=$_object_task->getAjaxChangePriorityUrl()}
                    </span//-->                     
                      <span class="option">                          
                          {select_priority_images name=$_object_task->getId() value=$_object_task->getPriority() url=$_object_task->getAjaxChangePriorityUrl()}
						  {is_recurring_task task=$_object_task}
                    </span>
                    </span>
                    <span class="right_options">
                        <span class="option">{*}{object_subscription object=$_object_task user=$logged_user}{*}</span>
						<span class="option"><p id="showmore" style="cursor:pointer;display:inline;position:relative;top:-5px;font-weight:bold;"></p></span>
						{*}BOF:mod 20120904{*}
						<span class="option">
							<a href="javascript://" onclick="javascript:moveTask('{$active_project->getId()}', '{$_object_task->getId()}');return false;" title="Move Task to another Project, Ticket or Page"><img src='{image_url name=movetask.png}' /></a>
						</span>
						{*}EOF:mod 20120904{*}
						{*}BOF:mod 20120911{*}
						<span class="option">
							<a href="javascript://" onclick="javascript:quickReminder('{$active_project->getId()}', '{$_object_task->getId()}');return false;" title="Reminder/Due Date"><img src='{image_url name=reminder-icon.png}' /></a>
						</span>
						{*}EOF:mod 20120911{*}
						{*}
                      {if module_loaded('timetracking') && $logged_user->getProjectPermission('timerecord', $_object_task->getProject())}
                        <span class="option">{object_time object=$_object_task show_time=no}</span>
                      {/if}
					  {*}
					  <span class="option">{snooze_task_reminder object=$_object_task}</span>
					  
                      {if $_object_task->getProjectId()=='62' ||  $_object_task->canEdit($logged_user)}
                      	<span class="option">{link href=$_object_task->getAjaxEditUrl() title='Quick edit...' onclick="editTask(this);return false;"}<img src='{image_url name=quickedit.png}' alt='' />{/link}</span>
                        <span class="option">{link href=$_object_task->getEditUrl() title='Edit...'}<img src='{image_url name=gray-edit.gif}' alt='' />{/link}</span>
                      {/if}
                      {if $_object_task->canDelete($logged_user)}
                        <span class="option">{link href=$_object_task->getTrashUrl() title='Move to Trash' class=remove_task}<img src='{image_url name=gray-delete.gif}' alt='' />{/link}</span>
                      {/if}
                    </span>
                    <span class="main_data">
					{*}BOF:mod 20120817{*}<div>{*}EOF:mod 20120817{*}
                      <input type="hidden" name="task[{$_object_task->getId(true)}]" />
                      {*}{$_object_task->getBody()|clean|clickable}{*}
                      {*}<span id="task_{$_object_task->getId(true)}" style="position:relative;top:-4px;">{$_object_task->getBody()|clickable}</span>{*}
						<span id="task_{$_object_task->getId(true)}" style="position:relative;top:-4px;"><span id="task_content">{if $_object_task->is_action_request_task()}{$_object_task->get_action_request_comment_body()|clickable}{else}{$_object_task->getBody()|clickable}{/if}</span>
                    {if $_object_task->hasAssignees(true) && $_object_task->getDueOn()}
                      <span class="details block"><span id="assignees_content">{object_assignees object=$_object_task}</span> | <span id="dueon_content">{due object=$_object_task}</span></span>
                    {elseif $_object_task->hasAssignees(true)}
                      <span class="details block"><span id="assignees_content">{object_assignees object=$_object_task}</span></span>
                    {elseif $_object_task->getDueOn()}
                      <span class="details block"><span id="dueon_content">{due object=$_object_task}</span></span>
                    {/if}
					{recurring_info object=$_object_task}
                    {*}{reminder object=$_object_task}
                    <span class="option">
                        {$_object_task->get_auto_email_value()}
                    </span>{*}
					{*}BOF:mod 20120817{*}</div>{*}EOF:mod 20120817{*}
                  </span>
                  </span>
                </li>