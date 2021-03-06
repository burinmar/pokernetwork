var livePokerAdm = function() {
	var quickEdit = function() {
		var controlHandles = function() {
			var rqWrLoadSingletonUsed = false; // rounds: betting structure
			var rqWeLoadSingletonUsed = false; // event: players info
			var stdShow = function(id) {
				var key = id.replace(/#rq-/, ''); // e.g. wp, we, ..
				$('#rq-' + key).show();
				$('.rq-sidembx').addClass('rq-side' + key);
			};
			var stdHide = function(id) {
				var key = id.replace(/#rq-/, '');
				$('#rq-' + key).hide();
				$('.rq-sidembx').removeClass('rq-side' + key);
			};
			var eventSubShow = function(subsect, suffix) { // prizepool, pp
				if (!$('.rq-sidembx').hasClass('rq-sidewe' + suffix)) {
					rqSectChange(null, 'rq-we');
					rqSubsectShow('rq-we', 'rq-we-sect-' + subsect + '-c', suffix);
				} else {
					eventSubHide(suffix);
				}
			};
			var eventSubHide = function(suffix) {
				$('#rq-we').hide();
				$('.rq-sidembx').removeClass('rq-sidewe' + suffix);
			};
			// sync some post/chips fields when switching
			var syncFields = function(map) {
				$.each(map, function(dst, src){
					try {
						var $dst = $(dst);
						// if ($dst.val().length == 0)
							$dst.val( $(src).val() );
					} catch (e) {}
				});
			};
			return{
			rqWpShow : function (id) {
				syncFields({
					'#rq-wp-body': '#rq-wc-body',
					'#rq-wp input[name=title]': '#rq-wc input[name=title]',
					'#rq-wp input[name=tags]': '#rq-wc input[name=tags]'
				});
				stdShow(id);
			}, // post
			rqWpHide : stdHide,
			rqWcShow : function (id) {
				syncFields({
					'#rq-wc-body': '#rq-wp-body',
					'#rq-wc input[name=title]': '#rq-wp input[name=title]',
					'#rq-wc input[name=tags]': '#rq-wp input[name=tags]'
				});
				stdShow(id);
			}, // post: chips
			rqWcHide : stdHide,
			rqWtShow : stdShow, // post: tweet
			rqWtHide : stdHide,
			rqWxShow : stdShow, // photos
			rqWxHide : stdHide,
			rqWrShow : function() { // rounds
				$('#rq-wr').show();
				$('.rq-sidembx').addClass('rq-sidewr');
				if (typeof write_round_rounds_url == 'undefined') {
					return;
				}
				if (rqWrLoadSingletonUsed) {
					return;
				}
				rqWrLoadSingletonUsed = true;
				$('#rq-wr-rounds-load').show();
				$.ajax({
					type: "POST",
					url: write_round_rounds_url,
					dataType: 'json',
					timeout: 30000,
					success: function(resp) {
						if (resp.status === 0) {
							$('#rq-wr-rounds-load').hide();
							$('#rq-wr-rounds').html(resp.data);
						}
					},
					error: function() {
						$('#rq-wr-rounds-load').hide();
					}
				});
			},
			rqWrHide : stdHide,
			rqWmShow : stdShow, // event: misc
			rqWmHide : stdHide,
			rqWlShow : function (id) {
				hugeEditables.loadPlayersPage();
				stdShow(id);
			}, // list-profiles (players info)
			rqWlHide : stdHide,
			rqWeShow : function() { // event: winners, prizepool'n'payouts (helper)
				var subSectControls = ['#rq-we-sect-prizepool-c', '#rq-we-sect-winners-c'];
				for (var q in subSectControls) {
					$(subSectControls[q] + '2').hide();
					$(subSectControls[q].replace(/-c$/, '')).hide();
				}
				$('#rq-we').show();
				if (typeof write_event_load_url == 'undefined') {
					return;
				}
				if (rqWeLoadSingletonUsed === true) {
					return;
				}
				rqWeLoadSingletonUsed = true;
				$('#rq-we-places-load').show();
				$('#rq-we-winners-load').show();
				$.ajax({
					type: "POST",
					url: write_event_load_url,
					dataType: 'json',
					timeout: 30000,
					success: function(resp) {
						if (resp.status === 0) {
							$('#rq-we-places-load').hide();
							$('#rq-we-winners-load').hide();
							$('#rq-we-places').html(resp.pdata);
							$('#rq-we-winners').html(resp.wdata);
							setupTogglableLists();
						}
					},
					error: function() {
						$('#rq-we-places-load').hide();
						$('#rq-we-winners-load').hide();
					}
				});
			},
			rqWeppShow : function() { // event: prizepool'n'payouts
				eventSubShow('prizepool', 'pp');
			},
			rqWewShow : function() { // event: winners
				eventSubShow('winners', 'w');
			},
			rqWeppHide : function() {
				eventSubHide('pp');
			},
			rqWewHide : function() {
				eventSubHide('w');
			}
		};}();

		function rqSectChange(e, id) { // quicknav section changed
			if (e !== null) {
				e.preventDefault();
			}
			var controlId = '#' + id.replace(/\-start$/, '');
			var getIdIdx = function(m) {
				return m.substr(1,1).toUpperCase();
			};
			var controlFunc;
			var sectControls = ['#rq-wr', '#rq-wp', '#rq-wt', '#rq-wc', '#rq-wx', '#rq-wm', '#rq-wl', '#rq-wepp', '#rq-wew'];
			for (var q in sectControls) { // hide all
				if (sectControls[q] != controlId) {
					controlFunc = sectControls[q].replace(/\-[a-z]/, getIdIdx).substr(1);
					eval('controlHandles.' + controlFunc + 'Hide("' + sectControls[q] + '")');
				}
			}
			controlFunc = controlId.replace(/\-[a-z]/, getIdIdx).substr(1);
			if ($(controlId + ':visible').length == 1) {
				eval('controlHandles.' + controlFunc + 'Hide("' + controlId + '")');
			} else {
				eval('controlHandles.' + controlFunc + 'Show("' + controlId + '")');
			}
		}
		// non-std, should not even exist
		function rqSubsectShow(changedSub, id, suffix) {
			$('#' + id).addClass(changedSub + '-sect-active');
			$('#' + id + '2').show();
			$('#' + id.replace(/-c$/, '')).show();
			$('.rq-sidembx').addClass('rq-sidewe' + suffix);
		}
		return {
			setup : function() {
				$('.rq-qn-startable').click(function(e){
					var id = this.id;
					rqSectChange(e, id);
				});
				if (typeof write_list_profiles_launch != 'undefined') {
					hugeEditables.loadPlayersPage(); // exception: others display initial content statically when loaded, but not this one
				} else if (typeof write_event_sect_winners_launch != 'undefined') {
					rqSubsectShow('rq-we', 'rq-we-sect-winners-c', 'w');
				} else if (typeof write_event_sect_prizepool_launch != 'undefined') {
					rqSubsectShow('rq-we', 'rq-we-sect-prizepool-c', 'pp');
				}

			},
			changeTo : function(id) {
				rqSectChange(null, id);
			}
		};
	}();
	
	var hugeEditables = function() {
		// tpl parser
		var rqWlListList = false;
		function rqWlSectListStep(i) {
			var hidden  = rqWlListList[i][3] ?
				'red' : '';
			var sponsor = rqWlListList[i][4] && write_list_profiles_sponsors[rqWlListList[i][4]] ?
				write_list_profiles_sponsors[rqWlListList[i][4]].name : '';
			var status  = rqWlListList[i][5] ?
				rqWlListList[i][5] : '';
			$('#rq-wl-data').append(rqWlListTpl
				.replace(/-id-/g, rqWlListList[i][1])
				.replace('-name-', rqWlListList[i][2])
				.replace('-sponsor-', sponsor)
				.replace('-status-',  status)
				.replace('-hidden-',  hidden)
			);
		}
		// load players
		function rqWlSectList(page) {
			if (typeof page == 'undefined') {
				page = 0;
			}
			if (!rqWlListList) {
				$('#rq-wl-load').show();
				$.ajax({
					type: "POST",
					data: {page: page},
					url: write_list_profiles_load_url,
					dataType: 'json',
					timeout: 30000,
					success: function(resp) {
						if (resp.status === 0) {
							$('#rq-wl-load').hide();
							rqWlListList = resp.data;
							rqWlSectList(resp.page);
						}
					},
					error: function() {
						$('#rq-wl-load').hide();
					}
				});
			} else {
				if (typeof rqWlListTpl == 'undefined') {
					rqWlListTpl = $('#rq-wl-data').html();
				}
				if ($('#rq-wl-pager').length) {
					$('#rq-wl-pager')[0].selectedIndex = page;
				}
				var min = Math.min(write_list_profiles_page_by * page, rqWlListList.length);
				var max = Math.min(write_list_profiles_page_by * page + write_list_profiles_page_by, rqWlListList.length);
				$('#rq-wl-data').html('').show();
				for (i = min; i < max; i ++) {
					rqWlSectListStep(i);
				}
			}
		}
		// players filter
		function rqWlSectListFilter() {
			$('#rq-wl-filter').keypress(function(e){
				if(e.keyCode == 13) {
					e.preventDefault();
				}
			});
			$('#rq-wl-filter').keyup(function(e) {
				if (typeof rqWeListFilterTimer != 'undefined') {
					clearTimeout(rqWeListFilterTimer);
				}
				if (rqWlListList === false) {
					return;
				}
				rqWeListFilterTimer = setTimeout(function() {
					var i;
					var search = $('#rq-wl-filter').val().toLowerCase();
					var limit = 200;
					if (search === '') {
						rqWlSectList($('#rq-wl-pager').val());
						return ;
					}
					$('#rq-wl-data').html('').show();
					for (i = 0; i < rqWlListList.length; i++) {
						if (limit === 0) {
							break;
						}
						if (rqWlListList[i][2].toLowerCase().indexOf(search) != -1) {
							rqWlSectListStep(i);
							limit--;
						}
					}
				}, 300);
			});
		}
		return {
			/* Event players list dynamic load */
			loadPlayersPage : function(page) {
				rqWlSectList(page);
			},
			setupPlayersFilter : function() {
				rqWlSectListFilter();
			}
		};
	}();
	
	function setupTogglableLists() {
		function toggleTogglables(parent) {
			var noneChecked = true;
			$('input.js-row-mark', parent).each(function(){
				if (this.checked) {
					noneChecked = false;
				}
			});
			if (noneChecked) {
				$('.js-row-mark-sc', parent).hide().prop('disabled', true);
			} else {
				$('.js-row-mark-sc', parent).show().prop('disabled', false);
			}
		}
		$('input.js-row-mark').click(function(){
			toggleTogglables($(this).parents('.js-row-mark-container'));
		});
	}

	function setupForms() {
		$('#rq-wp form').submit(function(e) {
			if ($.trim($('input[name=title]', this).val()) === '') {
				alert('Title is empty');
				return e.preventDefault();
			}
			if ($.trim($('textarea[name=body]', this).val()) === '') {
				alert('Text contents is empty');
				return e.preventDefault();
			}
		});
		$('#rq-wx form').submit(function(e) {
			if ($.trim($('input[name=title]', this).val()) === '') {
				alert('Title is empty');
				return e.preventDefault();
			}
			if ($('.xphoto-item', this).length === 0) {
				alert('No photos selected');
				return e.preventDefault();
			}
		});
		$('#rq-wr form').submit(function(e){
			var mandatory = [];
			if ($.trim($('#rq-wr [name=description]').val()) !== '') {
				mandatory = ["Round", "Duration", "Description"];
			} else if ($('#rq-wr-limit-not-blind')[0].checked) {
				mandatory = ["Round", "Duration", "Small_Limit", "Big_Limit", "Ante"];
			} else {
				mandatory = ["Round", "Duration", "Small_Blind", "Big_Blind", "Ante"];
			}
			if ($('#rq-wr-has-break')[0].checked) {
				mandatory.push('Break_Duration');
			}
			for (var q in mandatory) {
				if ($.trim($('#rq-wr [name=' + mandatory[q].toLowerCase() + ']').val()) === '') {
					alert(mandatory[q].replace(/_/, ' ') + ' is empty');
					return e.preventDefault();
				}
			}
		});
		(function(){
			function rqWcSectBatchAfterPreview(r) {
				$('#rq-wc-sect-batch-previewarea').html(r).show();
				$('#rq-wc-sect-batch-submitarea input').removeAttr('disabled');
				$('#rq-wc-sect-batch-submitarea-notice').hide();
			}
			$('#rq-wc #rq-wc-sect-batch-preview').click(function(event){
				event.preventDefault();
				$('#rq-wc form').ajaxSubmit({
					iframe: true,
					type: 'post',
					skipServerAbortCheck: true,
					success: function(r) {
						rqWcSectBatchAfterPreview(r);
					}
				});
			});
			$('#rq-wc #rq-wc-sect-batch-import').click(function(event){
				event.preventDefault();
				$('#rq-wc-sect-batch-import-hid').val('1');
				$('#action_import_new_chips_loading').show();
				$('#rq-wc form').ajaxSubmit({
					type: 'post',
					dataType: 'json',
					success: function(r) {
						$('#rq-wc-sect-batch-import-hid').val('0');
						$('#action_import_new_chips_loading').hide();
						$('#rq-wc textarea[name=import_textarea]').val(r.data);
						$('#rq-wc select[name=column_order]').val('1.2');
						rqWcSectBatchAfterPreview(r.preview);
					}, error: function() {
						$('#rq-wc-sect-batch-import-hid').val('0');
						$('#action_import_new_chips_loading').hide();
					}
				});
			});
			$('#rq-wc form').submit(function(e){
				if ($.trim($('input[name=title]', this).val()) === '') {
					alert('Title is empty');
					return e.preventDefault();
				}
			});
		})();

		$('#rq-wl #rq-wl-preview, #rq-wl #rq-wl-import').click(function(event){
			event.preventDefault();
			var addData = {};
			addData[$(this).attr('name')] = '';
			$('.action_new_plist_loading', $(this).parent()).show();
			$('#rq-wl form').ajaxSubmit({
				type: 'post',
				dataType : 'json',
				data: addData,
				success: function(r) {
					$('#rq-wl-previewarea').html(r.preview).show();
					if (typeof r.data != 'undefined') {
						$('#new_players_list_ta').val(r.data);
					}
					$('#rq-wl-save').removeAttr('disabled');
					$('#rq-wl-save-p').show();
					$('.action_new_plist_loading').hide();
				}, error : function() {
					$('.action_new_plist_loading').hide();
				}
			});
		});
		
		$('#rq_we_pl_placetable .rq-we-pl-newplace').livequery('keyup', function(){
			var addDay= true;
			$('#rq_we_pl_placetable .rq-we-pl-newplace').each(function(){
				if ($(this).val() === '') {
					addDay = false;
				}
			});
			if (addDay) {
				$('#rq_we_pl_placetable tr:last').after(rq_we_pl_newplace);
			}
		});
		
		$('a.rq-confirm-delete').click(function(e){
			if (!confirm('Sure to delete entry?')) {
				e.preventDefault();
			}
		});
		$('a.rq-confirm-delete-c').click(function(e){
			if (!confirm('Sure to delete entry?\nYou will have to review winners list afterwards,\nfor it will probably break.')) {
				e.preventDefault();
			}
		});
		$('a.rq-confirm-edit').click(function(e){
			if (!confirm('Sure to edit entry? This type of entry is discouraged from modifying.')) {
				e.preventDefault();
			}
		});
		$('a.rq-confirm-change-day').click(function(e){
			if (!confirm('Sure to change day state?')) {
				e.preventDefault();
			}
		});
		$('a.rq-confirm-change-evt').click(function(e){
			if (!confirm('Sure to change event state?')) {
				e.preventDefault();
			}
		});
		$('a.rq-confirm-change-round').click(function(e){
			if (!confirm('Sure to change round state?')) {
				e.preventDefault();
			}
		});
		$('#rq-wd-toggle-actions, #rq-we-toggle-actions').click(function(e){
			var nid = this.id.replace(/toggle-/, '');
			$('#' + nid).toggle();
			e.preventDefault();
		});
		(function(){
			function rqWrBlindLimitToggle(){
				if ($('#rq-wr-limit-not-blind').length === 0) {
					return;
				}
				if ($('#rq-wr-limit-not-blind')[0].checked) {
					$('#rq-wr-small-blind-p, #rq-wr-big-blind-p').hide();
					$('#rq-wr-small-limit-p, #rq-wr-big-limit-p').show();
				} else {
					$('#rq-wr-small-blind-p, #rq-wr-big-blind-p').show();
					$('#rq-wr-small-limit-p, #rq-wr-big-limit-p').hide();
				}
			}
			$('#rq-wr-limit-not-blind').click(rqWrBlindLimitToggle);
			rqWrBlindLimitToggle();
			function rqWrBreakToggle(){
				if ($('#rq-wr-has-break').length === 0) {
					return;
				}
				if ($('#rq-wr-has-break')[0].checked) {
					$('#rq-wr-break-data').show();
				} else {
					$('#rq-wr-break-data').hide();
				}
			}
			$('#rq-wr-has-break').click(rqWrBreakToggle);
			rqWrBreakToggle();
		})();
		if ($('#rq-wt-body').length) {
			document.getElementById('tweet-limit').innerHTML = $('#rq-wt-body').val().length;
		}
		$('#rq-wt-body').keyup(function(){
			if (this.value.length > 113) {
				this.value = this.value.substring(0, 113);
			} else {
				document.getElementById('tweet-limit').innerHTML = this.value.length;
			}
		});
		$('#rq-wt form').submit(function(event){
			var l = $('#rq-wt-body')[0].value.length;
			if (l === 0 || l > 113) {
				event.preventDefault();
				return ;
			}
		});

		// tag autocompletion for post and chips (from post contents) {
		$('#rq-wp .js-tag-suggestable, #rq-wc .js-tag-suggestable').focus(function(){
			var $scope = $(this).parents('#rq-wp, #rq-wc');
			var request = [
				$('input[name=title]', $scope).val(),
				$('#rq-wp-body, #rq-wc-body', $scope).val()
			].join(' ');
			if ($(this).data('ajax-tag-suggestion-hash') == request.length)
				return ;
			$(this).data('ajax-tag-suggestion-hash', request.length);
			(function($scope, self){ return function() {
				$.ajax({
					type: 'POST',
					url: '/',
					data: {
						event: 'livereporting.livereporting_event#suggest-tags',
						text: request,
						event_id: $(self).data('scope-event-id-hint')
					},
					success: function(resp) {
						$('.js-tag-suggestions', $scope).html(resp).fadeIn(300);
					},
					error: function() {
						$('.js-tag-suggestions', $scope).fadeOut(300);
					}
				});
			};})($scope, this)();
		});
		$('#rq-wp .js-tag-suggestions, #rq-wc .js-tag-suggestions').live('click', function(){
			var $scope = $(this).parents('#rq-wp, #rq-wc');
			var $tgt = $('.js-tag-suggestable', $scope);
			var currentTags = [], currentTagsLC = [];
			var tag = $(this).html();
			if ($tgt.val() != '') {
				currentTags = $tgt.val().split(',');
			}
			currentTagsLC = $tgt.val().toLowerCase().split(',');
			if ($.inArray(tag.toLowerCase(), currentTagsLC) == -1) {
				currentTags.push(tag);
				$tgt.val(currentTags.join(','));
			}
		});
		// }
	}
	
	function setupChipsTabControl() {
		function chipsRowsControlsInit() {
			$('input.newctchips').keypress(function(e){
				if(e.keyCode == 13 && !$('#newctchips')[0].disabled) {
					$('#newctchips').click();
				}
			});
			$('#newctplayer, #newctplayerchips').keypress(function(e){
				if(e.keyCode == 13 && !$('#newctplayerb')[0].disabled) {
					$('#newctplayerb').click();
				}
			});
		}
		chipsRowsControlsInit();
		$('#newctchips, #newctplayerb, #delctplayerb').click(function(){
			var data = {};
			switch (this.id) {
				case 'newctchips':
					var newChips = {};
					var newChipsCnt = 0;
					$('input.newctchips').each(function(){
						if (this.value === '') {
							return ;
						}
						newChips['data[' + this.name.match(/[0-9]+/) + ']'] = this.value;
						newChipsCnt++;
					});
					if (newChipsCnt === 0) {
						return;
					}
					data = newChips;
					data['sub'] = 'chips';
					data['sort_key'] = $('#cts_sort').val();
					break;
					
				case 'newctplayerb':
					var name=$.trim($('#newctplayer').val());
					var chips=$.trim($('#newctplayerchips').val());
					if (name === '' || chips === '') {
						return;
					}
					data = {
						'data' : name + ';' + chips,
						'sub' : 'newplayer',
						'sort_key' : $('#cts_sort').val()
					};
					break;
					
				case 'delctplayerb':
					var delPlayers = {};
					var delPlayersCnt = 0;
					$('input.delctchips').each(function(){
						if (this.checked === false) {
							return ;
						}
						delPlayers['data[' + this.name.match(/[0-9]+/) + ']'] = 1;
						delPlayersCnt++;
					});
					if (delPlayersCnt === 0) {
						return;
					}
					if (!confirm('Players will be completely deleted. Continue?')) {
						return;
					}
					data = delPlayers;
					data['sub'] = 'delplayer';
					data['sort_key'] = $('#cts_sort').val();
					break;
					
				default:
					return;
			}

			data['day_id'] = write_ctchips_day;
			$('#newctchips, #newctplayerb, #delctplayerb').attr('disabled', 'disabled');
			$.ajax({
				type: "POST",
				url: write_ctchips_url,
				data: data,
				dataType: 'json',
				timeout: 30000,
				success: function(resp) {
					$('#chipsCountEntries').html(resp.data);
					$('#newctchips, #newctplayerb, #delctplayerb').removeAttr('disabled');
					$('#newctplayer').val('');
					$('#delctchipsmark').attr('checked', false);
					chipsRowsControlsInit();
				}
			});
		});
		
		$('#delctchipsmark').click(function(){
			var check = this.checked;
			$('input.delctchips').each(function(){
				this.checked = check;
			});
		});
	}
	function setupPlayersSidebarControl() {
		$('#rSbPleft, #rSbPTotal').keypress(function(e){
			if(e.keyCode == 13) {
				$('#rSbPSave').click();
			}
		});
		$('#rSbPSave').click(function(){
			$('#rSbPleft, #rSbPTotal').attr('disabled', 'disabled').css('background-color', 'silver');
			$.ajax({
				type: "POST",
				url: write_sbplayers_url,
				data: {
					players_left :  $('#rSbPleft').val(),
					players_total : $('#rSbPTotal').val(),
					event: $('#rSbPEvent').val()
				},
				dataType: 'json',
				timeout: 30000,
				success: function(resp) {
					$('#rSbPleft, #rSbPTotal').removeAttr('disabled').css('background-color', 'transparent');
				}
			});
		});
	}
	function setupFormsPhotoControl() {
		var ipnBrowseWnd;
		$('#rq-wp-ipnattach, #rq-wp-ipnattach2, #rq-wx-ipnattach, #rq-wc-ipnattach, #rq-wc-ipnupload, #rq-wc-ipnattach2').click(function(e){
			e.preventDefault();
			ipnBrowseWnd = window.open(this.href, 'ipn_browser', 'width=840,height=645,toolbar=no,scrollbars=yes,resizable=yes');
		});
		$('#rq-wp-ipnupload').click(function(e){
			e.preventDefault();
			ipnBrowseWnd = window.open(this.href, 'ipn_browser', 'width=840,height=510,toolbar=no,scrollbars=yes,resizable=yes');
		});
		if ($('#rq-wp-ipnattach').length > 0 || $('#rq-wx-ipnattach').length > 0 || $('#rq-wc-ipnattach').length > 0) {
			var rqIpnAttachProxy = function(data) {
				var key = data.key,
					id = data.id,
					misc = data.misc,
					src = data.src,
					description = data.description,
					tags = data.tags,
					initialInBatch = data.initialInBatch;
				switch (key) {
					case 'post':
						rwIpnAttach(id, misc, src, description, tags, '#rq-wp', $('#ipnbase'));
						try {
							ipnBrowseWnd.close();
						} catch (e) {}
						break;
					case 'chips':
						rwIpnAttach(id, misc, src, description, tags, '#rq-wc', $('#ipncbase'));
						try {
							ipnBrowseWnd.close();
						} catch (e) {}
						break;
					case 'photos':
						rwIpnAttachPhotos(id, misc, src, description, tags);
						break;
					case 'photos-inst':
						if (initialInBatch && $('#rq-wx:visible').length === 0) {
							quickEdit.changeTo('rq-wx');
						}
						rwIpnAttachPhotos(id, misc, src, description, tags);
						break;
				}
			};
			var rqIpnAutosaveProxy = function(key) {
				if (key == 'photos-inst') {
					rwIpnAutosavePhotosInst();
				}
			};
			var behaveSilly = $.browser.msie; // depends on document.domain
			if (behaveSilly) {
				document.rqIpnAttach = rqIpnAttachProxy;
				document.rqIpnAutosave = rqIpnAutosaveProxy;
			} else {
				$.pm.bind("rqIpnAttach", rqIpnAttachProxy, 'http://imgsrv.pokernews.com');
				$.pm.bind("rqIpnAttach", rqIpnAttachProxy, 'http://imgsrv.pokernews.dev');
				$.pm.bind("rqIpnAutosave", rqIpnAutosaveProxy, 'http://imgsrv.pokernews.com');
				$.pm.bind("rqIpnAutosave", rqIpnAutosaveProxy, 'http://imgsrv.pokernews.dev');
			}
		}
		function rwIpnAttach(id, misc, src, description, tags, context, $ipnbase) {
			src = src.substring(0, src.length - 15) + 'b' + src.substring(src.length - 14);
			$(context + ' input[name=ipnimageid]').val(id);
			$(context + ' input[name=ipnimagemisc]').val(misc);
			$(context + ' input[name=ipnimagesrc]').val(src);
			$(context + ' input[name=ipnimagetitle]').val(description);
			$(context + '-ipndelete').show();
			$(context + '-ipndelete2').show();
			$(context + '-ipnpreview').show();
			$(context + '-ipnpreview img').attr('src', '' + $ipnbase.val() + src + '');
		}
		function rwIpnAutosavePhotosInst() {
			$('#rq-wx form').ajaxSubmit({
				type: 'post',
				dataType : 'json',
				data : {
					ajax : 1,
					published : ''
				},
				success : function(responseText) {
					$('#rq-wx input[name=photos_id]').val(responseText.id);
				}
			});
		}
		function rwIpnAttachPhotos(id, misc, src, description, tags) {
			if (typeof xphoto_item == 'undefined') {
				return ;
			}
			if ($('#xphoto-item-' + id).length > 0) {
				return ;
			}
			var ih = xphoto_item
				.replace(/\{id\}/g,  id)
				.replace(/\{src\}/g, $('#ipnxbase').val() + src);
			$('#xphotos-preview').append(ih);
			$('#xphoto-item-' + id + ' .misc').val(misc);
			$('#xphoto-item-' + id + ' .desc').val(description);
			$('#xphoto-item-' + id + ' .tags').val(tags);
			$('#xphoto-item-' + id + ' .psrc').val(src);
		}
		$('#rq-wp-ipndelete').click(function(e){
			e.preventDefault();
			$('#rq-wp input[name=ipnimageid]').val('');
			$('#rq-wp input[name=ipnimagemisc]').val('');
			$('#rq-wp input[name=ipnimagesrc]').val('');
			$('#rq-wp input[name=ipnimagetitle]').val('');
			$('#rq-wp-ipndelete').hide();
			$('#rq-wp-ipnpreview').hide();
		});
		$('#rq-wc-ipndelete').click(function(e){
			e.preventDefault();
			$('#rq-wc input[name=ipnimageid]').val('');
			$('#rq-wc input[name=ipnimagemisc]').val('');
			$('#rq-wc input[name=ipnimagesrc]').val('');
			$('#rq-wc input[name=ipnimagetitle]').val('');
			$('#rq-wc-ipndelete').hide();
			$('#rq-wc-ipnpreview').hide();
		});
	}
	
	function setuptAltEditHandles() {
		var ids = ['#rq-wp-start-bun','#rq-wu-start-bun','#rq-wc-start-bun','#rq-wx-start-bun'];
		var qe = function(e){
			e.preventDefault();
			$('#' + this.id.substring(0,5)).toggle();
		};
		for (var i = 0; i < ids.length; i++) {
			$(ids[i]).click(qe);
		}
	}
	
	function customDatetimeChange(dts) {
		if (typeof this.id != 'undefined') {
			dts = ['#' + this.id];
		}
		for (var i = 0; i < dts.length; i++) {
			var oid = dts[i];
			var cid = dts[i].replace(/options/, 'custom');
			if ($(oid).val() == 'sct_dt') {
				$(cid).show();
			} else {
				$(cid).hide();
			}
		}
	}
	
	return {
		xphotoDelete : function(id){
			$('#xphoto-item-' + id).remove();
		},
		setupNavEditPanel : function() {
			quickEdit.setup();

			$('#rq-wp-upload').click(function(e){
				e.preventDefault();
				var wnd = window.open(this.href, 'ipn_upload', 'width=840,height=510,toolbar=no,scrollbars=yes,resizable=yes');
			});
			$('#rq-wp-review').click(function(e){
				e.preventDefault();
				var wnd = window.open(this.href, 'ipn_manage', 'width=840,height=680,toolbar=no,scrollbars=yes,resizable=yes');
			});
		},
		setupQuickEdit : function() {
			setupForms();
			setupFormsPhotoControl();
			setupChipsTabControl();
			setupPlayersSidebarControl();

			setuptAltEditHandles();
			setupTogglableLists();

			$('#rq-wl-pager').change(function() {
				hugeEditables.loadPlayersPage(this.value);
			});
			hugeEditables.setupPlayersFilter();

			var dts = ['#rq-wp-datetime-options', '#rq-wc-datetime-options', '#rq-wx-datetime-options'];
			$(dts.join(',')).change(customDatetimeChange);
			customDatetimeChange(dts);
		},
		haxFixes : function() {
			$('#action_save_prizepool, #action_delete_prizepool').click(function(){
				$('#rq-we-winners input, #rq-we-winners textarea').attr('disabled', 'disabled');
			});
			$('#action_save_winners').click(function(){
				$('#rq-we-places input, #rq-we-places textarea').attr('disabled', 'disabled');
			});
			$('#action_save_new_plist').attr('disabled', 'disabled');
		}
	};
}();

document.domain = document.domain.match(/([a-z1-9]+.[a-z1-9]+)$/)[1];

$(document).ready(function()
{
	var $_ = livePokerAdm;
	$_.setupNavEditPanel();
	$_.setupQuickEdit();
	$_.haxFixes();
});