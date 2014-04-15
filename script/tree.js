/**
 * Script for the tree management interface
 */

var $GUI = jQuery('#plugin_move__tree');

$GUI.show();
jQuery('#plugin_move__treelink').show();

var checkForMovement = function($li) {
    var newparent = $li.parent().closest('li').attr('data-id');
    var newname   = $li.attr('data-name');
    var oldid     = $li.attr('data-id');
    var newid     = (newparent+':'+newname).replace(/^:+/, '');

    if (newid != oldid) {
        $li.addClass('moved');
        $li.children('div').attr('title', oldid+' -> '+newid);
    } else {
        $li.removeClass('moved');
        $li.children('div').attr('title', '');
    }
};


$GUI.find('ul.tree_list')
    .click(function (e) {
        var $clicky = jQuery(e.target);
        var $li = $clicky.parent().parent();

        if ($clicky.attr('href')) {  // Click on folder - open and close via AJAX
            e.stopPropagation();
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
                            ns: $clicky.attr('href'),
                            is_media: is_media
                        },
                        function (data) {
                            $li.append(data);
                        }
                    );
                }
            }
        } else if ($clicky[0].tagName == 'IMG') { // Click on IMG - do rename
            e.stopPropagation();
            var $a  = $clicky.parent().find('a');

            var newname = window.prompt(LANG.plugins.move.renameitem, $li.attr('data-name'));
            if(newname) {
                $li.attr('data-name', newname);
                $a.text(newname);
                checkForMovement($li);
            }
        }
        e.preventDefault();
    })
    // initialize sortable
    .find('ul').sortable({
        items: 'li',
        stop: function (e, ui) {
            checkForMovement(ui.item);
        }
    })
    // add title to rename icon
    .find('img').attr('title', LANG.plugins.move.renameitem);

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