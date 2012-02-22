/*************************************************
Modal Window
version: 1.0 (beta 3)
modified: 2008.12.23
project: PokerNews
author: Audrius N.
*************************************************/

function modalWindow(width,height)
{
    if(typeof(top.modalWindowInstances)=="undefined") top.modalWindowInstances = [];
	this.myID = top.modalWindowInstances.length;
	top.modalWindowInstances[this.myID] = this;

	this.active=false;
	this.zIndex=0;
	this.url=null;
    this.fg=this.bg=null;
	this.width=width;
	this.height=height;
	this.isClosed=true;
	this.isFrame=false;
	this.ie6=false;
	this.opener=window;
	//this.imgPath= document.location.host.indexOf('my.pokernews')==0 ? '/i/' : '/i/adm/';
	this.imgPath= '/img/adm/';

    var _self=this;
	jQuery(document).ready(  function (){_self.init();} );
	jQuery(window).bind( 'resize', function (){_self.setPosition();});
}


/*
* Aktyvuoja ar deaktyvuoja langa
* on - true | false
*************************************************/
modalWindow.prototype.focus=function (on)
{
    this.zIndex=0;
	var lastActive=-1;
	if (typeof(jQuery) == "undefined") jQuery=this.opener.jQuery;
	jQuery.each(
		top.modalWindowInstances,
		function () {
			this.active=false;
			if (this.zIndex>0) {
				if (lastActive==-1 || top.modalWindowInstances[lastActive].zIndex<this.zIndex) {
					lastActive=this.myID;
				}
			}
		}
	);
    if (on) {
    	top.modalActive=this;
    	this.active=true;
		this.zIndex= lastActive == -1 ? 1 : top.modalWindowInstances[lastActive].zIndex+1;
        jQuery(this.bg).css( {	zIndex : 100+10*this.zIndex	} );
		jQuery(this.fg).css( {	zIndex : 102+10*this.zIndex	} );
		jQuery(this.frame).css( {	zIndex : 104+10*this.zIndex	} );
	} else {
		if (lastActive !== -1) {
        	 top.modalWindowInstances[lastActive].focus(true);
		}
	}
}


/*
* Atidaro frame langa
* width ir height - optional
*************************************************/
modalWindow.prototype.openURL=function (url,width,height)
{   //this.focus(true);
	if (url) {
		if (width && width>0) this.width=width;
		if (height && height>0) this.height=height;
		if (this.fg && this.frame) {
    		this.loading();
			//Pakraunam frame
			this.isClosed=false;
			var _self=this;
			jQuery(this.frame).bind('load',function (){_self.showFrame()});
			this.frame.src=url;
		} else this.url=url;
	}
}


/*
* Atidaro ir padaro visible div langa
*
*************************************************/
modalWindow.prototype.open=function (html)
{
	this.focus(true);
    this.isFrame=false;
	this.isClosed=false;

	if (html) jQuery(this.fg).html('<div class="modalWindowContent"><a href="./" onclick="if (top.modalActive) top.modalActive.close();return false;" class="modalWindowClose" title="Close">Close</a>'+html+'</div>');

    this.setPosition();
	var ifr=this.ie6 ? this.bg.firstChild : this.bg;
	jQuery([ifr,this.bg,this.fg]).show();
	jQuery(this.frame).hide();
}


/*
* Uzdaro atidaryta modal langa (nesvarbu ar div ar frame)
*
*************************************************/
modalWindow.prototype.close=function ()
{
	var ifr=this.ie6 ? this.bg.firstChild : this.bg;
	jQuery([ifr,this.bg,this.fg,this.frame]).hide();
 	this.isClosed=true;
	this.isFrame=false;
	this.focus(false);
}


/*
* Pakeicia lnago matmenis
*
*************************************************/
modalWindow.prototype.resizeTo=function (width,height,remember)
{
	var was = {w:this.width, h:this.height}
    if (width && width>0) this.width=width;
	if (height && height>0) this.height=height;
	if (!this.isClosed) this.setPosition();
	if (typeof(remeber) == "undefined" || remember) {
		this.width = was.w;
		this.height = was.h;
	}
	return this;
}


