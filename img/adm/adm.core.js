/*************************************************
modified: 2009-01-21
version: 1.1
project: project: Moon (v. 2.5)
author: Audrius Naslenas, audrius@vpu.lt
*************************************************/
var admImgDir = '/i/adm/';
var alist = {

	titleSure : "Are you sure you want to delete?",
	titleSelect : "You must select at least one entry.",
	titleDeleteAll : "Are you sure? All items will be deleted!",

	zymek : true,

	isChecked : function (f)
	{
		var el=f.elements;
		for(var i=0;i<el.length;i++)
			if (el[i].name=='it[]' && el[i].checked) return true;
		return false;
	},

	deleteIt : function (event,f,name){
		if (f==null) f=document.getElementsByName('list-form')[0];
		if (name==null) name="it[]";
		if (this.isChecked(f,name)) {
			if (confirm(this.titleSure)) {
				f.event.value=event;
				f.submit();
			}
		} else alert (this.titleSelect);
	},

	deleteAll : function (event,f){
		if (f==null) f=document.getElementsByName('list-form')[0];
		if (confirm(this.titleDeleteAll)) {
			f.event.value=event;
			f.submit();
		}
	},

	checkIt : function (f,name){
		if (f==null) f=document.getElementsByName('list-form')[0];
		if (typeof f== 'undefined') return ;
		if (name==null) name="it[]";
		var el=f.elements;
		for(var i=0;i<el.length;i++)
			if (el[i].name=="it[]") el[i].checked=this.zymek;
		this.zymek=!this.zymek;
	},

	saveOrder : function (event,f){
		if (f==null) f=document.getElementsByName('list-form')[0];
		var rows = $('table.sortable').get(0).rows;
		var data = '';
        for (var i=0; i<rows.length; i++) {
            var rowId = rows[i]['id'];
            if (rowId) data += rowId + ';';
        }
		f.elements['event'].value = event;
		jQuery(f).append('<input type="hidden" name="rows" value="" />');
		f.elements['rows'].value= data;
		f.submit();
	}

}



function fillUri(el,id)
{
	var j=jQuery(el);
	if (j.val()=='' && jQuery('#'+id)) {
		j.val( makeUri(jQuery('#'+id).val()) );
		el.select();
	}
}

function makeUri(s)
{
	if( !s || !s.length ) return '';
	//begin utf to latin
	var doubles = {
		//DE
		'ß' : 'ss',
		// RU
		"а": "a",    "к": "k",    "х": "kh", "б": "b",    "л": "l",    "ц": "ts",
		"в": "v",    "м": "m",    "ч": "ch", "г": "g",    "н": "n",    "ш": "sh",
		"д": "d",    "о": "o",    "щ": "shch", "е": "e",    "п": "p",    "ъ": "",
		"ё": "jo",   "р": "r",    "ы": "y", "ж": "zh",   "с": "s",    "ь": "",
		"з": "z",    "т": "t",    "э": "eh", "и": "i",    "у": "u",    "ю": "ju",
		"й": "j",    "ф": "f",    "я": "ja",
		//SERBIAN
		"ђ": "dj", "љ": "lj", "њ": "nj", "ћ":  "c", "џ": "dz"
	}
	if (typeof(lang)!="undefined" && lang=='de') {
		doubles['ä'] = 'ae';
		doubles['ü'] = 'ue';
		doubles['ö'] = 'oe';
	}
	//characters LT        EE    IT   SE   ES      NO  TR
	var from = 'ąčęėįšųūž õäöü àéèìòù å   á íóú ñ åæø ı  ğşç ć';
	var to =   'aceeisuuz oaou aeeiou aao aeiouun aao iougsc c';
	var c;
	for (c in doubles) {
		if (typeof(c)=="string" && typeof(doubles[c])=="string") {
			s = s.replace(new RegExp(c, "gi") ,doubles[c]);
		}
	}
	for (var i=0;i<from.length;i++) {
		c = from.charAt(i);
		if (c == ' ') continue;
		s = s.replace(new RegExp(c, "gi") ,to.charAt(i));
	}
	//end utf to latin
	s = s.replace( /\s/g, '-' );
	s = s.replace( /[^a-z0-9-]/gi, '-' );
	s = s.replace( /-{2,}/g, '-' );
	s = s.substr( 0, 60 ).toLowerCase();
	s = s.replace( /^-*(.*?)-*$/g, '$1' );
	return s;
}

jQuery(document).ready(function () {
	//check all zenkliukas
	if (alist != null) {
		jQuery("table.list .checkall").html('<a href="" onclick="alist.checkIt();return false;" title="Check/uncheck all items">&nbsp;</a>');
	}
	jQuery("input.isDate").each(function () {
		var id = jQuery(this).attr('id');
		if (id) {
			jQuery(this).click(function (){pickDate(this.id)});
			jQuery(this).before('<a href="void(0);" onclick="pickDate(\'' + id + '\');return false" title=""  class="calendar"></a>&nbsp;');
		}
	});

	/* SORTING */
	if (jQuery.tableDnD) {
		jQuery('table.sortable tr').each(function(){
			if (this.cells[0]) {
				var s = '<td class="drag" >&nbsp;</td>';
				if (this.cells[0].nodeName.toUpperCase() == 'TH') {
					jQuery(this).addClass('nodrag nodrop')
					s = '<th style="width: 20px;" >&nbsp;</th>';
				}
				jQuery(this.cells[0]).before(s);
			}
	});
		jQuery('table.sortable').tableDnD({
			onDragClass: 'dragClass',
			onDrop: function(table, row) {
				jQuery('td',row).css('font-weight','bolder');
				jQuery('.controls').css('display', 'block');
			}
		});

		var evSort = jQuery('form[name=list-form] input[name=event-saveorder]').val();
		if (evSort) {
			jQuery('.list-footer').prepend('<span class="formButton controls hide" style="margin-left: 0"><input type="submit" value="Save order" onclick="alist.saveOrder(\''+evSort+'\')"/></span>');
		}
	}

	/* DELETION */
	var jList = jQuery('form[name=list-form]');
	if (jList.size() > 0) {
		var f = jList.get(0);
        jQuery('input.btDel', f).click(function (){

			if (alist.isChecked(f,"it[]")) {
				if (confirm(alist.titleSure)) {
					//f.event.value=event;
					f.submit();
				}
			} else alert (alist.titleSelect);

		});

		if (f.elements['event-clear']) {
			var v = f.elements['event-clear'].value;
			//yra delete all mygtukas
			jQuery('input.btDel',f).after('<input type="button" class="button fr btDel" value="Delete ALL items" onclick="if (confirm(alist.titleDeleteAll)) {this.form.event.value=\''+v+'\'; this.form.submit();}" /> &nbsp; &nbsp; ');

		}
	}
	//alert(flist);

	// COUNT CHARS

	var cc_class_prefix = 'count_chars_';
	var ci_class = 'count_info';
	var ci_text = 'Chars left: ';
	var elm = $('input:text[class^="'+cc_class_prefix+'"], textarea[class^="'+cc_class_prefix+'"]').keyup(function(event){countChars(this);})


	function countChars(obj){
		var aLen = parseInt(obj.className.substring(cc_class_prefix.length,100));
		if ($(obj).next().attr("tagName") == 'SPAN' && $(obj).next().attr("className") == ci_class){
			$(obj).next().html(ci_text+(aLen-$(obj).val().length));
		}else{
			$(obj).after('<span class="'+ci_class+'">' + ci_text +(aLen-$(obj).val().length)+'</span>');
		}
	}

});