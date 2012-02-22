/*************************************************
class: calendar
version: 4.0
modified: 2009.02.12
project: Moon (v. 2.6)
author: Audrius Naslenas, audrius@vpu.lt
*************************************************/

//sukuria kalendoriaus html objekta (div) ir nupiesia kalendoriu
function calendar(o) {
	/*o = {
	id
	minDate
	maxDate
	}*/

	if (typeof(calendar.instances)=="undefined") calendar.instances=[];
    this.index = calendar.instances.length;
	calendar.instances[this.index] = this;

	//properties
	this.objname="calendar.instances["+this.index+"]";//objname;
	this.divObj=null;
	this.isShown = false;
	this.viewMoreMonths = false;
	this.startSunday=false;
	this.specClass=[];
	this.horizontal=true;//kalendoriaus orientacija

    this.weekDays=["Mo", "Tu", "We", "Th", "Fr", "Sa","Su"];
	this.monthNames = ["January", "February", "March", "April", "May", "June",
							"July", "August", "September", "October", "November", "December"];

	//constructor
    this.divID= (o['id']==null) ? "divCalendar" : o['id'];


    if (!o['minDate']) o['minDate'] = '2000-01-01';
	if (!o['maxDate']) o['maxDate'] = '2030-01-01';
    this.oFrom=this.str2date(o['minDate']);
	this.oTo=this.str2date(o['maxDate']);
	this.oToday = this.str2date();
	this.month(this.oToday.show());
	//c.select('calCurrentday', ['2009-02-10', '2009-03-12'] );

	return this;
}
//**********************

calendar.prototype.inArray = function (el, arr)
{
    for (var i=0;i<arr.length;i++) {
		if (el == arr[i]) return true;
	}
	return false;
}

calendar.prototype.select = function (className, days)
//sia funkcija reikia perrasyti savu metodu
{
	this.specClass[className] = [];
	var arr = this.specClass[className];
	var d = this.str2date();
	var el;

	for (var i=0;i<days.length;i++) {
		el = 0 + d.setDate(days[i]);
		if (!this.inArray(el,arr)) arr[arr.length] = el;
	}
}

calendar.prototype.selectedDays = function (className)
//sia funkcija reikia perrasyti savu metodu
{
	if (this.specClass[className] == "undefined") return [];
	else return this.specClass[className]
}

calendar.prototype.dayMarkedWith = function (day)
//sia funkcija reikia perrasyti savu metodu
{
	var i, arr;
	var classNames = '';
	for (i in this.specClass) {
		arr = this.specClass[i];
		if (typeof(arr) == 'object' && this.inArray(day, arr)) {
			classNames += ' ' + i;
		}
	}
	return classNames;
}

calendar.prototype.onclick = function (date, e)
//sia funkcija reikia perrasyti savu metodu
{
	alert('output: '+date);
}

calendar.prototype.month=function (date)
{//nustato kuri menesi rodyti
    this.month1st=this.str2date(date);
	this.month1st.setDate( 1 + this.month1st - this.month1st.getDay() );
	this.viewMoreMonths = false;
	this._redraw();
}

calendar.prototype.show=function(month)
{
	if (month!=null) this.month(month);
	this.isShown = true;
	this._redraw();
}

calendar.prototype.close=function()
{
	this.isShown = false;
    var div = this._myDiv();
	if (div) {
		div.style.visibility="hidden";
		div.style.display="none";
	}

}

calendar.prototype.str2date = function (stamp)
//data pavercia i datos objekta
{
	return (new myDate (stamp));
}


calendar.prototype._d = function (d,e)
//gauna pasirinkta diena ir perduoda outputui
{
	e = e || event;
	//var code = e.which || e.keyCode;
	/* reject initial shift use */
	//if(code==16) return;
	//var shift = e.shiftKey;
	//alert('code: '+code+'\n'+'shift: '+shift+'\n'+'txt: '+d)
	//if (!shift) this.close();
	this.onclick(this.str2date().fromDays(d).show(), e);
	return false;
}

calendar.prototype._redraw = function ()
//perpiesia kalendoriu
{
	if (this.isShown) {
		var div = this._myDiv();
		if (div) {
			div.innerHTML = this._content();
			div.style.visibility="visible";
			div.style.display="none";
			div.style.display="block";
		}
	}
}


calendar.prototype._myDiv = function ()
//Grazina div elementa
{
	if (this.divObj==null) {
		if (!(this.divID && document.getElementById && document.getElementById(this.divID))) {
			document.write('<div id="'+this.divID+'">&nbsp;</div>');
			document.close();
		}
		if (document.getElementById) this.divObj=document.getElementById(this.divID);
		//else eval("this.divObj="+this.divID);
	}
	return this.divObj;
}

