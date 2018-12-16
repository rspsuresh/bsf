// JavaScript Document
var oldPageNo = 0;
function getBaseURL() {
    var url = location.href;  // entire url including querystring - also: window.location.href;
    var baseURL = url.substring(0, url.indexOf('/', 14));

    if ((baseURL.indexOf('http://localhost') == 0) || (baseURL.indexOf('https://localhost') == 0)) {
        // Base Url for
        var url = location.href;  // window.location.href;
        var pathname = location.pathname;  // window.location.pathname;
        var index1 = url.indexOf(pathname);
        var index2 = url.indexOf("/", index1 + 1);
        var baseLocalUrl = url.substr(0, index2);
        return baseLocalUrl + "/public/";
    } else if ((baseURL.indexOf('http://www.buildsuperfast.com') == 0) || (baseURL.indexOf('https://www.buildsuperfast.com') == 0)) {
        return baseURL + "/public/";
    } else if ((baseURL.indexOf('http://micromen.com') == 0) || (baseURL.indexOf('http://micromen.com') == 0)  || (baseURL.indexOf('http://www.micromen.com') == 0)) {
        return baseURL + "/testing/bsf_v1.2/public/";
    } else {
        // Root Url for domain name
        return baseURL + "/bsf_v1.2/public/";
    }
}

$(document).ready(function()
{
	$('.signin_btn').hover(function(){
		$('.signin_key').removeClass('rotateInDownLeft').addClass('animated rotateInUpLeft');
    },function(){
	    $('.signin_key').removeClass('rotateInUpLeft').addClass('rotateInDownLeft');
    });
	
	$('.l_menuicon_nav').on( "click", function() {
		$(this).toggleClass('cross');
		$(window).trigger('resize');
	});
	
	$('.mainmenu_open').on( "click", function() {
		$('.main_module').slideToggle(500);
	});
	
	$('.user_image a').hover (function() {
		$('.user_image a img').addClass('animated_1_5s flipInX').removeClass('bounceIn').css("opacity","1");
	},function(){
		$('.user_image a img').addClass('bounceIn').removeClass('flipInX').css("opacity","0.7");
	});	
	
	$('.comp_view_cover').hover (function() {
		$('.compcover_change').addClass('animated bounce').removeClass('bounceOut').css("opacity","1");
	},function(){
		$('.compcover_change').removeClass('bounce').addClass('bounceOut').css("opacity","0");
	});
	$('.ovrcls').hover (function() {
		$(this).find('.icon-hvr').addClass('animated fadeInUp').removeClass('fadeInDown');
	},function(){
		$(this).find('.icon-hvr').removeClass('fadeInUp').addClass('fadeInDown');
	});
	
	$('.refresh').click(function() {
    	location.reload();
	});
	
	$('.file_link').click(function(){
        $(this).parent().find('input').click();
    });
	
	$(".rightside_trigger").on("click", function () {
		$('.rightside_show').css('display','block');
		$(".rightside_area").toggleClass("rightside_show");	
	});
	
	$('[data-toggle="tooltip"]').tooltip();
	
	//$('.wlcm_scrn .slideshow_button_next,.wlcm_scrn .continue,.wlcm_scrn .onbdback_btn').hide(); 
	
	$('.onbdate_input input').on("click focus", function() {
		$('.intro').hide();
		$('.wlcm_scrn .slideshow_button_next,.wlcm_scrn .continue').show().addClass('animated_1_5s fadeInDown');
		$('.wlcm_scrn .onbdback_btn').show().addClass('animated_1_5s fadeInRight');
	});
	
	$('.intro_hide').on("click", function(){
		$('.intro').hide();
	});
	
	$('.btn-group .tbl_input,.tblinput_ricon').click(function(){
		var parentId = $(this).parent().attr('id');
		$('#'+ parentId+' .tblinput_ricon').css("display","block");
	});
	
	$('.btn-group .tbl_input,.tblinput_ricon').blur(function(){
		var parentId = $(this).parent().attr('id');
		$('#'+ parentId+' .tblinput_ricon').css("display","none");
	});
	
	$('.tbl_input_ddown a').click(function(){
		var parentId = $(this).closest('div').attr('id');
		var clickValue = $(this).html();	
		$('#'+ parentId+' .tbl_input').val(clickValue);
	});
    
    $('form').on('submit', function() {
        if (!navigator.onLine) {
            alert("Connection temporarily OffLine, Do not Submit");
            return false;
        }
    });
	$('.panel-heading').click(function(){
		$('.panel-heading').removeClass('accordion_head_crnt');
		if($(this).next('div.in'))
			$(this).addClass('accordion_head_crnt')
		else
			$(this).removeClass('accordion_head_crnt')
	});
	
});
/*--------------------loading-------------------*/
$(window).load(function() 
{
	$('.loading_area').fadeOut(1000, function() {
		$(this).hide();
	});
	
  	$('.rightside_area').addClass('rightside_show');
	
	if ($(window).width() <=1274){
	  	$('.rightside_area').removeClass('rightside_show');
	}
	if ($(window).width() <=980){
	  	$('.left-panel').toggleClass('open');
		$( '.l_menuicon_nav' ).toggleClass('cross');
	}
	$('.cd-popup').addClass('is-visible');
});	

