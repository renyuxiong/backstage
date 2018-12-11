
    token = localStorage.getItem('token');

    layui.use(['layer'], function(){  //如果只加载一个模块，可以不填数组。如：layui.use('form')
        layer = layui.layer//获取form模块
        if (!token) {
            top.location.href = './login.html';
        }
    });


var baseURL={
    API:"http://backstage.iyaa180.com:9501"
};
 

var ajax = {
    send:function (url, type, data = {}, headers = {'app-auth': 'ADGN ' + token}) {
        return this._ajax(url, type,data, headers)
    },
    _ajax:function (url, type,data, headers) {
        var r = '';
        $.ajax({
            url:baseURL.API+url,
            type:type,
            dataType:"json",
            headers: headers,
            data:data,
            async: false,
            success:function(res){
                switch (res.status_code) {
                    case 200:
                        r = res;
                        break;
                    case 30001:
                        localStorage.clear();
                        top.location.href = './login.html';
                        return;
                    case 30002:
                        localStorage.clear();
                        top.location.href = './login.html';
                        return;
                    case 10004:
                        console.log(111111);
                        top.layer.alert(res.message, {icon: 6});
                        r = false;
                        break;
                    case 20001:
                        layer.alert(res.message, {icon: 6});
                        r = false;
                        break;
                    default:
                        layer.msg(res.message);
                        break;
                }
            },
            error: function (res) {
                if (res.status == 500) {
                    layer.msg('服务器内部错误，请稍后再试');
                }
            }
        });

        return r;
    }
};
