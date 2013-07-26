{*}<div class="resource object_attachments object_section" id="object_attachments_{$_object_attachments_object->getId()}" {if !is_foreachable($_object_attachments) && !$_object_attachments_show_empty}style="display: none"{/if}>{*}
<div class="resource object_attachments object_section" id="object_attachments_{$_object_attachments_object->getId()}">
{if $_object_attachments_show_header}
  <div class="head">
    {if $_object_attachments_brief}
      <ul class="attachments_options">
        <li>{link href=$_object_attachments_object->getAttachmentsUrl() class="show_file_details"}{lang}Show Details{/lang}{/link}</li>
        {if $_object_attachments_object->canEdit($logged_user)}
        <li>{link href=$_object_attachments_object->getAttachmentsUrl() class="attach_another_file"}{lang}Attach Another File{/lang}{/link}</li>
        {/if}
		{*}BOF:mod 20121030{*}
		{if $show_reply_to_comment_link}
		<li>
			<button type="button" class="reply_to_this_comment"><span><span>Reply</span></span></button>
		</li>
		{/if}
		{*}EOF:mod 20121030{*}
      </ul>
      <h4>{lang}Attachments{/lang}</h4>
    {else}
    <h2 class="section_name"><span class="section_name_span">
      <span class="section_name_span_span">{lang}Attachments{/lang}</span>
      <ul class="section_options">
		{*BOF:mod 20120119*}
		{*}<li>Import <a href="javascript://" id="ancEmailId" style="background:none;color:#000000;text-decoration:underline;">Email</a> / <a href="javascript://" id="ancTktId" style="background:none;color:#000000;text-decoration:underline;">Ticket ID</a></li>{*}
		<li><a href="mailto:active-collab@projects.ffbh.org?subject={ldelim}ID{$_object_attachments_object->getId()}{rdelim}" title="Click to Email Attachments to this Ticket">{lang}Import Attachment via Email{/lang}</a></li>
		{*EOF:mod 20120119*}
        <li>&nbsp;</li>
      {if $_object_attachments_object->canEdit($logged_user)}
        <li>{link href=$_object_attachments_object->getAttachmentsUrl() class="attach_another_file"}{lang}Attach Another File{/lang}{/link}</li>
      {/if}
      </ul>
      <div class="clear"></div>
    </span></h2>
	{*}<script type="text/javascript">
	  //BOF:mod 20120119
	  var clip1 = new ZeroClipboard.Client();
	  clip1.setText('active-collab@projects.ffbh.org');
	  clip1.glue('ancEmailId');
	  
	  var clip2 = new ZeroClipboard.Client();
	  clip2.setText('{ldelim}ID{$_object_attachments_object->getId()}{rdelim}');
	  clip2.glue('ancTktId');
	  //EOF:mod 20120119
	</script>{*}
    {/if}
  </div>
{/if}

  <div class="body {if $_object_attachments_brief}brief{else}full{/if}">
  {if !is_foreachable($_object_attachments)}
    <p class="details center files_moved_to_trash">{lang}There are no files attached to this object{/lang}</p>
  {/if}
    
    <div class="brief_files_view" id="bfv_{$_object_attachments_object->getId()}" style="display: {if $_object_attachments_brief}block{else}none{/if};">
      <ul>
      {foreach from=$_object_attachments item=_attachment}
        <li class="attachment_{$_attachment->getId()}">
        	{link href=$_attachment->getViewUrl()}{object_attachment_is_image object=$_attachment}{/link}        	
			{link href=$_attachment->getViewUrl()}{$_attachment->getName()|clean}{/link} <span class="details">({$_attachment->getSize()|filesize})</span>
		</li>
      {/foreach}
      </ul>
    </div>
    
    <div class="full_files_view" id="ffv_{$_object_attachments_object->getId()}" style="display: {if $_object_attachments_brief}none{else}block{/if};">
      <table>
        <tbody>
        {assign_var name='_object_attachments_cycle_name'}object_attachments_cycle_{$_object_attachments_object->getId()}{/assign_var}
        {foreach from=$_object_attachments item=_attachment name=att}            
          {if $smarty.foreach.att.iteration < 12}
          {include_template name=_object_attachments_row module=resources controller=attachments}
          {/if}          
        {/foreach}
        </tbody>
      </table>
    </div>
       {*//BOF-20120228SA*}
   {if $_total_attachments > 11}
    <p id="show_all_ticket_attachments"><a id="{$_object_attachments_object->getId()}" href="{$_object_attachments_object->getAttachmentsAllUrl()}">{lang total=$_total_attachments}Show all :total attachments{/lang}</a></p>
  {/if}
    {*//EOF-20120228SA*}
    {if $_object_attachments_object->canEdit($logged_user)}
      <div class="actions">
        <div class="attach_another_file" {if $_object_attachments_show_header}style="display: none"{/if}>
          {form action=$_object_attachments_object->getAttachmentsUrl() method=post enctype="multipart/form-data" class=object_resource_form}
            {wrap field=file}
            {if !$_object_attachments_show_header}
              {label}Attach a File{/label}
            {/if}
              {attach_files}
            {/wrap}
            {button type=submit}Submit{/button}
          {/form}
        </div>
      </div>
    {/if}    
  </div>
</div>
<script type="text/javascript">
  App.resources.ObjectAttachments.init('object_attachments_{$_object_attachments_object->getId()}');
</script>