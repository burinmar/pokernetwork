/* WYSIWYG objektas (toolbar and attachments)
* konstruktorius
*********************************************************/

function findRTF(id) {
	try {
		var c=top.document.attachments.length;
	} catch (e) {
		document.domain = document.domain.match(/([a-z1-9]+.[a-z1-9]+)$/)[1];
	}
	if (top.document.attachments) {
		var c=top.document.attachments.length;
		for (var i in top.document.attachments) {
			if (top.document.attachments[i].wysiwyg.config.instance==id)
				return  top.document.attachments[i];
		}
	}
	return null;
}

function rtfObject(config)
{
	this.config = config;
	this.textarea = null;
	if (this.imgPath == null) {
		this.imgPath='/img/rtf/';
	}

	if(typeof(rtfObject.instances)=="undefined") {
		top.document.rtfReturn = [];
		top.document.attachments = [];
		rtfObject.instances = [];
		//modal langas objektams

		//uzkomentuota, nes IE atlaisvina
		//if (typeof(top.rtfModalWindow)=="undefined")
			top.rtfModalWindow = new modalWindow(620,400);
		rtfObject.modal = top.rtfModalWindow;

	}
	this.index = rtfObject.instances.length;
	rtfObject.instances[this.index] = this;

	//Aprasomi toolbar'o mygtukai
	function button(code,title,img)
	{
		this.title=title;
		if ("string" == typeof(img)) this.image = img;

		this.onclick = function(wysiwyg) {
            var str = '[' + code + ']' + wysiwyg.selectedText() + '[/' + code + ']';
			var k = code.length+2;
            wysiwyg.insert(str, k, k+1);
        }

	}
    //
    this.button = new Array();
	this.button['heading'] = new button('H', 'Heading');
    this.button['bold'] = new button('B', 'Bold');
	this.button['italic'] = new button('I', 'Italic');
	this.button['underline'] = new button('U', 'Underline');

    this.button['list'] = {
        title: 'Bulleted List',
        image: this.imgPath + 'listbulleted.png',
        onclick: function(wysiwyg) {
            var str = '\n[LIST]\n[*]' + wysiwyg.selectedText() + '\n[*]\n[/LIST]\n';
            wysiwyg.insert(str, 11, 13);
        }
    }
    this.button['olist'] = {
        title: 'Ordered list (LIST=1 or LIST=a)',
        image: this.imgPath + 'listnumbered.png',
        onclick: function(wysiwyg) {
            var str = '\n[LIST="1"]\n[*]' + wysiwyg.selectedText() + '\n[*]\n[/LIST]\n';
            wysiwyg.insert(str, 15, 13);
        }
    }

    this.button['table'] = {
        title: 'Simple table',
        image: this.imgPath + 'table.png',
        /*onclick: function(wysiwyg) {
            var str = '\n[TABLE]\n' + wysiwyg.selectedText() + '|\n[/TABLE]\n';
            wysiwyg.insert(str, 9, 11);
        },*/
		onclick: function(wysiwyg) {
			wysiwyg.textarea.focus();
        },
		form: this.formTable()
    }

    this.button['code'] = new button('CODE', 'Code (monospaced text)');
	this.button['quote'] = new button('QUOTE', 'Quote');
	this.button['strike'] = new button('STRIKE', 'Strike throught');
	this.button['sub'] = new button('SUB', 'Subscript');
	this.button['sup'] = new button('SUP', 'Superscript');
	this.button['spoiler'] = new button('SPOILER', 'Spoiler');

	this.button['readmore'] = {
        title: 'Read more',
        image: this.imgPath + 'readmore.png',
        onclick: function(wysiwyg) {
            var str = wysiwyg.selectedText() + '\n---ReadMore---\n';
            wysiwyg.insert(str, 0, 0);
        },
		hide: true
    }

	this.button['pagebreak'] = {
        title: 'PageBreak',
        onclick: function(wysiwyg) {
            var str = wysiwyg.selectedText() + '\n---PageBreak---\n';
            wysiwyg.insert(str, 0, 0);
        },
		hide: true
    }
    this.button['img'] = {
        title: 'Image',
        image: this.imgPath + 'image.png',
        onclick: function(wysiwyg) {
            //var str = '[IMG]' + wysiwyg.selectedText() + '[/IMG]\n';
            //wysiwyg.insert(str, 5, 7);
        },
		form: this.formImg()
    }

    this.button['video'] = {
        title: 'Video',
        image: this.imgPath + 'video.png',
        onclick: function(wysiwyg) {
            //var str = '[VIDEO]' + wysiwyg.selectedText() + '[/VIDEO]\n';
            //wysiwyg.insert(str, 7, 9);
        },
		form: this.formVideo()
    }

	this.button['hand'] = {
        title: 'Hand Player',
        onclick: function(wysiwyg) {
        	var wo=rtfObject;
            var wid=wysiwyg.config.instance;
			if(encodeURIComponent) wid=encodeURIComponent(wid);
			wo.modal.openURL(wysiwyg.config.urlHands+ (wysiwyg.config.urlHands.indexOf('?')==-1 ? '?':'&') + 'wid=' + wid, 1000, 850);
			wo.modal.resizeTo(1000,800,false);

        },
		hide: true
    }

	this.button['poll'] = {
        title: 'Insert poll',
        onclick: function(wysiwyg) {
        	var wo=rtfObject;
            var wid=wysiwyg.config.instance;
			if(encodeURIComponent) wid=encodeURIComponent(wid);
			wo.modal.openURL(wysiwyg.config.urlPoll+ (wysiwyg.config.urlPoll.indexOf('?')==-1 ? '?':'&') + 'wid=' + wid, 800, 450);
			wo.modal.resizeTo(800,450,false);
        },
		hide: true
    }
	
    this.button['smiles'] = {
        title: 'Smiles',
		image: this.imgPath + 'smilies.png',
        onclick: function(wysiwyg) {
			wysiwyg.textarea.focus();
        },
		form: this.formSmiles()
    }
    this.button['cards'] = {
        title: 'Cards',
        image: this.imgPath + 'cards.png',
        onclick: function(wysiwyg) {
			wysiwyg.textarea.focus();
        },
		form: this.formCards()
    }
    this.button['link'] = {
        title: 'Hyperlink',
        image: this.imgPath + 'link.png',
		onclick: function(wysiwyg) {
			jQuery("#linkTextField"+wysiwyg.index).val(wysiwyg.selectedText());
			jQuery("#linkURLField"+wysiwyg.index).select();
        },
		form: this.formLink()
    }

	this.button['twitter'] = {
        title: 'Twitter',
        image: this.imgPath + 'twitter.png',
		onclick: function(wysiwyg) {
			jQuery("#twitterURLField"+wysiwyg.index).select();
        },
		form: this.formTwitter()
    }
	this.button['game'] = new button('GAME', 'Game',this.imgPath +'cards.png');


	this.button['timer'] = {
        title: 'Timer',
        image: this.imgPath + 'clock.png',
        onclick: function(wysiwyg) {
        	var zero = function (n) {return (n>9 ? n : ('0' + n))}
        	var dt = new Date();
			var y = dt.getYear();
			var ds = (y < 1000 ? (1900 + y) : y) + '-' + zero((dt.getMonth()+1)) + '-' + zero(dt.getDate());
            var str = '[TIMER="'+ds+' 00:00|'+ds+' 23:59"]' + wysiwyg.selectedText() + '[/TIMER]';
            wysiwyg.insert(str, 43, 8);
        }
    }

    this.button['preview'] = {
        title: 'Preview',
        onclick: function(wysiwyg) {
        	var wo=rtfObject;
            var wid=wysiwyg.config.instance;
			if(encodeURIComponent) wid=encodeURIComponent(wid);
			jQuery.post(
				wysiwyg.config.uriPreview+ (wysiwyg.config.uriPreview.indexOf('?')==-1 ? '?':'&') + 'wid=' + wid,  //url
				{ body : wysiwyg.textarea.value } , //post duomenys
            	function (s) {wo.modal.open(s); wo.modal.resizeTo(800,600,false);}
			);
        }
    }

    var _self = this;
	jQuery(window).ready( function() { _self.init(config) });
}

