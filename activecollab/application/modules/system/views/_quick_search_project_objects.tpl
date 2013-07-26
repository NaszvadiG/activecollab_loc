{*
12 April2012 (SA)Ticket #769: modify ac search results to list active tickets first
<div id="quick_search_project_objects_result">
  <h3>{lang}Search Results{/lang}</h3>
{if is_foreachable($results)}
  <table>
{foreach from=$results item=object}
    <tr class="{cycle values='odd,even'}">
      <td class="star">{object_star object=$object user=$logged_user}</td>
      <td class="name"><span style="color:black;">{lang}{$object->getType()}{/lang}:</span>&nbsp;
        {object_link object=$object} <span class="details">{lang}in{/lang} {project_link project=$object->getProject()}</span>
      </td>
    </tr>
{/foreach}
  </table>
  {if $pagination->hasNext()}
  {assign var=items_per_page value=$pagination->getItemsPerPage()}
  <p id="quick_search_more_results"><a href="{assemble route=search q=$search_for type=$search_type search_object=$search_object_type search_project_id=$search_project_id}">{lang count=$pagination->getTotalItems()-$items_per_page}:count more &raquo;{/lang}</a></p>
  {/if}
{else}
  <p>{lang}We haven't found any data in projects that matched your request{/lang}</p>
{/if}
</div>
*}
{assemble route=search q=$search_for type=$search_type search_object=$search_object_type search_project_id=$search_project_id}