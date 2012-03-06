function rChange() {
	var t = 0;
	var j = jQuery('.r');
	j.each(function (){t += jQuery(this).val() * 10})
	t = t/j.size();
	jQuery('#rtotal').html(Math.floor(t)/10)
}

//*******************
function slider(id) {
	this.width = 200;
	this.drag = false;
	this.id = id;
	this.oC = $("#c"+id).get(0);
	this.oI = $("#r"+id).get(0);
	this.ctx = this.oC.getContext("2d");
	var self = this;
	jQuery(document).ready(function(){
		jQuery(self.oC).bind('mousedown mouseup mousemove mouseover mouseout', function(e) {return self.mouseEvent(e)});
	});
	this.oI.readOnly = true;
	var v = parseFloat(this.oI.value);
	if (isNaN(v)) v = 0;
	this.bar(Math.round(v * 10, 10));
}

slider.prototype.bar = function (v) {
	v = Math.round(v, 10);
	var width = this.width;
	//padding
	var minx = 5;
	//intervalo ilgis
	var ilength = (width-2*minx) / 100;
	//maxx
	var maxx = Math.floor(ilength * 100);
    //
	var ctx = this.ctx;
	ctx.lineCap = "square";
	//ctx.fillStyle = 'white';
	ctx.clearRect(0, 0, width, 20);
	ctx.fillStyle = '#aaa';
	ctx.fillRect(minx, 16, maxx, 3);
	//gradavimas
	ctx.lineWidth = 1;
	var n;
	ctx.beginPath();
	for (var i = 0; i <= 10; i++){
		n = minx + Math.floor(10 * i * ilength);
		ctx.moveTo(n, i>0 && i<10 ? 18 : 15);
		ctx.lineTo(n,20);
	}ctx.stroke();
	ctx.closePath();
	//
	v = Math.max(0, Math.min(100, v));
	// kiek pazymeta
	this.ctx.fillStyle = 'blue';
	var w = Math.floor(v * ilength) ;
	this.ctx.fillRect(minx, 15, w, 4);
	// zymeklis
	ctx.save();
	ctx.translate(w + minx, 0);
	//ctx.fillStyle = "rgba(200,200,200,0.3)";
	ctx.fillStyle = "#aaa";
	ctx.beginPath();
	ctx.moveTo(-5, 0);
	ctx.lineTo(5, 0);
	ctx.lineTo(5, 9);
	ctx.lineTo(0, 14);
	ctx.lineTo(-5, 9);
	ctx.lineTo(-5, 0);
	ctx.fill();
	ctx.closePath();
	ctx.restore();
	this.oI.value = v / 10;
	rChange();


}
slider.prototype.mouseEvent = function(e) {
	//this section is from http://www.quirksmode.org/js/events_properties.html
    var targ;
    if (!e)
        e = window.event;
    if (e.target)
        targ = e.target;
    else if (e.srcElement)
        targ = e.srcElement;
    if (targ.nodeType == 3) // defeat Safari bug
        targ = targ.parentNode;

    // jQuery normalizes the pageX and pageY
    // pageX,Y are the mouse positions relative to the document
    // offset() returns the position of the element relative to the document
    var x = parseInt(e.pageX - $(targ).offset().left, 10);
    var y = parseInt(e.pageY - $(targ).offset().top, 10);
    //console.log(e.type + ' ?| x: '+x + ', y: ' + y);
	var proc = (100 * (x-5)) / (this.width-10);
 	switch (e.type) {

		case 'mouseover':
			this.drag = false;
			break;
		case 'mousemove':
			if (this.drag) this.bar(proc);

			break;
		case 'mouseout':
		case 'mouseup':
			this.drag = false;
			break;

		case 'mousedown':
			this.drag = true;
			this.bar(proc);
			break;

		default:
			//console.log(e.type + ' ?| x: '+x + ', xx: ' + x);
	}
    return {"x": x, "y": y};
}
$(document).ready(function() {
	jQuery('.r').bind('change mouseup keyup', rChange);
	var rc1 = new slider('1');
	var rc2 = new slider('2');
	var rc2 = new slider('3');
	var rc2 = new slider('4');
	var rc2 = new slider('5');
});