/* Inicializuoja. Paleidzia konstruktorius pasikrovus puslapiui
*********************************************************/
rtfObject.prototype.init = function(config)
{
    // textarea laukas, prie kurio pririsamas toolbaras ir attachmentai
	var jT = jQuery('#'+config.textarea);
	this.textarea = jT.get(0);

	if (this.textarea != null) {
        // IE dd > textarea fix
		/*var fix = 0;
		if (fix = jT.height()) {
			jT.height(fix);
		}
		if (fix = jT.width()) {
			jT.width(fix);
		}*/
        //dedam toolbara
		jT.before(this.toolbar());
		//prikabinsim attachmentu ifframe
		if (this.config.uriObjects) {

			jT.after(this.attachments());
			this.loadAttachments();
		}

        //textarea prisegam kursoriaus pozicijos saugojima
		function caretTracker(e)
		{
			if (this.createTextRange) {
				var e = typeof(e) == "undefined" ? window.event : e;
				if (e.type != 'blur') {
					this.caretPos = this.document.selection.createRange().duplicate();
				}
			}
			else if (typeof(this.selectionStart) != "undefined") {
				this.caretPos = this.selectionStart;
			}

		}

		var objRTF =this;
		function ctrlTracker(e)	{
			var e = typeof(e) == "undefined" ? window.event : e;
			var isCtrl = (e.metaKey==1 || e.ctrlKey) && !e.altKey;
			switch (e.type) {
				case "keypress":
				case "keydown":
					switch (e.keyCode) {
						case 17: break; //key up
						case 66: // b
						case 73: // i
						case 85: // u
						case 75: // k
							if (isCtrl) {
								if (e.type == "keydown") {
									var cmd={66:'bold', 73:'italic', 85:'underline', 75:'link'};
									objRTF.buttonEvent(cmd[e.keyCode], 'click');
								}
								return false;
							}
						break;
					}
				break;
			}
		}
		jT.bind('click select blur keyup', caretTracker);
		jT.bind('keyup keypress keydown', ctrlTracker);
	}
}

