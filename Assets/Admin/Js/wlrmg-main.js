if (typeof wlrmg_jquery == 'undefined') {
    wlrmg_jquery = jQuery.noConflict();
}
wlrmg = window.wlrmg || {};
(function () {
    wlrmg.createJob = function () {
        let response;
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wlrmg_create_job',
                wlrmg_nonce: wlrmg_localize_data.create_job,
            },
            cache: false,
            async: false,
            success: function (res) {
                console.log(res)
                /*if (res.success) {
                    if (res.data.success === 'complete') {
                        alertify.success(res.data.message);
                    }
                } else {
                    alertify.error(res.data.message);
                    wlrmg_jquery.each(res.data.field_error, function (index, value) {
                        alertify.error(value);
                    });
                }*/
                response = res.success;
            }
        });
        return response;
    }
})(wlrmg);