/*---------------Aside Left Menu----------------*/
$(document).ready(function()
{
	$('.l_menuicon_nav').click(function(){
		$('.left-panel').toggleClass('open');
	});
	
	$('.modlsub_open').click(function(){
		$('.modlsub_open').removeClass('down_arrow');
		$(".modl_lmenu li ul").slideUp(300);
		$(".navigation ul li").removeClass('current');
		if(!$(this).next().is(":visible"))
		{
			$(this).next().slideToggle(300);
			$(this).closest('li').addClass('current');
			$(this).toggleClass('down_arrow');
		}
		return false;
	});
	
	/*----------------Scroll To Top----------------*/
	$('.scrollToTop').click(function(){
		$('html, body').animate({scrollTop : 0},800);
		return false;
	});
});

/*-----------------MainModlue menu-----------------*/
$(document).ready(function(){
	  var scroll = false;
	  var launcherMaxHeight = 351;
	  var launcherMinHeight = 296;
	  $(".mainmdl_mg2").hide();
	  // Mousewheel event handler to detect whether user has scrolled over the container
	  $('.mainmdl_m').bind('mousewheel', function(e){
			if(e.originalEvent.wheelDelta /120 > 0) {
			  // Scrolling up
			}
			else{
				// Scrolling down
				if(!scroll){
					$(".mainmdl_mg2").show();
					$('.mainmdl_m').css({height: launcherMinHeight}).addClass('overflow');
					scroll =true; 
					$(this).scrollTop(e.originalEvent.wheelDelta);
				}
			}
		});
	  // Scroll event to detect that scrollbar reached top of the container
	  $('.mainmdl_m').scroll(function(){
		var pos=$(this).scrollTop();
		if(pos == 0){
			scroll =false;
			$('.mainmdl_m').css({height: launcherMaxHeight}).removeClass('overflow');
			$(".mainmdl_mg2").hide();
		}
	  });
	  // Click event handler to show more apps
	  $('.mainmdl_m .more').click(function(){
		$(".mainmdl_mg2").show();
		$(".mainmdl_m").animate({ scrollTop: $('.mainmdl_m')[0].scrollHeight}).css({height: 296}).addClass('overflow');
	  });
	  // Click event handler to toggle dropdown
	  $(".mainmdl_plus").click(function(event){
		event.stopPropagation();
		$(".mainmdl_ddown").toggle();
			if($(".mainmdl_ddown").css('display') == 'block') {
				$(".mainmdl_plus").addClass('animated_1_5s rotate45deg').removeClass('rotate45deg_out');
			} else {
				$(".mainmdl_plus").removeClass('rotate45deg').addClass('rotate45deg_out');	
			}
	  });
	  $(document).click(function() {
		//Hide the launcher if visible
		$('.mainmdl_ddown').hide();
		$(".mainmdl_plus").removeClass('rotate45deg').addClass('rotate45deg_out');
		});
		// Prevent hiding on click inside app launcher
		$('.mainmdl_ddown').click(function(event){
			event.stopPropagation();
		});
});
// Resize event handler to maintain the max-height of the app launcher
$(window).resize(function(){
    var $mainmdl_m = $('.mainmdl_m');
    if($mainmdl_m.length != 0)
        $mainmdl_m.css({maxHeight: $(window).height() - $mainmdl_m.offset().top});
});

