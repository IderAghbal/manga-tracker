// ==UserScript==
// @name         Manga Tracker
// @namespace    https://github.com/DakuTree/userscripts
// @author       Daku (admin@codeanimu.net)
// @description  A cross-site manga tracker.
// @homepageURL  https://trackr.moe
// @supportURL   https://github.com/DakuTree/manga-tracker/issues
// @include      /^https:\/\/(?:(?:dev|test)\.)?trackr\.moe\/user\/options.*$/
// @include      /^http:\/\/mangafox\.me\/manga\/.+\/(?:.*\/)?.*\/.*$/
// @include      /^http:\/\/(?:www\.)?mangahere\.co\/manga\/.+\/.*\/?.*\/.*$/
// @include      /^https?:\/\/bato\.to\/reader.*$/
// @include      /^http:/\/dynasty-scans\.com\/chapters\/.+$/
// @include      /^http:\/\/www\.mangapanda\.com\/(?!(?:search|privacy|latest|alphabetical|popular|random)).+\/.+$/
// @include      /^https?:\/\/mangastream.com\/r\/.+\/.+\/[0-9]+(?:\/[0-9]+)?$/
// @include      /^http:\/\/www\.webtoons\.com\/(?:en|zh-hant|zh-hans|th|id)\/[a-z0-9A-Z-_]+\/[a-z0-9A-Z-_]+\/[a-z0-9A-Z-_]+\/viewer\?title_no=[0-9]+&episode_no=[0-9]+$/
// @include      /^http:\/\/kissmanga\.com\/Manga\/[a-zA-Z0-9-_]+\/[a-zA-Z0-9-_]+\?id=[0-9]+$/
// @include      /^http:\/\/reader\.kireicake\.com\/read\/.*?\/[a-z]+\/[0-9]+\/[0-9]+\/.*$/
// @updated      2016-XX-XX
// @version      0.9.0
// @require      http://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js
// @resource     fontAwesome https://opensource.keycdn.com/fontawesome/4.6.3/font-awesome.min.css
// @grant        GM_addStyle
// @grant        GM_getResourceText
// @grant        GM_getValue
// @grant        GM_setValue
// @run-at       document-start
// ==/UserScript==
/* jshint -W097, browser:true, devel:true, multistr:true */
/* global $:false, jQuery:false, GM_addStyle:false, GM_getResourceText:false, GM_getValue, GM_setValue */
'use strict';

