/**
 * Book.js
 */

(function($) {
	$(function() {
		/* Start book page tool bar */
		$("#book_tools a").tipsy();

		var z_index = 300;

		var toggle_pannel = function(pannel_id, button) {
			// hide all other dialog_boxes
			$(".dialog_box").hide();

			pannel = $('#' + pannel_id);

			var width = $('#book_item_tools').width();
			var right = width - ($(button).position().left + $(button).width());

			// font size changer are center aligned
			if (pannel_id == 'font_size_pannel') {
				right -= (pannel.width() - $(button).width()) / 2;
			}

			pannel.css('right', right);
			pannel.css('z-index', ++z_index);

			pannel.fadeIn('fast');
		}

		$.each(['page_outline', 'share_page', 'font_size'], function(index, id) {
			$('#' + id).hover(function() {
				toggle_pannel(id + '_pannel', this);

				$('#' + id + '_pannel').mouseleave(function() {
					$(this).fadeOut();
				});
			});
		});

		$("#share_page_pannel a").click(function() {
			$('#share_page_pannel').hide();
		});

		$("#page_outline_pannel a").click(function() {
			$("#page_outline_pannel").toggle();
		});

		// change the font-size of the book body
		var change_font_size = function(delta) {
			var book_body = $("#book_body");
			var org_font_size = book_body.css("font-size");

			if (!$("#book_body").data('org_font_size')) {
				$("#book_body").data("org_font_size", org_font_size);
			}

			var new_size = delta ? parseFloat(org_font_size) + delta + "px": book_body.data("org_font_size");
			$("#book_body").css("font-size", new_size);
		}

		$("#font_size_incr").click(function() {
			change_font_size(1);
		});

		$("#font_size_decr").click(function() {
			change_font_size( - 1);
		});

		$("#font_size_default").click(function() {
			change_font_size();
		});

		/* Make the book toolbar more smart */
		(function() {
			var toolbar = $("#book_tools");
			if (toolbar.length == 0) return;

			var tb_width = toolbar.width();
			var tb_height = toolbar.height();
			var tb_top = toolbar.position().top;
			var org_body_margin = $("#book_body").css("margin-top");

			$(window).scroll(function() {
				if ($(window).scrollTop() > tb_top) {
					$('body').addClass('smartscroll');
					toolbar.css("width", tb_width);
					$("#book_body").css('margin-top', tb_height);
				}
				else {
					// restore
					$('body').removeClass('smartscroll');
					$("#book_body").css('margin-top', org_body_margin);
				}
			});
		})();

		/* End book page tool bar */

		/* Start code pre auto extend  */
		var initialWidth = $('pre').width();
		$('pre').hover(function() { 
            var pop = $('<p class="er-code" >er ... </p>');
            $(this).after(pop);
            $(this).css('left'      , '10px');
            $(this).css('height'    , $(this).height());
            $(this).css('z-index'   , '1');
            $(this).css('position'  , 'absolute');
            $(this).css('width'     , '96%');
            $(this).css('white-space' ,'');
            pop.css('padding' , '10px');
            pop.css('height' , $(this).height()  +  'px');
		},
		function() { 
            $('.er-code').remove();
            $(this).css('position' , '');
            $(this).css('left' , '100px');
            $(this).css('white-space' , 'pre-wrap'); /* css-3 */
            $(this).css('white-space' , ' -moz-pre-wrap'); !important; /* Mozilla, since 1999 */
            $(this).css('white-space' , ' -pre-wrap'); /* Opera 4-6 */
            $(this).css('white-space' , ' -o-pre-wrap'); /* Opera 7 */
            $(this).css('word-wrap'   , ' break-word'); /* Internet Explorer 6.5+ */
			$(this).animate({ width: initialWidth }, 'fast'); 
		});
		/* End code pre auto extend */

	});
})(jQuery);