calendar.prototype.moreMonths = function (current)
//metu dropdown
{
	if (typeof(current) == "undefined") {
		current = this.month1st.toDays();
		this.viewMoreMonths = this.viewMoreMonths ? false : (0+current);
	} else {
		this.viewMoreMonths = current;
	}
	this._redraw();
}

calendar.prototype._content = function ()
//sukonstruoja kalendoriaus html. atiduoda kaip string
{
    var m=this.str2date();
	var prevMonLastDay=m.fromDays(this.month1st-1).countMdays();

	m=this.month1st;
	var wFirst=m.getWday();
	var d1=wFirst>0 ? (wFirst-1): 6; //0-6
	//jei prasideda sekmadieniu
	if (this.startSunday != false) d1 = (d1==6) ? 0 : (d1+1);

	var lastDay=m.countMdays();
	var num_weeks=Math.ceil((lastDay+d1)/7);//kiek savaiciu turi menuo
    var nStart=m-d1;

	var s='';

	s+='<table cellspacing="0" class="calTable"><thead>\n';

	if (this.viewMoreMonths) s+='<tr><td class="calHead">';
	else s+='<tr><td colspan="'+(this.horizontal ? 7:(num_weeks+1))+'" class="calHead">';

	if (m>this.oFrom)
		s+='<a href="" onclick="'+this.objname+'.month('+ (m-1) +');return false" class="calMonthPrev">&lt;&nbsp;</a>';
	s+='<strong><a href="" onclick="'+this.objname+'.moreMonths();return false;">'+m.getYear()+' '+this.monthNames[m.getMonth()-1]+'</a></strong>';
    if ((m+lastDay)<=this.oTo)
		s+='<a href="" onclick="'+this.objname+'.month('+ (m+lastDay) +');return false" class="calMonthNext">&nbsp;&gt;</a>';
	//s+='<div class="moreMonths" style="text-align:center;"></div>\n';
	s+=' </td></tr>\n';

    s+='</thead><tbody>';

	if (this.viewMoreMonths) {
		s += ' <tr><td class="calChooseMonth">';
        var d = this.str2date(this.viewMoreMonths);
		var name = '';
	    for (var i=0;i<=6;i++) {
			d = d.fromDays(d - 1);
			d = d.fromDays(d - d.countMdays() + 1);
		}
		var nuo = this.str2date(this.oFrom.getYear() + '-' + this.oFrom.getMonth() + '-01');
		var thisMonth = this.str2date(this.oToday.getYear() + '-' + this.oToday.getMonth() + '-01');
		for (var i=0;i<=12;i++) {
			d = d.fromDays(d + d.countMdays());
			if (d<nuo || d>this.oTo) continue;
			if ((i==0 || i==12) && (d-nuo)!=0) {
				s += '<a href="" onclick="' +this.objname+ '.moreMonths('+(0+d)+');return false;">...</a><br/>';
			} else {
				name = d.getYear() + ' ' + this.monthNames[d.getMonth() - 1];
				if (0==(d-thisMonth)) name += '<sup>*</sup>';
				if (0==(d-this.month1st)) name = '<b>'+name+'</b>';
				s += '<a href="" onclick="' +this.objname+ '.month('+(0+d)+');return false;">' + name + '</a><br/>';
			}
		}

		s +=  '</td></tr>\n';;
	} else {
		var grid = [];
		var day;
		var shift = this.startSunday != false ? -1 : 0;
		var k;
		for (i=0;i<num_weeks;i++){
			grid[i] = [];
		    for (var j=1;j<=7;j++) {
				day=7*i+j-d1;
				if (day>lastDay) day = -(day-lastDay);
				else if(day<1) day = -(prevMonLastDay+day);
				k = (shift+j) % 7;
				if (k==0) k=7;
				grid[i][j] = this._drawCell(nStart+7*i+j-1,day,k);
			}
		}
		if (this.horizontal){
			s+='<tr>';
			for(var i=0;i<7;i++) {
				k = (shift+i+7) % 7;
				s+='<th'+((this.startSunday == 'il' && k>3 && k<6) || (this.startSunday !== 'il' && k>4) ? ' class="calWeekend"':'')+'>'+this.weekDays[k]+'</th>';
			}
			s+='</tr>\n';
			for (i=0;i<num_weeks;i++){
				s+='<tr>';
				for (var j=1;j<=7;j++) {
					s+= grid[i][j];
				}
			    s+='</tr>';
			}
		}else{
			for (i=1;i<=7;i++){
				s+='<tr>';
				k = (shift+i-1+7) % 7;
	            s+='<th'+((this.startSunday == 'il' && k>3 && k<6) || (this.startSunday !== 'il' && k>4) ? ' class="calWeekend"':'')+'>'+this.weekDays[k]+'</th>';
				for (var j=0;j<num_weeks;j++) {
					s+= grid[j][i];
			    }
				s+='</tr>';
			}
		}
	}
	s+='</tbody>';
    if (this.viewMoreMonths) s+='<tr><td class="calFoot">';
	else s+='<tr><td colspan="'+(this.horizontal ? 7:(num_weeks+1))+'" class="calFoot">';
	s+='<a href="" onclick="'+this.objname+'.close();return false" class="calClose">close</a>';
	s+='</td></tr>';
	s+='</table>';
	return s;
}