GM_addStyle(GM_getResourceText("fontAwesome").replace(/\.\//g, 'https://opensource.keycdn.com/fontawesome/4.6.3/'));

/* CORE TODO
Get a proper logo for the topbar (so we're not just using the AMR one anymore.
Setup events for topbar favourites, stop tracking. Unsure how exactly we should go about "stop tracking" though?
Get an actual working place to view your tracking stuff. Preferably similar to NovelUpdates.
*/

var bookmarkBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACaElEQVQ4jX2SvU+TURTGf/d96QtYChT5CB8BU40I0SA4oA6Kootx5i8wxkaKKIszEzFRAaFGXVwciHEwaIwhDjL4FXFwESQBJQw0BQq0lL5t33scWjQgcJMnJzn3OU+e556LiLAXxh419o72+7p2u99zOHjD8G0sPEssTvWHB/0U7cQx2OM0NLXezisqyN1fdaDUV9/QtRNnV4Fgl1lz/OSFDuIhSEVpOXXp6sA15d7Oy9neGOpULsPIqWhqbukrLikoIhYCLCqr62oaG4/0BgPGHUdLODAsGkCJCK/7qrqrag+cLfQU1nqKi8vdnkKv2+12I2nAykDlImKxvhaNxtaWI9FIOLS2Gp5TIsL4gG/0TFvbZSyBdAJEwHFAVCalmBngAuUCrTI1Gs9EmPgx25mKx/PbTre2m1YCkmsgKdBp0A44AhpwsmKmBzuxj7Fvn14oEQHgnl/VHav2Pjnf7LtoGnEQnXEgm8M6CyFpW/J2cn5keiUW+CsAcNevalqqi56eO1rZnhHgn4DWoAWdEnkzFXo+GYn7e4KyvGWNPQ9lPrJuf8BOg+1A0slU2wFbZ+GoSCI51hOU5R3XWJZv1ZPK2gVAgc4+qgOGhlLL1bDrPygxzYPYacAEBxZjGxsmGN68/FzEgXQKr2E2bvK3RBjsVKVlOTm1aDfLq6b9bmbp/cjMgn9kNtQ9Phf5srpupaGAcsM8dP+6cv3nwFJU2ElDffwdnvi+svR4XeTlrWEJDQaU8TW68upnbLWjxeO9UijK7VKUAKEtWxgKqBJDOGEL0zeH5df2eIMBZWjNYUtRrRWfOx9I7A+InWebTg8pngAAAABJRU5ErkJggg==';
var trackBase64    = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9sFBAwYLqR4MpkAAAIhSURBVDjLzZLPaxNREMe/u91sSkISBTGxJcaA4EFbETy2FAQNmFxavXmQIJV4EIIpniTdg5coAb0IkUBzkiT4BxRECoVaFFtKwORiiLgRIfTH1jS7b9++3fUQs1hqvHjxC8OXBzOfN8wM8I/ifn/k83khFApdNQw6wwMR0zRBGWuKovtNMplcBWAPBVSr1TlVVfOh4MkzkfExNA5EaIQiMqJAltv29t7elsvlupdKpd4fAVQqFcnj8WRnpqe4nuDH4rsf6GgcdlQTIzbDo4tA0Gjj7coqNS0rmclkXg0AfLFYvOn1erOJRILzBY7h8fo+bpzzYT68C3+tDJ0aeLBOcSocxezcrGhZVkmSpGkHQAjJxmIxDgA+dXR4RkWcYNuAvAX+42t4PyzB4F1Y/qwh4PMiHo+7er3e8wFAoJQySikEQYBtA/5REc+WKnhyNoCXt27/SlsGGoDWAI4DuKvrl54OOuB5PlMul03GGC4E3djtqohevwMAMBUFRqcDXZZBWi2o9Tp6tRr219acIfLpdHpFUZT5QqHAvn5p4f6kGzuqCQCwKIWl67AIgaVpfSfkz2vM5XJThJAX4fGxidORKM5/a0OXZafYZgwWIbANAwe1Gi5vbHBHDgkAJ0mLV7rd7rWFicmHpNVyfrYZc0C9en0owNH3UslW63Wnbdsw+m6a0JpNB8D/7c5txvphGH03TdiMHcoRhhUfbG4emvb/q59HqiRjuY+xSgAAAABJRU5ErkJggg==';

$.fn.reverseObj = function() {
	return $(this.get().reverse());
};
function escapeHTML(html) { return $('<div/>').text(html).html(); }
function getCookie(k){return(document.cookie.match('(^|; )'+k+'=([^;]*)')||0)[2];}

/***********************************************************************************************************/

var base_site = {
	init : function() {
		var _this = this;

		this.preInit(function() {
			_this.setObjVars();

			_this.stylize();

			_this.setupTopBar();

			_this.setupViewer();
		});
	},
	preInit : function(callback) { callback(); }, //callback must always be called

	//Functions
	setObjVars      : function() {},
	stylize         : function() {},
	preSetupTopBar  : function(callback) { callback(); }, //callback must always be called
	postSetupTopBar : function(topbar) {},
	preSetupViewer  : function(callback) { callback(); }, //callback must always be called
	postSetupViewer : function(topbar) {},


	//Fixed Functions
	setupTopBar : function() {
		var _this = this;

		this.preSetupTopBar(function() {
			GM_addStyle(`
				#TrackerBar {
					position: fixed    !important;
					top:      0        !important;
					z-index:  10000000 !important;

					height: 0; /*Allows everything outside the topbar to be clicked properly*/
					width:  100% !important;

					opacity: .9 !important;
					padding:  0 !important;
					margin:   0 !important;

					font:       14px 'Open Sans', Arial, Helvetica, sans-serif !important;
					color:      black  !important;
					text-align: center !important;
				}
				#TrackerBarIn {
					display: inline-block;

					opacity: 1               !important;
					padding: 0 15px 2px 15px !important;
					margin:  0               !important;

					background-color: #FFF !important;

					border:        1px solid #CCC !important;
					border-top:    0              !important;
					border-radius: 0 0 6px 6px    !important;
				}


				#TrackerBarLayout {
					padding: 0 !important;
					margin:  0 !important;
				}
				a.buttonTracker {
					display: inline-block;
					cursor: pointer;

					margin:    5px;
					padding:   2px;
					min-width: 100px;

					background: linear-gradient(0, #EEE, #FFF);

					border:        1px solid rgb(221, 221, 221);
					border-radius: 5px;

					font-size:       13px;
					font-weight:     initial;
					color:           black;
					text-align:      center;
					text-decoration: none;

					transition: all 0.4s ease-in-out;
				}
				a.buttonTracker:hover {
					background: linear-gradient(0, rgb(255, 255, 255), rgb(238, 238, 238));

					border-color: #3278BE;

					color:           #003C82 !important;
					text-decoration: none    !important;
				}
				a.buttonTracker:active {
					background: #4195DD; /* For browsers that do not support gradients */
					background: linear-gradient(90deg, #003C82, #4195DD);
				}

				#TrackerBar *         { vertical-align: middle  !important; }
				#TrackerBar a         {
					vertical-align: initial !important;
					color:          black   !important;
				}
				#TrackerBar a:visited { color:black !important; }

				#TrackerBarIn .fa {
					margin: auto 5px !important;

					font-size: 16px;

					cursor: pointer !important;
				}
				#TrackerBarIn select {
					/* A lot of sites tend to overwrite the base <select> styles, so we need to revert */
					margin: 0 !important;

					background-color: initial;

					border: 1px solid rgb(221, 221, 221);

					font:  inherit;
					color: initial;
				}
				#TrackerBarIn select { margin: 0 !important; }
			`);
			var topbar = $('<div/>', {id: 'TrackerBar'}).append(
				$('<div/>', {id: 'TrackerBarIn'}).append(
					$('<a/>', {href: main_site, target: '_blank'}).append(
						$('<i/>', {class: 'fa fa-home', 'aria-hidden': 'true'}))).append(
					$('<div/>', {id: 'TrackerBarLayout', style: 'display: inline-block'}).append(
						(Object.keys(_this.chapterList).indexOf(_this.chapterListCurrent) > 0 ? $('<a/>', {class: 'buttonTracker', href: Object.keys(_this.chapterList)[Object.keys(_this.chapterList).indexOf(_this.chapterListCurrent) - 1], text: 'Previous'}) : "")).append(
						$('<select/>', {style: 'float: none; max-width: 943px', title: _this.viewerTitle}).append(
							$.map(_this.chapterList, function(k, v) {var o = $('<option/>', {value: v, text: k}); if(_this.chapterListCurrent == v) {o.attr('selected', '1');} return o.get();}))).append(
						(Object.keys(_this.chapterList).indexOf(_this.chapterListCurrent) < (Object.keys(_this.chapterList).length - 1) ? $('<a/>', {class: 'buttonTracker', href: Object.keys(_this.chapterList)[Object.keys(_this.chapterList).indexOf(_this.chapterListCurrent) + 1], text: 'Next'}) : "")).append(
						// $('<img/>', {class: 'bookAMR', src: bookmarkBase64, title: 'Click here to bookmark this chapter'})).append(
						// $('<img/>', {class: 'trackStop', src: trackBase64, title: 'Stop following updates for this manga'})).append(
						$('<i/>', {id: 'report-bug', class: 'fa fa-bug', 'aria-hidden': 'true', title: 'Report bug'})).append(
						$('<i/>', {id: 'trackCurrentChapter',  class: 'fa fa-book', 'aria-hidden': 'true', style: 'color: maroon', title: 'Mark this chapter as latest chapter read'})).append(
						$('<span/>', {id: 'TrackerStatus'})
					)
				)
			);

			$(topbar).appendTo('body');

			//Setup select chapter change event
			$(topbar).on('change', 'select', function(e) {
				console.log(this.value);
				location.href = this.value;
				if(this.value.indexOf('#') !== -1) {
					window.location.reload();
				}
			});

			//Setup prev/next events
			$(topbar).on('click', 'a.buttonTracker', function(e) {
				e.preventDefault();

				location.href = $(this).attr('href');
				if($(this).attr('href').indexOf('#') !== -1) {
					window.location.reload();
				}
			});
			//Setup tracking event.
			$(topbar).on('click', '#trackCurrentChapter', function(e) {
				e.preventDefault();

				_this.trackChapter(true);
				// $(this).css('color', '#00b232');
			});
			//Setup bug report event.
			$(topbar).on('click', '#report-bug', function(e) {
				e.preventDefault();

				//// _this.trackChapter(true);
			});

			_this.postSetupTopBar(topbar);
		});
	},
	trackChapter : function(askForConfirmation) {
		askForConfirmation = (typeof askForConfirmation !== 'undefined' ? askForConfirmation : false);

		if(config['api-key']) {
			var params = {
				'api-key' : config['api-key'],
				'manga'   : {
					'site'    : this.site,

					//Both title and chapter can contain anything, as parsing is done on the backend.
					'title'   : this.title,
					'chapter' : this.chapter
				}
			};
			//TODO: Check if everything is set, and not null.

			if(!askForConfirmation || askForConfirmation && confirm("This action will reset your reading state for this manga and this chapter will be considered as the latest you have read.\nDo you confirm this action?")) {
				$.post(main_site + '/ajax/userscript/update', params, function () {
					//TODO: We should really output this somewhere other than the topbar..
					$('#TrackerStatus').text('Updated');
				}).fail(function(jqXHR, textStatus, errorThrown) {
					switch(jqXHR.status) {
						case 400:
							alert('ERROR: ' + errorThrown);
							break;
						case 429:
							alert('ERROR: Rate limit reached.');
							break;
						default:
							alert('ERROR: Something went wrong!\n'+errorThrown);
							break;
					}
				});
			}
		} else {
			alert('API Key isn\'t set.'); //TODO: This should give the user more info on how to fix.
		}
	},
	setupViewer : function() {
		var _this = this;

		//FIXME: VIEWER: Is it possible to make sure the pages load in order without using async: false?
		//FIXME: VIEWER: Is it possible to set the size of the image element before it is loaded (to avoid pop-in)?
		//FIXME: Somehow handle the viewer header code here?

		this.preSetupViewer(function(useCustomHeader, useCustomImageList) {
			useCustomHeader    = (typeof useCustomHeader !== 'undefined' ? useCustomHeader : false);
			useCustomImageList = (typeof useCustomImageList !== 'undefined' ? useCustomImageList : false);

			GM_addStyle(`
				#viewer                 { width: auto; max-width: 95%; margin: 0 auto !important; text-align: center; background: inherit; border: inherit; }
				#viewer > .read_img     { background: none; }
				#viewer > .read_img img { width: auto; max-width: 95%; border: 5px solid #a9a9a9; /*background: #FFF repeat-y; background: url("http://mangafox.me/media/loading.gif") no-repeat center;*/ min-height: 300px;}
				.pageNumber             { border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border-collapse: collapse; background-color: black; color: white; /*height: 18px; */font-size: 12px; font-family: Verdana; font-weight: bold; position: relative; bottom: 11px; width: 50px; text-align: center; opacity: 0.75; border-width: 2px; border-style: solid; border-color: white; border-radius: 16px !important; margin: 0px auto !important; padding: 0px !important; border-spacing: 0px !important;}
				.pageNumber .number     { border-collapse: collapse; text-align: center; display: table-cell; width: 50px; height: 18px; vertical-align: middle; border-spacing: 0px !important; padding: 0px !important; margin: 0px !important; }
				#viewer_header          { font-weight: bolder; text-align: center; }
			`);

			//Setup viewer header if enabled
			if(!useCustomHeader) {
				$('#viewer').append(
					$('<div/>', {id: 'viewer_header'}).append(
						$('<a/>', {href: _this.chapter_url, text: _this.viewerChapterName})).append(
						'  ----  ').append(
						$('<a/>', {href: _this.title_url, text: _this.viewerTitle})
					)
				);
			}

			//Generate the viewer using a loop & AJAX.
			for(var pageN=1; pageN<=_this.page_count; pageN++) {
				if(pageN == 1) {
					$('<div/>', {id: 'page-'+pageN, class: 'read_img'}).appendTo($('#viewer'));
				} else {
					$('<div/>', {id: 'page-'+pageN, class: 'read_img'}).insertAfter($('#viewer > .read_img:last'));
				}

				if(!useCustomImageList) {
					$.ajax({
						url: _this.viewerChapterURLFormat.replace('%pageN%', pageN),
						type: 'GET',
						page: pageN,
						//async: false,
						success: function(data) {
							var original_image  = $(data.replace(_this.viewerRegex, '$1')).find('img:first').addBack('img:first');
							var image_container = $('<div/>', {class: 'read_img'}).append(
								//We want to completely recreate the image element to remove all additional attributes
								$('<img/>', {src: $(original_image).attr('src')})).append(
								//Add page number
								$('<div/>', {class: 'pageNumber'}).append(
									$('<div/>', {class: 'number', text: this.page}))
							);

							//Replace the placeholder image_container with the real one
							$('#page-'+this.page).replaceWith(image_container);
						}
					});
				} else {
					//FIXME: We should probably split this and the above into a seperate function to avoid code duplication...
					var image_container = $('<div/>', {class: 'read_img'}).append(
						//We want to completely recreate the image element to remove all additional attributes
						$('<img/>', {src: _this.viewerCustomImageList[pageN-1]})).append(
						//Add page number
						$('<div/>', {class: 'pageNumber'}).append(
							$('<div/>', {class: 'number', text: pageN}))
					);

					//Replace the placeholder image_container with the real one
					$('#page-'+pageN).replaceWith(image_container);
				}
			}

			//Auto-track chapter if enabled.
			$(window).on("load", function() {
				if(config.auto_track && config.auto_track == 'on') {
					_this.trackChapter();
				}
			});

			_this.postSetupViewer();
		});
	},


	/** Variables **/
	//Used for tracking.
	site    : location.hostname.replace(/^(?:dev|test)\./, ''),
	title   : '',
	chapter : '',

	//Used by everything for easy access
	chapter_url : '',
	title_url   : '',

	//Used for topbar.
	chapterListCurrent : '',
	chapterList        : {},

	//Used for custom viewer header (if requested)
	viewerChapterName      : '',
	viewerTitle            : '',
	viewerChapterURLFormat : '%pageN%', //%pageN% is replaced by the page number on load.
	//Used for viewer AJAX (if used)
	viewerRegex            : /^$/, // First img tag MUST be the chapter page
	viewerCustomImageList  : [] //This is is only used if useCustomImageList is true
};
function extendSite(o) { return Object.assign({}, base_site, o); }
function generateChapterList(target, attrURL) {
	var chapterList = {};
	if(target instanceof jQuery) {
		$(target).each(function() {
			chapterList[$(this).attr(attrURL)] = $(this).text().trim();
		});
	} else {
		//TODO: Throw error
	}
	return chapterList;
}

var sites = {
	'mangafox.me' : extendSite({
		setObjVars : function () {
			var segments     = window.location.pathname.replace(/^(.*\/)(?:[0-9]+\.html)?$/, '$1').split( '/' );

			this.title       = segments[2];
			this.chapter     = (!!segments[4] ? segments[3]+'/'+segments[4] : segments[3]);

			this.page_count  = $('#top_bar .prev_page + div').text().trim().replace(/^[\s\S]*of ([0-9]+)$/, '$1');

			this.title_url   = 'http://mangafox.me/manga/'+this.title+'/';
			this.chapter_url = 'http://mangafox.me/manga/'+this.title+'/'+this.chapter+'/';

			this.chapterListCurrent = this.chapter_url;
			this.chapterList        = {}; //This is set via preSetupTopbar

			this.viewerTitle            = $('#series > strong:last > a').text().slice(0, -6);
			this.viewerChapterURLFormat = this.chapter_url + '%pageN%'+'.html';
			this.viewerRegex            = /^[\s\S]*(<div class="read_img">[\s\S]*<\/div>)[\s\S]*<div id="MarketGid[\s\S]*$/;
		},
		stylize : function() {
			//This removes the old border/background. The viewer adds borders to the images now instead which looks better.
			$('#viewer').css({
				'background' : 'none',
				'border'     : '0'
			});

			//Remove page count from the header, since all pages are loaded at once now.
			$('#tool > #series > strong:eq(1)').remove();

			//Float title in the header to the right. This just looks nicer and is a bit easier to read.
			$('#tool > #series > strong:last').css('float', 'right');
		},
		preSetupTopBar : function(callback) {
			var _this = this;

			//The inline chapter list is cached. This causes new chapters to not properly show on the list. (Why the cache isn't reset when a new chapter is added is beyond me)
			//Because of this, we can't use the inline chapter list as a source, and instead we need to check the manga page.
			$.ajax({
				url: _this.title_url,
				beforeSend: function(xhr) {
					xhr.setRequestHeader("Cache-Control", "no-cache, no-store");
					xhr.setRequestHeader("Pragma", "no-cache");
				},
				cache: false,
				success: function(response) {
					response = response.replace(/^[\S\s]*(<div id="chapters"\s*>[\S\s]*)<div id="discussion" >[\S\s]*$/, '$1'); //Only grab the chapter list
					var div = $('<div/>').append($(response));

					$("#chapters > .chlist > li > div > a + * > a", div).reverseObj().each(function() {
						var chapterTitle     = $('+ span.title', this).text().trim();
						var url              = $(this).attr('href').replace(/^(.*\/)(?:[0-9]+\.html)?$/, '$1'); //Remove trailing page number
						var realChapterTitle = url.replace(/^.*\/manga\/[^/]+\/(?:v(.*?)\/)?c(.*?)\/$/, 'Vol.$1 Ch.$2').replace(/^Vol\. /, '') + (chapterTitle !=='' ? ': '+chapterTitle : '');

						_this.chapterList[url] = realChapterTitle;
					});

					callback();
				}
			});
		},
		postSetupTopBar : function() {
			$('#top_center_bar, #bottom_center_bar').remove();
			$('#tool').parent().find('> .gap').remove();
			$('#series').css('padding-top', '0');
		},
		preSetupViewer : function(callback) {
			$('#viewer').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			callback();
		}
	}),

	'www.mangahere.co' : extendSite({
		//MangaHere uses pretty much the same site format as MangaFox, with a few odd changes.
		setObjVars : function() {
			var segments       = window.location.pathname.replace(/^(.*\/)(?:[0-9]+\.html)?$/, '$1').split( '/' );

			//FIXME: Is there a better way to do this? It just feels like an ugly way of setting vars.
			this.page_count    = $('.go_page:first > .right > select > option').length;
			this.title         = segments[2];
			this.chapter       = (!!segments[4] ? segments[3]+'/'+segments[4] : segments[3]);

			this.title_url   = 'http://www.mangahere.co/manga/'+this.title+'/';
			this.chapter_url = 'http://www.mangahere.co/manga/'+this.title+'/'+this.chapter+'/';

			this.chapterListCurrent = this.chapter_url;
			// this.chapterList        = {}; //This is set via preSetupTopbar

			this.viewerTitle            = $('.readpage_top > .title > h2').text().slice(0, -6);
			this.viewerChapterURLFormat = this.chapter_url + '%pageN%'+'.html';
			this.viewerRegex            = /^[\s\S]*<section class="read_img" id="viewer">[\s\S]*(<img src[\s\S]*\/>)[\s\S]*<\/section>[\s\S]*<section class="readpage_footer[\s\S]*$/;
		},
		stylize : function() {
			GM_addStyle(`
				.read_img { min-height: 0; }
				.readpage_top {margin-bottom: 5px;}
				.readpage_top .title h1, .readpage_top .title h2 {font-size: 15px;}
			`);

			//Remove banners
			$('.readpage_top > div[class^=advimg], .readpage_footer > div[class^=banner-]').remove();

			//Remove Tsukkomi thing
			$('.readpage_footer > .tsuk-control, #tsuk_container').remove();

			//Remove social bar.
			$('.plus_report').remove();

			$('#viewer').css({
				'background' : 'none',
				'border'     : '0'
			});

			//Format the chapter header
			$('.readpage_top > .title').html(function(i, html) { return html.replace('</span> / <h2', '</span><h2'); });
			$('.readpage_top > .title > span[class^=color]').remove();
			$('.readpage_top > .title h2').addClass('right');
		},
		preSetupTopBar : function(callback) {
			var _this = this;

			//Much like MangaFox, the inline chapter list is cached so we need to grab the proper list via AJAX.
			$.ajax({
				url: _this.title_url,
				beforeSend: function(xhr) {
					xhr.setRequestHeader("Cache-Control", "no-cache, no-store");
					xhr.setRequestHeader("Pragma", "no-cache");
				},
				cache: false,
				success: function(response) {
					response = response.replace(/^[\S\s]*(<section id="main" class="main clearfix">[\S\s]*(?=<\/section>)<\/section>)[\S\s]*$/, '$1'); //Only grab the chapter list
					var div = $('<div/>').append($(response).find('.detail_list > ul:first'));

					$('li > span.left > a', div).reverseObj().each(function() {
						var chapterTitle     = $(this).parent().clone().children().remove().end().text().trim();

						var url              = $(this).attr('href').replace(/^(.*\/)(?:[0-9]+\.html)?$/, '$1'); //Remove trailing page number
						var realChapterTitle = url.replace(/^.*\/manga\/[^/]+\/(?:v(.*?)\/)?c(.*?)\/$/, 'Vol.$1 Ch.$2').replace(/^Vol\. /, '') + (chapterTitle !=='' ? ': '+chapterTitle : '');

						_this.chapterList[url] = realChapterTitle;
					});

					callback();
				}
			});
		},
		postSetupTopBar : function() {
			$('.go_page:first').remove();
		},
		preSetupViewer : function(callback) {
			$('#viewer').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			callback(true);
		}
	}),

	'bato.to' : extendSite({
		preInit : function(callback) {
			var _this = this;

			//Bato.to loads the image page AFTER page load via AJAX. We need to wait for this to load.
			var dfd = $.Deferred();
			var checkSelector = setInterval(function () {
				if ($('#reader').text() !== 'Loading...') {
					//AJAX has loaded, resolve deferred.
					dfd.resolve();
					clearInterval(checkSelector);
				} else {
					console.log("forever loading");
				}
			}, 1000);
			dfd.done(function () {
				callback();
			});
		},
		setObjVars : function() {
			var chapterNParts   = $('select[name=chapter_select]:first > option:selected').text().trim().match(/^(?:Vol\.(\S+) )?(?:Ch.([^\s:]+)):?.*/);

			this.page_count     = $('#page_select:first > option').length;
			this.is_web_toon    = ($('a[href$=_1_t]').length ? ($('a[href$=_1_t]').text() == 'Want to see this chapter per page instead?' ? 1 : 2) : 0); //0 = no, 1 = yes & long strip, 2 = yes & chapter per page

			this.chapter_hash   = location.hash.substr(1).split('_')[0];
			this.chapter_number = (chapterNParts[1] ? 'v'+chapterNParts[1]+'/' : '') + 'c'+chapterNParts[2];

			this.title_url      = $('#reader a[href*="/comic/"]:first').attr('href');
			this.manga_language = $('select[name=group_select]:first > option:selected').text().trim().replace(/.* - ([\S]+)$/, '$1');

			this.title          = this.title_url.split('/')[6] + ':--:' + this.manga_language;
			this.chapter        = this.chapter_hash + ':--:' + this.chapter_number;
			this.chapter_url    = 'http://bato.to/reader#'+this.chapter_hash;

			this.chapterListCurrent = this.chapter_url;
			this.chapterList        = generateChapterList($('select[name=chapter_select]:first > option').reverseObj(), 'value');

			this.viewerChapterName      = this.chapter_number;
			this.viewerTitle            = document.title.replace(/ - (?:vol|ch) [0-9]+.*/, '').replace(/&#(\d{1,4});/, function(fullStr, code) { return String.fromCharCode(code); });
			this.viewerChapterURLFormat = 'http://bato.to/areader?id='+this.chapter_hash+'&p=' + '%pageN%';
			this.viewerRegex            = /^[\s\S]+(<img id="comic_page".+?(?=>)>)[\s\S]+$/;
			this.viewerCustomImageList  = $('#reader').find('#read_settings + div + div img').map(function(i, e) {
				return $(e).attr('src');
			});
		},
		stylize : function() {
			//Nothing?
		},
		preSetupViewer : function(callback) {
			this.viewerCustomImageList = $('#reader').find('#read_settings + div + div img').map(function(i, e) {
				return $(e).attr('src');
			});

			$('#reader').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			if(this.is_web_toon !== 1) {
				callback();
			} else {
				//Bato.to has an option for webtoons to show all chapters on a single page (with a single ajax), we need to do stuff differently if this happens.
				this.page_count = this.viewerCustomImageList.length;
				callback(false, true);
			}
		}
	}),

	'dynasty-scans.com' : extendSite({
		setObjVars : function() {
			this.is_one_shot = !$('#chapter-title > b > a').length;

			if(!this.is_one_shot) {
				this.title_url   = $('#chapter-title > b > a').attr('href').replace(/.*\/(.*)$/, '$1');
				this.chapter_url = location.pathname.split(this.title_url + '_').pop(); //There is really no other valid way to get the chapter_url :|
			} else {
				this.title_url   = location.pathname.substr(10);
				this.chapter_url = 'oneshot'; //This is labeled oneshot so it's properly handled in the backend.
			}

			this.title   = this.title_url + ':--:' + (+this.is_one_shot);
			this.chapter = this.chapter_url;

			this.chapterListCurrent = location.pathname;
			this.chapterList = {}; //This is set in preSetupTopBar

			this.viewerTitle = $('#chapter-title > b > a, #chapter-title > b').get(0).innerText; //FIXME: This doesn't prepend series names (if exists)
			this.viewerCustomImageList = $('script:contains("/system/releases/")').html().match(/"(\/system[^"]+)"/g).map(function(e, i) {
				return e.replace(/^"|"$/g, '');
			});
			this.page_count = this.viewerCustomImageList.length;
		},
		stylize : function() {
			//These buttons aren't needed since we have our own viewer.
			$('#chapter-actions > div > .btn-group:last, #download_page').remove();
			$('#reader').addClass('noresize');

			//Topbar covers a bunch of nav buttons.
			GM_addStyle(`
				#content > .navbar > .navbar-inner { padding-top: 42px; }
			`);
		},
		preSetupTopBar : function(callback) {
			var _this = this;

			if(!_this.is_one_shot) {
				//Sadly, we don't have any form of inline chapterlist. We need to AJAX the title page for this one.
				$.ajax({
					url: 'http://dynasty-scans.com/series/'+_this.title_url,
					beforeSend: function(xhr) {
						xhr.setRequestHeader("Cache-Control", "no-cache, no-store");
						xhr.setRequestHeader("Pragma", "no-cache");
					},
					cache: false,
					success: function(response) {
						response = response.replace(/^[\S\s]*(<dl class="chapter-list">[\S\s]*<\/dl>)[\S\s]*$/, '$1');
						var div = $('<div/>').append($(response));

						_this.chapterList = generateChapterList($(".chapter-list > dd > a.name", div), 'href');

						callback();
					}
				});
			} else {
				_this.chapterList[location.pathname] = 'Oneshot';

				callback();
			}
		},
		preSetupViewer : function(callback) {
			$('#reader').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			callback(true, true);
		}
	}),

	'www.mangapanda.com' : extendSite({
		preInit : function(callback) {
			//MangaPanda is tricky. For whatever stupid reason, it decided to not use a URL format which actually seperates its manga URLs from every other page on the site.
			//I've went and already filtered a bunch of URLs out in the include regex, but since it may not match everything, we have to do an additional check here.
			if($('#topchapter, #chapterMenu, #bottomchapter').length === 3) {
				//MangaPanda is another site which uses the MangaFox layout. Is this just another thing like FoolSlide?

				callback();
			}
		},
		setObjVars : function() {
			var segments        = window.location.pathname.split( '/' );

			this.page_count     = parseInt($('#topchapter #selectpage select > option:last').text());
			this.title          = segments[1];
			this.chapter        = segments[2];

			this.chapterListCurrent = '/'+this.title+'/'+this.chapter;
			// this.chapterList = {}, //This is set via preSetupTopBar.

			this.title_url      = 'http://www.mangapanda.com/'+this.title+'/';
			this.chapter_url    = 'http://www.mangapanda.com/'+this.title+'/'+this.chapter+'/';

			// this.viewerChapterName      = '';
			this.viewerTitle            = $('#mangainfo > div[style*=float] > h2').text().slice(0, -6);
			this.viewerChapterURLFormat = this.chapter_url + '%pageN%';
			this.viewerRegex            = /^[\s\S]+(<img id="img".+?(?=>)>)[\s\S]+$/;
		},
		stylize : function() {
			//Remove page count from the header, since all pages are loaded at once now.
			$('#mangainfo > div:first .c1').remove();

			//Float title in the header to the right. This just looks nicer and is a bit easier to read.
			$('#mangainfo > div + div:not(.clear)').css('float', 'right');
		},
		preSetupTopBar : function(callback) {
			var _this = this;

			//MangaPanda is tricky here. The chapter list is loaded via AJAX, and not a <script> tag. As far as I can tell, we can't watch for this to load without watching the actual element.
			//TODO: This should auto-fail after x amount of tries.
			var checkExist = setInterval(function() {
				if($('#topchapter > #selectmanga > select > option').length) {
					clearInterval(checkExist);

					_this.chapterList = generateChapterList($('#topchapter > #selectmanga > select > option'), 'value');
					callback();
				}
			}, 500);
		},
		postSetupTopBar : function(topbar) {
			//Remove MangaFox's chapter navigation since we now have our own. Also remove leftover whitespace.
			$('#topchapter > #mangainfo ~ div, #bottomchapter > #mangainfo ~ div').remove();
		},
		preSetupViewer : function(callback) {
			var _this = this;

			$('.episode-table').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			callback(true);
		}
	}),

	'mangastream.com' : extendSite({
		setObjVars : function() {
			var segments     = window.location.pathname.split( '/' );

			this.https       = location.protocol.slice(0, -1);

			this.page_count  = parseInt($('.controls ul:last > li:last').text().replace(/[^0-9]/g, ''));
			this.title       = segments[2];
			this.chapter     = segments[3]+'/'+segments[4];

			this.title_url   = this.https+'://mangastream.com/manga/'+this.title;
			this.chapter_url = this.https+'://mangastream.com/r/'+this.title+'/'+this.chapter;

			// this.chapterList     = {}; //This is set via preSetupTopBar.
			this.chapterListCurrent = this.chapter_url+'/1';

			this.viewerChapterName      = 'c'+this.chapter.split('/')[0];
			this.viewerTitle            = $('.btn-reader-chapter > a > span:first').text();
			this.viewerChapterURLFormat = this.chapter_url + '/' + '%pageN%';
			this.viewerRegex            = /^[\s\S]+(<div class="page">.+(?:.+)?(?=<\/div>)<\/div>)[\s\S]+$/;
		},
		stylize : function() {
			GM_addStyle(`
				.page { margin-right: 0 !important; }
				#reader-nav { margin-bottom: 0; }
			`);

			$('.page-wrap > #reader-sky').remove(); //Ad block
		},
		preSetupTopBar : function(callback) {
			var _this = this;

			$.ajax({
				url: _this.title_url,
				beforeSend: function(xhr) {
					xhr.setRequestHeader("Cache-Control", "no-cache, no-store");
					xhr.setRequestHeader("Pragma", "no-cache");
				},
				cache: false,
				success: function(response) {
					var table = $(response.replace(/^[\S\s]*(<table[\S\s]*<\/table>)[\S\s]*$/, '$1'));

					_this.chapterList = generateChapterList($('tr:not(:first) a', table).reverseObj(), 'href');

					callback();
				}
			});
		},
		postSetupTopBar : function() {
			$('.subnav').remove(); //Remove topbar, since we have our own
		},
		preSetupViewer : function(callback) {
			var _this = this;

			$('.page').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			callback();
		}
	}),

	'www.webtoons.com' : extendSite({
		setObjVars : function() {
			var segments     = window.location.pathname.split( '/' );

			var title_id     = window.location.search.match(/title_no=([0-9]+)/)[1];
			var chapter_id   = window.location.search.match(/episode_no=([0-9]+)/)[1];
			this.title       = title_id   + ':--:' + segments[1] + ':--:' + segments[3] + ':--:' + segments[2];
			this.chapter     = chapter_id + ':--:' + segments[4];

			this.title_url   = 'http://www.webtoons.com/'+segments[1]+'/'+segments[2]+'/'+segments[3]+'/list?title_no='+title_id;
			this.chapter_url = 'http://www.webtoons.com/'+segments[1]+'/'+segments[2]+'/'+segments[3]+'/'+segments[4]+'/viewer?title_no='+title_id+'&episode_no='+chapter_id;

			this.chapterList        = generateChapterList($('.episode_lst > .episode_cont > ul > li a'), 'href');
			this.chapterListCurrent = this.chapter_url;

			this.viewerTitle = $('.subj').text();
		}
	}),

	'kissmanga.com' : extendSite({
		preInit : function(callback) {
			//Kissmanga has bot protection, sometimes we need to wait for the site to load.
			if($('.cf-browser-verification').length === 0) {
				//Kissmanga has a built-in method to show all pages on the same page. Check if the cookie is correct, otherwise change and refresh.
				if(getCookie('vns_readType1') !== '1') {
					callback();
				} else {
					document.cookie = 'vns_readType1=0; expires=Fri, 6 Sep 2069 00:00:00 UTC; path=/;';
					location.reload();
				}
			}
		},
		setObjVars : function() {
			var segments     = window.location.pathname.split( '/' );

			var chapter_id   = document.location.search.match(/id=([0-9]+)/)[1];

			this.title       = segments[2];
			this.chapter     = segments[3] + ':--:' + chapter_id;

			this.title_url   = 'http://kissmanga.com/Manga/'+this.title;
			this.chapter_url = this.title_url+'/'+segments[3]+'?id='+chapter_id;

			this.chapterList        = generateChapterList($('.selectChapter:first > option'), 'value');
			this.chapterListCurrent = segments[3]+'?id='+chapter_id;


			this.viewerChapterName     = $('.selectChapter:first > option:selected').text().trim();
			this.viewerTitle           = $('title').text().trim().split("\n")[1];
			this.viewerCustomImageList = $('#headnav + div + script').html().match(/"(http:\/\/[^"]+)"/g).map(function(e, i) {
				return e.replace(/^"|"$/g, '');
			});
			this.page_count = this.viewerCustomImageList.length;
		},
		postSetupTopBar : function() {
			//Remove extra unneeded elements.
			$('#divImage').prevAll().remove();
			$('#divImage').nextAll().remove();
		},
		preSetupViewer : function(callback) {
			$('#divImage').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div

			this.page_count = this.viewerCustomImageList.length;
			callback(false, true);
		}
	}),

	'reader.kireicake.com' : extendSite({
		setObjVars : function() {
			var segments     = window.location.pathname.split( '/' );

			this.title       = segments[2];
			this.chapter     = segments[3] + '/' + segments[4] + '/' + segments[5] + (segments[6] && segments[6] !== 'page' ? '/' + segments[6] : '');

			this.title_url   = 'http://reader.kireicake.com/series/'+this.title;
			this.chapter_url = 'http://reader.kireicake.com/read/'+this.title+'/'+this.chapter;

			this.chapterList        = generateChapterList($('.topbar_left > .tbtitle:eq(2) > ul > li > a').reverseObj(), 'href');
			this.chapterListCurrent = this.chapter_url+'/';

			// this.viewerChapterName     = $('.selectChapter:first > option:selected').text().trim();
			this.viewerTitle           = $('.topbar_left > .dropdown_parent > .text a').text();
			this.viewerCustomImageList = $('#content > script:first').html().match(/(http:\\\/\\\/[^"]+)/g).filter(function(value, index, self) { 
				return self.indexOf(value) === index;
			}).map(function(e, i) {
				return e.replace(/\\/g, '');
			});
			this.page_count = this.viewerCustomImageList.length;
		},
		postSetupTopBar : function() {
			$('.topbar_left > .tbtitle:eq(2)').remove();
			$('.topbar_right').remove();
		},
		preSetupViewer : function(callback) {
			$('#page').replaceWith($('<div/>', {id: 'viewer'})); //Set base viewer div
			callback(true, true);
		}
	}),

	//Tracking site
	//FIXME: We <probably> shouldn't have this here, but whatever.
	'trackr.moe' : {
		init : function() {
			/* TODO:
			Stop generating HTML here, move entirely to PHP, but disable any user input unless enabled via userscript.
			If userscript IS loaded, then insert data.
			Seperate API key from general options. Always set API config when generate is clicked.
			*/

			//Enable the form
			$('#userscript-check').remove();
			$('#userscript-form fieldset').removeAttr('disabled');
			$('#userscript-form input[type=submit]').removeAttr('onclick');

			//CHECK: Is there a better way to mass-set form values from an object/array?
			$('#userscript-form input#auto_track').attr('checked', !!config.auto_track);

			$('#userscript-form').submit(function(e) {
				var data = $(this).serializeArray().reduce(function(m,o){ m[o.name] = o.value; return m;}, {});
				if(config['api-key']) {
					data['api-key'] = config['api-key'];
					// data['init'] = false;

					GM_setValue('config', JSON.stringify(data));
					$('#form-feedback').text('Settings saved.').show().delay(4000).fadeOut(1000);
				} else {
					$('#form-feedback').text('API Key needs to be generated before options can be set.').show().delay(4000).fadeOut(1000);
				}

				e.preventDefault();
			});

			$('#api-key').text(config['api-key'] || "not set");
			$('#api-key-div').on('click', '#generate-api-key', function() {
				$.getJSON(main_site + '/ajax/get_apikey', function(json) {
					if(json['api-key']) {
						$('#api-key').text(json['api-key']);

						config['api-key'] = json['api-key'];
						GM_setValue('config', JSON.stringify(config));
					} else {
						alert('ERROR: Something went wrong!\nJSON missing API key?');
					}
				}).fail(function(jqXHR, textStatus, errorThrown) {
					switch(jqXHR.status) {
						case 400:
							alert('ERROR: Not logged in?');
							break;
						case 429:
							alert('ERROR: Rate limit reached.');
							break;
						default:
							alert('ERROR: Something went wrong!\n'+errorThrown);
							break;
					}
				});
			});

			if(config.init === true) {
				//TODO: Point user to generating API key.
			}
		}
	}
};

/********************** SCRIPT *********************/
var main_site = 'https://dev.trackr.moe';
//FIXME: This should point to non-dev. We should only point to dev if requested

var config = JSON.parse(GM_getValue('config') || '{"init": true}');
console.log(config); //TODO: Disable on production

if(!$.isEmptyObject(config)) {
	//Config is loaded, do stuff.
	var hostname = location.hostname.replace(/^(?:dev|test)\./, '');
	if(hostname == 'trackr.moe') {
		sites[hostname].init();
	} else if(sites[hostname]) {
		$(function() {
			sites[hostname].init();
		});
	}
} else {
	alert('Tracker isn\'t setup! Go to tracker.moe/user/options to set things up.');
}
