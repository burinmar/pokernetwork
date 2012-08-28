var docWrite = [];
var funcDocumentWrite = function (s) {
	if (typeof(docWrite[Banners.currPosID]) == "undefined") docWrite[Banners.currPosID] = '';
	docWrite[Banners.currPosID] += s;
	if (!Banners.timer) {
		Banners.timer = setTimeout(function(){
			if (Banners.timer) {
				clearTimeout(Banners.timer);
				Banners.timer = false;
			}
			for (var i in docWrite) {
				Banners.currPosID = i;
				jQuery(i).append(docWrite[i]);
			}
			docWrite = [];
		},100);
	}
};


function Banners()
{

	this.statsUrl = null;
	this.roomId = null;
	this.geoTarget = null;
	this.liveContainers = {};
	this.templates = (typeof(bnTemplates) != 'undefined') ? bnTemplates : {};
	this.activeBanners = {};
	this.uriList = {};
	document.write = funcDocumentWrite;
}
$.extend(Banners.prototype, {
	process: function () {
		var banners = (typeof(bannersList) != 'undefined') ? bannersList : {},
			c = bnContainers,
			geoTarget = this.geoTarget,
			roomId = this.roomId,
			candidates = {},
			banner,
			liveRotationOn = 0;
		// go through active containers set
		for (var cId in c) {
			if (c.hasOwnProperty(cId)) {
				var opt = c[cId].options,
					zone = c[cId].zone,
					zoneBanners,
					cCandidates = {},
					cCandidatesAll = {}, // relevant when urlMode is 'default'
					urlMode = 'default',
					modeOpt = ['default', 'all', 'strict'],
					bnPriorityCnt = 0,
					bnPriorityCntAll = 0;
				if (banners.hasOwnProperty(zone)) {
					// available banners for this zone
					zoneBanners = banners[zone];
					for (var n in zoneBanners) {
						if (zoneBanners.hasOwnProperty(n)) {
							banner = zoneBanners[n];
							// *FILTERS*
							// geo target
							if (geoTarget != null && banner.geo_target && !(banner.geo_target & geoTarget)) continue;
							// ids include
							if (opt.ids && $.isArray(opt.ids)) {
								if ($.inArray(banner.id, opt.ids) === -1) continue;
							}
							// ids exclude
							/*if (opt.idsExclude && $.isArray(opt.idsExclude)) {
								if ($.inArray(banner.id, opt.ids) !== -1) continue;
							}*/
							// room id
							if (roomId && roomId !== banner.room_id) continue;
							
							// url mode -> url targeting
							if (opt.urlMode && $.inArray(opt.urlMode, modeOpt) !== -1) {
								urlMode = opt.urlMode;
							}

							switch (urlMode) {
								case 'strict':
									// only exact matches are good
									if (!banner.uri_target || !this.isUriMatch(banner.uri_target)) continue;
									break;
								case 'all':
									break;
								case 'default':
								default:
									// first strict - then all if none match
									if (!banner.uri_target)
										cCandidatesAll[banner.id] = banner;
									if (banner.priority) {
										bnPriorityCntAll += parseInt(banner.priority);
									}
									if (!banner.uri_target || !this.isUriMatch(banner.uri_target)) continue;
									break;
							}
							// candidate is good
							cCandidates[banner.id] = banner;
							if (banner.priority) {
								bnPriorityCnt += parseInt(banner.priority);
							}
						}
					}
				}
				if ((!opt.urlMode || opt.urlMode === 'default') && this._objCountAttr(cCandidates) < 1) {
					candidates[cId] = {zone: zone, liveRotation: (opt.liveRotation) ? 1 : 0, template: (opt.template) ? opt.template : null, banners: cCandidatesAll, priorityCnt: bnPriorityCntAll};
				} else {
					candidates[cId] = {zone: zone, liveRotation: (opt.liveRotation) ? 1 : 0, template: (opt.template) ? opt.template : null, banners: cCandidates, priorityCnt: bnPriorityCnt};
				}
			}
		}

		// choose banners to display from selected candidates
		for (var cId in candidates) {
			if (candidates.hasOwnProperty(cId)) {
				var cBanners = candidates[cId].banners,
					zone = candidates[cId].zone,
					template = candidates[cId].template;
				if (candidates[cId].liveRotation && this._objCountAttr(cBanners) > 1) {
					var o = this;
					$('#' + cId).mouseover(function () {
						o.liveContainers[this.id].halt = true;
					}).mouseout(function() {
						o.liveContainers[this.id].halt = false;
					});

					liveRotationOn = 1;
					this.liveContainers[cId] = {
						zone: zone,
						template: template,
						banners: cBanners,
						idsShown: [],
						halt: 0//,
						//disableTrackViews: 0
					};
					this.rotateLive();
				} else {
					banner = this.chooseBanner(cId, zone, cBanners, candidates[cId].priorityCnt);
					this.displayBanner(cId, zone, template, banner);
				}
			}
		}
		// track viewed banners
		this.trackViews(); // only first from live rotation is tracked
		
		if (liveRotationOn) {
			setInterval(function () { o.rotateLive(); }, 30000);
		}
	},
	isUriMatch: function (uriTarget) {
		var patterns = [],
			path = window.location.pathname,
			isMatch = false
			uriList = this.uriList;
		if (uriTarget == '' || uriTarget == '*') return true;
		patterns = uriTarget.split('\n');
		for (var n in patterns) {
			if (patterns.hasOwnProperty(n)) {
				var pattern = patterns[n];

				var pTmp = pattern.replace(/\*/g, '');
				if (uriList.hasOwnProperty(pTmp)) {
					pattern = pattern.replace(pTmp, uriList[pTmp]);
				}

				if (pattern == '') continue;
				if (pattern.substring(0, 1) !== '/') pattern = '/' + pattern;
				var chunks = pattern.split('*');
				if (chunks.length == 1 && pattern == path) {
					return true;
				} else if (chunks.length == 2 && chunks[1] == '' && path.indexOf(chunks[0]) == 0) {
					return true;
				}
			}
		}
		return isMatch;
	},
	rotateLive: function () {
		var c = this.liveContainers;
		for (var cId in c) {
			if (c.hasOwnProperty(cId)) {
				if (!c[cId].halt) {
					var	container = c[cId],
						bannerToShow = {},
						bannersCnt = this._objCountAttr(container.banners),
						idsShown = container.idsShown,
						banner;

					// if all banners are shown
					if (idsShown.length >= bannersCnt) {
						container.idsShown = [];
						idsShown = [];
						//container.disableTrackViews = 1;
					}

					for (var b in container.banners) {
						if (container.banners.hasOwnProperty(b)) {
							banner = container.banners[b];
							if (idsShown && $.inArray(banner.id, idsShown) !== -1) {
								continue;
							} else {
								container.idsShown.push(banner.id);
								bannerToShow = banner;
								break;
							}
						}
					}
					this.displayBanner(cId, container.zone, container.template, bannerToShow);//, (container.disableTrackViews) ? 1 : 0);
					this.liveContainers[cId] = container;
				}
			}
		}
		return;
	},
	chooseBanner: function (cId, zone, zoneBanners, priorityBnCnt) {
		var	cookieName = 'bn_' + zone,
			cookie = readCookie(cookieName),
			bannersCnt = 0,
			idsShown = [],
			idsShownTbl = {},
			idsCnt = 0,
			idsCntUnique = 0,
			lastId = 0,
			activeBanners = this.activeBanners,
			bIds = [];
		if (cookie) {
			idsShown = cookie.split(',');
		}
		for (var n in idsShown) {
			id = idsShown[n];
			if (idsShownTbl.hasOwnProperty(id)) {
				idsShownTbl[id]++;
			} else {
				idsShownTbl[id] = 1;
			}
		}
		idsCnt = idsShown.length;
		idsCntUnique = this._arrayCountUnique(idsShown);
		lastId = idsShown[idsCnt - 1];
		for (var b in zoneBanners) { // *1 don't show twice in a row
			if (zoneBanners.hasOwnProperty(b)) {
				bannersCnt++;
				if ((id = zoneBanners[b].id) != lastId) bIds.push(zoneBanners[b].id);
			}
		}
		bIds = this._arrayShuffle(bIds);
		bIds.push(lastId);
		
		if (idsCnt >= 10 || (idsCntUnique == bannersCnt && idsCnt == priorityBnCnt)) { // 2* all priority and less than 10
			eraseCookie(cookieName);
			idsShown = [];
			idsShownTbl = {};
			idsCnt = 0;
		}

		for (var b in bIds) {
			var id = bIds[b];
			if (zoneBanners.hasOwnProperty(id)) {
				var banner = zoneBanners[id];
				var priority = (banner.priority) ? banner.priority : 0;
				
				if (
				(priority && idsShownTbl.hasOwnProperty(banner.id) && idsShownTbl[banner.id] >= priority) || // 4* priority banner shown >= than his priority
				(!priority && idsCnt < priorityBnCnt) || // 3* show first with priority
				(activeBanners.hasOwnProperty(banner.id)) // 5 * already displayed
				) continue;
				
				if (bannersCnt > 1) {
					idsShown.push(banner.id);
					createCookie(cookieName, idsShown.toString());
				}
				return banner;
			}
		}
		eraseCookie(cookieName);
		return {};
	},
	displayBanner: function (cId, zone, template, banner) {
		var templates = this.templates, tplName = (template) ? template : banner.type;
		if (templates.hasOwnProperty(tplName)) {
			var html = this.tplParse(templates[tplName], banner, {'zone': zone});

			// show outer container if option set
			if (bnContainers[cId].options.outerId) {
				$('#' + bnContainers[cId].options.outerId).css('display', 'block');
			}

			Banners.currPosID = '#' + cId;
			$('#' + cId).html(html).show();//fadeOut('fast').fadeIn('normal');

			if (typeof(banner.gid) == 'undefined') {
				this.activeBanners[banner.id] = 1; // not show same bn
			} else {
				this.activeBanners[banner.id] = {'gid':banner.gid, 'cid':banner.cid, 'sid':banner.sid, 'zone': zone}; // not show same bn
			}
		}
	},
	tplParse: function (tpl, vars, opt) {
		var q;
		if (typeof opt['zone'] != 'undefined') {
			opt['zone'] = opt['zone'].replace(new RegExp(':', 'g'), '_');
			opt['url'] = opt['redirect_url'];
		}
		for (q in vars) {
			tpl = tpl.replace(new RegExp('\{' + q + '\}', 'g'), vars[q]);
		}
		return tpl;
	},
	trackViews: function () {
		if (!this.statsUrl) return;
		var activeBn = this.activeBanners, d = [], bn = {};
		var dataStr = '';
		for (var bId in activeBn) {
			if (activeBn.hasOwnProperty(bId)) {
				bn = activeBn[bId];
				if (typeof(bn.gid) == 'undefined') {
					d.push(bId);
				} else {
					dataStr = '{"gid":' + bn.gid + ', "cid": ' + bn.cid + ', "sid": ' + bn.sid + ', "zone": "' + bn.zone + '"}';
					d.push(dataStr);
				}
			}
		}
		if (d.length < 1) return;

		var url = this.statsUrl + '?d=' + encodeURIComponent(d.join('|'));
		var s = document.createElement('script');
		s.type= 'text/javascript';
		s.src = url;
		document.getElementsByTagName('body')[0].appendChild(s);
	},
	_objCountAttr: function (o) {
		var cnt = 0;
		if (o.__count__ === undefined) {
			for (var n in o) {
				if (o.hasOwnProperty(n)) {
					cnt++;
				}
			}
		} else {
			cnt = o.__count__;
		}
		return cnt;
	},
	_arrayCountUnique: function (a) {
		var cnt = 0, o = {};
		for (var n in a) {
			var id = a[n];
			if (!o.hasOwnProperty(id)) {
				o[id] = 1;cnt++;
			}
		}
		return cnt;
	},
	_arrayShuffle: function(a) {
		function randOrd_(){return (Math.round(Math.random())-0.5); }
		return a.sort(randOrd_);
	}
});
var bnContainers = {};
function addBanner(cId, zone, opt)
{
	bnContainers[cId] = {
		zone: zone,
		options: (opt) ? opt : {}
	};
}
function createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = '; expires='+date.toGMTString();
	}
	else var expires = '';
	document.cookie = name+'='+value+expires+'; path=/';
}
function readCookie(name) {
	var	nameEQ = name + '=',
		ca = document.cookie.split(';'),
		l = ca.length;
	for(var i=0;i<l;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}
function eraseCookie(name) {
	createCookie(name,'',-1);
}