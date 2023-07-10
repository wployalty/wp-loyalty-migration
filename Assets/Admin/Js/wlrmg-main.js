if (typeof wlrmg_jquery == 'undefined') {
    wlrmg_jquery = jQuery.noConflict();
}
wlrmg = window.wlrmg || {};
(function () {
    alertify.set('notifier', 'position', 'top-right');
    wlrmg.saveSettings = function () {
        let form_data = wlrmg_jquery('#wlrmg-main-page #wlrmg-settings #settings-form').serialize();
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: form_data + '&action=wlrmg_save_settings&wlrmg_nonce=' + wlrmg_localize_data.save_settings,
            cache: false,
            async: false,
            success: function (res) {
                (res.status) ? alertify.success(res.data.message) : alertify.error(res.data.message);
            }
        });
    }
    wlrmg.migrateUsers = function (type) {
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wlrmg_migrate_users',
                wlrmg_nonce: wlrmg_localize_data.migrate_users,
                migration_action: type,
            },
            cache: false,
            async: false,
            success: function (res) {
                (res.status) ? alertify.success(res.data.message) : alertify.error(res.data.message);
            }
        });
    }
})(wlrmg);