/*

Aug. 26 2014
Thomas Wiegand
Profile object

*/

//Constants
var IMAGES_PRIMARY_KEY = 'item_id';
var ARTWORKS_PRIMARY_KEY = 'artwork_id';

String.prototype.replaceAt=function(index, character) {
    return this.substr(0, index) + character + this.substr(index+character.length);
};
//Usage:	query_string = query_string.replaceAt(0,"?");

var g12_request;
var g12_post;

var G12_Cloud = (function()
{
	function chk(permission, value)
	{
		return permission & value ? 'checked="checked"' : '';
	}

	function ImageProfile(data)
	{
		this.fields = data.content.fields;
		this.permissions = data.permissions;
		this.isEditing = false;
		
		//Thumbnail anchor and img
		var img_class = 'portrait';
		var ratio = this.fields.artwork_height.value/this.fields.artwork_width.value;
		if(ratio < 0.8)
		{
			img_class = 'landscape';
		}
		var thumb = this.fields.path.root_url + 'thumbs/' + this.fields.path.value;
		var card =  this.fields.path.root_url + 'cards/' + this.fields.path.value;
		$('#profile_thumb').attr("src",thumb);
		$('#profile_thumb').attr("class",img_class);
		$('#profile_card').addClass("fancybox");
		$('#profile_card').attr("href",card);
		$('#profile_caption').html(this.fields.title.value);
		
		var self = this;
		
		if(AC_CREATE & this.permissions)
		{
			$('#profile_edit').show();	
		}
		else
		{
			$('#profile_edit').hide();	
		}
		$('#profile_edit').off('click');
		$('#profile_edit').on('click',function(e)
		{
			if(self.isEditing)
			{
				//roll back changes
				self._setChanges(false);
			}
			else
			{
				$(this).html("Cancel Edit");
				self.isEditing = true;
				$('#image_form input').prop('readonly', false);
				$('#image_form textarea').prop('readonly', false);
				$('#image_form select').prop('disabled', false);
			}
			return false;
		});
		
		$('#image_form input').off('change');
		$('#image_form input').on('change', function(e)
		{
			$('#profile_save_changes').show();
			$(this).attr('data-dirty','true');
		});
		
		$('#image_form textarea').off('change');
		$('#image_form textarea').on('change', function(e)
		{
			$('#profile_save_changes').show();
			$(this).attr('data-dirty','true');
		});

		$('#image_form').off('submit');
		$('#image_form').on('submit',function(e) 
		{
       		self.Update();
			return false;     
        });
	}
	
	ImageProfile.prototype._setChanges = function(isSubmit)
	{
		$('#profile_edit').html("Edit");
		$('#profile_save_changes').hide();
		this.isEditing = false;
		if(isSubmit) //update JSon data with using form values
		{
			this._updateDataValues();
		}
		else //roll back changes
		{
			this._setFormValues();
		}
		$('#image_form input').prop('readonly', true);
		$('#image_form textarea').prop('readonly', true);
		$('#image_form select').prop('disabled', true);
		//reset data-dirty flags
		$('#image_form input').each(function(index, element)
		{
			$(this).attr('data-dirty','false');
		});
		$('#image_form textarea').each(function(index, element)
		{
		  $(this).attr('data-dirty','false');
		});
	};
	
	ImageProfile.prototype._setFormValues = function()
	{
		$('#artwork_title').val(this.fields.artwork_title.value);
		$('#artwork_medium').val(this.fields.artwork_medium.value);
		$('#artwork_height').val(this.fields.artwork_height.value);
		$('#artwork_width').val(this.fields.artwork_width.value);
		$('#artwork_year').val(this.fields.artwork_year.value);
		$('#artwork_units').val(this.fields.artwork_units.value);
		$('#keywords').val(this.fields.keywords.value);
		$('#description').val(this.fields.description.value);
	};
	
	ImageProfile.prototype._updateDataValues = function()
	{
		this.fields.artwork_title.value = $('#artwork_title').val();
		this.fields.artwork_medium.value = $('#artwork_medium').val();
		this.fields.artwork_height.value = $('#artwork_height').val();
		this.fields.artwork_width.value = $('#artwork_width').val();
		this.fields.artwork_year.value = $('#artwork_year').val();
		this.fields.artwork_units.value = $('#artwork_units').val();
		this.fields.keywords.value = $('#keywords').val();
		this.fields.description.value = $('#description').val();
	};

	ImageProfile.prototype.Show = function()
	{
		//this._setFormValues();
		//populate form values
		this._setChanges(false);
		$('#data .profile').show();
	};
	
	ImageProfile.prototype.Update = function()
	{
		var self = this;
		var dirty = false;
		var query_string = IMAGES_PRIMARY_KEY + '=' + this.fields.item_id.value + '&' + ARTWORKS_PRIMARY_KEY + '=' + this.fields.artwork_id.value;
		$('#image_form input').each(function(index, element)
		{
			if($(this).attr('data-dirty') == 'true')
			{
				dirty = true;
				query_string += '&' + $(this).attr('id') + '=' + encodeURIComponent($(this).val());
			}
		});
		$('#image_form textarea').each(function(index, element)
		{
			if($(this).attr('data-dirty') == 'true')
			{
				dirty = true;
				query_string += '&' + $(this).attr('id') + '=' + encodeURIComponent($(this).val());
			}
		});
		if(dirty)
		{
			// abort any pending request
			if (g12_request) {
				g12_request.abort();
			}
			
			//alert(query_string);

			// fire off the request to /form.php
			g12_request = $.ajax({
				url: "lib/ProfileUpdate.php",
				type: "post",
				data: query_string
			});
			
			// callback handler that will be called on success
			g12_request.done(function (response, textStatus, jqXHR){
				// log a message to the console
				if(response.status == "200")
				{
					//console.log("Hooray, it worked!");
					//On succesful update reset all dirty flags hide submit button ...
					self._setChanges(true);
				}
				else if(response.status == "500")
				{
					alert(response.errorMsg);
				}
			});
		
			// callback handler that will be called on failure
			g12_request.fail(function (jqXHR, textStatus, errorThrown){
				// log the error to the console
				alert("Request Failed status: " + textStatus + " Error: " + errorThrown);
				console.error(
					"The following error occured: "+
					textStatus, errorThrown
				);
			});
		
			// callback handler that will be called regardless
			// if the request failed or succeeded
			g12_request.always(function ()
			{
				
			});
		}
		else
		{
			alert("No changes to submit.");		
		}
	};

	function FolderPermissions(json, hasPermission)
	{
		var disabled = hasPermission ? "" : "disabled";
		if(!hasPermission)
		{
			hide("#add_group_btn");
			hide("#add_user_btn");
		}
		else
		{
			show("#add_group_btn");
			show("#add_user_btn");
		}
		
		this.json = json;
		$("#permissions_form").dialog('option', 'title', 'Permissions for: ' + json.path + ' acl: ' + json.acl_id);
		var p = json.permission_set;
		
		//clear out old data
		$(".dynamic").remove();
		
		var html = "";
		for(var i=0; i < p.group_set.length; i++)
		{
			var obj = p.group_set[i];
			var remove_grp_btn = hasPermission ? '<button data-group_id="' + obj.group_id + '" data-group_name="' + obj.name + '" class="remove_group_btn" >Remove</button>' : '';
			var tr = '<tr class="dynamic groups" data-group_id="' + obj.group_id + '" >' +
			'<th class="row-header">' + obj.name + '</th>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_VIEW + '" ' + chk(AC_VIEW, obj.permissions) + ' ></td>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_CREATE + '" ' + chk(AC_CREATE, obj.permissions) + ' ></td>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_RENAME + '" ' + chk(AC_RENAME, obj.permissions) + ' ></td>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_DELETE + '" ' + chk(AC_DELETE, obj.permissions) + ' ></td>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_UPLOAD + '" ' + chk(AC_UPLOAD, obj.permissions) + ' ></td>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_DOWNLOAD + '" ' + chk(AC_DOWNLOAD, obj.permissions) + ' ></td>' +
			'<td><input type="checkbox" disabled data-mask_val="' + AC_PERMISSIONS + '" ' + chk(AC_PERMISSIONS, obj.permissions) + ' ></td>' +
			'<td></td>' +
			'<td>' + remove_grp_btn + '</td>' +
			'</tr>';
			html += tr;
		}
		$(html).insertAfter("#groups_top");
		
		html = "";
		for(var i=0; i < p.user_set.length; i++)
		{
			var obj = p.user_set[i];
			var remove_usr_btn = hasPermission ? '<button data-user_id="' + obj.user_id + '" data-user_name="' + obj.name + '" class="remove_user_btn" >Remove</button>' : '';
			var tr = '<tr class="dynamic users" data-user_id="' + obj.user_id + '" >' +
			'<th class="row-header">' + obj.name + '</th>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_VIEW + '" ' + chk(AC_VIEW, obj.permissions) + ' ></td>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_CREATE + '" ' + chk(AC_CREATE, obj.permissions) + ' ></td>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_RENAME + '" ' + chk(AC_RENAME, obj.permissions) + ' ></td>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_DELETE + '" ' + chk(AC_DELETE, obj.permissions) + ' ></td>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_UPLOAD + '" ' + chk(AC_UPLOAD, obj.permissions) + ' ></td>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_DOWNLOAD + '" ' + chk(AC_DOWNLOAD, obj.permissions) + ' ></td>' +
			'<td><input class="user_data_check" type="checkbox" ' + disabled + ' data-mask_val="' + AC_PERMISSIONS + '" ' + chk(AC_PERMISSIONS, obj.permissions) + ' ></td>' +
			'<td></td>' +
			'<td>' + remove_usr_btn + '</tr>';
			html += tr;
		}
		$(html).insertAfter("#users_top");


	}
	
	FolderPermissions.prototype.OpenDialog = function()
	{
		hide("#save_permissions_btn");
		show("#permissions_form");
		$("#permissions_form").dialog( "open" );
	}
	
	FolderPermissions.prototype.removeGroup = function(group_id)
	{
		//remove the table row
		var selector = '*[data-group_id="' + group_id + '"]';
		$(selector).remove();
		//remove the json element
		var p = this.json.permission_set;
		for(var i=0; i < p.group_set.length; i++)
		{
			var obj = p.group_set[i];
			if(obj.group_id == group_id)
			{
				p.group_set.splice(i,1);
				return;
			}
		}
	}
	
	FolderPermissions.prototype.removeUser = function(user_id)
	{
		//remove the table row
		var selector = '*[data-user_id="' + user_id + '"]';
		$(selector).remove();
		//remove the json element
		var p = this.json.permission_set;
		for(var i=0; i < p.user_set.length; i++)
		{
			var obj = p.user_set[i];
			if(obj.user_id == user_id)
			{
				p.user_set.splice(i,1);
				return;
			}
		}
	}

	FolderPermissions.prototype.setPermissionForUser = function(permissions, user_id)
	{
		var p = this.json.permission_set;
		for(var i=0; i < p.user_set.length; i++)
		{
			var obj = p.user_set[i];
			if(obj.user_id == user_id)
			{
				obj.permissions = permissions;
				return;
			}
		}
	}
	
	FolderPermissions.prototype.saveChanges = function()
	{
		var self = this;
		$("#permissions_data tr.users").each(function(index, element) {
            var permissions = 0;
			var user_id = element.dataset.user_id;
			$(this).find('input').each(function(index, element) {
				if(element.checked)
				{
					permissions += parseInt(element.dataset.mask_val);
				}
             });
			 //alert("user_id: " + user_id + " permissions: " + permissions);
			 self.setPermissionForUser(permissions, user_id);
        });
		//alert(JSON.stringify(this.json));
		console.log(JSON.stringify(this.json));
		
		// abort any pending request
		if (g12_post) {
			g12_post.abort();
		}
		
		// fire off the request to /form.php
		g12_post = $.ajax({
			url: "lib/PermissionUpdate.php",
			type: "post",
			data: {json_data: JSON.stringify(this.json)}
		});
		
		// callback handler that will be called on success
		g12_post.done(function (response, textStatus, jqXHR){
			// log a message to the console
			if(response.status == "200")
			{
				console.log("Hooray, it worked!");
			}
			else if(response.status == "500")
			{
				alert(response.errorMsg);
			}
			else
			{
				alert("Status: " + respones.status + " " + textStatus);
			}
		});
	
		// callback handler that will be called on failure
		g12_post.fail(function (jqXHR, textStatus, errorThrown){
			// log the error to the console
			alert("Request Failed status: " + textStatus + " Error: " + errorThrown);
			console.error(
				"The following error occured: "+
				textStatus, errorThrown
			);
		});
	
		// callback handler that will be called regardless
		// if the request failed or succeeded
		g12_post.always(function ()
		{
			$("#permissions_form").dialog("close");
			hide("#permissions_form");
		});
	}

	function FolderMods(json, hasPermission) {
	
	}
	

		return {ImageProfile:ImageProfile, FolderPermissions:FolderPermissions};
})();