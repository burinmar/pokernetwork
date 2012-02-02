function isEmail(email) {
	if (/^[a-zA-Z0-9!#$%&*+\/=?\_`{|}~-]+([\\.-][a-zA-Z0-9!#$%&*+\/=?^_`{|}~-]+)*@[a-zA-Z0-9]+([\.-]?[a-zA-Z0-9]+)*(\.[a-zA-Z]{2,})+$/.test(email)){return (true);}
	else{return (false);}
}

error = {

evFocus: function ()
{
},

evBlur: function ()
{
	error.check(this.form, this.name);
},

check: function (form, name)
{
	var el = form.elements[name];
	var v = $.trim(el.value);
	//alert(this.name);
	switch (name) {

		case 'nick':
			var reg = new RegExp('^[a-z0-9_\\.-]{4,15}$', 'ig');
			if (v == '') {
				error.trigger(el, 'err1');
			}
			else if (! reg.test(v)) {
				error.trigger(el, 'err2');
			} else {
				error.ok(el);
			}
			break;

		case 'email':
			if (!isEmail(v)) {
				error.trigger(el, 'err3');
			}
			else {
				error.ok(el);
			}
			break;

		case 'password':
			var pv = $.trim(form.elements['password2'].value);
            if (v == "") {
            	error.trigger(el, 'err4');
            }
			else {
				if (pv == "") {
					//error.trigger(el, 'err1');
					error.ok(el, false);
				}
				else if (pv != v) {
					error.trigger(el, 'err5');
				}
				else {
					error.ok(el);
				}
			}
			break;

		case 'password2':
			el = form.elements['password'];
			var pv = $.trim(el.value);
            if (pv == "") {
            	error.trigger(el, 'err4');
            }
			else if (pv != v) {
				error.trigger(el, 'err5');
			}
			else {
				error.ok(el);
			}
			break;

		case 'toc':
            if (!el.checked) {
            	error.trigger(el, 'err6');
            }
			else {
				error.ok(el, false);
			}
			break;

		case 'code':
            if (v == "") {
            	error.trigger(el, 'err10');
            }
			else {
				error.ok(el, false);
			}
			break;
	}
},

evSubmit: function ()
{
    var elF = this;
	error.isError = false;
    error.check(this, 'nick');
    error.check(this, 'email');
    error.check(this, 'password2');
    error.check(this, 'code');
    error.check(this, 'toc');
	return !error.isError;
},

trigger : function(el, code)
{
	if (typeof(error.messages) != "undefined" && typeof(error.messages[code]) != "undefined") {
		error.isError = true;
		var parent = $(el).closest(el.name == 'toc' ? 'dd' : 'dd');
        parent.addClass('error');
		var span = parent.children('.tip');
		if (span.size()) {
        	span.text(error.messages[code]);
		}
		else {
			$(el).after('<em class="tip errorTip">' + error.messages[code] + '</em>');
		}
	}
},

ok : function(el, empty)
{
	var parent = $(el).closest(el.name == 'toc' ? 'dd' : 'dd');
	parent.removeClass('error');
	if (typeof(empty) != "boolean" || empty != false) {
		parent.addClass('ok');
	}
	parent.children('.tip').text('');
},

isError : false

}
