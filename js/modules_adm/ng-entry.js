$(document).ready(function(){
	function ng_value(obj, isInitial) {
		cmd = obj.className.match(new RegExp('ng_ctrl_value_set([a-z0-9]*)'));
		if (!cmd || !cmd.length || cmd.length < 1) {
			return ;
		}
		switch (obj.tagName.toLowerCase()) {
			case 'input':
				switch ($(obj).attr('type').toLowerCase()) {
					case 'text':
						val = $(obj).val();
						break;
				}
				break;
		}
		$(['.ng_ctrl_accept_set' , cmd[1]].join('')).each(function(){
			if (this.className.match(new RegExp('ng_mod_makeuri'))) {
				if (isInitial && this.className.match(new RegExp('ng_mod_noload'))) {
					return;
				}
				cVal = makeUri(val);
				switch (this.tagName.toLowerCase()) {
					case 'input':
						$(this).val(cVal);
						break;
				}
			}
		});
	}
	$('.ng_ctrl').each(function(){
		var obj = $(this);
		var tagName   = this.tagName.toLowerCase();
		var className = this.className;
		var typeAttr  = obj.attr('type').toLowerCase();
		matches = this.className.match(new RegExp('ng_ctrl_[a-z]+_set[0-9]+', 'g'));
		for (i=0; i<matches.length; i++) {
			cmd = matches[i].match(new RegExp('^ng_ctrl_([a-z]*)_set([a-z0-9]*)$'));
			switch (cmd[1]) {
				case 'value':
					switch (tagName) {
						case 'input':
							switch (typeAttr) {
								case 'text':
								$(this).change(function(){
									ng_value(this, 0);
								});
								break;
							}
							break;
					}
					ng_value(this, 1);
					break;
			}
		}
	});
	
	// Older ones, better not use
	function autotoggle(o, prefix, variant, is_initial) {
		cmd = o.className.match(new RegExp(prefix + '\-autoset\-([a-z0-9]*)'));
		if (!cmd || !cmd.length || cmd.length < 2) {
			return ;
		}
		switch (o.tagName.toLowerCase()) {
			case 'a':
				if (!is_initial) {
					$(['.auto-set-' , cmd[1]].join('')).toggle();
				}
				break;
			case 'input':
				if (o.checked) {
					$(['.auto-set-' , cmd[1]].join('')).show();
				} else {
					$(['.auto-set-' , cmd[1]].join('')).hide();
				}
				break;
		}
	}
	function autovalue(o, prefix, variant, is_initial) {
		cmd = o.className.match(new RegExp(prefix + '\-autoset\-([a-z0-9]*)'));
		if (!cmd || !cmd.length || cmd.length < 2) {
			return ;
		}
		if (typeof(o.selectedIndex) != 'undefined') {
			switch (variant) {
				case 1:
					el = $(o.options[o.selectedIndex]).parent();
					if (el.get(0).tagName.toLowerCase() == 'optgroup') {
						val = el.attr('label');
					} else {
						el = $(o.options[o.selectedIndex]);
						if (el.attr('title') == '') {
							val = makeUri(el.text());
						} else {
							val = el.attr('title');
						}
					}
					break;
				default:
					el = $(o.options[o.selectedIndex]);
					if (el.attr('title') == '') {
						val = makeUri(el.text());
					} else {
						val = el.attr('title');
					}
					break;
			}
		}
	}
	$('.autocontrol').each(function(){
		matches = this.className.match(new RegExp('[a-z]+\-autoset\-[a-z0-9]+', 'g'));
		for (i=0; i<matches.length; i++) {
			cmd = matches[i].match(new RegExp('^([a-z]*)\-autoset\-([a-z0-9]*)$'));
			switch (cmd[1]) {
				case 'toggle':
					$(this).click(function(){
						autotoggle(this, 'toggle', 0, 0);
					});
					autotoggle(this, 'toggle', 0, 1);
					break;
				case 'value':
					$(this).click(function(){
						autovalue(this, 'value', 0, 0);
					});
					autovalue(this, 'value', 0, 1);
					break;
				case 'gvalue':
					$(this).click(function(){
						autovalue(this, 'value', 1, 0);
					});
					autovalue(this, 'value', 1, 1);
					break;
			}
		}
	});
});