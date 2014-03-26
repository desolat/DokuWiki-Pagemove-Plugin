/**
 * JavasScript code for the preview in the move plugin
 *
 * @author Michael Hamann <michael@content-space.de>
 */

jQuery(function() {
    jQuery('form.move__form').each(function() {
        var $this = jQuery(this);
        var $preview = jQuery('<p></p>');
        $this.find('input[type=submit]').before($preview);
        var updatePreview = function() {
            if ($this.find('input[name=move_type]').val() == 'namespace') {
                var targetns = $this.find('select[name=targetns]').val();
                var newnsname = $this.find('input[name=newnsname]').val();
                var previewns;
                if (targetns == ':') {
                    previewns = newnsname;
                } else {
                    previewns = targetns + ':' + newnsname;
                }
                $preview.text(LANG['plugins']['move']['previewns'].replace('OLDNS', JSINFO['namespace']).replace('NEWNS', previewns));
            } else {
                var ns_for_page = $this.find('select[name=ns_for_page]').val();
                var newns = $this.find('input[name=newns]').val();
                var newname = $this.find('input[name=newname]').val();
                var newid = '';
                if (typeof newns == 'undefined') {
                    return;
                }
                if (newns.replace(/\s/g) != '') {
                    newid = newns + ':';
                } else if (ns_for_page != ':') {
                    newid = ns_for_page + ':';
                }
                newid += newname;
                $preview.text(LANG['plugins']['move']['previewpage'].replace('OLDPAGE', JSINFO['id']).replace('NEWPAGE', newid));

            }
        };
        updatePreview();
        $this.find('input,select').change(updatePreview);
        $this.find('input').keyup(updatePreview);
    });

    jQuery('form.move__nscontinue').each(function() {
        var $this = jQuery(this);
        var $container = jQuery('div.plugin__move_forms');
        var submit_handler = function() {
            $container.empty();
            var $progressbar = jQuery('<div></div>');
            $container.append($progressbar);
            $progressbar.progressbar({value: false});
            var $message = jQuery('<div></div>');
            $container.append($message);
            var skip = jQuery(this).hasClass('move__nsskip');

            var continue_move = function() {
                jQuery.post(
                    DOKU_BASE + 'lib/exe/ajax.php',
                    {
                        call: 'plugin_move_ns_continue',
                        id: JSINFO['id'],
                        skip: skip
                    },
                    function(data) {
                        if (data.remaining === false) {
                            $progressbar.progressbar('option', 'value', false);
                        } else {
                            $progressbar.progressbar('option', 'value', data.pages + data.media + data.affected - data.remaining);
                            $progressbar.progressbar('option', 'max', data.pages + data.media + data.affected);
                        }
                        $message.html(data.html);
                        if (data.remaining === false) {
                            $container.find('form.move__nscontinue, form.move__nsskip').submit(submit_handler);
                        } else if (data.remaining === 0) {
                            window.location.href = data.redirect_url;
                        } else {
                            window.setTimeout(continue_move, 200);
                        }
                    },
                    'json'
                );
                skip = false;
            };

            continue_move();
            return false;
        };
        $this.submit(submit_handler);
    });

    // hide preview list on namespace move
    jQuery('#move__preview_list').each(function(){
        var $this = jQuery(this);
        $this.find('ul').hide();
        $this.find('span')
            .click(function(){
                console.log('woah');
                $this.find('ul').dw_toggle();
                $this.find('span').toggleClass('closed');
            })
            .addClass('closed');
    });

    // page move dialog
    // FIXME check permissions
    jQuery('.plugin_move_page')
        .show()
        .click(function(e) {
            e.preventDefault();

            var renameFN = function () {
                var self = this;
                var newid = $dialog.find('input[name=id]').val();
                if (!newid) return;

                // remove buttons and show throbber
                $dialog.html(
                    '<img src="'+DOKU_BASE+'lib/images/throbber.gif" /> '+
                    LANG.plugins.move.inprogress
                );
                $dialog.dialog('option', 'buttons', []);

                // post the data
                jQuery.post(
                    DOKU_BASE + 'lib/exe/ajax.php',
                    {
                        call: 'plugin_move_rename',
                        id: JSINFO.id,
                        newid: newid
                    },
                    // redirect or display error
                    function (result) {
                        if(result.error){
                            $dialog.html(result.error);
                        } else {
                            window.location.href = result.redirect_url;
                        }
                    }
                );

                return false;
            };

            // basic dialog template
            var $dialog = jQuery(
                '<div>' +
                    '<form>' +
                    '<label>' + LANG.plugins.move.newname + ' ' +
                    '<input type="text" name="id">' +
                    '</label>' +
                    '</form>' +
                '</div>'
            );
            $dialog.find('input[name=id]').val(JSINFO.id);
            $dialog.find('form').submit(renameFN);

            // set up the dialog
            $dialog.dialog({
                title: LANG.plugins.move.rename+' '+JSINFO.id,
                width: 340,
                height: 180,
                dialogClass: 'plugin_move_dialog',
                modal: true,
                buttons: [
                    {
                        text: LANG.plugins.move.cancel,
                        click: function () {
                            $dialog.dialog("close");
                        }
                    },
                    {
                        text: LANG.plugins.move.rename,
                        click: renameFN
                    }
                ],
                // remove HTML from DOM again
                close: function () {
                    jQuery(this).remove();
                }
            })
        });
});