calendar.prototype._drawCell = function (d,diena,wDay)
//nupiesia viena dienos lastele
{
	var style="";
	if (diena<1) {
		style+='calOut ';
		diena = -diena;
	}
    var s = '' + diena;
    //if (d==this.oCurrent) style+="calCurrentday ";
	if ( (this.startSunday != "il" && wDay>5) || (this.startSunday == "il" && wDay>4 && wDay<7) ) style+="calWeekend ";
	if (d==this.oToday) style+="calToday ";
	style += this.dayMarkedWith(d)

	if (d<this.oFrom || d>this.oTo) s='<span>'+s+'</span>';
	else s='<a href="" onclick="return '+this.objname+'._d(\''+d+'\',event);">'+s+'</a>';
	s='<td'+(style!='' ? ' class="'+style+'"' : '')+'>'+s+'</td>';
	return s;
}







/**********************************************************/
/**********************************************************/
/**********************************************************/

//datos objektas. optional stamp yra pavidalo yyyy-mm-dd (nenurodzius imama dabartine diena)
function myDate(stamp)
{
	//properties
	this.y=0;
	this.m=0;
	this.d=0;

	//contructor
    this.setDate(stamp);
}

//objektui priskiria data. stamp pavidalo yyyy-mm-dd
myDate.prototype.setDate = function (stamp)
{
    if (typeof(stamp)=="undefined" || stamp == "") {
		stamp=this.now();
	}
	if (typeof(stamp)=="number") this.fromDays(stamp);
	else {
		stamp=stamp.replace(/\./g,'-');
		var re_date = /^(\d+)-(\d+)-(\d+)$/;
	    var myMatch = re_date.exec(stamp);
		if (!myMatch) {
		  	alert("Incorrect format of the date: "+ stamp);
	        //this.y=this.m=this.d=0;
			this.setDate();
	    } else {
			this.y=parseInt(myMatch[1],10);
			this.m=Math.min( 12, parseInt(myMatch[2],10));
			this.d = Math.min( parseInt(myMatch[3], 10), this.countMdays() );
		}
	}
	return this;
}

//pasako,kurie metai
myDate.prototype.getYear = function ()
{
	return(this.y);
}

myDate.prototype.getMonth = function ()
{
	return(this.m);
}

myDate.prototype.getDay = function ()
{
	return(this.d);
}

myDate.prototype.getWday = function ()
//kelinta svaites diena 1 - pirmadienis, 7 - sekmadienis
{
	var M=(this.m>2 ? (this.m-2):(this.m+10)) ;
	var c=Math.floor(this.y/100);
	var Y=this.y % 100;
	if (this.m<=2) Y-- ;
	var s=this.d+Math.floor( (13*M-1)/5 )+Y+Math.floor(c/4)+Math.floor(Y/4)-2*c;
	s=s % 7;
	if (s<1) s+=7;
	return s;
}

myDate.prototype.countMdays = function ()
//kiek sis menuo turi dienu
{
    var s =( ( Math.floor(this.m+this.m/8) % 2 ) ? 31:30);
	if (this.m==2) { //jei vasaris
		var Y=this.y;
		s= (Y % 400==0 || ((Y % 4==0) && (Y % 100)) ) ? 29 : 28;
	}
   return s;
}
	
myDate.prototype.toDays = function ()
//pasako kiek dienu praejo nuo metu pradzios (kaip analogiska mysql funkcija)
{
	var y,m,d,c,k;
	y=this.y;
	m=this.m;
	d=this.d;
	c=Math.floor((y-1)/100);//simtmeciu skaicius
	k=y*365+Math.floor((y-1)/4)+Math.floor(c/4)-c;
	var mon=new Array('', 0,31,59,90,120,151,181,212,243,273,304,334);
	k+=mon[m]+d;
	if (m>2 && (y % 4 == 0)) {
		if	(y % 100 !=0 || (Math.floor(y/100) % 4 ==0) ) k+=1;
	}
	return k;
}