/*-----------------Toggle Full Screen-----------------*/
$(document).on('click', '.toggleFullScreen', function()
{	
	if ((document.fullScreenElement && document.fullScreenElement !== null) || (!document.mozFullScreen && !document.webkitIsFullScreen)) {
		$(".toggleFullScreen>i").removeClass('fa-expand').addClass('fa-compress').css('color','#506faf');
		if (document.documentElement.requestFullScreen) {
			document.documentElement.requestFullScreen();
		} else if (document.documentElement.mozRequestFullScreen) {
			document.documentElement.mozRequestFullScreen();
		} else if (document.documentElement.webkitRequestFullScreen) {
			document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
		}
	} else {
		$(".toggleFullScreen>i").removeClass('fa-compress').addClass('fa-expand').css('color','#fff');
		if (document.cancelFullScreen) {
			document.cancelFullScreen();
		} else if (document.mozCancelFullScreen) {
			document.mozCancelFullScreen();
		} else if (document.webkitCancelFullScreen) {
			document.webkitCancelFullScreen();
		}
	}
});

/*--------------------pie timer---------------------*/
var timer;var timerFinish;var timerSeconds;function drawTimer(c, a) {$("#note_" + c).html('<div class="percent"></div><div id="slice"' + (a > 50 ? ' class="gt50"' : "") + '><div class="pie"></div>' + (a > 50 ? '<div class="pie fill"></div>' : "") + "</div>");var b = 360 / 100 * a;$("#note_" + c + " #slice .pie").css({"-moz-transform": "rotate(" + b + "deg)","-webkit-transform": "rotate(" + b + "deg)","-o-transform": "rotate(" + b + "deg)",transform: "rotate(" + b + "deg)"});a = Math.floor(a * 100) / 100;arr = a.toString().split(".");intPart = arr[0];dec = arr[1];if (!dec > 0) {dec = 0}$("#note_" + c + " .percent").html('<span class="int">' + intPart + '</span>' + '%')}function stopNote(d, b) {var c = (timerFinish - (new Date().getTime())) / 1000;var a = 100 - ((c / timerSeconds) * 100);a = Math.floor(a * 100) / 100;if (a <= b) {drawTimer(d, a)} else {b = $("#note_" + d).data("note");arr = b.toString().split(".");$("#note_" + d + " .percent .int").html(arr[0]);$("#note_" + d + " .percent .dec").html("." + arr[1])}}$(document).ready(function() {timerSeconds = 5;timerFinish = new Date().getTime() + (timerSeconds * 1000);$(".pieload_cnt").each(function(a) {note = $("#note_" + a).data("note");timer = setInterval("stopNote(" + a + ", " + note + ")", 0)})});
/*--------------------cd popup----------------------*/
//jQuery(document).ready(function($){
//	//open popup
//	$('.cd-popup-trigger').on('click', function(event){
//		event.preventDefault();
//		$('.cd-popup').addClass('is-visible');
//	});
//	/*//close popup
//	$('.cd-popup').on('click', function(event){
//		if( $(event.target).is('.cd-popup-close') || $(event.target).is('.cd-popup') ) {
//			event.preventDefault();
//			$(this).removeClass('is-visible');
//		}
//	});
//	//close popup when clicking the esc keyboard button
//	$(document).keyup(function(event){
//    	if(event.which=='27'){
//    		$('.cd-popup').removeClass('is-visible');
//	    }
//    });*/
//});
/*-----------------Jquery URLLive------------------*/
$(document).ready(function()
{
	//$('.urlive_textarea').on('input propertychange', function () {
	//	$('.urlive_textarea').urlive({
	//	imagesize: 'small',
	//	callbacks: {
	//		onStart: function () {
	//			$('.urlive-container').urlive('remove');
	//		},
	//		onSuccess: function (data) {
	//			$('.urlive-container').urlive('remove');
	//		},
	//		noData: function () {
	//			$('.urlive-container').urlive('remove');
	//		}
	//	}
    //});
	//}).trigger('input');
});
/*-----------------textarea expand------------------*/
$(document).ready(function()
{
	$(document).on('focus.textarea', '.autoExpand', function(){
		var savedValue = this.value;
		this.value = '';
		this.baseScrollHeight = this.scrollHeight;
		this.value = savedValue;
	});
	$(document).on('input.textarea', '.autoExpand', function(){
		var minRows = this.getAttribute('data-min-rows')|0, rows;
		this.rows = minRows;
		rows = Math.ceil((this.scrollHeight - this.baseScrollHeight) / 17);
		this.rows = minRows + rows;
	});
});
/*-----------------Save Or Discard changes on page------------------*/
var changesMadeOnPage = false;
function initPageChangeFn() {
    $('input, textarea, select').on('change', function () {
        changesMadeOnPage = true;
    });
    window.onbeforeunload = function (e) {
        if (changesMadeOnPage) {
            var e = e || window.event;
            // For IE and Firefox prior to version 4
            if (e) {
                e.returnValue = 'Your changes made will be discarded!';
            }
            // For Safari
            return 'Your changes made will be discarded!';
        }
    };
}
function setPageChanges(bool) {
    if(typeof bool != 'boolean')
        return false;

    changesMadeOnPage = bool;
}

