(function( $ ){
 
	$.fn.multiple_emails = function() {
		
		return this.each(function() {
			var $orig = $(this);
			$list = $('<ul class="ulclass" />'); // create html elements - list of email addresses as unordered list

			if ($(this).val() != '') {
				$.each(jQuery.parseJSON($(this).val()), function( index, val ) {
					$list.append($('<li class="multiple_emails-email"><span class="email_name">' + val + '</span></li>')
					  .prepend($('<a href="#" class="multiple_emails-close" title="Remove"><span class="glyphicon glyphicon-remove"></span></a>')
						   .click(function(e) { $(this).parent().remove(); refresh_emails(); e.preventDefault(); })
					  )
					);
				});
			}
			
			var $input = $('<li style="float: left;"><input type="text" class="multiple_emails-input text-left" style="box-sizing: border-box;border: medium none;font-size: 15px;padding-top: 2px;margin-top: 5px;width:0.75em;" label="Enter One or More Vendor Email to Invite" />').keyup(function(event) { // input

				var width = '';
				var minimumWidth = $(this).find('input').val().length + 1;
				width = (minimumWidth * 0.75) + 'em';
				$(this).find('input').css('width', width);

				$(this).find('input').removeClass('multiple_emails-error');
				var input_length = $(this).find('input').val().length;
				
				//if(event.which == 8 && input_length == 0) { $list.find('li').last().remove(); }
				if(event.which == 13 || event.which == 32 || event.which == 188) { // key press is enter, space or comma
					 
					var val = $(this).find('input').val(); // remove space/comma from value
					 if(event.which != 13)
						 val = val.slice(0, -1); // remove space/comma from value
					 
					var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
					if (pattern.test(val) == true) {
						$li = $('<li style="background-color: #DBE8F1;cursor: default;float: left;margin: 8px 5px -4px 0px;padding: 4px 5px;line-height: 19px;" class="multiple_emails-email"><span style="display: inline-block;color: #808080;width: 16px;height: 16px;cursor: pointer;text-align: center;margin: 2px 0px 0px 15px;float: right;font-size: 18px;line-height: 16px;">×</span><span class="email_name">' + val + '</span></li>');
						 $list.find('li:last').before($li);
						
						 $li.find('span:first').click(function(e) { $(this).closest('li').remove(); refresh_emails(); e.preventDefault(); });
						refresh_emails ();
						$(this).find('input').val('');
					}
					else { $(this).find('input').val(val).addClass('multiple_emails-error'); }
				}
			});
			$input.find('input').focus(function(){
				$(this).closest('.polymer-form').addClass('dirty');
				$(this).closest('.polymer-form').find('.bar-in').addClass('active');
			});
			$input.find('input').blur(function(){
				var val = $(this).val();
				var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
				if (pattern.test(val) == true) {
					$li = $('<li style="background-color: #DBE8F1;cursor: default;float: left;margin: 8px 5px -4px 0px;padding: 4px 5px;line-height: 19px;" class="multiple_emails-email"><span style="display: inline-block;color: #808080;width: 16px;height: 16px;cursor: pointer;text-align: center;margin: 2px 0px 0px 15px;float: right;font-size: 18px;line-height: 16px;">×</span><span class="email_name">' + val + '</span></li>');
					 $list.find('li:last').before($li);
					
					 $li.find('span:first').click(function(e) { $(this).closest('li').remove(); refresh_emails(); e.preventDefault(); });
					refresh_emails ();
					$(this).val('');
				}
				else{
					$(this).val('');
				}
				$(this).closest('.polymer-form').find('.bar-in').removeClass('active');
				if($(this).closest('.polymer-form').find('textarea').val() == '' && $(this).closest('.polymer-form').find('textarea').val() == [])
					$(this).closest('.polymer-form').removeClass('dirty');
			});			
			var $container = $('<div class="" style="width:100%;"/>');
			$list.append($input);
			$container.append($list).insertAfter($(this)); // insert elements into DOM
			$container.closest('.polymer-form').click(function(){
				$(this).find('input').focus();
			});
			function refresh_emails () {
				var emails = new Array();
				$('.multiple_emails-email span.email_name').each(function() { emails.push($(this).html());	});
				if(emails.length > 0)
					$orig.val(JSON.stringify(emails));
				else
					$orig.val('');
			}
			//$('.multiple_emails-input').polymerForm();	
			
			return $(this).hide();
          });
     };
	

	 
})(jQuery);