/* Inicializuoja. Paleidzia konstruktorius pasikrovus puslapiui
*********************************************************/
rtfObject.prototype.loadAttachments = function(src)
{
	if (!src) {
        var wid=this.config.instance;
		if(encodeURIComponent) wid=encodeURIComponent(wid);
		src=this.config.uriObjects + (this.config.uriObjects.indexOf('?')==-1 ? '?':'&') + 'wid=' + wid;
	}
	jQuery('#frm_obj_browser'+this.index).load(src);
}


/* Grazina textarea esanti pazymeta teksta
*********************************************************/
rtfObject.prototype.selectedText = function()
{
	var res = '';
	if(this.textarea) {
		var t = this.textarea;
		if (t.createTextRange && t.caretPos) {
			res = t.caretPos.text;
		} else if(typeof(t.selectionStart)!="undefined" && typeof(t.caretPos)!="undefined") {
			res = t.value.substr(t.selectionStart, t.selectionEnd - t.selectionStart);
		}
	}
	res = res.replace(/\r\n/g,"\n");
	return res;
}


/* Iterpia i textarea stringa str
* Galima iterpta teksta pazymeti nurodant startPos ir endPos (str atzvilgiu)
*********************************************************/
rtfObject.prototype.insert = function(str, startPos, endPos) {
	if (this.textarea) {
		var t = this.textarea;

		if (typeof(startPos) == "undefined") {
			startPos = 0;
		}
		if (typeof(endPos) == "undefined") {
			endPos = 0;
		}

		if (t.createTextRange && t.caretPos) {
			t.caretPos.text = str;

			if (startPos > 0 && endPos > 0) {
				t.caretPos.moveStart("character", -str.length + startPos);
				t.caretPos.moveEnd("character", -endPos);
				t.caretPos.select();
			}
		}
		else if (typeof(t.selectionStart) != "undefined" && typeof(t.caretPos) != "undefined") {
            var scrollHeight = t.scrollHeight;
    		var scrollTop = t.scrollTop;
			var s = t.value.substr(0, t.caretPos) + str + t.value.substr(t.caretPos + t.selectionEnd - t.selectionStart);
			t.value = s;
			t.caretPos = t.caretPos + str.length;
			if (startPos > 0 && endPos > 0) {
				t.setSelectionRange(t.caretPos - str.length + startPos, t.caretPos - endPos);
			} else {
				t.setSelectionRange(t.caretPos, t.caretPos);
			}
			t.caretPos = t.selectionStart;
            //
		    // Retain scroll position in firefox
		    //
            if (scrollTop) {
      			t.scrollTop = scrollTop + (t.scrollHeight - scrollHeight);
    		}

		} else {
			t.value = str + t.value;
		}

		t.focus();
	}
}


