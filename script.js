/**
 * JavasScript code for the preview in the pagemove plugin
 *
 * @author Michael Hamann <michael@content-space.de>
 */

jQuery(function() {
    jQuery('form.pagemove__form').each(function() {
        var $this = jQuery(this);
        var $preview = jQuery('<p></p>');
        $this.find('input[type=submit]').before($preview);
        var updatePreview = function() {
            if ($this.find('input[name=move_type]').attr('value') == 'namespace') {
                var targetns = $this.find('select[name=targetns]').attr('value');
                var newnsname = $this.find('input[name=newnsname]').attr('value');
                var previewns;
                if (targetns == ':') {
                    previewns = newnsname;
                } else {
                    previewns = targetns + ':' + newnsname;
                }
                $preview.text(LANG['plugins']['pagemove']['pm_previewns'].replace('OLDNS', JSINFO['namespace']).replace('NEWNS', previewns));
            } else {
                var ns_for_page = $this.find('select[name=ns_for_page]').attr('value');
                var newns = $this.find('input[name=newns]').attr('value');
                var newname = $this.find('input[name=newname]').attr('value');
                var newid = '';
                if (newns.replace(/\s/g) != '') {
                    newid = newns + ':';
                } else if (ns_for_page != ':') {
                    newid = ns_for_page + ':';
                }
                newid += newname;
                $preview.text(LANG['plugins']['pagemove']['pm_previewpage'].replace('OLDPAGE', JSINFO['id']).replace('NEWPAGE', newid));

            }
        };
        updatePreview();
        $this.find('input,select').change(updatePreview);
        $this.find('input').keyup(updatePreview);
    });
});