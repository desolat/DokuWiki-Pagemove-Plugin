/**
 * Script for the tree management interface
 */


jQuery('#plugin_move__tree').find('ul.tree_list')
    // make folders open and close via AJAX
    .click(function (e) {
        var $link = jQuery(e.target);
        if ($link.attr('href')) {
            e.stopPropagation();
            var $li = $link.parent().parent('li');

            if ($li.hasClass('open')) {
                $li
                    .removeClass('open')
                    .addClass('closed')
                    .find('ul').remove();
            } else {
                $li
                    .removeClass('closed')
                    .addClass('open');

                var is_media = $li.closest('div.tree_root').hasClass('tree_media') ? 1 : 0;
                jQuery.post(
                    DOKU_BASE + 'lib/exe/ajax.php',
                    {
                        call: 'plugin_move_tree',
                        ns: $link.attr('href'),
                        is_media: is_media
                    },
                    function (data) {
                        $li.append(data);
                    }
                );
            }
        }
        e.preventDefault();
    })
    // initialize sortable
    .sortable({
        items: 'li',
        stop: function (e, ui) {
            var newparent = ui.item.parent().closest('li').attr('data-id');
            var oldparent = ui.item.attr('data-parent');

            console.log(newparent);

            if (newparent != oldparent) {
                ui.item.addClass('moved');
            } else {
                ui.item.removeClass('moved');
            }
        }
    });

jQuery('button.plugin_move_tree_exec').click(function(e){
    var data = [];

    jQuery('ul.plugin_move_tree .moved').each(function(idx, el){
        var $el = jQuery(el);
        var newparent = $el.parent().closest('li').attr('data-id');

        data[data.length] = {
            type: $el.hasClass('type-d') ? 'd' : 'f',
            from: $el.attr('data-id'),
            to: newparent + ':' + $el.attr('data-name')
        };
    });

    console.log(JSON.stringify(data));
});