function footNotesLinks() {
	if (!document.getElementById ||
		!document.getElementsByTagName ||
		!document.createElement) {

		return false;
	}
	var jq = jQuery('.content');
	if (jq.size()<1) {
		return false;
	}

	/*if (!document.getElementById(containerID) ||
	!document.getElementById(targetID)) return false;
	var container = document.getElementById(containerID);
	var target    = document.getElementById(targetID);*/

	var container = jq.get(0);
	var target    = container;
	jq = jQuery('#printFootnotes');
	if (jq.size()>0) {
		target = jq.get(0);
	}

	var h2        = document.createElement('h2');
	jQuery(h2).addClass('printOnly');
	var heading = typeof(linksHeading) == "string" ? linksHeading : 'Links';
	var h2_txt    = document.createTextNode(heading);
	h2.appendChild(h2_txt);
	var coll = container.getElementsByTagName('*');
	var ol   = document.createElement('ol');
	jQuery(ol).addClass('printOnly');
	var myArr = [];
	var thisLink;
	var num = 1;
	for (var i=0; i<coll.length; i++) {
		var thisClass = coll[i].className;
		if ( (coll[i].getAttribute('href') ||
			coll[i].getAttribute('cite')) &&
			typeof(coll[i].getAttribute('src')) != "string" && /*ie img itraukia*/
			(thisClass == '' ||
			thisClass.indexOf('ignore') == -1)) {

			thisLink = coll[i].getAttribute('href') ? coll[i].href : coll[i].cite;
			var note = document.createElement('sup');
			jQuery(note).addClass('printOnly');
			var note_txt;
			var j = jQuery.inArray(thisLink, myArr);
			if ( j != -1 ) {
				note_txt = document.createTextNode(j+1);
			} else {
				var li     = document.createElement('li');
				var li_txt = document.createTextNode(thisLink);
				li.appendChild(li_txt);
				ol.appendChild(li);
				myArr.push(thisLink);
				note_txt = document.createTextNode(num);
				num++;
			}
			note.appendChild(note_txt);
			if (coll[i].tagName.toLowerCase() == 'blockquote') {
				var lastChild = lastChildContainingText.apply(coll[i]);
				lastChild.appendChild(note);
			} else {
				coll[i].parentNode.insertBefore(note, coll[i].nextSibling);
			}
		}
	}
	if (myArr.length > 0) {
		target.appendChild(h2);
		target.appendChild(ol);
	}
	jQuery(document.getElementsByTagName('html')[0]).addClass('noted');
	return true;
}



jQuery(document).ready( function () {
	footNotesLinks();
	window.print();
	history.back();
});