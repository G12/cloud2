var AC_VIEW = 1;
var AC_CREATE = 2;
var AC_RENAME = 4;
var AC_DELETE = 8;
var AC_UPLOAD = 16;
var AC_DOWNLOAD = 32;
var AC_PERMISSIONS = 64;

var NAV_BAR_HEIGHT = 50 + 20; //Navbar and padding

/**
 * Rounds a number to the specified precision
 * <p>
 * If no precision is specified the number will be rounded
 * using Math.round
 * </p>
 *
 * @param  n a number
 * @param  p the number of decimal places to round to
 * @return A number rounded to the correct number of decimal places
 */
function g12_round(n, p) {
    if (!p) return Math.round(n);
    var a = Math.pow(10, p);
    return Math.round(n * a) / a;
}

function isValidName(name) {
    var pattern = new RegExp(/[^ ,a-zA-Z-_0-9.]/);
    var res = pattern.test(name);
    if (res) {
        alert("Invalid Filename: " + name);
        return false;
    }
    return true;
}

var menu_height = 60;
var g_cancel; //Lame cancelation scheme TODO improve this
var g_first = true;
var g_profile;
var g_folder_permissions;

$(function () {
    var g_url; //The URL of the jQuery File Upload Plugin PHP.
    //For example: plugin_manager.php?folder=someFolderPath
    //Files picked in the Upload Images dialog will be saved to the folder specified in this URL

    //jQuery-File-Upload
    //provides a submit method for the data argument, that will start the file upload:
    //See https://github.com/blueimp/jQuery-File-Upload/wiki/API
    $('#upload_files').fileupload({
        dataType: 'json',
        //This function is called for every file selected for upload
        add: function (e, data) {
            data.url = g_url;
            var file = data.files[0];
            if (isValidName(file.name)) {
                var name = file.name.length > 18 ? file.name.substring(0, 15) + '...' : file.name;
                var size = g12_round(file.size / 1024.0, 1);
                //Add the uploading message to a p tag
                //Copy to data.context - will be used in the done function (below)
                //Note: data.context is a holder for the jquery object representing the uploading message
                //Append the p tag to the #msg div in the $("#upload_form").dialog
                data.context = $('<p/>').text('uploading... ' + name + ' size:' + size + 'KB ').appendTo("#msg");
                data.submit();
            }
            else {
                $('<p/>').text('Error Invalid Filename ' + file.name).appendTo("#msg");
            }
        },
        done: function (e, data) {
            //Notify user that upload is complete - see above for data.context info
            data.context.text('Upload finished.');
        }

    });

    ///////////////////////////////////////////////////////////// Dialogs //////////////////////////////////////////////////
    //jQuery UI Dialog Widget
    //Contains the file input tag with id="upload_files"
    //See jQuery-File-Upload initialization method above
    $("#upload_form").dialog(
        {
            autoOpen: false,
            height: 300,
            width: 350,
            modal: true,
            buttons: {
                Close: function () {
                    $(this).dialog("close");
                }
            },
            close: function () {
                //Called when upload_form dialog is closed.
                $('#tree').jstree(true).refresh();
                $("#msg").html(""); //clear upload list
                hide("#upload_form");
            }
        });

    $("#busy_box").dialog(
        {
            autoOpen: false,
            height: 300,
            width: 350,
            modal: true,
            close: function () {
                //alert("Close Stuff Here");
                g_cancel = true;
            }
        });

    $("#permissions_form").dialog(
        {
            autoOpen: false,
            height: 'auto',
            width: 'auto',
            modal: true,
            close: function () {
                //alert("Close Permissions Dialog Stuff Here");
            }
        });

    //If permission check boxes are changed show save button
    $("#permissions_data").on('click', 'input[class=user_data_check]', function (e) {
        show("#save_permissions_btn");
    });

    $("#permissions_data").on('click', 'button[class=remove_group_btn]', function (e) {
        //show("#save_permissions_btn");
        if (confirm("Remove Group: " + this.dataset.group_name)) {
            g_folder_permissions.removeGroup(this.dataset.group_id);
            show("#save_permissions_btn");
        }
    });

    $("#permissions_data").on('click', 'button[class=remove_user_btn]', function (e) {
        //show("#save_permissions_btn");
        if (confirm("Remove User: " + this.dataset.user_name)) {
            g_folder_permissions.removeUser(this.dataset.user_id);
            show("#save_permissions_btn");
        }
    });

    $("#add_group_btn").click(function (e) {
        $("#wait_msg").html('<p>Under Construction TODO add group to ACL</p>');
        $("#busy_box").dialog("open");
    });

    $("#add_user_btn").click(function (e) {
        $("#wait_msg").html('<p>Under Construction TODO add user to ACL</p>');
        $("#busy_box").dialog("open");
    });

    $("#save_permissions_btn").click(function (e) {
        g_folder_permissions.saveChanges(user_name, user_id);
    });

    ////////////////////////////////////////////////////////////////  Slider /////////////////////////////////////////
    /* Used to resize tree column */
    $("#slider").draggable({
        axis: "x", start: function () {
        },
        drag: function () {
        },
        stop: function () {
            var x = $("#slider").offset().left;
            $('#tree').width(x);
            $("#slider").offset({left: x});
            var w = $(window).width() - $('#tree').width() - $('#slider').width() - 6;
            $('#data').width(w);
        }
    });

    //Set actions to perform when window is resized
    //Then call resize to initiate
    $(window).resize(function () {
        var h = Math.max($(window).height() - 0, 420) - NAV_BAR_HEIGHT;
        $('#container, #data, #tree, #slider, #data .content').height(h); //.filter('.default').css('lineHeight', h + 'px');
        var w = $(window).width() - $('#tree').width() - $('#slider').width() - 6;
        $('#data').width(w);
    }).resize();

    var isLoading = true;

    //JQuery tree plugin
    //See http://www.jstree.com/
    //Initialize properties and callback functions
    $('#tree').jstree(
        {
            'core': {
                'data':  //Builds JSTree using server call for each node
                {
                    'url': 'lib/MenuOps.php?operation=get_node', //Specifies the Server file to get file and folder information from
                    'multiple': false,
                    'data': function (node) {
                        if (isLoading) {
                            //$("#tree").addClass("hide");
                            //$("#default_content").html("Permission for " + user_name + " to view files not available.");
                            isLoading = false;
                        }
                        else {
                            $("#tree").removeClass("hide");
                            //$("tree").addClass("show");
                            $("#default_content").html("");
                        }
                        return {'id': node.id};
                    }
                },
                'check_callback': function (o, n, p, i, m) {
                    if (m && m.dnd && m.pos !== 'i') {
                        return false;
                    }
                    if (o === "move_node" || o === "copy_node") {
                        if (this.get_node(n).parent === this.get_node(p).id) {
                            return false;
                        }
                    }
                    return true;
                },
                'themes': {
                    'responsive': false,
                    'variant': 'small',
                    'stripes': false
                }
            },
            'sort': function (a, b) {
                return this.get_type(a) === this.get_type(b) ? (this.get_text(a) > this.get_text(b) ? 1 : -1) : (this.get_type(a) >= this.get_type(b) ? 1 : -1);
            },
            'contextmenu': {
                'items': function (node) {
                    //alert("permissions[" + node.original.permissions + "]");
                    //Permissions are set in FileSys.php in the lst function when the tree is being built
                    var mask = node.original.permissions;
                    //alert("node.original.permissions" + mask);
                    var jstree = $.jstree;

                    var temp = $.jstree.defaults.contextmenu.items();

                    //Remove Cut Copy Paste to keep it simple stupid
                    temp.ccp = null;

                    //Remove menu items user does not have permissions for
                    if (!(AC_CREATE & mask)) {
                        //temp.create._disabled = true;
                        temp.create = null;
                    }
                    else {
                        if (node.type === "file") {
                            temp.create = null;
                        }
                        else {
                            temp.create =
                            {
                                "separator_before": false,
                                "separator_after": true,
                                "_disabled": false, //(this.check("create_node", data.reference, {}, "last")),
                                "label": "New Folder",
                                "action": function (data) {
                                    var name = prompt("Enter New Folder Name");
                                    if (name != null) {
                                        var inst = $.jstree.reference(data.reference),
                                            obj = inst.get_node(data.reference);

                                        //Test if name is unique
                                        var result = obj.children.find(function (item) {
                                            var arr = item.split("/");
                                            var str = arr[arr.length - 1];
                                            return str === name;
                                        })
                                        if (result === undefined) {
                                            inst.create_node(obj, {}, "last", function (new_node) {
                                                new_node.text = name;
                                                setTimeout(function () {
                                                    inst.edit(new_node);
                                                }, 0);
                                            });
                                        }
                                        else {
                                            alert("Duplicate Name: " + name + ". Please enter another name.");
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if (!(AC_RENAME & mask)) {
                        //temp.rename._disabled = true;
                        temp.rename = null;
                    }
                    else {
                        if (node.type === "file") {
                            temp.create = null;
                        }
                        else {
                            temp.rename =
                            {
                                "separator_before": false,
                                "separator_after": false,
                                "_disabled": false, //(this.check("rename_node", data.reference, this.get_parent(data.reference), "")),
                                "label": "Rename",
                                "action": function (data) {
                                    var name = prompt("Enter New Folder Name");
                                    if (name != null) {
                                        var inst = $.jstree.reference(data.reference),
                                            obj = inst.get_node(data.reference);


                                        //if (true) {
                                        //    return;
                                        //}
                                        //else {
                                            $.get('lib/MenuOps.php?operation=rename_node', {'id': obj.id, 'text': name})
                                                .done(function (d) {
                                                    obj.text = name;
                                                    $('#tree').jstree(true).refresh();
                                                    g_first = true; //allow change event to refresh the profile
                                                })
                                                .fail(function () {
                                                    alert("Failed to Rename " + obj.text + " to " + name + ". Check for duplicate names.");
                                                    $('#tree').jstree(true).refresh();
                                                });
                                        //}
                                    }
                                }
                            }
                        }
                    }

                    if (
                        !(AC_DELETE & mask)
                    ) {
                        //temp.remove._disabled = true;
                        temp.remove = null;
                    }

                    //Add custom menu items user has permissions for
                    if (this.get_type(node) === "file") {
                        if (AC_CREATE & mask) {
                            temp.update_info =
                            {
                                "separator_after": true,
                                "label": "Update",
                                "action": function (data) {
                                    var inst = $.jstree.reference(data.reference),
                                        obj = inst.get_node(data.reference);
                                    //alert("Update" + obj.id);
                                    $.get('lib/MenuOps.php?operation=update_info', {'id': obj.id})
                                        .done(function (d) {
                                            alert("Status:" + d.status);
                                        })
                                        .fail(function () {
                                            alert("Failed:");
                                        });
                                }
                            };
                        }
                        if (AC_DOWNLOAD & mask) {
                            temp.download_file =
                            {
                                "separator_after": true,
                                "label": "Download",
                                "action": function (data) {
                                    var inst = $.jstree.reference(data.reference),
                                        obj = inst.get_node(data.reference);
                                    var name = obj.id.replace(/^.*\/|\.[^.]*$/g, '');
                                    var url = "picConfirm.php?name=" + encodeURIComponent(name) + "&id=" + encodeURIComponent(obj.id);
                                    var new_win = window.open(url, "DownloadPic", "location=no, toolbar=no, scrollbars=no, resizable=no, top=400, left=400, width=600, height=420");
                                }
                            }
                        }
                    }
                    else {
                        //Always show permissions dialog menu item
                        //if(AC_PERMISSIONS & mask)
                        //{
                        temp.edit_permissions =
                        {
                            "separator_after": true,
                            "label": "Permissions",
                            "action": function (data) {
                                var inst = $.jstree.reference(data.reference),
                                    obj = inst.get_node(data.reference);

                                //$("#wait_msg").html('<p>Getting Permission information for: ' + obj.id + '</p>');
                                //g_cancel = false;
                                //$("#busy_box").dialog("open");

                                $.get('lib/MenuOps.php?operation=permission_info', {'id': obj.id})
                                    .done(function (json) {
                                        //Add user name and id to json NOTE user_name and user_id defined on index.php
                                        json.user_name = user_name;
                                        json.user_id = user_id;
                                        g_folder_permissions = new G12_Cloud.FolderPermissions(json, AC_PERMISSIONS & mask);
                                        g_folder_permissions.OpenDialog();
                                    })
                                    .fail(function () {
                                        alert("Failed:");
                                    })
                                    .always(function () {
                                        //$("#busy_box").dialog("close");
                                    });

                            }
                        };
                        //}
                        if (AC_UPLOAD & mask) {
                            temp.upload_images =
                            {
                                "separator_after": true,
                                "label": "Upload",
                                "action": function (data) {
                                    var inst = $.jstree.reference(data.reference),
                                        obj = inst.get_node(data.reference);
                                    g_url = 'plugin_manager.php?folder=' + obj.id + '&user_name=' + user_name;
                                    $("#upload_form").attr("title", "Upload Images to: " + obj.id);
                                    show("#upload_form");
                                    $("#upload_form").dialog("open");
                                }
                            };
                        }
                        if (AC_PERMISSIONS & mask) {
                            temp.set_mods =
                            {
                                "separator_after": true,
                                "label": "Mods",
                                "action": function (data) {
                                    var inst = $.jstree.reference(data.reference),
                                        obj = inst.get_node(data.reference);
                                    var mod = 0;

                                    if (confirm("Current Mod is " + obj.original.mods + ". Press OK to set Filename Parsing (Mod = 1) for: " + obj.id)) {
                                        mod = 1;
                                    }
                                    $.get('lib/MenuOps.php?operation=update_mods', {'id': obj.id, 'mods': mod})
                                        .done(function (d) {
                                            //alert("SUCCESS: " + JSON.stringify(d));
                                            $('#tree').jstree(true).refresh();
                                        })
                                        .fail(function () {
                                            alert("Failed mods update");
                                        });
                                }
                            }
                        }
                    }
                    return temp;
                }
            },
            'types': {
                'default': {
                    'icon': 'folder'
                }
                ,
                'file': {
                    'valid_children': [], 'icon': 'file'
                }
            }
            ,
            'plugins': ['state'/*,'dnd'*/, 'sort', 'types', 'contextmenu', 'unique']
        })
        .on('delete_node.jstree', function (e, data) {
            if (confirm("Delete " + data.node.id)) {
                $.get('lib/MenuOps.php?operation=delete_node', {'id': data.node.id})
                    .fail(function () {
                        data.instance.refresh();
                    });
            }
            else {
                data.instance.refresh();
            }
        })
        .on('create_node.jstree', function (e, data) {

            var new_name = data.node.text;
            //var name = prompt("Enter Folder Name:");
            //if(name != null)
            //{
            //    new_name = name;
            //}

            $.get('lib/MenuOps.php?operation=create_node', {
                'type': data.node.type,
                'id': data.node.parent,
                'text': new_name//data.node.text
            })
                .done(function (d) {
                    data.instance.set_id(data.node, d.id);
                    //$('#tree').jstree(true).refresh();
                    data.instance.refresh();
                    //data.instance.select_node(data.node);
                })
                .fail(function () {
                    data.instance.refresh();
                });
        })
        .on('rename_node.jstree', function (e, data) {
            /*
             $.get('lib/MenuOps.php?operation=rename_node', {'id': data.node.id, 'text': data.text})
             .done(function (d) {
             data.instance.set_id(data.node, d.id);
             //$('#tree').jstree(true).refresh();
             g_first = true; //allow change event to refresh the profile
             data.instance.refresh(); //refresh child nodes with new name
             data.instance.select_node(data.node);

             })
             .fail(function () {
             data.instance.refresh();
             });
             */
        })
        //triggered when a node is moved (drag and drop) TODO future implementation?
        //See http://www.jstree.com/api/#/?q=.jstree%20Event&f=move_node.jstree
        /*
         .on('move_node.jstree', function (e, data) {
         $.get('lib/MenuOps.php?operation=move_node', { 'id' : data.node.id, 'parent' : data.parent })
         .done(function (d) {
         //data.instance.load_node(data.parent);
         data.instance.refresh();
         })
         .fail(function () {
         data.instance.refresh();
         });
         })
         */
        .on('copy_node.jstree', function (e, data) {
            $.get('lib/MenuOps.php?operation=copy_node', {'id': data.original.id, 'parent': data.parent})
                .done(function (d) {
                    //data.instance.load_node(data.parent);
                    data.instance.refresh();
                })
                .fail(function () {
                    data.instance.refresh();
                });
        })
        .on('show_contextmenu.jstree', function (e, data) {
            var fudge_factor = $("a").outerHeight() + $("a").outerHeight() / 3;
            var cntxt_top = parseInt($(".vakata-context").css("top"), 10);
            var cntxt_height = parseInt($(".vakata-context").css("height"), 10);
            var tree_height = $("#tree").height();
            var max_height = tree_height - cntxt_height - fudge_factor;
            var t = "test";
            if (data.y > max_height) {
                var val = (data.y - cntxt_height - fudge_factor) + "px";
                $(".vakata-context").css("top", val);
            }
        })
        .on('changed.jstree', function (e, data) {
            if (data && data.selected && data.selected.length) {
                //var action = data.action; //TODO this attribute could be usefull
                //alert(action);
                var button;
                if (data.event) {
                    button = data.event.button;
                }
                if (button == 0 || g_first) //Only respond to Left Mouse Button or when page first opens
                {
                    g_first = false;
                    if (g_cancel) {
                        //alert("Server Busy please wait!");
                        if (confirm("Server Busy - Cancel the Operation Now?")) {
                            g_cancel = false;
                        }
                        return;
                    }
                    //alert('get_content&id=' + data.selected.join(':'));
                    $("#wait_msg").html('<p>Getting information for: ' + data.selected.join(':') + '</p>');
                    g_cancel = false;
                    $("#busy_box").dialog("open");
                    $.get('lib/MenuOps.php?operation=get_content&id=' + data.selected.join(':'), function (d) {
                        //alert("Received dat for:" + d.type);
                        var cancel = g_cancel;
                        $("#busy_box").dialog("close"); //dialog close always sets g_cancel = true
                        if (!cancel) {
                            var w = $(window).width() - $('#tree').width() - $('#slider').width() - 6;
                            $('#data').width(w);

                            //alert(d.type);
                            if (d && typeof d.type !== 'undefined') {
                                $('#data .content').hide();
                                $('#data .default').hide();
                                $('#data .profile').hide();
                                switch (d.type.toLowerCase()) {
                                    case 'txt':
                                    case 'text':
                                    case 'md':
                                    case 'js':
                                    case 'json':
                                    case 'css':
                                    case 'html':
                                    case 'htm':
                                    case 'xml':
                                    case 'c':
                                    case 'cpp':
                                    case 'h':
                                    case 'sql':
                                    case 'log':
                                    case 'py':
                                    case 'rb':
                                    //case 'htaccess':
                                    case 'php':
                                        $('#data .code').show();
                                        $('#code').val(d.content);
                                        break;
                                    case 'png':
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'bmp':
                                    case 'gif':
                                        g_profile = null;
                                        g_profile = new G12_Cloud.ImageProfile(d);
                                        g_profile.Show();
                                        //Initialize fancybox to expand image thumbnail on the profile
                                        $(".fancybox").fancybox({
                                            helpers: {
                                                title: {
                                                    type: 'inside'
                                                }
                                            }
                                        });
                                        break;
                                    case 'pdf':
                                        $('#data .default').html(d.content).show();
                                        $('#pdf').height($(window).height());
                                        break;
                                    default:
                                        //alert("Folder?");
                                        $('#profile_card').removeClass("fancybox"); //Stop fancy box displaying last profile card
                                        var str = '<div>';
                                        for (var i = 0; i < d.content.length; i++) {
                                            var obj = d.content[i];
                                            var img_class = 'portrait';
                                            var ratio = obj.height / obj.width;
                                            if (ratio < .8) {
                                                img_class = 'landscape';
                                            }
                                            if (obj.icon == 'folder') {
                                                str += '<div class="img_box"><a title="' + obj.name + '" href="#" id="' + obj.id + '">' + '<figure><img  class="' + img_class + '" alt="' + obj.name + '" title="' + obj.name + '"' + ' src="' + obj.imgURL + '"></figure><figcaption>' + obj.name + '</figcaption></a></div>';

                                            }
                                            else {
                                                //Uncomment this for thumbnails using file names
                                                /*
                                                str += '<div class="img_box"><a class="fancybox" rel="group" title="' + obj.name + '" href="' + obj.cardURL + '" id="' + obj.id + '">' +
                                                    '<figure><img  class="' + img_class + '" alt="' + obj.name + '" title="' + obj.title + '"' +
                                                    ' src="' + obj.imgURL + '"></figure><figcaption>' + obj.name + '</figcaption></a></div>';
                                                */

                                                str += '<div class="img_box"><a class="fancybox" rel="group" title="' + obj.name + '" href="' + obj.cardURL + '" id="' + obj.id + '">' +
                                                    '<figure><img  class="' + img_class + '" alt="' + obj.name + '" title="' + obj.title + '"' +
                                                    ' src="' + obj.imgURL + '"></figure><figcaption>' + obj.title + '</figcaption></a></div>';
                                            }
                                        }
                                        str += '</div>';
                                        $('#data .default').html(str).show();
                                        //Initialize fancybox for use on the list of thumbnails generated above.
                                        $(".fancybox").fancybox({
                                            helpers: {
                                                title: {
                                                    type: 'inside'
                                                }
                                            }
                                        });

                                        break;
                                }
                            }
                        }
                    }).fail(function (d) {
                        $("#wait_msg").html('<p>' + d.statusText + ':</p><p>' + d.responseText + '</p>');
                    }).always(function () {
                        //alert( "finished" );
                        g_cancel = false;
                    });
                }
            }
            else {
                //TODO original example code calls this - Was there any good reason for this?
                //$('#data .content').hide();
                //$('#data .default').html('Select a file from the tree.').show();
            }
        });
})
;

////////////////////////////////  Utility  ///////////////////////////////
function show(id) {
    $(id).removeClass("hide");
    $(id).addClass("show");
}

function hide(id) {
    $(id).removeClass("show");
    $(id).addClass("hide");
}

function showInline(id) {
    $(id).removeClass("hide");
    $(id).addClass("show_inline");
}

function hideInline(id) {
    $(id).removeClass("show_inline");
    $(id).addClass("hide");
}