// bind grid resize functionality
/*function bindJqxGridAutoResize() {
    // resize after page load
    var $window = $(window);
    $window.bind('load', function(){
        $window.trigger('resize');
    });
    $window.on('resize', function() {
        // Get width of parent container
        var targetContainer = '.table-responsive',
            targetGrid = '#jqxGrid';
        var width = $(targetContainer).attr('clientWidth');
        if (width == null || width < 1){
            // For IE, revert to offsetWidth if necessary
            width = $(targetContainer).attr('offsetWidth');
        }
        width = width - 2; // Fudge factor to prevent horizontal scrollbars
        if (width > 0 &&
                // Only resize if new width exceeds a minimal threshold
                // Fixes IE issue with in-place resizing when mousing-over frame bars
            Math.abs(width - $(targetGrid).width()) > 5)
        {
            $(targetGrid).setGridWidth(width);
        }
    }).trigger('resize');

    $('.l_menuicon_nav').on('click', function () {
        $window.trigger('resize');
    });
}*/

//script for GEO Location
function GetUserGeo(data) {
	stras = data.as;
	strcity = data.city;
	strcountry = data.country;
	strcountryCode = data.countryCode;
	strips = data.isp;
	strlatitude = data.lat; 
	strlongitude = data.lon;
	strip = data.query;  
	strorg = data.org;
	strregion = data.region; 
	strregionName = data.regionName;
	strtimezone = data.timezone;
	strzip = data.zip;
	$.ajax({
		url:getBaseURL()+"application/index/set-user-geo",
		type:"post",
		data:{'ip':strip,'country':strcountry,'countryCode':strcountryCode,'city':strcity,'region':strregionName,'latitude': strlatitude,'longtitude':strlongitude,'timezone': strtimezone},
		success:function(data,textStatus,jqXHR){
				
		}
	});
}
$(document).ready(function() {
	if ($('#newsFeedsList').length > 0) {
		$(window).scroll(function(){
			if ($('#newsFeed_totalFound').val() > 10) {
				if($(window).scrollTop() + $(window).height() >= $(document).height() -100) {
					//$('#noMoreNewsFeed').hide();
					newsFeedsList($('#newsFeed_pageNo').val(),$('#newsFeed_type').val(),$('#newsFeed_user').val());
				}
			}
		});
	}
});

function newsFeedsList (PageNo,FeedType,UserId) {
	if ($('#newsFeed_pageNo').length > 0 ) {
		if (PageNo == 0) {
			PageNo = 1;
			oldPageNo = 0;
		} else {
			PageNo = parseInt($('#newsFeed_pageNo').val())+1;
		}
		console.log(oldPageNo+':::::::'+PageNo);
		if (oldPageNo == PageNo) {
            return false;
        }
	    oldPageNo = PageNo;
	    
        var MaxPageNo = parseInt(parseInt($('#newsFeed_totalFound').val())/parseInt($('#newsFeed_perpage').val()));
        if (parseInt(parseInt($('#newsFeed_totalFound').val())%parseInt($('#newsFeed_perpage').val())) != 0)
        MaxPageNo = parseInt(MaxPageNo) + 1;
        if (MaxPageNo >= PageNo)
        {
			$('#moreNewsFeed').show();
			$.ajax({
				url: getBaseURL() + 'application/index/add-more-feeds',
				type: 'POST',
				data: {'PageNo': PageNo,'FeedType':FeedType,'UserId':UserId},
				success: function (data, textStatus, jqXHR) {
					if (PageNo == 1) {
						$("#newsFeedsList").html(data);
						$("#newsFeed_pageNo").val(1);
						$("#newsFeed_totalFound").val($(".IndexPageNews-TotalFound-Hiddden:last").text());
						$('#moreNewsFeed').hide();
						lightGallery();
					} else {
						$("#newsFeedsList").append(data);
						$("#newsFeed_pageNo").val(PageNo);
						$('#moreNewsFeed').hide();
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					if (jqXHR.status == 400)
						alert(jqXHR.responseText);
					else
						alert("Request Failed");

				}
			});
        } else {
           $('#noMoreNewsFeed').show();
        }
    }
}