myDate.prototype.fromDays = function (days)
//atvirkstine toDays funkcija
{
	days=Math.abs(parseInt(days,10));
	if (days<366 || days>3652424) {
		this.y=this.m=this.d=0;
		return (false);
	}
	var mon=new Array(0, 0,31,59,90,120,151,181,212,243,273,304,334);
	var c,y,m,d,hasd,i;
	y=Math.floor(days*100/36525);
	c=Math.floor((y-1)/100);
	d=days-y*365-Math.floor((y-1)/4)-Math.floor(c/4)+c;
	while (d > (hasd=((y % 4==0 && (y % 400==0 || y % 100)) ? 366:365)) ) {
		d-=hasd;	y++;
	}
	while (d<1) d+=(--y % 4==0 && (y % 400==0 || y % 100)) ? 366:365;
	if (hasd===366) for (i=3;i<=12;i++) mon[i]+=1;
	for (i=12;i>=1;i--) {
    	if (mon[i]<d) {
			m=i;
			d=d-mon[i];
			break;
		}
	}
	this.y=y;
	this.m=m;
	this.d=d;
	return this;
}


// Grazina sios dienos data
myDate.prototype.now = function ()
//sios dienos data
{
	var time=new Date();
	return ((2000+time.getYear()%100)+'-'+(time.getMonth()+1)+'-'+time.getDate());
}

myDate.prototype.show = function (delim)
{
	if (delim==null) delim='-';
	var s='';
	if (this.y<10) s+='0';
	if (this.y<100) s+='0';
	if (this.y<1000) s+='0';
	return s+this.y+delim+(this.m<10 ? '0':'')+this.m+delim+(this.d<10 ? '0':'')+this.d;
}

myDate.prototype.valueOf = function ()
{
	return this.toDays();
}

myDate.prototype.toString = function ()
{
	return this.show('-');
}



/**********************************************************/
/**********************************************************/
/**********************************************************/

function pickDate(id)
{
	var input = jQuery('#'+id);
	if (typeof(oCalendar)!="undefined") {
		var close = oCalendar['isShown'] && oCalendar['divID'] == "divCalendar_"+id ? true : false;
		oCalendar.oInput.css('border-color', oCalendar.css);
		oCalendar.close();
		if (close) {
			return;
		}
	}
	var input = jQuery('#'+id);
	var xy = input.offset();

	var c=new calendar({
		id: "divCalendar_"+id,
		minDate : '2000-01-01'
		});
	if (!jQuery('#divCalendar_'+id).size()) jQuery('body').append('<div id="divCalendar_'+id+'" style="position:fixed;background:white"></div>');
	var jWin=jQuery(window,top.document);
	jQuery('#divCalendar_'+id).css({'left': (parseInt(xy.left-jWin.scrollLeft()) + 'px'), 'top': (parseInt(xy.top+input.outerHeight()+3-jWin.scrollTop()) + 'px')});


	var currVal = input.val();
	c.month(input.val());
	//c.startSunday='uk';
	if (currVal) c.select('calSelected', [currVal] );

	c.onclick = function (date, e) {
		input.val(date);
		//jei shift paspaustas, neuzdarom
		if (e.shiftKey) {
			this.select('calSelected', [date] );
			this._redraw();
		} else {
			this.oInput.css('border-color', this.css);
			this.close();
		}
	}
	//c.horizontal=false;
	c.show();
	c.oInput = input;
	oCalendar = c;
	oCalendar.css = oCalendar.oInput.css('border-color');
	oCalendar.oInput.css('border-color', '#007FD9');
}

function onScroll() {
	if (typeof(oCalendar)!="undefined") {
		if (oCalendar['isShown']) {
			var input = oCalendar.oInput;
			var xy = input.offset();
			var jWin=jQuery(window,top.document);
			jQuery(oCalendar.divObj).css({'left': (parseInt(xy.left-jWin.scrollLeft()) + 'px'), 'top': (parseInt(xy.top+input.outerHeight()+3-jWin.scrollTop()) + 'px')});
			//oCalendar.oInput.css('border-color', oCalendar.css);
			//oCalendar.close();
		}
	}
}
jQuery(document).bind('scroll', onScroll);
jQuery(window).bind('resize', onScroll);