/*
* Parodo modalini langa "Loading..."
*
*************************************************/
modalWindow.prototype.loading = function ()
{
	this.open('<div class="window-loading"></div>');
}


/*
* Privati. Inicializuoja. Pasikrovus puslapiui paleidzia konstruktorius.
*
*************************************************/
modalWindow.prototype.init=function ()
{
    var topDocument=top.document;
	//if (!topDocument.getElementById('modalWindowForeground')) {

		//sukuriam objektus
        var objBody = topDocument.getElementsByTagName("body").item(0);

        //background
		jQuery(objBody).append('<div id="modalWindowBackground'+ this.myID +'" class="modalWindowBackground" style="display:none;background-color:#000;position:absolute;z-index:100;"></div>');


		//modal - frame (kai kontentas frame)
		jQuery(objBody).append('<iframe id="modalWindowFrame'+ this.myID +'" name="modalFrame" frameborder="0" scrolling="auto" class="modalWindow" style="display:none;background-color:#fff;position:absolute;z-index:104;width:0px;height:0px;"></iframe>');

		//modal - div (kai kontentas dive)
		jQuery(objBody).append('<div id="modalWindowForeground'+ this.myID +'" class="modalWindow" style="display:none;background-color:#fff;position:absolute;z-index:102;overflow:auto;">Foreground</div>');
	//}

	this.bg=topDocument.getElementById('modalWindowBackground'+this.myID);
    this.fg=topDocument.getElementById('modalWindowForeground'+this.myID);
	this.frame=topDocument.getElementById('modalWindowFrame'+this.myID);

	if (this.bg) this.setOpacity(this.bg,10);
    //ie6 reikia ifframe backgrounde papildomai
		if (jQuery.browser["msie"] && !jQuery.browser["opera"]) { //IE
            var index = navigator.appVersion.indexOf('MSIE');
			if (index != -1 && parseInt(navigator.appVersion.substring(index+5))<7) {
			//if (parseInt(jQuery.browser.version.substring(0,1))<7) {
            	jQuery(this.bg).append('<iframe frameborder="0" scrolling="no" style="display:none;position:absolute;"></iframe');
				this.ie6=true;
			}
		}
	var _self=this;
	jQuery(this.bg).bind('click', function(){_self.close();});

	if (this.url) this.openURL(this.url);

}


/*
* Privati. Frame langa padaro visible
*
*************************************************/
modalWindow.prototype.showFrame=function ()
{
	if (this.isClosed) return;
	this.isFrame=true;
	this.focus(true);
	this.setPosition(true);
	var ifr= this.ie6 ? this.bg.firstChild:this.bg;
	jQuery([ifr,this.bg,this.fg,this.frame]).show();
	jQuery(this.fg).hide();
}


/*
* Privati. Fonui nustato dalini permatomuma
*
*************************************************/
modalWindow.prototype.setOpacity=function (object, opacity)
{
    object.style.opacity = (opacity / 100);
	object.style.MozOpacity = (opacity / 100);
	object.style.KhtmlOpacity = (opacity / 100);
	object.style.filter = "alpha(opacity=" + opacity + ")";
}


/*
* Privati. Centruoja modalini langa (nustato jo pozicija)
*
*************************************************/
modalWindow.prototype.setPosition=function () {
    if (!this.active) return;


	var isFrame = top.modalActive && top.modalActive.isFrame ? true : false;
    if(this.fg) {
    	//if (this.myID==1) alert(top.document);
    	var jDoc = jQuery('body',top.document);
		var x = jDoc.width();
		var y = jDoc.height();

		//Opera 9.5 neuzdengia visko, jei dokumentas trumpesnis
		if(window.navigator.userAgent.indexOf( 'Opera' )!==-1 &&  document.documentElement && (!document.compatMode || document.compatMode=="CSS1Compat")) {
        	var fix = top.window.innerHeight;
			if (fix>y) y=fix;
		}

		var ifr=this.ie6 ? this.bg.firstChild:this.bg;
		jQuery([ifr,this.bg]).css({
            width : x + 'px',
        	height : y + 'px',
        	top : '0px',
        	left : '0px'
		});

        var jWin=jQuery(window,top.document);
		var viewPortCenter = {
			x: jWin.width()/2 + jWin.scrollLeft() ,
			y: jWin.height()/2 + jWin.scrollTop()
		}
		var fg = isFrame ? this.frame : this.fg;
		var winModal = {
			width: this.width ? this.width : jQuery(fg).width(),
			height: this.height ? this.height : jQuery(fg).height()
		}
		//if (frame) winModal.height=jQuery(this.frame.contentDocument).height();
		jQuery(fg).css( {
			width : winModal.width+'px',
        	height : winModal.height+'px',
        	left : (viewPortCenter.x - (winModal.width / 2)) + 'px',
        	top : (viewPortCenter.y - (winModal.height / 2)) + 'px'
		} );
    }
}

