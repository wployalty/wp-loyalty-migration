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
                (res.success) ? alertify.success(res.data.message) : alertify.error(res.data.message);
            }
        });
    }
    wlrmg.needConfirmPointUpdate = function (type) {
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type:"POST",
            dataType:"json",
            data:{
                action:"wlrmg_confirm_update_points",
                category:type,
                wlrmg_nonce:wlrmg_localize_data.migrate_users,
            },
            cache:false,
            async:false,
            success:function (json){
                if (json['status'] === true) {
                    wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section").addClass('active');
                    wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section .wlrmg-overlay").html(json['data']['html']);
                }
            }
        });
    }

    wlrmg.migrateUsers = function () {
        let type = wlrmg_jquery("#wlrmg-popup #migration_type").val();
        let update_point = wlrmg_jquery("#wlrmg-popup #update_point").val();
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wlrmg_migrate_users',
                wlrmg_nonce: wlrmg_localize_data.migrate_users,
                migration_action: type,
                update_point: update_point,
            },
            cache: false,
            async: false,
            success: function (res) {
                if (res.success) {
                    alertify.success(res.data.message)
                    setTimeout(function () {
                        window.location.reload();
                    }, 1000);
                } else {
                    alertify.error(res.data.message);
                }
            }
        });
    }
    wlrmg.redirectToUrl = function (url) {
        window.location.href = url;
    }
    wlrmg.exportPopUp = function (job_id, action) {
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                wlrmg_nonce: wlrmg_localize_data.popup_nonce,
                action: 'wlrmg_export_popup',
                migration_action: action,
                job_id: job_id,
            },
            success: function (result) {
                if (result.success) {
                    wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section").addClass('active');
                    wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section .wlrmg-overlay").html(result.data.html);
                }
            }
        });

    }
    wlrmg.startExport = function () {
        var values = wlrmg_jquery('#wlba-export-preview').serializeArray();
        wlrmg_jquery('#wlba-process-export-button').attr('disabled', true);
        var request = wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'post',
            data: values,
            dataType: 'json',
            cache: false
        });
        request.done(function (json) {
            if (json['data']['success'] != 'completed') {
                wlrmg_jquery('#wlba_limit_start').val(json['data']['limit_start']);
                wlrmg_jquery('#wlba-notification').css("display", "flex");
                wlrmg_jquery('#wlba-notification').html("<div class='alert success'>" + json['data']['notification'] + "</div>");
                var total_count = wlrmg_jquery('#wlba-total-count').val();
                if (total_count < json['data']['limit_start']) {
                    wlrmg_jquery('#wlba-process-count').html(total_count);
                } else {
                    wlrmg_jquery('#wlba-process-count').html(json['data']['limit_start']);
                }
                wlrmg.startExport();
            } else if (json['data']['success'] == 'completed') {
                wlrmg_jquery('#wlba-notification').append("<div class='alert success'> " + json['data']['notification'] + "</div>");
                setTimeout(function () {
                    alertify.alert().close();
                    location.reload();
                }, 1500);
            }
        })
    }
    wlrmg.showExported = function (job_id, action_type, bulk_action_type) {
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                wlba_nonce: wlrmg_localize_data.popup_nonce,
                action: 'wlba_get_exported_list',
                bulk_action_type: bulk_action_type,
                action_type: action_type,
                job_id: job_id,
            },
            success: function (result) {
                if (result.success) {
                    wlrmg_jquery("#wlba-main-page #wlba-overlay-section").addClass('active');
                    wlrmg_jquery("#wlba-main-page #wlba-overlay-section .wlba-overlay").html(result.data.html);
                }
            }
        });
    }

})(wlrmg);