/* Toolbar'o mygtukui prisega css klases.
*********************************************************/
rtfObject.prototype.buttonEvent = function(btn_type, event) {
    switch(event) {
        case 'click':


            //if(btn_type=="cards" || btn_type=="smiles" || btn_type=="link") {

                var btn_id = '#btn_' + this.index + '_' + btn_type;
                jQuery(btn_id).parent().siblings().removeClass('expanded');
				if(this.button[btn_type].form) {
					jQuery(btn_id).parent().toggleClass('expanded');
				}
            //}
			if(this.button[btn_type].onclick) this.button[btn_type].onclick(this);
            break;
        /*case 'mouseover':
            if(this.button[btn_type].onmouseover) this.button[btn_type].onmouseover(this);
            var btn_id = '#btn_' + this.index + '_' + btn_type;
            jQuery(btn_id).addClass('btn_raised');
            break;
        case 'mouseout':
            if(this.button[btn_type].onmouseout) this.button[btn_type].onmouseout(this);
            var btn_id = '#btn_' + this.index + '_' + btn_type;
            jQuery(btn_id).removeClass('btn_raised');
            break;*/
        case 'submit':
            if(this.button[btn_type].onsubmit) this.button[btn_type].onsubmit(this);
            break;

		case 'close':
				var btn_id = '#btn_' + this.index + '_' + btn_type;
                if(this.button[btn_type].form) {
					jQuery(btn_id).parent().removeClass('expanded');
				}
    }
}


/* TOOLBAR. Pavaizduoja mygtuku juosta
*********************************************************/
rtfObject.prototype.toolbar = function() {
    var res = '';
    var btCustom = new Array();
    var b;
    var link_window = false;
    var preview_window = false;
    var defaultButtons=new Array();
	if (this.config.buttons) {
	    btCustom = this.config.buttons.split(',');
	}
    for(var i in this.button) {
        var btn_type = i;
        if(jQuery.inArray('-' + btn_type,btCustom)!=-1) continue;
        else if(this.button[btn_type].hide && jQuery.inArray(btn_type,btCustom)==-1) continue;
        if(btn_type=='link' && !this.config.uriPreview) continue;
        if(btn_type=='objects' && !this.config.uriObjects) continue;
		var add='';
        if(this.button[btn_type]) {
            b = this.button[btn_type];
			if ("string" != typeof(b.image)) {
				b.image=this.imgPath + btn_type + '.png';
			}
			if ("string" != typeof(b.form)) {
				b.form = '';
			}
			add = b.form ? '<div class="btnPopup btnPopup'+btn_type+'" id="wysiwyg_'+btn_type+'_' + this.index + '">'+b.form+'</div>': '';
			b.title = this.translate( b.title);

            var btn_id = 'btn_' + this.index + '_' + btn_type;
            res += '<li><img src="' + b.image + '" id="' + btn_id + '" class="btn' + (b.form ? ' btnExpanding':'') + '" onclick="rtfObject.instances[' + this.index + '].buttonEvent(\'' + btn_type + '\', \'click\');" onmouseover="rtfObject.instances[' + this.index + '].buttonEvent(\'' + btn_type + '\', \'mouseover\');" onmouseout="rtfObject.instances[' + this.index + '].buttonEvent(\'' + btn_type + '\', \'mouseout\');" title="' + b.title + '" width="16" height="16" />'+add+'</li>';
        }
    }
    return '<ul class="wysiwygControls">'+res+'</ul>';
}


/* Attachments. Pavaizduoja attachment ifframe juosta
*********************************************************/
rtfObject.prototype.attachments = function()
{
	var i=this.index;
	var wid=this.config.instance;
	if(encodeURIComponent) wid=encodeURIComponent(wid);
	else if(escape) wid=escape(wid);
	var src=this.config.uriObjects + (this.config.uriObjects.indexOf('?')==-1 ? '?':'&') + 'wid=' + wid;

//objektas, per kuri jungsis attachmentu formos
	top.document.attachments[i] = {
			// modal lango valdymas
		modal: rtfObject.modal,
			//edit lango atidarymas
		edit: function (url) {this.modal.openURL(url)},
			//sitas pats wysiwyg objektas
		wysiwyg: rtfObject.instances[i],
			//iterpia teksta
		insertText: function (txt){this.wysiwyg.insert(this.wysiwyg.selectedText()+' '+txt+' ')},
			//
		create: function (){alert('n/a')},
			//metodas atnaujinantis attachmentu ifframe
		reload: function (url){this.wysiwyg.loadAttachments(url)/*jQuery('#frm_obj_browser'+i).get(0).src=src*/},
			//metodas parodantis arba paslepiantis attachmentus
		show: function (on) {return;jQuery('#frm_obj_browser'+i).height( on ? "150px":"1px" );}
	}

    var sAttach = '';
	if (this.config.attachments) {
		btCustom = this.config.attachments.split(',');
		var a;
		for(var k in btCustom) {
			a=jQuery.trim(btCustom[k]);
			if (sAttach != '') sAttach += ' | ';
			sAttach +=  '<a href="javascript:void(0)" onclick="top.document.attachments['+i+'].create(\''+a+'\');return false">'+a+'</a>';
		}
	}
	if (sAttach == '') return '';
	return '<div class="attachments" id="frm_obj_browser'+i+'"></div>';
}


