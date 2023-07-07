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
    wlrmg.saveSettings = function (){
        let form_data = wlrmg_jquery('#wlrmg-main-page #wlrmg-settings #settings-form').serialize();
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: form_data + '&action=wlrmg_save_settings&wlrmg_nonce=' + wlrmg_localize_data.save_settings,
            cache: false,
            async: false,
            success: function (res) {
                console.log(res)
             // window.location.reload()
                // (res.success) ? alertify.success(res.data.message) : alertify.error(res.data.message);
            }
        });
    }
})(wlrmg);