modalWindow.prototype.setPosition=function () {
	var isFrame = top.modalActive && top.modalActive.isFrame ? true : false;
    if(this.fg) {
    	var jDoc = jQuery('body',top.document);
		var x = jDoc.width();
		var y = jDoc.height();

		//Opera 9.5 neuzdengia visko, jei dokumentas trumpesnis
		if(jQuery.browser.opera) {
			if (window.innerHeight>y) y=window.innerHeight;
		}

		var ifr=this.ie6 ? this.bg.firstChild:this.bg;
		jQuery([ifr,this.bg]).css({
            width : x + 'px',
        	height : y + 'px',
        	top : '0px',
        	left : '0px'
		});

        /*var jWin=jQuery(window);

		var wH=jWin.height();
		var wW=jWin.width();*/


		function viewportSize() {
		    var w = 0;
			var h = 0;
			/*window.navigator.userAgent.indexOf( 'Opera' )==-1 &&*/
		    if( document.documentElement && (!document.compatMode || document.compatMode=="CSS1Compat")) {
				w = top.document.documentElement.clientWidth;
		        h = top.document.documentElement.clientHeight;
			}
			else if (document.compatMode && document.body && document.body.clientWidth) {
		        w = top.document.body.clientWidth;
		        h = top.document.body.clientHeight;
			}
		    else if(window.innerWidth) {
		        w = window.innerWidth;
		        h = window.innerHeight;
		    }
			return {width: w, height: h};
		}

		function getScroll() {
		    var _x = 0;
		    var _y = 0;

			if(top.document.documentElement && (top.document.documentElement.scrollLeft || top.document.documentElement.scrollTop)) {
		        _x = top.document.documentElement.scrollLeft;
		        _y = top.document.documentElement.scrollTop;
			}
			else if(top.document.body && (top.document.body.scrollLeft || top.document.body.scrollTop)) {
				_x = top.document.body.scrollLeft;
		        _y = top.document.body.scrollTop;
			}
		    else if(window.pageXOffset || window.pageYOffset) {
				_x = window.pageXOffset;
				_y = window.pageYOffset;
			}
			return {x: _x, y: _y};
		}

		var tmp= viewportSize();
        var wW=tmp.width;
		var wH=tmp.height;
		tmp=getScroll();
		//opera neteisingai pasako
        if ( jQuery.browser.opera || (jQuery.browser.safari && parseInt(jQuery.browser.version) > 520) ) {
			//wH = window.innerHeight - ((jQuery('body',top.document).height() > window.innerHeight) ? 20 : 0);
		}
		var viewPortCenter = {
			x: wW/2 + tmp.x ,
			y: wH/2 + tmp.y
		}
		var fg = isFrame ? this.frame : this.fg;
		var winModal = {
			width: this.width ? this.width : jQuery(fg).width(),
			height: this.height ? this.height : jQuery(fg).height()
		}
		//if (frame) winModal.height=jQuery(this.frame.contentDocument).height();
		jQuery(fg).css( {
			width : winModal.width+'px',
        	height : winModal.height+'px',
        	left : (viewPortCenter.x - (winModal.width / 2)) + 'px',
        	top : (viewPortCenter.y - (winModal.height / 2)) + 'px'
		} );
    }
}