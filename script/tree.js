/**
 * Script for the tree management interface
 */

var $GUI = jQuery('#plugin_move__tree');

$GUI.show();

$GUI.find('ul.tree_list')
    // make folders open and close via AJAX
    .click(function (e) {
        var $link = jQuery(e.target);
        if ($link.attr('href')) {
            e.stopPropagation();
            var $li = $link.parent().parent();

            if ($li.hasClass('open')) {
                $li
                    .removeClass('open')
                    .addClass('closed');

            } else {
                $li
                    .removeClass('closed')
                    .addClass('open');

                // if had not been loaded before, load via AJAX
                if (!$li.find('ul').length) {
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
        }
        e.preventDefault();
    })
    // initialize sortable
    .find('ul').sortable({
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

/**
 * Gather all moves from the trees and put them as JSON into the form before submit
 */
jQuery('#plugin_move__tree_execute').submit(function (e) {
    var data = [];

    $GUI.find('.tree_pages .moved').each(function (idx, el) {
        var $el = jQuery(el);
        var newparent = $el.parent().closest('li').attr('data-id');

        data[data.length] = {
            class: $el.hasClass('type-d') ? 'ns' : 'doc',
            type: 'page',
            src: $el.attr('data-id'),
            dst: newparent + ':' + $el.attr('data-name')
        };
    });
    $GUI.find('.tree_media .moved').each(function (idx, el) {
        var $el = jQuery(el);
        var newparent = $el.parent().closest('li').attr('data-id');

        data[data.length] = {
            class: $el.hasClass('type-d') ? 'ns' : 'doc',
            type: 'media',
            src: $el.attr('data-id'),
            dst: newparent + ':' + $el.attr('data-name')
        };
    });

    jQuery(this).find('input[name=json]').val(JSON.stringify(data));
});