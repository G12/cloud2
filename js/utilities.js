/**
 * Created by thomaswiegand on 2016-05-11.
 */

//$tmp = preg_match('([^ ,a-zа-я-_0-9.]+)ui', $item);
var isValid=(function(){
    var rg1=/^[^\\/:\*\?"<>\|]+$/; // forbidden characters \ / : * ? " < > |
    var rg2=/^\./; // cannot start with dot (.)
    var rg3=/^(nul|prn|con|lpt[0-9]|com[0-9])(\.|$)/i; // forbidden file names
    return function isValid(fname){
        return rg1.test(fname)&&!rg2.test(fname)&&!rg3.test(fname);
    }
})();

//isValid('file name');

//TODO add configurable user permissions
var CONST_USER_PERMISSIONS = 51; //Owner role

$(function () {
    $("#user_list").click(function (e) {
        e.preventDefault();
        var dirName = $("#directory_name").val();
        if(dirName.length > 2)
        {
            if(isValid(dirName))
            {
                var role = $('input[name=user_permission]:checked', '#directory_vals').val();
                var group_id = $('input[name=user_permission]:checked', '#directory_vals').data("group_id");

                var values = new Array();
                //var $checked = $("input[name='group_set2[]']:checked");
                $.each($("input[name='group_set2[]']:checked"), function() {
                    var item = $(this)[0];
                    var val = $(this).val();
                    values.push({group_id:val, role:item.dataset.role});
                });
                
                var msg = "Add directory named: " + dirName + " for " + e.target.text + " with id " + e.target.id +
                    " and role " + role + " and group_id: " + group_id + " and group_set: " + JSON.stringify(values);
                if(confirm(msg))
                {
                    //Call DirectoryAdd.php
                    var data = {'user_id':e.target.id, 'path':dirName, 'permissions': role, 'group_set':JSON.stringify(values)};
                    postData("lib/DirectoryAdd.php", data, function(json){
                        alert("Successfully Added Directory respone: " + JSON.stringify(json));
                    }, function(json)
                    {
                        alert("Server Problem response: " + JSON.stringify(json));
                    });
                }
            }
            else {
                alert("Invalid directory name.");
            }
        }
        else {
            alert("Directory name must be longer than 2 characters!");
        }
    });
});

///////////////////////////////////   Utiliy Functions   //////////////////////////////////////

function logOjectProperties(data)
{
    for(var name in data) {
        console.log(name + ": " + data[name]);
    }
}

function postData(strUrl, data, success, error)
{
    var my_str = "method:" + data.method;
    console.log("postData(" + strUrl + ")");
    console.log(my_str);
    console.log("data = {");
    logOjectProperties(data);
    console.log("}");
    var jqxhr = $.post(strUrl, data);
    // results
    jqxhr.done(function(json)
    {
        if(json)
        {
            var str = "status: " + json.status;
            if(json.status == "SUCCESS")
            {
                success(json);
            }
            else
            {
                error(json);
            }
        }
        else
        {
            console.log("ERROR null json Object");
            console.log("postData(" + strUrl + ")");
            console.log(my_str);
            console.log("data = {");
            logOjectProperties(data);
            console.log("}");
        }
    });

    jqxhr.always(function(json)
    {
        console.log("RESPONSE");
        console.log(JSON.stringify(json));
    });

    jqxhr.fail(function(e)
    {
        console.log("jqxhr.fail");
        console.log("Error status: " + e.status + " Error: " + e.statusText);
        var str = my_str + "AJAX Error status: " + e.status + " Error: " + e.statusText + " responseText: " + e.responseText;
        alert(str.substring(0, 2500)); //Limit the string size
    });

}