rtfObject.prototype.formImg = function()
{
	var id = this.index;
	top.document.rtfReturn[id] = this;
	var res = '<p class="btnPopupTitle">Insert&nbsp;Image</p>' +
				'<span class="btnPopupRow"><label for="imageURLField'+id+'">Image&nbsp;URL: </label><input type="text"  name="rtf_imgurl'+id+'" value="http://" id="imageURLField'+id+'"/  onfocus="this.select()"></span>' +
				'<button type="button" onclick="var str=\'[IMG]\' + this.form.rtf_imgurl'+id+'.value + \'[/IMG]\'; top.document.rtfReturn['+id+'].insert(str, 5,6);top.document.rtfReturn['+id+'].buttonEvent(\'img\',\'close\');">Insert</button>';
   return res;
}

rtfObject.prototype.formVideo = function()
{
	var id = this.index;
	top.document.rtfReturn[id] = this;
	var lang_ = typeof lang != 'undefined' ? lang : '';
	var res = '<p class="btnPopupTitle">Insert&nbsp;Video</p>' +
				'<span class="btnPopupRow"><label for="videoURLField'+id+'">Video&nbsp;URL: </label><input type="text"  name="rtf_videourl'+id+'" value="http://" id="videoURLField'+id+'"/  onfocus="this.select()"></span>' +
				'<span class="btnPopupRow">Only <i>YouTube, Vimeo, Daylimotion, myHands, PokerHandReplays, PokerReplay' + (lang_=='si' ? ', 24ur.com' : '') + (lang_=='bg' ? ', Vbox7' : '') + ', Pokertube (embed code), Brightcove (embed code)</i> video-sharing websites are supported.</span>' +
				'<button type="button" onclick="var str=\'[VIDEO]\' + this.form.rtf_videourl'+id+'.value + \'[/VIDEO]\'; top.document.rtfReturn['+id+'].insert(str, 7,8);top.document.rtfReturn['+id+'].buttonEvent(\'video\',\'close\');">Insert</button>';
   return res;
}

rtfObject.prototype.formLink = function()
{
	var id = this.index;
	top.document.rtfReturn[id] = this;
	var res = '<p class="btnPopupTitle">Insert Link</p>' +
				'<span class="btnPopupRow"><label for="linkURLField'+id+'">Link URL: </label><input type="text"  name="rtf_url'+id+'" value="http://" id="linkURLField'+id+'"/  onfocus="this.select()"></span>' +
				'<span class="btnPopupRow"><label for="linkTextField'+id+'">Link Text: </label><input type="text" name="rtf_comment'+id+'" value="" id="linkTextField'+id+'"/></span>' +
				'<span class="btnPopupRow"><label class="checkboxLabel"><input type="checkbox" name="rtf_newwin'+id+'"  value="1" /> Open in new window</label></span>' +
				'<button type="button" onclick="var str=\'[URL=&quot;\' + this.form.rtf_url'+id+'.value + \'&quot;\' + (this.form.rtf_newwin'+id+'.checked ? \'+\':\'\') + \']\' + (this.form.rtf_comment'+id+'.value==\'\' ? this.form.rtf_url'+id+'.value.replace(/http:\\\/\\\//, \'\') : this.form.rtf_comment'+id+'.value) + \'[/URL]\'; top.document.rtfReturn['+id+'].insert(str, (this.form.rtf_newwin'+id+'.checked ? 9:8) + this.form.rtf_url'+id+'.value.length, 6);top.document.rtfReturn['+id+'].buttonEvent(\'link\',\'close\');">Insert</button>';
   return res;
}

