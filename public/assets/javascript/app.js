// Do stuff that we need to do on every page...
//12 April 2012 (SA) Ticket #769: modify ac search results to list active tickets first
$(document).ready(function() {
  App.layout.init();
  App.RefreshSession.init();
  App.PrintPreview.init();
  App.widgets.SendReminder.init();

  $('img[src*=imageuploader]').attr('title', 'Click to view full image').css('cursor', 'pointer').click(function(){
  	var win = window.open($(this).attr('src'), '_blank');
  	win.focus();
  	/*var img = new Image();
  	img.src = $(this).attr('src');
  	$(this).attr({'width': img.width, 'height': img.height});*/
  	return false;
  });
  //BOF:mod
  //$('div#menu ul li:first').html('').html('<a class="main" href="javascript://"><span class="outer"><span class="inner" style="padding:10px 8px 9px 8px;"><div style="width:30px;float:left;">[ AR</div> : 000/000 ]<br/><br/><div style="width:30px;float:left;">[ FYI</div> : 000/000 ]</span></span></a>');
  /*$.ajax({
    type : 'POST',
    url : App.extendUrl(App.data.hometab_url, {async : 1, skip_layout : 1}),
    data : {
            show_summary : '1'
            },
    success : function(response) {
        alert(response);
    }
  });*/
  //EOF:mod
  //BOF:mod
  //Line Below added to have focus on search input field
  $('#search_for_input').focus();
  //EOF:mod
});

