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
		d = {}, dis = [],
		dx, dy, res;
	for (var i = 0; i < 4; i++) {
		for (var j = 4; j < 8; j++) {
			dx = Math.abs(p[i].x - p[j].x),
			dy = Math.abs(p[i].y - p[j].y);
			if ((i == j - 4) || (((i != 3 && j != 6) || p[i].x < p[j].x) && ((i != 2 && j != 7) || p[i].x > p[j].x) && ((i !== 0 && j != 5) || p[i].y > p[j].y) && ((i != 1 && j != 4) || p[i].y < p[j].y))) {
				dis.push(dx + dy);
				d[dis[dis.length - 1]] = [i, j];
			}
		}
	}
	if (dis.length === 0) {
		res = [0, 4];
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
		if (line.bg)
			line.bg.attr({path: path});
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

	function dataParseGetRevListData(dayPairsRaw)
	{
		var dayPairs = [], revHash = {}, danglingDays = [], potentialRoots = [], rootNode = null, nameHash = {}, k, k2, dayName, dayMergeName;

		// normalize names and mergenames
		for (k in dayPairsRaw) {
			dayName = dayPairsRaw[k][0];
			dayMergeName = dayPairsRaw[k][1];
			if (dayName !== '') {
				dayPairs.push([dayName, dayMergeName]);
				danglingDays.push(dayName);
			}
		}
		// names hash[name] = [name, mergename]
		for (k in dayPairs) {
			dayName = dayPairs[k][0];
			nameHash[dayName] = dayPairs[k];
		}
		// reverse hash[mergename] = [name, name, ...]
		for (k in dayPairs) {
			dayName = dayPairs[k][0];
			mergeName = dayPairs[k][1];
			if (typeof(nameHash[mergeName]) == 'undefined')
				continue;
			if (typeof(revHash[mergeName]) == 'undefined')
				revHash[mergeName] = [];
			revHash[mergeName].push(dayName);
		}

		// danglingDays: lost names
		for (k in revHash) {
			var remove = [k];
			for (k2 in revHash[k])
				remove.push(revHash[k][k2]);
			for (k2 in remove) {
				var ridx = danglingDays.indexOf(remove[k2]);
				if (ridx != -1)
					danglingDays.splice(ridx, 1);
			}
		}
		// special case: just one day
		if (dayPairs.length == 1)
			danglingDays = [];

		// potential roots: has no mergeday, not lost
		for (k in dayPairs) {
			dayName = dayPairsRaw[k][0];
			dayMergeName = dayPairsRaw[k][1];
			if (typeof(nameHash[dayMergeName]) == 'undefined' && danglingDays.indexOf(dayName) == -1)
				potentialRoots.push(dayName);
		}
		
		// add dangling days
		if (danglingDays.length > 0) {
			if (typeof(revHash['?']) == 'undefined')
				revHash['?'] = [];
			for (k in danglingDays) {
				revHash['?'].push(danglingDays[k]);
			}
			potentialRoots.push('?');
		}
		// add single root, if necessary
		if (potentialRoots.length > 1) {
			if (typeof(revHash['?']) == 'undefined')
				revHash['?'] = [];
			for (k in potentialRoots) {
				if (potentialRoots[k] != '?')
					revHash['?'].push(potentialRoots[k]);
			}
			rootNode = '?';
		} else if (potentialRoots.length == 1) {
			rootNode = potentialRoots[0];
		}

		return [revHash, rootNode];
	}

	function revListToTree(revList, rootNode)
	{
		var daysTree = {}, dimStats = [];
		function createSubtree(revList, rootNode, depth)
		{
			var subtree = {};
			if (typeof(dimStats[depth]) == 'undefined')
				dimStats[depth] = 0;
			for (var k in revList[rootNode]) {
				var dayName = revList[rootNode][k];
				subtree[dayName] = createSubtree(revList, dayName, depth+1);
				dimStats[depth]++;
			}
			return subtree;
		}

		daysTree[rootNode] = createSubtree(revList, rootNode, 0);
		dimStats.pop();
		dimStats.unshift(1);
		dimStats.reverse();

		return [daysTree, dimStats];
	}

	function drawTree(paper, paperWidth, paperHeight, daysTree, daysDims, isValidTree)
	{
		paper.clear();
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
		
		traverse(
			daysTree,
			function(name, descendants, col, row, rowOf) {
				return (function(x, y) {
					var text = paper.text(x, y, name);
					text.attr({
						'fill': "#000",
						'font-size': 12,
						'font-family': 'Tahoma'
					});
					var border = paper.rect(x-20, y-10, 40, 20, 5);
					border.attr({
						// stroke: color,
						"stroke-width": 2
					});
					return border;
				})(
					30+col*100,
					// 30+(rowOf-row-1)*30
					paperHeight/2 - (-(rowOf-1)/2 + row) * 30 //
				);
			},
			function(a, b) {
				paper.connection(b, a, "#000");
			},
			daysDims.length - 1
		);

		var root = '';
		for (var q in daysTree) {
			root = q; break;
		}
		var zoom = Math.min(1, paperWidth / (days[root].getBBox().x2+5));
		paper.setViewBox(0, 0, paperWidth/zoom, paperHeight/zoom, true);
	}

	function setDays(dayPairsRaw) {
		var context = contexts[$(this).attr('id')];

		// Preprocess input, get:
		// 0: 1-level list of names as keys and their descendants as value array
		// 1: root node name
		var revListData = dataParseGetRevListData(dayPairsRaw);

		// Build tree:
		// 0: tree
		// 1: calculated tree dim stats array
		var daysTreeData = revListToTree(revListData[0], revListData[1]);

		// render data
		drawTree(context.paper, context.width, context.height, daysTreeData[0], daysTreeData[1], revListData[1] !== null);
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