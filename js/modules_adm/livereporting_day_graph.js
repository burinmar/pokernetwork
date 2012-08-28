Raphael.fn.connection = function (obj1, obj2, line, bg) {
	if (obj1.line && obj1.from && obj1.to) {
		line = obj1;
		obj1 = line.from;
		obj2 = line.to;
	}
	var bb1 = obj1.getBBox(),
		bb2 = obj2.getBBox(),
		p = [{x: bb1.x + bb1.width / 2, y: bb1.y - 1},
		{x: bb1.x + bb1.width / 2, y: bb1.y + bb1.height + 1},
		{x: bb1.x - 1, y: bb1.y + bb1.height / 2},
		{x: bb1.x + bb1.width + 1, y: bb1.y + bb1.height / 2},
		{x: bb2.x + bb2.width / 2, y: bb2.y - 1},
		{x: bb2.x + bb2.width / 2, y: bb2.y + bb2.height + 1},
		{x: bb2.x - 1, y: bb2.y + bb2.height / 2},
		{x: bb2.x + bb2.width + 1, y: bb2.y + bb2.height / 2}],
		d = {}, dis = [];
	for (var i = 0; i < 4; i++) {
		for (var j = 4; j < 8; j++) {
			var dx = Math.abs(p[i].x - p[j].x),
				dy = Math.abs(p[i].y - p[j].y);
			if ((i == j - 4) || (((i != 3 && j != 6) || p[i].x < p[j].x) && ((i != 2 && j != 7) || p[i].x > p[j].x) && ((i != 0 && j != 5) || p[i].y > p[j].y) && ((i != 1 && j != 4) || p[i].y < p[j].y))) {
				dis.push(dx + dy);
				d[dis[dis.length - 1]] = [i, j];
			}
		}
	}
	if (dis.length == 0) {
		var res = [0, 4];
	} else {
		res = d[Math.min.apply(Math, dis)];
	}
	var x1 = p[res[0]].x,
		y1 = p[res[0]].y,
		x4 = p[res[1]].x,
		y4 = p[res[1]].y;
	dx = Math.max(Math.abs(x1 - x4) / 2, 10);
	dy = Math.max(Math.abs(y1 - y4) / 2, 10);
	var x2 = [x1, x1, x1 - dx, x1 + dx][res[0]].toFixed(3),
		y2 = [y1 - dy, y1 + dy, y1, y1][res[0]].toFixed(3),
		x3 = [0, 0, 0, 0, x4, x4, x4 - dx, x4 + dx][res[1]].toFixed(3),
		y3 = [0, 0, 0, 0, y1 + dy, y1 - dy, y4, y4][res[1]].toFixed(3);
	var path = ["M", x1.toFixed(3), y1.toFixed(3), "C", x2, y2, x3, y3, x4.toFixed(3), y4.toFixed(3)].join(",");
	if (line && line.line) {
		line.bg && line.bg.attr({path: path});
		line.line.attr({path: path});
	} else {
		var color = typeof line == "string" ? line : "#000";
		return {
			bg: bg && bg.split && this.path(path).attr({
				stroke: bg.split("|")[0], 
				fill: "none", 
				"stroke-width": bg.split("|")[1] || 3
			}),
			line: this.path(path).attr({
				stroke: color, 
				fill: "none",
				'stroke-width': 2
			}),
			arrow: this.path(["M", (x4-3), ",", (y4-2), " L", (x4-3), ",", (y4+2), " L", x4-1, ",", y4, " z"].join('')).attr({
				fill: "none",
				stroke: color, 
				"stroke-width": 2
			}),
			from: obj1,
			to: obj2
		};
	}
};