/** Layout **/
App.layout = function() {

  // Result
  return {

    /**
     * Initialize layout
     */
    init : function() {
      // Preload indicator...
      var indicator = new Image();
      indicator.src = App.data.indicator_url;

      // Jump to project button
      var project_menu_item = $('#menu_item_projects');
	  //BOF:mod 20121211
	  /*
	  //EOF:mod 20121211
      project_menu_item.append('<span class="additional"><a href="' + App.data.jump_to_project_url + '"><span>' + App.lang('Jump to Project') + '</span></a></span>');
      project_menu_item.find('span.additional a').click(function() {
        App.ModalDialog.show('jump_to_project', App.lang('Jump to Project'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(App.data.jump_to_project_url), {});
        return false;
      });
	  //BOF:mod 20121211
	  */
	  project_menu_item.append('<span class="additional mnu_teams"><a><span></span></a></span>');
	  
	  $('span.mnu_teams a').live('click', function(event){
		event.preventDefault();
		$('div#project_list').remove();
		$('body').append('<div id="project_list"><img src="' + App.data.big_indicator_url + '" alt="" style="float:right;" /></div>');
		var offset = $('span.mnu_teams a').offset();
		$('div#project_list').css({
			'position' : 'absolute', 
			//'left' : offset.left+8, 
			//'top' : offset.top+3, 
			'left' : offset.left - 42.5, 
			'top' : offset.top + 39, 
			'z-index' : '1000' 
			//'padding' : '20px', 
			//'background-color' : 'white' 
			//'-webkit-border-radius' : '10px', 
			//'-moz-border-radius' : '10px', 
			//'border-radius' : '10px'
		});
		$.ajax({
			url: App.data.jump_to_project_url,
			success: function(html){
				//$('div#project_list').html('<img id="img_close_drp_teams" src="assets/images/dismiss.gif" style="float:right;margin:-15px -15px 0 0;cursor:pointer;" />' + html);
				$('div#project_list').html(html);
			}
		});
	  });
	  
	  $('select#drp_teams').live('change', function(){
		location.href = $(this).val();
	  });
	  $('img#img_close_drp_teams').live('click', function(){
		$('div#project_list').remove();
	  });
	  //EOF:mod 20121211

      // Search button
      var search_menu_item = $('#menu_item_search a').click(function() {
        var quick_search_url = App.extendUrl($(this).attr('href'), {
          skip_layout : 1,
          async : 1
        });
        App.ModalDialog.show('quick_search', App.lang('Quick Search'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(quick_search_url), {
          buttons : false
        });
        return false;
      });

      $('#page_actions .with_subitems>a').click(function() {
        return false;
      });

      // Quick add button
      $('#menu_item_quick_add a').click(function() {
        var url = App.extendUrl(App.data.quick_add_url, {skip_layout : 1});

        App.ModalDialog.show('quick_add', App.lang('Quick Add'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(url), {
          buttons : false,
          width: 560
        });
        return false;
      });

      // Flash
      $('#success, #error').click(function() {
        $(this).hide('fast');
      });

      // Hoverable
      $('.hoverable').hover(function() {
        $(this).addClass('hover');
      }, function() {
        $(this).removeClass('hover');
      });

      // Card
      $('.card div.options').each(function() {
        wrapper = $(this);
        var first_list_item = wrapper.find('li.first');
        wrapper.find('a').hover(function() {
          first_list_item.text($(this).attr('title'));
        }, function() {
          first_list_item.html('&nbsp;');
        });
      });

      // Scale big images in object description blocks
      $('div.body.content').scaleBigImages();

      $('.button_dropdown').each(function () {
        var dropdown_button = $(this);
        var dropdown_menu = dropdown_button.find('.dropdown_container');
        dropdown_button.hover(function () {

        }, function () {
          dropdown_menu.fadeOut(100);
        }).click(function () {
          if (dropdown_menu.is(':visible')) {
            dropdown_menu.fadeOut(100);
          } else {
            dropdown_menu.fadeIn(100);
          } // if
        });
      });
    },

    /**
     * Init star unstar link
     *
     * @param string id
     * @return null
     */
    init_star_unstar_link : function(id) {
      $('#' + id).click(function() {
        var link = $(this);
        var parent = link.parent();

        // Block additional clicks
        if(link[0].block_clicks) {
          return false;
        } else {
          link[0].block_clicks = true;
        } // if

        var img = link.find('img');
        var old_src = img.attr('src');

        img.attr('src', App.data.indicator_url);

        $.ajax({
          url     : App.extendUrl(link.attr('href'), {async : 1}),
          type    : 'POST',
          data    : {'submitted' : 'submitted'},
          success : function(response) {
            parent.empty();
            parent.append(response);
          },
          error   : function() {
            img.attr('src', old_src);
          }
        });

        return false;
      });
    },

    /**
     * Complete / reopen task
     *
     * @param string id
     */
    init_complete_open_link : function(id) {
      $('#' + id).click(function() {
        var link = $(this);
        var parent = link.parent();

        // Block additional clicks
        if(link[0].block_clicks) {
          return false;
        } else {
          link[0].block_clicks = true;
        } // if

        var img = link.find('img');
        var old_src = img.attr('src');

        img.attr('src', App.data.indicator_url);

        $.ajax({
          url     : App.extendUrl(link.attr('href'), {async : 1}),
          type    : 'POST',
          data    : {'submitted' : 'submitted'},
          success : function(response) {
            parent.empty();
            parent.append(response);
          },
          error   : function() {
            img.attr('src', old_src);
          }
        });

        return false;
      });
    },

    /**
     * Initialize subscribe / unsubscribe link
     *
     * @param string wrapper_id
     * @return null
     */
    init_subscribe_unsubscribe_link : function(wrapper_id) {
      $('#' + wrapper_id + ' a').click(function(e) {
        var link = $(this);
        var parent = link.parent();

        // Block additional clicks
        if(link[0].block_clicks) {
          return false;
        } else {
          link[0].block_clicks = true;
        } // if

        var img = link.find('img');
        var old_src = img.attr('src');

        img.attr('src', App.data.indicator_url);

        $.ajax({
          url     : App.extendUrl(link.attr('href'), {async : 1}),
          type    : 'POST',
          data    : {'submitted' : 'submitted'},
          success : function(response) {
            parent.empty();
            parent.append(response);
          },
          error   : function() {
            img.attr('src', old_src);
          }
        });

        return false;
      });
    },

    /**
     * Reindex opened tasks table, change colors of rows, and display hidden row if necessarry
     *
     * @param string table
     * @return null
     */
    reindex_task_table: function (table) {
      table = $(table);
      var counter = 0;
      table.find('li:not(.empty_row):not(.ui-sortable-helper):not(.sort_placeholder)').each(function() {
        row = $(this);
        if ((counter % 2) == 1) {
          row.removeClass('odd');
          row.addClass('even');
        } else {
          row.removeClass('even');
          row.addClass('odd');
        } // if
        counter++;
      });

      if (counter<1) {
        table.find('.empty_row').show();
      } else {
        table.find('.empty_row').hide();
      } // if
    },

    /**
     * Init row in tasks table
     *
     * @param object row
     * @param object wrapper
     */
    init_object_task: function (row, wrapper) {
      if (wrapper.drag_enabled==true) {
        row.find('.drag_handle').show();
      } else {
        row.find('.drag_handle').hide();
      }

      // complete task
      row.find('a.complete_task').click(function() {
        var link = $(this);
        var complete_tasks_table = link.parents('.object_tasks').find('.completed_tasks_table');

        // Block additional clicks
        if(link[0].block_clicks) {
          return false;
        } else {
          link[0].block_clicks = true;
        } // if

        var img = link.find('img');
        var old_src = img.attr('src');

        //BOF:mod 20110617
        var img_src = img.attr('src');
        //EOF:mod 20110617
        img.attr('src', App.data.indicator_url);
        $.ajax({
          url     : link.attr('href'),
          type    : 'POST',
          data    : {'submitted' : 'submitted'},
          success : function(response) {
          	//BOF:mod 20110617
          	if (response.indexOf('Message:')==-1){
          	//EOF:mod 20110617
	            var response_obj = $(response);
	            var open_tasks_table = link.parents('.object_tasks').find('.tasks_table');
	            complete_tasks_table.prepend(response_obj);
	            row.remove();
				var new_recurring_task_id = complete_tasks_table.find('li:eq(0)').attr('new_recurring_task_id');
				if (new_recurring_task_id!=''){
					open_tasks_table.append(complete_tasks_table.find('li:eq(1)'));
					complete_tasks_table.find('li:eq(1)').remove();
				}
	            App.layout.init_object_task(response_obj,wrapper);
	            App.layout.reindex_task_table(open_tasks_table);
	        //BOF:mod 20110617
			} else {
				img.attr('src', img_src);
				alert(response.replace('<div>', '').replace('</div>', ''));
			}
			//EOF:mod 20110617
          },
          error   : function() {
            img.attr('src', old_src);
          }
        });

        return false;
      });

      // open task
      row.find('a.open_task').click(function() {
        var link = $(this);
        var open_tasks_table = link.parents('.object_tasks').find('.open_tasks_table');

        // Block additional clicks
        if(link[0].block_clicks) {
          return false;
        } else {
          link[0].block_clicks = true;
        } // if

        var img = link.find('img');
        var old_src = img.attr('src');

        img.attr('src', App.data.indicator_url);

        $.ajax({
          url     : link.attr('href'),
          type    : 'POST',
          data    : {'submitted' : 'submitted'},
          success : function(response) {
            var response_obj = $(response);
            open_tasks_table.append(response_obj);
            row.remove();
            App.layout.init_object_task(response_obj,wrapper);
            App.layout.reindex_task_table(open_tasks_table);
          },
          error   : function() {
            img.attr('src', old_src);
          }
        });

        return false;
      });

      // Remove buttons
      row.find('a.remove_task').click(function() {
	    if (confirm('Delete this entry?')){
        var link = $(this);

        // Block additional clicks
        if(link[0].block_clicks) {
          return false;
        } else {
          link[0].block_clicks = true;
        } // if

        var img = link.find('img');
        var old_src = img.attr('src');

        img.attr('src', App.data.indicator_url);

        $.ajax({
          url     : App.extendUrl(link.attr('href'), {'async' : 1}),
          type    : 'POST',
          data    : {'submitted' : 'submitted'},
          success : function() {
            row.remove();
          },
          error   : function() {
            img.attr('src', old_src);
          }
        });
		}
        return false;
      });
    },

    /**
     * Initialize tasks table
     *
     * @param string wrapper_id ID of wrapper div
     */
    init_object_tasks : function(wrapper_id, enable_reordering) {
        var wrapper = $('#' + wrapper_id);
        var form_wrapper = wrapper.find('div.add_task_form');
        var show_form = wrapper.find('a.add_task_link');
        var hide_form = wrapper.find('a.cancel_button');
        var active_tasks_table = wrapper.find('.tasks_table.open_tasks_table');
		//BOF:mod 20121126
		//$('img[id^="snooze_"]').click(function(){
		$('img[id^="snooze_"]').live('click', function(){
			var url = App.extendUrl($(this).parent().attr('href'), {skip_layout : 1});

			App.ModalDialog.show(
				'snooze_reminder', 
				App.lang('Snooze Reminder'), 
				$('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(url), 
				{
					buttons : [
					{
						label: App.lang('Snooze'), 
						callback: function(){
							$('form[name="frm_snooze"]').submit();
						}
					}, 
					{
						label: App.lang('Close'), 
						callback: function(){
							App.ModalDialog.close();
						}
					} 
					], 	
				width: 560
				}
			);
		});
		//EOF:mod 20121126
		//BOF:mod 20120817
		var max_visible_height = '20px';
		var show_more_label = 'More';
		var hide_more_label = 'Hide';

		$('a#expand_all_tasks').click(function(){
			if ($(this).find('span').text()==show_more_label){
				$(this).find('span').text(hide_more_label);
				$('span.option p#showmore').each(function(){
					$(this).html(hide_more_label).parent().parent().parent().find('span.main_data div').css({'height' : 'auto', 'overflow' : 'visible'});
				});
			} else if ($(this).find('span').text()==hide_more_label){
				$(this).find('span').text(show_more_label);
				$('span.option p#showmore').each(function(){
					var elem = $(this).html(show_more_label).parent().parent().parent().find('span.main_data div');
					if (parseInt($(elem).height()) > parseInt(max_visible_height) ){
						$(elem).css({'height' : max_visible_height, 'overflow' : 'hidden'});
					}
				});
			}
		});
		
		$('li.sort span.main_data div').each(function(){
			if (parseInt($(this).height()) > parseInt(max_visible_height) ){
				$(this).css({'height' : max_visible_height, 'overflow' : 'hidden'}).parent().parent().find('span.option p#showmore').css('visibility', 'visible').html(show_more_label);
			} else {
				//$(this).parent().parent().find('span.option p#showmore').css('visibility', 'hidden');
				$(this).css({'height' : max_visible_height}).parent().find('span.option p#showmore').css('visibility', 'hidden');
				$(this).find('span p:eq(0)').css('margin', '0');
				$(this).find('span[id^="task_"]').css('top', '0');
			}
		});

		$('li.sort span.main_data div span[id^="task_"]').hover(
			function(){
				if (!document.getElementById('task_text_div')){
					var task_text_div = document.createElement('div');
					task_text_div.setAttribute('id', 'task_text_div');
					$('body').append(task_text_div);
					
					$('#task_text_div')
					.css({
						'position': 'absolute', 
						'z-index': '100', 
						'width': '500px', 
						'font': '11px "Lucida Grande", Verdana, Verdana, Arial, Helvetica, sans-serif', 
						'color': '#333', 
						'text-align': 'left', 
						'padding': '5px', 
						'border': '5px solid rgb(60, 88, 175)', 
						'border': '5px solid rgba(60, 88, 175, .5)', 
						'border-top-width' : '15px', 
						'-webkit-background-clip' : 'padding-box', 
						'background-clip' : 'padding-box',
						'-webkit-border-radius' : '10px', 
						'-moz-border-radius' : '10px', 
						'border-radius' : '10px'
					})
					.bind('mouseleave', function(){
						$(this).css({'display': 'none'}).html('');
					});
				}
				var content = $(this).parent().find('span#task_content').html();
				
				var assignees_info = $(this).parent().find('span#assignees_content').html();
				if (assignees_info!='' && assignees_info!=null){
					content += '<br/>' + assignees_info;
				}
				
				var due_info = $(this).parent().find('span#dueon_content').html();
				if (due_info!='' && due_info!=null){
					content += '<br/>' + due_info;
				}
				
				var recurring_info = $(this).parent().find('span.recurring').html();
				if (recurring_info!='' && recurring_info!=null){
					content += '<br/>' + recurring_info;
				}
				$('#task_text_div').css({
					'display': '' 
					 });
				$('#task_text_div').html(content);
				$('#task_text_div').css({
					//'display': '', 
					//'top': $(this).offset().top+10, 
					//'left': $(this).offset().left+200, 
					'top': $(this).offset().top - $('#task_text_div').outerHeight()-20, 
					'left': $(this).offset().left - 50, 
					'background-color': '#ffffff', 
					'margin': '10px' });
			},
			function(){
				$('#task_text_div').css({'display': 'none'}).html('');
			}
		);
		
		$('span.option p#showmore').click(function(){
			if ($(this).html().toLowerCase().indexOf('more')!=-1)
				$(this).html(hide_more_label).parent().parent().parent().find('span.main_data div').css({'height' : 'auto', 'overflow' : 'visible'});
			else if ($(this).html().toLowerCase().indexOf('hide')!=-1)
				$(this).html(show_more_label).parent().parent().parent().find('span.main_data div').css({'height' : max_visible_height, 'overflow' : 'hidden'});
				
		});
		//EOF:mod 20120817
		
        //BOF:mod
        var add_task_top_link = wrapper.find('a.top_link');

        form_wrapper.find('#ancEditType').click(function(){
        	if ($(this).html()=='Quick edit'){
        		form_wrapper.find('#span_task_summary_plain').css('display', 'block');
        		form_wrapper.find('#span_task_summary_plain #taskSummary_').attr('name', 'task[body]');
        		form_wrapper.find('#span_task_summary_editor').css('display', 'none');
        		form_wrapper.find('#span_task_summary_editor #taskSummary').attr('name', 'task[body_editor]');
        		$(this).html('Text editor');
				//tinyMCE.execCommand('mceRemoveControl', true, 'taskSummary');
        		//$('#form_new_task').find('#mode').val('plain');
        		$('#span_task_summary_plain #taskSummary_').focus();
        	} else {
        		form_wrapper.find('#span_task_summary_plain').css('display', 'none');
        		form_wrapper.find('#span_task_summary_plain #taskSummary_').attr('name', 'task[body_plain]');
        		form_wrapper.find('#span_task_summary_editor').css('display', 'block');
        		form_wrapper.find('#span_task_summary_editor #taskSummary').attr('name', 'task[body]');
        		$(this).html('Quick edit');
				//tinyMCE.execCommand('mceRemoveControl', true, 'taskSummary');
				//tinyMCE.execCommand("mceAddControl", true, 'taskSummary');
        		//$('#form_new_task').find('#mode').val('editor');
        	}
			var obj = $('div.form_left_col');
			var top = obj.offset().top;
			$('html, body').animate({scrollTop: top}, 1000);
        });
		//tinyMCE.execCommand('mceRemoveControl', true, 'taskSummary');
		//tinyMCE.execCommand("mceAddControl", true, 'taskSummary');
        //EOF:mod

        form_wrapper.find('.show_due_date_and_priority a').click(function () {
          $(this).parent().hide();
          form_wrapper.find('.due_date_and_priority').slideDown();
          return false;
        });
        form_wrapper.find('.due_date_and_priority').hide();

        // Submit add task form
        form_wrapper.find('form').submit(function() {
          var form = $(this);
          //if(UniForm.is_valid(form)) {
            var old_form_action = form.attr('action');

            form.attr('action', App.extendUrl(old_form_action, {async : 1}));

            var loading_row = '<li><img src="' + App.data.indicator_url + '" alt="loading" /> <strong>' + App.lang('Working') + '</strong></li>';
            active_tasks_table.append(loading_row);
            var temp_row = active_tasks_table.find('li:last');

            // submit form via ajax
            form.ajaxSubmit({
              success : function(response) {
                var response_obj = $(response);
                // insert real row in table
                response_obj.insertAfter(temp_row);
                // remove fake row
                temp_row.remove();
                App.layout.init_object_task(response_obj, wrapper);
                App.layout.reindex_task_table(active_tasks_table);
                //BOF:mod
                //document.getElementById('taskSummary').value = '';
                //$("#taskSummary").val('');
                //EOF:mod
                try{
                //tinyMCE.getInstanceById('taskSummary').execCommand('mceSetContent', false, '');
                } catch(e){}
              },
              error : function (response) {
                // remove fake row
                temp_row.remove();
              }
            });

            // empty task message
            //form.attr('action', old_form_action).find('input:first').val('').focus();
            //$('span#prev_content').html(form.attr('action', old_form_action).find('.taskSummaryText').val());
			form.attr('action', old_form_action).find('#taskSummary').val('').focus();
            try{
            tinyMCE.getInstanceById('taskSummary').execCommand('mceSetContent', false, '');
            } catch(e){}
          //} // if
          return false;
        });

        // Show task form
        show_form.click(function() {
          show_form.hide();
          $('.main_object .resource.object_tasks').show();
          form_wrapper.show().focusFirstField();
          //BOF:mod
          try{
				var attr_val = $(this).attr('class');
				if (attr_val.indexOf('top_link')!=-1){
					var obj = $('div.form_left_col');
					var top = obj.offset().top;
					$('html, body').animate({scrollTop: top}, 1000);
				}
          } catch(e){}
        		form_wrapper.find('#span_task_summary_plain').css('display', 'none');
        		form_wrapper.find('#span_task_summary_plain #taskSummary').attr('name', 'task[body_plain]');
        		form_wrapper.find('#span_task_summary_editor').css('display', 'block');
        		form_wrapper.find('#span_task_summary_editor #taskSummary').attr('name', 'task[body]');
        		$(this).html('Text editor');
        		//$('#form_new_task').find('#mode').val('plain');
                        //$('#span_task_summary_plain #taskSummary').focus();

                        tinyMCE.getInstanceById('taskSummary')._iframe_element.css('height', '100px');
          //EOF:mod
          return false;
        });

        $('#object_quick_option_new_task a').click(function () {
          show_form.hide();
          $('.main_object .resource.object_tasks').show();
          form_wrapper.show().focusFirstField();
          $('#span_task_summary_plain #taskSummary').focus();
          try{
            var obj = $('div.form_left_col');
            var top = obj.offset().top;
            $('html, body').animate({scrollTop: top}, 1000);
          } catch(e){}
          return false;
        });

        // Hide task form
        hide_form.click(function() {
          show_form.show();
          form_wrapper.clearErrorMessages().hide();
          form_wrapper.find('input:eq(0)').val('');
          return false;
        });

        form_wrapper.find('input').keypress(function(e) {
          if (e.keyCode == 27) {
            hide_form.click();
            return false;
          } else if (e.keyCode==13){
          	/*if ($('span#prev_content').html()!=''){
          		$(this).val($('span#prev_content').html());
          	}
          	return false;*/
          	return true;
          } // if
        });

        if (enable_reordering > 0) {
          // init sortable behvaiour
          wrapper.find('.open_tasks_table').sortable({
            axis : 'y',
            cursor: 'move',
            items: 'li.sort',
            delay: 3,
            revert: false,
            connectWith: ['.open_tasks_table'],
            tolerance : 'pointer',
            placeholder: 'sort_placeholder',
            forcePlaceholderSize : false,
			sort: function(){
				var is_checklist_page = location.href.indexOf('checklists')!=-1 ? true : false;
				var completed_tasks_exist = $('ul.completed_tasks_table li').length>=1 ? true : false;
				if (is_checklist_page && completed_tasks_exist){
					alert('Sort order can be changed only when 100% of the tasks are marked as Active');
					return false;
				}
			},
            update: function (e, ui) {
              var sort_form = $(this).parents('form.sort_form');
              ui.item.parent().attr('style','');
              sort_form.ajaxSubmit({
                method : 'POST'
              });
              App.layout.reindex_task_table($(this));
            },
            over: function (table_object,ui) {
              $(this).addClass('dragging');
            },
            out: function (table_object,ui) {
              $(this).removeClass('dragging');
            },
            receive : function (event, ui) {
              App.layout.reindex_task_table($(this));
            },
            remove : function (event, ui) {
              App.layout.reindex_task_table($(this));
            }
          });
        } // if

        // init every row in table
        wrapper.find('.tasks_table li, .completed_tasks_table li').each(function () {
          App.layout.init_object_task($(this), wrapper);
        });

        // 'view all completed' behaviour
        wrapper.find('.completed_tasks_table li.list_all_completed a').click(function () {
          var anchor = $(this);
          var completed_tasks_table = anchor.parents('ul.completed_tasks_table:first');
          anchor.after('<span class="loading"><img src="' + App.data.indicator_url + '" alt="" />' + App.lang('Loading...') + '</a>');
          var loading_block = anchor.parent().find('.loading:first');
          anchor.hide();

          $.ajax({
            url : App.extendUrl(anchor.attr('href'), {async : 1, skip_layout : 1}),
            success : function (response) {
              completed_tasks_table.html(response);
              completed_tasks_table.find('li').each(function () {
                App.layout.init_object_task($(this), wrapper);
              });
            },
            error : function () {
              loading_block.remove();
              anchor.show();
            }
          });
          return false;
        });
    }
  } // init

}();

/**
 * Modal dialog module
 */
App.ModalDialog = function() {

  /**
   * Current dialog reference
   *
   * @var jQuery
   */
  var dialog_object;

  // Let's return public interface object
  return {

    /**
     * Show modal dialog
     *
     *
     * @param String name
     * @param String title
     * @param mixed body
     * @param mixed settings
     */
    show : function(name, title, body, settings) {
      // dialog options
      var options = {
        modal     : true,
        draggable : false,
        resizable : true,
        title     : title,
        id        : name,
        position  : 'top',
        bgiframe  : true,
        close     : function (type,data) {
          if (settings.close) {
            settings.close();
          } // if
          dialog_object.dialog('destroy').remove();
        },
        resizeStart : function (type,data) {

        }
      };

      if (settings) {
        // width and height settings
        options.width = settings.width ? settings.width : 410;
        options.height = settings.height ? settings.height : 'auto';
        // additional buttons
        options.buttons = {};
        if (settings && settings.buttons) {
          for (var x = 0; x < settings.buttons.length; x++) {
            if (settings.buttons[x].callback) {
              options.buttons[settings.buttons[x].label] = settings.buttons[x].callback;
            } else {
              options.buttons[settings.buttons[x].label] = function () {
                dialog_object.dialog('close');
              } // function
            } // if
          } // if
        } // if
      } // if

      options.maxWidth = options.width;
      options.minWidth = options.width;

      dialog_object = $(body).dialog(options);

      var counter = 0;
      dialog_object.parent().parent().find('.ui-dialog-buttonpane button').each(function () {
        var button = $(this);
        button.removeClass('ui-state-default').removeClass('ui-corner-all');

        var label = button.html();
        button.html('<span><span>' + label + '</span></span>');
        if (counter != 0) {
          button.addClass('alternative');
        } // if
        counter++;
      });
    },

    /**
     * Close the dialog
     */
    close : function() {
      dialog_object.dialog('destroy').remove();
    },

    /**
     * sets width of dialog
     */
    setWidth : function (width_px) {
      var dom_dialog = $('.ui-dialog');
      var position = dom_dialog.position();
      var new_left_offset = position.left - ((width_px - dom_dialog.width())/2);
      dom_dialog.css('width' , width_px+'px').css('left', new_left_offset+'px');
    },

    /**
     * Sets dialog title
     */
    setTitle : function (title) {
     var dom_dialog = $('.ui-dialog .ui-dialog-titlebar span.ui-dialog-title').html(title);
    },

    /**
     * Checks if dialog is open
     */
    isOpen : function () {
      if ($('.ui-dialog').length > 0) {
        return true;
      } else {
        return false;
      }
    }
  };

}();


/**
 * Print preview module
 */
App.PrintPreview = function() {
  /**
   * Dom element of main css
   *
   * @var jQuery
   */
  var css_main;
  /**
   * Dom element of theme css
   *
   * @var jQuery
   */
  var css_theme;
  /**
   * Dom element of css preview
   *
   * @var jQuery
   */
  var css_print_preview;

  // Return value
  return {

    /**
     * Initialize print preview behavior
     *
     * @param void
     * @return null
     */
    init : function() {
      $('#print_button').click(function(e) {
        App.PrintPreview.open();
        e.stopPropagation();
        return false;
      });

      $('#print_preview_header #print_preview_close').click(function() {
        App.PrintPreview.close();
        return false;
      });

      $('#print_preview_header #print_preview_print').click(function() {
        window.print();
        return false;
      });

      css_main = $('#style_main_css');
      css_theme = $('#style_theme_css');
      css_print_preview = $('#print_preview_css');
    },

    /**
     * Show print preview view
     *
     * @param void
     * @return null
     */
    open : function() {
        css_main.attr('disabled', true);
        css_theme.attr('disabled', true);

        if ($.browser.msie == true) {
          $('#print_preview_css').each(function () {
            // please don't ask me why i did this stupendity
            this.disabled = false;
            this.disabled = true;
            this.disabled = false;
          });
        } else {
          $('#print_preview_css').attr('rel','stylesheet').each(function () {
            this.disabled = false;
          });
        } // if
    },

    /**
     * Close print preview view
     *
     * @param void
     * @return null
     */
    close : function() {
        css_main.attr('disabled', false);
        css_theme.attr('disabled', false);
        if ($.browser.msie == true) {
          css_print_preview.each(function () {
            // please don't ask me why i did this stupendity
            this.disabled = true;
            this.disabled = false;
            this.disabled = true;
          });
        } else {
          css_print_preview.attr('rel','stylesheet').each(function () {
            this.disabled = true;
          });
        } // if
    }

  };

}();

/**
 * Comment options behavior
 */
App.CommentOptions = function() {

  /**
   * Result
   */
  return {

    /**
     * Initialize
     *
     * @param string wrapper_id ID of warpper div
     * @return void
     */
    init : function(wrapper_id) {
      $('#' + wrapper_id).each(function() {
        var wrapper = $(this);
        var first_element = wrapper.find('li.comment_options_first');

        wrapper.find('a, span').hover(function() {
          first_element.html($(this).attr('title'));
        }, function() {
          first_element.html('&nbsp;');
        });
      });
    } // init

  }

}();

App.EmailObject = function() {
  return {
    init : function (object_id) {
      var email_object = $('#'+object_id);
      var blockquotes = email_object.find('>blockquote');
      blockquotes.each(function () {
        var blockquote = $(this);
        if (!blockquote.parent().is('div.content')) {
          blockquote = blockquote.parent();
        } // if
        blockquote.before('<a href="#" class="hidden_history" style="display:none;">' + App.lang('Hidden Email History') + '</a>');
        blockquote.hide();
        var blockquote_anchor = blockquote.prev();

        blockquote_anchor.click(function () {
          blockquote.slideDown();
          $(this).remove();
          return false;
        });
      });
    }
  }
}();

// Refresh session requests
App.RefreshSession = function() {

  /**
   * Interval object used to call refresh function
   */
  var refresh_interval = null;

  // Return value
  return {

    /**
     * Initialize refresh interval
     *
     * @params void
     * @return void
     */
    init : function() {
      if(App.data.keep_alive_interval > 0) {
        refresh_interval = setInterval('App.RefreshSession.refresh()', App.data.keep_alive_interval);
      } // if
    },

    /**
     * Function used to refresh session
     *
     * @param void
     * @return null
     */
    refresh : function() {
      $.ajax({
        url : App.data.refresh_session_url
      });
    }
  }

}();

/**
 * Quick search module
 */
App.QuickSearch = function() {

  // Public interface
  return {

    /**
     * Initialize quick search form
     *
     * @param void
     * @return undefined
     */
    init : function() {
      $('#quick_search_form').submit(function() {
        $('#quick_search_button').hide();
        $('#quick_search_indicator').show();

        var form = $(this);
        var results = $('#quick_search_results');

        results.empty();

        $.ajax({
          type : 'POST',
          url : App.extendUrl(form.attr('action'), {async : 1}),
          data : {
            submitted : 'submitted',
            search_for : $('#quick_search_input').val(),
            search_type : $('#quick_search_type').val(),
            search_object_type : $('#search_object_type').val(),
            search_project_id : $('#search_project_id').val()
          },
          success : function(response) {
        	  location.href = response;
            /*results.append(response);

            $('#quick_search_button').show();
            $('#quick_search_indicator').hide();*/
          }
        });
        return false;
      });

      $('#search_object_type').change(function(){
      	$('#quick_search_form').submit();
      });

      $('#quick_search_form ul li').click(function() {
        var list_element = $(this);

        $('#quick_search_form ul li').removeClass('selected');
        list_element.addClass('selected');

        $('#quick_search_type').val(list_element.attr('id').substr(7));
      });

      $('#quick_search_form #quick_search_input')[0].focus();

      $('#search_for_people, #search_for_projects').click(function(){
		$('#search_object_type, #search_project_id').css('visibility', 'hidden').val('');
	  });
      $('#search_in_projects').click(function(){
		$('#search_object_type, #search_project_id').css('visibility', 'visible');
	  });

    }
  };

}();

/**
 * Functions for main menu
 */
App.MainMenu = function() {
  var menu

  // Public interface
  return {

    /**
     * Initialize main menu
     *
     * @param void
     * @return undefined
     */
    init : function(menu_id) {
      menu = $('#'+menu_id);
    },

    /**
     * add item to menu
     *
     *  @param object item
     *  @param string group_id
     *  @return null
     */
    addToGroup: function (item, group_id) {
      var button_class = 'last';

      var group = $('#menu_group_'+group_id, menu);
      if (group.length > 0) {
        var button_text = "<li id='menu_item_" + item.id + "' class='item " + button_class + "'>";
        button_text +=    "<a class='main' href='" + item.href + "'><span class='outer'>";
        button_text +=    "<span style='background-image: url(" + item.icon + ");' class='inner'>";
        if (item.badge_value > 0) {
          button_text +=    "<span class='badge'>" + item.badge_value + "</span>"
        } // if
        button_text +=    item.label;
        button_text +=    "</span>";
        button_text +=    "</span></a>";
        button_text +=    "</li>";
        $('li.item:last', group).removeClass('last').removeClass('single').addClass('middle');
        group.append(button_text);
      } // if
    },

    /**
     * Check if item with id item exists in group with group_id
     *
     *  @param string item
     *  @param string group_id
     *  @return bolean
     */
    itemExists: function (item_id, group_id) {
      var group = $('#menu_group_'+group_id, menu);
      if (group.length > 0) {
        var menu_item = $('#menu_item_' + item_id, group);
        if (menu_item.length > 0) {
          return true;
        } // if
      };
      return false;
    },

    /**
     * Remove item if exists
     *
     *  @param string item
     *  @param string group_id
     *  @return bolean
     */
    removeButton: function (item_id, group_id) {
      var group = $('#menu_group_'+group_id, menu);
      if (group.length > 0) {
        var previous_class = 'last'
        if ($('li', group).length <= 2) {
          previous_class = 'single';
        } // if
        $('#menu_item_' + item_id, group).remove();
        $('li:last', group).removeClass('middle').addClass(previous_class);
      };
    },

    /**
     * Set badge value for item
     *
     *  @param string item
     *  @param string group_id
     *  @param string badge_value
     *  @return bolean
     */
    setItemBadgeValue: function (item_id, group_id, badge_value) {
      var group = $('#menu_group_'+group_id, menu);
      if (group.length > 0) {
        var menu_item = $('#menu_item_' + item_id, group);
        if (menu_item.length) {
          if (badge_value > 0) {
            var badge = $('span.badge', menu_item);
            if (badge.length > 0) {
              badge.text(badge_value);
            } else {
              $('a>span>span', menu_item).prepend('<span class="badge">' + badge_value + '</span>');
            } // if
            return true;
          } else {
            $('span.badge', menu_item).remove();
            return true;
          } // if
        } // if
      } // if
      return false;
    }
  };
}();

/*
App.Menu = function() {

  return {
    set_badge_value : function(item_id, value) {
      if(value > 0) {
        var parent = $('#' + item_id + '>a>span>span');
        var badge = parent.find('span.badge');
        if(badge.length > 0) {
          badge.text(value);
        } else {
          parent.prepend('<span class="badge">' + value + '</span>');
        } // if
      } else {
        $('#' + item_id + ' span.badge').remove();
      }
    }

  };

}();
*/

function sort_page(order_by){
	try{
		var key_order_by = 'order_by';
		var key_order_by_index = -1;
		var order_by_exists = false;
		var key_sort_order = 'sort_order';
		var key_sort_order_index = -1;
		var sort_order_value = ';'
		var url_str = location.href;
		var url = url_str.substring(0, url_str.indexOf('?'));
		var query_string = url_str.substring(url_str.indexOf('?')+1);
		var values = query_string.split('&');
		for(var i=0; i<values.length; i++){
			if (values[i].indexOf(key_order_by)!=-1){
				order_by_exists = true;
				key_order_by_index = i;
			}
			if (values[i].indexOf(key_sort_order)!=-1){
				sort_order_value = values[i].substring(values[i].indexOf('=')+1);
				key_sort_order_index = i;
			}
		}
		if (order_by_exists){
			sort_order_value = (sort_order_value=='asc' ? 'desc' : 'asc');
		} else {
			sort_order_value = 'asc';
		}
		if (key_order_by_index==-1){
			values.push(key_order_by + '=' + order_by);
		} else {
			values[key_order_by_index] = key_order_by + '=' + order_by;
		}
		if (key_sort_order_index==-1){
			values.push(key_sort_order + '=' + sort_order_value);
		} else {
			values[key_sort_order_index] = key_sort_order + '=' + sort_order_value;
		}
		location.href = url + '?' + values.join('&');
	} catch(e){
		alert(e);
	}
}

function no_notification_checkbox_onclick(ref){
	alert(ref.checked);
}

//BOF:mod
/*App.EditTask = function(){
	return {
		init : function(){
			var form = $('#form_edit_task');
			alert(form);
			form.find('#ancEditType').click(function(){
		       	if ($(this).html()=='Quick edit'){
		       		form.find('#span_task_summary_plain').css('display', 'block');
		       		form.find('#span_task_summary_plain #taskSummary').attr('name', 'task[body]');
		       		form.find('#span_task_summary_editor').css('display', 'none');
		       		form.find('#span_task_summary_editor #taskSummary').attr('name', 'task[body_editor]');
		       		$(this).html('Text editor');
		       		$('#span_task_summary_plain #taskSummary').focus();
		       	} else {
		       		form.find('#span_task_summary_plain').css('display', 'none');
		       		form.find('#span_task_summary_plain #taskSummary').attr('name', 'task[body_plain]');
		       		form.find('#span_task_summary_editor').css('display', 'block');
		       		form.find('#span_task_summary_editor #taskSummary').attr('name', 'task[body]');
		       		$(this).html('Quick edit');
		       	}
			});
		}
	};
}();*/
//EOF:mod

function set_complete_status(drpRef){
	if (drpRef.options[drpRef.selectedIndex].value=='complete'){
		var ids = '';
		var frm = document.forms['entries'];
		for( var i=0; i<frm.elements.length; i++){
			if (frm.elements[i].type=='checkbox' && frm.elements[i].checked)
			ids += frm.elements[i].value + ',';
		}
		if (ids!=''){
			ids = ids.substring(0, ids.length-1);
		}
		location.href = location.href + '&mark_completed=' + ids
	}
}

//BOF:task_1260
function set_action_request_fyi_flag(chkRef, inbox_page_mode){
	try{
		var id = chkRef.id;
		var temp = id.split('_');
		if (temp.length==7){
			var container = chkRef.parentNode.parentNode;
			chkRef.parentNode.innerHTML = '<img src="' + App.data.indicator_url + '">';
			var action_type = temp[1]=='action' ? 'actionrequest' : 'fyi';
			var comment_id 	= temp[4];
			var user_id 	= temp[6];
			var url = location.href.substring(0, location.href.indexOf('?'));
			var qString = location.href.substring(location.href.indexOf('?')+1);
			if (qString.indexOf('&')!=-1){
				qString = qString.substring(0, qString.indexOf('&'));
			}
			var val = unescape(qString.replace(/path_info=/i, ''));
			var temp02 = val.split('/');
			temp02[2] = 'comments';
			temp02[3] = comment_id;
			temp02[4] = (action_type=='actionrequest' ? 'actionrequestcompleted' : 'fyiread');
			val = temp02.join('%2F');
			var ajax_url = url + '?path_info=' + val;
        	$.ajax({
          		type : 'POST',
          		url : App.extendUrl(ajax_url, {async : 0, skip_layout : 1}),
          		data : {
            		user_id : user_id,
            		comment_id : comment_id
          		},
          		success : function(response) {
					try{
						//alert(response);
						//$("div#" + (action_type=='action' ? 'action_request_div' : 'fyi_div') + " div #" + chkRef.id).html('');
						//var prnt = chkRef.parentNode;
						//$("div#action_request_div").css('backround-color', 'yellow');
						if (inbox_page_mode){
							var anc = container.getElementsByTagName('a')[0];
							var form = document.getElementById('fyi_read_comments');
							var div = document.createElement('div');
							div.appendChild(anc);
							div.appendChild(document.createElement('hr'));
							form.appendChild(div);
						}
						container.innerHTML = '';
					} catch(e) {
						alert('ERROR:/n' + e);
					}
          		}
        	});
		}
	} catch(e){
		alert(e);
	}
}
//EOF:task_1260

function on_recurring_flag_selected(radio_elem_ref){
	try{
		var div = document.getElementById('recurring_params');
		if (radio_elem_ref.value=='1'){
			div.style.visibility = 'visible';
		} else if (radio_elem_ref.value=='0'){
			div.style.visibility = 'hidden';
		}
	} catch(e){
		alert(e);
	}

}

//BOF:mod 20110623
function auto_select_checkboxes(elemref){
	/*try{
		user_id = elemref.value;
		if (user_id.indexOf('_')!=-1){
			user_id = user_id.substring(0, user_id.indexOf('_'));
		}
		if ((elemref.type=='checkbox' && elemref.checked) || (elemref.type=='select-one' && elemref.options.selectedIndex>0)){
			$("input[type='checkbox'][value='" + user_id + "'][id^='select_assignees_']").attr('checked', 'true');
		}
		if (elemref.type=='select-one' && elemref.options.selectedIndex>0){
			$("input[type='checkbox'][value='" + user_id + "'][name*='flag_actionrequest']").attr('checked', 'true');
		}
	} catch(e){
		alert(e);
	}*/
    //BOF:mod
    try{
        var user_id = elemref.value;
        var action_req = $("input[type='checkbox'][value='" + user_id + "'][name='assignee[flag_actionrequest][]']");
        var fyi = $("input[type='checkbox'][value='" + user_id + "'][name='assignee[flag_fyi][]']");
        var email = $("input[type='checkbox'][value='" + user_id + "'][name='assignee[flag_email][]']");

        var user = $("input[type='checkbox'][value='" + user_id + "'][id^='select_assignees_']");

        if ($(action_req).attr('checked') || $(fyi).attr('checked') || $(email).attr('checked')){
            $(user).attr('checked', 'checked');
            $(user).next().addClass('user_selected');
        } else {
            $(user).attr('checked', '');
            $(user).next().removeClass('user_selected');
        }
    } catch(e){}
    //EOF:mod
}

function highlight_user(elemref){
    try{
        /*
        var chk;
        var drp;
        if (elemref.type=='checkbox'){
            chk = elemref;
            drp = $(elemref).parent().parent().children(':eq(1)').find('select');
        } else if (elemref.type=='select-one'){
            drp = elemref;
            chk = $(elemref).parent().parent().children(':eq(2)').find('input[type="checkbox"]');
        }
        $(elemref).parent().parent().children(':eq(0)').css('font-weight', (chk.checked || $(drp).val().indexOf('-99')==-1 ? 'bolder' : 'normal'));
        */
       var tr = $(elemref).parent().parent();
       var chk_action_request = $(tr).children(':eq(1)').find('input[type="checkbox"]');
       var chk_fyi = $(tr).children(':eq(2)').find('input[type="checkbox"]');
       var chk_email = $(tr).children(':eq(3)').find('input[type="checkbox"]');
       $(tr).children(':eq(0)').css('font-weight', ($(chk_action_request).is(':checked') || $(chk_fyi).is(':checked') || $(chk_email).is(':checked') ? 'bolder' : 'normal') );
       
    } catch(e){
        //alert(e);
    }
}

function auto_select_action_request_checkbox(elemref){
	try{
		user_id = elemref.value;
		if (user_id.indexOf('_')!=-1){
			user_id = user_id.substring(0, user_id.indexOf('_'));
		}
		if (elemref.type=='select-one' && elemref.options.selectedIndex>0){
			$("input[type='checkbox'][value='" + user_id + "'][name='comment[action_request][]']").attr('checked', 'true');
		}  else {
			$("input[type='checkbox'][value='" + user_id + "'][name='comment[action_request][]']").attr('checked', '');
		}
                highlight_user(elemref);
	} catch(e){
		alert(e);
	}
}
//EOF:mod 20110623
//BOF:mod 20110715 ticketid246
var clicktimer;
function customtab_ondblclick(liElem, tab_index, event){
    App.ModalDialog.show('custom_tabs_manager', App.lang('Custom Tabs Manager'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(App.data.custom_tabs_manager + '&tab_index=' + tab_index), {});
}

function customtab_remove(){
    $('input#tab_name').val('');
    $('input#tab_url').val('');
    $('form[name="tabsmanager"]').submit();
}

function customtab_form_validate(){
    if ($('input#tab_name').val()==''){
        alert('Tab description missing');
        return false;
    }
    if ($('input#tab_url').val()==''){
        alert('Tab link missing');
        return false;
    }
    return true;
}
//EOF:mod 20110715 ticketid246
function hometab_links_summary(){
    var anc_class   = $('#layout_type').val()=='summary' ? 'anc01' : 'anc02';
    var user_id     = $('#user_id').val();
    $('a.' + anc_class).click(function(e){
        e.preventDefault();
        var link = $(this).attr('href');
        //BOF:mod 20111019 #448
        /*
        //EOF:mod 20111019 #448
        var comment_id = link.substring(link.indexOf('comments%2F')).replace(/comments%2F/i, '');
        //BOF:mod 20111019 #448
        */
        var temp_link = link;
        var temp_index = temp_link.length-1;
        while(temp_link.substring(temp_index, temp_index+1)!='F'){
          temp_index -= 1;
        }
        var comment_id = link.substring(temp_index+1, link.length);
        var project_id = link.substring(link.indexOf('projects%2F')).replace(/projects%2F/i, '');
        project_id = project_id.substring(0, project_id.indexOf('%2F'));

        //EOF:mod 20111019 #448
        $.ajax({
            type : 'POST',
            url : App.extendUrl(location.href, {async : 1, skip_layout : 1}),
            data : {
                    project_id : project_id,
                    comment_id : comment_id,
                    user_id:    user_id
                    },
            success : function(response) {
                window.open(link, '_blank');
                //location.href = location.href;
                var content = $('div.container ul li a.current span').html();
                content = content.substring(0, content.indexOf('(')) + response;
                $('div.container ul li a.current span').html(content);
            }
        });
        $(this).removeClass(anc_class).addClass(anc_class + '_visited');
    });
    //BOF:mod 20111102
    //BOF:mod 20120106
    $('img#icon_plus, img#icon_minus').click(function(e){
       var current_id = $(this).attr('id');
       var temp = $(this).parent().attr('id').split('_');
       var action_type = '';
       if (temp[0]=='action'){
           action_type = 'actionrequest';
       } else if (temp[0]=='fyi'){
           action_type='fyi';
       }
       var parent_id = temp[1];
       var new_id = '';
       var new_src = '';
       if (current_id=='icon_plus'){
           new_id = 'icon_minus';
           new_src = $(this).attr('src').replace('icon_plus', 'icon_minus');
           var img_ref = $(this);
           //$(this).parent().parent().append('<div id="div_' + action_type + '_' + parent_id + '"></div>');
           $.ajax({
               type: 'GET',
               url: App.extendUrl(App.data.render_comments_url, {asynch: 1, skip_layout: 1}),
               data: {action_type: action_type, parent_id: parent_id},
               success: function(resp){
                   $(img_ref).parent().parent().append('<div id="div_' + action_type + '_' + parent_id + '">' + resp + '</div>');
               }
           })
       } else if(current_id=='icon_minus'){
           new_id = 'icon_plus';
           new_src = $(this).attr('src').replace('icon_minus', 'icon_plus');
           $(this).parent().parent().find('div#div_' + action_type + '_' + parent_id).remove();
       }
       $(this).attr({id: new_id, src: new_src});
    });
    //EOF:mod 20120106
    $('a.mark_as_complete, a.mark_as_read').live('click', function(e){
       e.preventDefault();

       var link = $(this).attr('href');
       var link_num = parseInt($(this).attr('count'));
       $(this).css('display', 'none').parent().append('<img src="' + App.data.indicator_url + '" />');
       var row_ref = $(this).parent().parent();

       $.ajax({
           url: App.extendUrl(link, {asynch: 1, skip_layout: 1}),
           success : function(response){
               var layout_type = $('#layout_type').val();
               switch(layout_type){
                   case 'details':
                       var table_ref = $(row_ref).parent();
                        var index = $(table_ref).children().index($(row_ref));
                        $(table_ref).find('tr:gt(' + (index-6) + '):lt(8)').remove();
                       break;
                   case 'summary':
                        var comments_table = $(row_ref).parent();
                        while($(comments_table)[0].tagName.toLowerCase()!='table'){
                            comments_table = $(comments_table).parent();
                        }
                        var parent_td = $(comments_table).parent().parent().parent();
                        var links_count = $(parent_td).find("a[class^='anc']").length;
                        if (links_count==1){
                            $(parent_td).remove();
                        } else {
                            $(row_ref).remove();
                            if (!isNaN(link_num)){
                                $(parent_td).find("a[class^='anc']:eq(" + (link_num-1) + ")").remove();
                            }
                        }
                        break;
               }
           }
       });
        return false;
    });
    //EOF:mod 20111102
    $('div.container ul li a.current span').append(' (' + $('#unvisited_action_request_comments').val() + ' / ' + $('#unvisited_fyi_comments').val() + ') ');
}

function manage_priority(){
    $('td.priority img').css('cursor', 'pointer').click(function(e){
       $('td.priority').each(function(index){
           $(this).children(':eq(1)').remove()
           $(this).children(':eq(0)').css('display', '');
       });

       $(this).css('display', 'none');
       var val = '';
       switch($(this).attr('title')){
		//BOF:mod 20121107
           case 'Urgent Priority':
               val = '3';
               break;
		//EOF:mod 20121107
           case 'Highest Priority':
               val = '2';
               break;
           case 'High Priority':
               val = '1';
               break;
           case 'Normal Priority':
               val = '0';
               break;
           case 'Low Priority':
               val = '-1';
               break;
           case 'Lowest Priority':
               val = '-2';
               break;
           case 'Ongoing Priority':
               val = '-3';
               break;
           case 'Hold Priority':
               val = '-4';
               break;
       }
       $(this).parent().append($('span#priority_pulldown_container').html());
       $('td #priority_selector').val(val).change(function(e){
           var temp = $(this).parent().attr('class');
           var index = temp.indexOf('obj_');
           var drp_ref = this;
           if (index!=-1){
               var object_id = temp.substr(index + 4);
               if (object_id!=''){
                $.ajax({
                    type : 'POST',
                    url : App.extendUrl(location.href, {async : 1, skip_layout : 1}),
                    data : {
                            object_id : object_id,
                            priority: $(this).val()
                            },
                    success : function(response) {
                       var modified_priority_image = '';
                       var current_priority_image = '';
                       var modified_priority_title = '';

                       switch($(drp_ref).parent().children(':eq(0)').attr('title')){
							//BOF:mod 20121107
                           case 'Urgent Priority':
                               current_priority_image = 'urgent.png';
                               break;
							//EOF:mod 20121107
                           case 'Highest Priority':
                               current_priority_image = 'highest.gif';
                               break;
                           case 'High Priority':
                               current_priority_image = 'high.gif';
                               break;
                           case 'Normal Priority':
                               current_priority_image = 'normal.gif';
                               break;
                           case 'Low Priority':
                               current_priority_image = 'low.gif';
                               break;
                           case 'Lowest Priority':
                               current_priority_image = 'lowest.gif';
                               break;
                           case 'Ongoing Priority':
                               current_priority_image = 'ongoing.png';
                               break;
                           case 'Hold Priority':
                               current_priority_image = 'hold.png';
                               break;
                       }
                       switch($(drp_ref).val()){
							//BOF:mod 20121107
                           case '3':
                               modified_priority_image = 'urgent.png';
                               modified_priority_title = 'Urgent Priority';
                               break;
							//EOF:mod 20121107
                           case '2':
                               modified_priority_image = 'highest.gif';
                               modified_priority_title = 'Highest Priority';
                               break;
                           case '1':
                               modified_priority_image = 'high.gif';
                               modified_priority_title = 'High Priority';
                               break;
                           case '0':
                               modified_priority_image = 'normal.gif';
                               modified_priority_title = 'Normal Priority';
                               break;
                           case '-1':
                               modified_priority_image = 'low.gif';
                               modified_priority_title = 'Low Priority';
                               break;
                           case '-2':
                               modified_priority_image = 'lowest.gif';
                               modified_priority_title = 'Lowest Priority';
                               break;
                           case '-3':
                               modified_priority_image = 'ongoing.png';
                               modified_priority_title = 'Ongoing Priority';
                               break;
                           case '-4':
                               modified_priority_image = 'hold.png';
                               modified_priority_title = 'Hold Priority';
                               break;
                       }
                       var src = $(drp_ref).parent().children(':eq(0)').attr('src');
                       var src_modified = src.replace(current_priority_image, modified_priority_image);
                       $(drp_ref).parent().children(':eq(0)').attr({src: src_modified, title: modified_priority_title}).css('display', '');
                       $(drp_ref).parent().children(':eq(1)').remove();
                    }
                });
               }
           }
       });

    });
}

//BOF:mod 20121116
function convert_object_to_ticket(project_id, object_id, object_type){
	var url = App.extendUrl(App.data.convert_to_ticket_url, {skip_layout : 1});
	url = url.replace('--PROJECT_ID--', project_id).replace('--OBJECT_ID--', object_id);

	App.ModalDialog.show('convert_object_to_ticket', App.lang('Convert to Ticket'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(url), {
	  buttons : [
		{
			label: App.lang('Close'), 
			callback: function(){
				App.ModalDialog.close();
			}
		}
	   ], 	
	  width: 560
	});
}

function convert_object_to_milestone(project_id, object_id, object_type){
	var url = App.extendUrl(App.data.convert_to_milestone_url, {skip_layout : 1});
	url = url.replace('--PROJECT_ID--', project_id).replace('--OBJECT_ID--', object_id);

	App.ModalDialog.show('convert_object_to_milestone', App.lang('Convert to Milestone'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(url), {
	  buttons : [
		{
			label: App.lang('Close'), 
			callback: function(){
				App.ModalDialog.close();
			}
		}
	  ],
	  width: 560
	});
}

function convert_object_to_page(project_id, object_id, object_type){
	var url = App.extendUrl(App.data.convert_to_page_url, {skip_layout : 1});
	url = url.replace('--PROJECT_ID--', project_id).replace('--OBJECT_ID--', object_id);

	App.ModalDialog.show('convert_object_to_page', App.lang('Convert to Page'), $('<p><img src="' + App.data.assets_url + '/images/indicator.gif" alt="" /> ' + App.lang('Loading...') + '</p>').load(url), {
	  buttons : [
		{
			label: App.lang('Close'), 
			callback: function(){
				App.ModalDialog.close();
			}
		}
	   ], 	
	  width: 560
	});
}
//EOF:mod 20121116