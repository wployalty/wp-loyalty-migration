/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
if (typeof wlrmg_jquery == 'undefined') {
    wlrmg_jquery = jQuery.noConflict();
}
wlrmg = window.wlrmg || {};
wlrmg_jquery(document).ready(function () {
    wlrmg_jquery('#wlrmg-main-page .wlrmg-multi-select').select2();

    wlrmg_jquery("#search_email").keypress(function (event) {
        if (event.which === 13) {
            event.preventDefault();
            wlrmg_jquery("#search_button").click();
        }
    });
});
(function () {
    alertify.set('notifier', 'position', 'top-right');
    wlrmg.searchActivityByEmail = function (url) {
        let email = wlrmg_jquery("#wlrmg-main-page #wlrmg-activity-details #search_email").val();
        if (email !== "") {
            url = url + "&search=" + email;
        }
        window.location.href = url + "#wlrmg-activity-list-table";
    }
    wlrmg.saveSettings = function () {
        var button = wlrmg_jquery("#wlrmg-main-page #wlrmg-settings #wlrmg-save-settings");
        if (button.attr('disabled') === 'disabled') {
            return;
        }
        button.attr('disabled', true);
        wlrmg_jquery("#wlrmg-main-page #wlrmg-settings #wlrmg-save-settings").css({
            'cursor': 'not-allowed',
            'opacity': '0.4'
        });

        let form_data = wlrmg_jquery('#wlrmg-main-page #wlrmg-settings #settings-form').serialize();
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: form_data + '&action=wlrmg_save_settings&wlrmg_nonce=' + wlrmg_localize_data.save_settings,
            cache: false,
            success: function (res) {
                (res.success) ? alertify.success(res.data.message) : alertify.error(res.data.message);
                button.removeAttr('onclick');
                button.attr('disabled', false).attr('onclick', 'wlrmg.saveSettings()');
                wlrmg_jquery("#wlrmg-main-page #wlrmg-settings #wlrmg-save-settings").css({
                    'cursor': 'pointer',
                    'opacity': '1'
                });
            }
        });
    }
    wlrmg.needConfirmPointUpdate = function (type) {
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: "POST",
            dataType: "json",
            data: {
                action: "wlrmg_confirm_update_points",
                category: type,
                wlrmg_nonce: wlrmg_localize_data.migrate_users,
            },
            cache: false,
            async: false,
            success: function (json) {
                if (json['success'] === true) {
                    wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section").addClass('active');
                    wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section .wlrmg-overlay").html(json['data']['html']);
                    wlrmg_jquery('#wlrmg-main-page #wlrmg-overlay-section .wlrmg-multi-select').select2();

                }
            }
        });
    }

    wlrmg.migrateUsers = function () {
        let type = wlrmg_jquery("#wlrmg-popup #migration_type").val();
        let update_point = wlrmg_jquery("#wlrmg-popup #update_point").val();
        let update_banned_user = wlrmg_jquery("#wlrmg-popup #update_banned_user").val();
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wlrmg_migrate_users',
                wlrmg_nonce: wlrmg_localize_data.migrate_users,
                migration_action: type,
                update_point: update_point,
                update_banned_user: update_banned_user,
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
        var values = wlrmg_jquery('#wlrmg-export-preview').serializeArray();
        wlrmg_jquery('#wlrmg-process-export-button').attr('disabled', true);
        wlrmg_jquery('#wlrmg-overlay-section .wlrmg-export-popup .wlrf-close-circle').css('display', 'none');
        var request = wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'post',
            data: values,
            dataType: 'json',
            cache: false
        });
        request.done(function (json) {
            if (json['data']['success'] != 'completed') {
                wlrmg_jquery('#wlrmg_limit_start').val(json['data']['limit_start']);
                wlrmg_jquery('#wlrmg-notification').css("display", "flex");
                wlrmg_jquery('#wlrmg-notification').html("<div class='alert success'>" + json['data']['notification'] + "</div>");
                var total_count = wlrmg_jquery('#wlrmg-total-count').val();
                if (total_count < json['data']['limit_start']) {
                    wlrmg_jquery('#wlrmg-process-count').html(total_count);
                } else {
                    wlrmg_jquery('#wlrmg-process-count').html(json['data']['limit_start']);
                }
                wlrmg.startExport();
            } else if (json['data']['success'] == 'completed') {
                wlrmg_jquery('#wlrmg-overlay-section .wlrmg-export-popup .wlrf-close-circle').css('display', 'block');
                wlrmg_jquery('#wlrmg-notification').append("<div class='alert success'> " + json['data']['notification'] + "</div>");
                setTimeout(function () {
                    alertify.alert().close();
                    location.reload();
                }, 1500);
            }
        })
    }
    wlrmg.showExported = function (job_id, action_type) {
        wlrmg_jquery.ajax({
            url: wlrmg_localize_data.ajax_url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                wlrmg_nonce: wlrmg_localize_data.popup_nonce,
                action: 'wlrmg_get_exported_list',
                action_type: action_type,
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

    wlrmg.closePopUp = function (is_reload) {
        wlrmg_jquery("#wlrmg-main-page #wlrmg-overlay-section").removeClass('active');
        if (is_reload) {
            window.location.reload();
        }
    }

})(wlrmg);