(function($) {
var autoId = 1;
var contexts = {};
$.fn.pnReportingDayGraph = function(args) {
	function attach(args) {
		args = $.extend({
			width: 100,
			height: 100,
			instanceId: $(this).attr('id')
		}, args);

		if ($(this).attr('id') === '') {
			args.instanceId = ['pnelimination-instance', autoId].join('-');
			autoId++;
			$(this).attr('id',  args.instanceId);
		}

		contexts[args.instanceId] = {
			// self: $(this),
			width: args.width,
			height: args.height,
			paper: Raphael(args.instanceId, args.width, args.height)
		};

		return this;
	}

	function setDays(dayNames) {
		var context = contexts[$(this).attr('id')];

		// assemble data
		var daysData = (function(dayNames){
			var daysFlat = {}, bigDays = [], addedToFlat = 0;
			for (var k in dayNames) {
				dayName = dayNames[k].toLowerCase();
				var bigDay = dayName.match(/^([0-9]+)/);
				try {
					bigDay = parseInt(bigDay[1], 10);
				} catch (e) { continue; }
				if ($.inArray(bigDay, bigDays) == -1) {
					bigDays.push(bigDay);
					daysFlat[bigDay] = [];
				}
				daysFlat[bigDay].push(dayName);
				addedToFlat++;
			}
			bigDays.sort(function(a,b){return a - b;}); // numeric sort

			// rules: 1 final day
			// connect each big day to each previous big day, unless excluded by rules in event_list.php
			var daysTree = {}, addedToTree = 1, isRoughlyValidTree = false;
			daysTree[bigDays.pop()] = (function(daysFlat, bigDays) {
				function isDayParallel(currName, peersCnt, parentName) {
					var parentComplexDay = parentName.match(/^([0-9]+)([a-z]+)/);
					if (!parentComplexDay)
						return false;
					if (parentComplexDay[1] == '2') {
						if (peersCnt == 4) {
							switch (parentComplexDay[2]) {
							case 'a':
								if ($.inArray(currName, ['1b', '1d']) > -1)
									return true;
								break;
							case 'b':
								if ($.inArray(currName, ['1a', '1c']) > -1)
									return true;
								break;
							}
						} else if (peersCnt == 3) {
							var currComplexDay = currName.match(/^([0-9]+)([a-z]+)/);
							if (currComplexDay) {
								if (! (parentComplexDay[2].indexOf(currComplexDay[2]) > -1))
									return true;
							}
						}
					}

					return false;
				}
				function createSubtree(bigDayNr, parentDayName) {
					var subtree = {};
					if (bigDayNr >= 0) {
						var relevantDayNames = daysFlat[bigDays[bigDayNr]];
						for (var q in relevantDayNames) {
							if (bigDayNr >= 0 && !isDayParallel(
								relevantDayNames[q], relevantDayNames.length, parentDayName
							)) {
								addedToTree++;
								var dayName = relevantDayNames[q];
								subtree[dayName] = createSubtree(bigDayNr - 1, dayName);
							}
						}
					}
					return subtree;
				}
				var tree = createSubtree(bigDays.length - 1, '');
				isRoughlyValidTree = (addedToTree == addedToFlat) && addedToFlat != 1;
				return tree;
			})(daysFlat, bigDays);

			var daysDims = [];
			for (var i in bigDays) {
				daysDims.push(daysFlat[bigDays[i]].length);
			}
			daysDims.push(1);

			return [daysTree, daysDims, isRoughlyValidTree];
		})(dayNames);

		// console.log(daysData[0]);

		// render data
		(function(p, daysTree, daysDims, isValidTree){
			p.clear();
			if (!isValidTree)
				return ;
			var days = {};
			var levelPositionCounter = [];
			function traverse(o, funcDrawDay, funcConnect, lvl) {
				if (!levelPositionCounter[lvl])
					levelPositionCounter[lvl] = 0;
				for (var i in o) {
					days[i] = funcDrawDay.apply(this, [i, o[i], lvl, levelPositionCounter[lvl]++, daysDims[lvl]]);  
					if (typeof(o[i])=="object") {
						traverse(o[i], funcDrawDay, funcConnect, lvl-1);
						for (var j in o[i]) { // lookahead
							funcConnect.apply(this, [days[i], days[j]]);
						}
					}
				}
			}			
			traverse(daysTree, function(name, descendants, col, row, rowOf) {
				return (function(x, y) {
					var text = p.text(x, y, name);
					text.attr({
						'fill': "#000",
						'font-size': 12, 
						'font-family': 'Tahoma'
					});
					var border = p.rect(x-20, y-10, 40, 20, 5);
					border.attr({
						// stroke: color, 
						"stroke-width": 2
					});
					return border;
				})(
					30+col*100, 
					// 30+(rowOf-row-1)*30
					context.height/2 - (-(rowOf-1)/2 + row) * 30 // 
				);
			}, function(a, b) {
				p.connection(b, a, "#000");
			}, daysDims.length - 1);

			var root = '';
			for (var q in daysTree) {
				root = q; break;
			}
			var zoom = Math.min(1, context.width / (days[root].getBBox().x2+5));
			p.setViewBox(0, 0, context.width/zoom, context.height/zoom, true);
		})(context.paper, daysData[0], daysData[1], daysData[2]);
	}

	var methods = {
		set: setDays
	};
	if (methods[args]) {
		return methods[args].apply(this, Array.prototype.slice.call(arguments, 1));
	} else if ( typeof args === 'object' || ! args ) {
		this.each(function(){
			var $this = $(this);
			if (!$this.data('pnelgraph-initialized')) {
				$this.data('pnelgraph-initialized', true);
				attach.call($this, args);
			}
		});
		return this;
	}

};
})(jQuery);