rtfObject.prototype.formTable = function()
{
	var id = this.index;
	top.document.rtfReturn[id] = this;
	var res = '<p class="btnPopupTitle">Insert Table</p>' +
				'<span class="btnPopupRow" style="color:#666">Hint: You can paste data from your excel file - &quot;|&quot; isn\'t necessary</span>'+
				'<span class="btnPopupRow">Align: <label class="checkboxLabel"><input type="radio" name="rtf_tbalign'+id+'"  value="" checked="checked" />&nbsp;Default</label> <label class="checkboxLabel"><input type="radio" name="rtf_tbalign'+id+'"  value=" left" />&nbsp;Left</label> <label class="checkboxLabel"><input type="radio" name="rtf_tbalign'+id+'"  value=" center" />&nbsp;Center</label></span>' +
				'<span class="btnPopupRow">Width: <label class="checkboxLabel"><input type="radio" name="rtf_tbwidth'+id+'"  value="" checked="checked" /> Auto</label> <label class="checkboxLabel"><input type="radio" name="rtf_tbwidth'+id+'"  value="50" /> 50%</label> <label class="checkboxLabel"><input type="radio" name="rtf_tbwidth'+id+'"  value="100" /> 100%</label></span>' +
				'<button type="button" onclick="var oRTF =top.document.rtfReturn['+id+']; var str=\'\\n[TABLE=\' + oRTF.radio(this.form,\'rtf_tbwidth'+id+'\') + oRTF.radio(this.form,\'rtf_tbalign'+id+'\') + \']\' ; oRTF.insert(str+ \'\\n*heading1|heading2*\\n|\' + oRTF.selectedText() + \'\\n[/TABLE]\\n\', str.length, 8);oRTF.buttonEvent(\'table\',\'close\');">Insert</button>';
   return res;
}

rtfObject.prototype.formTwitter = function()
{
	var id = this.index;
	top.document.rtfReturn[id] = this;
	var res = '<p class="btnPopupTitle">Insert Twitter Message</p>' +
				'<span class="btnPopupRow"><label for="twitterURLField'+id+'">Twitter Message URL: </label><input type="text"  name="rtf_twitterurl'+id+'" value="http://" id="twitterURLField'+id+'"/  onfocus="this.select()"></span>' +
				'<button type="button" onclick="top.document.rtfReturn['+id+'].getTwitterMsg(this.form.rtf_twitterurl'+id+'.value)">Insert</button>';
   return res;
}

rtfObject.prototype.formCards = function() {
    var res = '';

    c1 = new Array(2, 3, 4, 5, 6, 7, 8, 9, 10, 'j', 'q', 'k', 'a');
	c2 = new Array('&clubs;', '&spades;', '&hearts;', '&diams;', 'x');
	c3 = new Array('c', 's', 'h', 'd', 'x');

	function cards_td_tpl(index, code, title, img) {
		return '<li><img src="/img/cards/' + img + '.gif" alt="' + code + '" width="25" height="15" title="' + title + '" '+
					'onclick="rtfObject.instances[' + index + '].insert(rtfObject.instances[' + index + '].selectedText()+\'' + code + '\');" '+
					' /></li>';
	}
	
    for(var i=0; i<c3.length; i++) {
        var cards_row = '';
        for(var j=0; j<c1.length; j++) {
            var img = c1[j] + c3[i];
            var code = '{' + c1[j] + c3[i] + '}';
            var title = c1[j] + c2[i];
            cards_row += cards_td_tpl(this.index, code, title, img);
        }
        res += '<ul class="btnPopupRow">' + cards_row + '</ul>';
    }

    var cards_row = '';
    for(var i=0; i<c3.length; i++) {
		var img = 'x' + c3[i];
		var code = '{x' + c3[i] + '}';
		var title = 'x' + c2[i];
		cards_row += cards_td_tpl(this.index, code, title, img);;
	}
    res += '<ul class="btnPopupRow">' + cards_row + '</ul>';
	return res;
}



