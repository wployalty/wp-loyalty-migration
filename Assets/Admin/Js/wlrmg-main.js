if (typeof wlrmg_jquery == 'undefined') {
    wlrmg_jquery = jQuery.noConflict();
}
wlrmg = window.wlrmg || {};
(function () {
    wlrmg.createJob = function (form_id) {
        let form_data = wlrmg_jquery(form_id).serialize();
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: form_data+"&action=wlrmg_create_job&wlrmg_nonce="+wlrmg_localize_data.create_job,
            cache: false,
            async: false,
            success: function (res) {
                console.log(res)
                response = res.success;
            }
        });
    }
})(wlrmg);