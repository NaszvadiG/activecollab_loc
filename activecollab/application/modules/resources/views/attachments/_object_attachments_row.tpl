{if $_object_attachments_cycle_name}
<tr class="{cycle values='odd,even' name=$_object_attachments_cycle_name}" attachment_id="{$_attachment->getId()}">
{else}
<tr attachment_id="{$_attachment->getId()}">
{/if}
  <td class="thumbnail"><a href="{$_attachment->getViewUrl()}" class="{if $_attachment->hasPreview()}has_preview{/if}"><img src="{$_attachment->getThumbnailUrl()}" alt="{$_attachment->getName()|clean}" /></a></td>
  <td class="name">
    <dl class="details_list">
      <dt>{lang}Name{/lang}</dt>
      {*}<dd>{link href=$_attachment->getViewUrl() class="filename"}{$_attachment->getName()|clean}{/link}</dd>{*}
	  <dd><a href="{$_attachment->getViewUrl()}" class="filename">{$_attachment->getName()|clean}</a></dd>
      
      <dt>{lang}Size and Type{/lang}</dt>
      <dd class="light"><span class="filesize">{$_attachment->getSize()|filesize}</span>, <span class="mimetype">{$_attachment->getMimeType()}</span></dd>
      
      <dt></dt>
      <dd class="light">{action_on_by action="Uploaded" user=$_attachment->getCreatedBy() datetime=$_attachment->getCreatedOn() parent_obj=$_attachment->getParent() }
          {*}BOF:mod 20111206{*}
          <a href="javascript://" onclick="attachmnent_rename('{$active_project->getId()}', '{$_attachment->getId()}');">Rename</a>&nbsp;&nbsp;<a href="javascript://" onclick="attachment_copy('{$active_project->getId()}', '{$_attachment->getId()}');">Copy to...</a>
          {*}EOF:mod 20111206{*}
		  &nbsp;&nbsp;<a href="javascript://" onclick="attachment_move('{$active_project->getId()}', '{$_attachment->getId()}');">Move to...</a>
      </dd>
    </dl>
  </td>
  <td class="options">
  {*}{if $_attachment->canDelete($logged_user)}{link href=$_attachment->getDeleteUrl() title='Delete permanently'}<img src='{image_url name=gray-delete.gif}' alt='delete' />{/link}{/if}{*}
    {link href=$_attachment->getDeleteUrl() title='Delete permanently'}<img src='{image_url name=gray-delete.gif}' alt='delete' />{/link}
  </td>
</tr>