rtfObject.prototype.formSmiles = function() {
    var res = '';
    var smiles = new Array(
		[':)', 'smile.gif','Smile'],
		[':(', 'sad.gif','Sad'],
		[':D', 'biggrin.gif','Big Grin'],
		['(lol)', 'laugh.gif','Laugh'],
		['(rofl)', 'rofl.gif','Rofl'],
		['(rolleyes)', 'rolleyes.gif','Roll Eyes'],
		[':P', 'tongue.gif','Tongue'],
		['(blush)', 'blush.gif','Blush'],
		['|-(', 'bored.gif','Bored'],
		[':?', 'confused.gif','Confused'],
		['8-)', 'cool.gif','Cool'],
		['(pokerface)', 'pokerface.gif','Poker Face'],
		['(+)', 'yes.gif','Yes'],
		['(-)', 'no.gif','No'],
		[';(', 'crying.gif','Crying'],
		null,
		['(unsure)', 'unsure.gif','Unsure'],
		['(!@#$)', 'cursing.gif','Cursing'],
		[']:D', 'devil.gif','Devil'],
		['(blink)', 'blink.gif','Blink'],
		['(huh)', 'huh.gif','Huh'],
		[':@', 'angry.gif','Angry'],
		[':|', 'speechless.gif','Speechless'],
		[':O', 'ohmy.gif','Oh My!'],
		['(doh)', 'doh.gif','Doh!'],
		['(scared)', 'scared.gif','Scared'],
		['(sick)', 'sick.gif','Sick'],
		['|-)', 'sleep.gif','Sleep'],
		['(sneaky)', 'sneaky.gif','Sneaky'],
		['(n)', 'thumbdown.gif','Thumb Down'],
		['(y)', 'thumbup.gif','Thumb Up'],
		null,
		['(yy)', 'thumbsup.gif','Thumbs Up'],
		['(drool)', 'drool.gif','Drool'],
		['(w00t)', 'w00t.gif','W00t'],
		['(whistling)', 'whistling.gif','Whistling'],
		['(inlove)', 'inlove.gif','In Love'],
		['(alien)', 'alien.gif','Alien'],
		[':-/', 'ehhh.gif','Ehhh'],
		[':G', 'drunk.gif','Drunk'],
		[';)', 'wink.gif','Wink'],
		['(drink)', 'drink.gif','Drink'],
		['(emo)', 'emo.gif', 'Emo'],
		['(nuts)', 'nuts.gif', 'Nuts'],
		['(facepalm)', 'facepalm.gif', 'Facepalm'],
		['(pipi)', 'pipi.gif', 'Pee'],
		null,
		['(love_pn)', 'love_pn.gif', 'I Love PokerNews'],
		['(lol_broke)', 'lol_broke.gif', 'LOL BROKE'],
		['(level)', 'level.gif', 'Level'],
		['(hu4)', 'hu4.gif', 'HU4ROLLZ'],
		['(ontopic)', 'ontopic.gif', 'On Topic'],
		null,
		['(gay)', 'gay.gif', 'Thats Gay'],
		['(rooster)', 'rooster.gif', 'Rooster']
    );
    for(var i=0; i<smiles.length; i++) {
        var a = smiles[i];
		if (a === null) {
			res += '</ul><ul class="btnPopupRow">';
			continue;
		}
        res +=	'<li><img src="/img/smilies/' + a[1]  + '" alt="" ' +
				'title="' + this.translate( a[2]) + '" '+
				'onclick="rtfObject.instances[' + this.index + '].insert(rtfObject.instances[' + this.index + '].selectedText()+\' ' + a[0] + ' \');" '+
				' /></li>';
    }
    return '<ul class="btnPopupRow">' + res + '</ul>';
}

rtfObject.prototype.translate = function( s)
{
    return s;
}

rtfObject.prototype.radio= function(form,vardas){
  var el = form.elements;
  for(i=0;i<el.length;i++){
  	if (el[i].name==vardas && el[i].checked) {return el[i].value}
  }
  return false;
}

rtfObject.prototype.getTwitterMsg = function( url)
{
	var me = this;
	if (url.indexOf('twitter')==-1) {
		alert('Not a twitter url?');
		return;
	}
	var func = function (d) {
    	if (d['text']) {
			var img = d['user']['profile_image_url'];
			var str='[TWITTER="' + url + '"]\nimg=' + (img ? img : '')+'\nnick=' + d['user']['screen_name'] + '\nname=' + d['user']['name'] + '\ndate=' + d['created_at'] + '\ntext=' + d['text'] + '\n[/TWITTER]';
			me.insert(str, 12 + url.length, 10);
		}
		me.buttonEvent('twitter','close');
	}
	var a = /([0-9]+)$/.exec(url);
    if (a[1]) {
		jQuery.getJSON('http://api.twitter.com/1/statuses/show.json?id='+a[1]+'&include_entities=false&callback=?',func);
	}
}
