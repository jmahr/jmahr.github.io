;(function($) {

"use strict";

var $body = $('body');
// var $head = $('head');
// var $mainWrapper = $('#main-wrapper');

/* -------------------------------------------------------------------------
    TWITTER FEED
  ------------------------------------------------------------------------- */
$.fn.uouTwitterFeed = function(){
  if ( $.fn.tweet ) {

    var self = $(this),
    feed_id = self.data( 'id' ),
    feed_limit = self.data( 'limit' ),
    widget = self.parents( '.twitter-widget' );

    self.bind( 'loaded', function(){
      widget.removeClass( 'loading' );
      if ( self.hasClass( 'paginated' ) && $.fn.owlCarousel ) {
        var interval = self.data( 'interval' ) ? parseInt( self.data( 'interval' ) ) > 0 : false;
        self.find( '.tweet-list' ).fadeIn(500);
        widget.find( '.tweet-nav' ).fadeIn(500);
        self.find( '.tweet-list' ).owlCarousel({
          autoPlay: interval,
          slideSpeed: 300,
          pagination: false,
          paginationSpeed : 400,
          singleItem:true
        });
        widget.find( '.tweet-nav-prev' ).click(function(){
          self.find( '.tweet-list' ).trigger( 'owl.prev' );
        });
        widget.find( '.tweet-nav-next' ).click(function(){
          self.find( '.tweet-list' ).trigger( 'owl.next' );
        });
      }
    });

    self.tweet({
      username: feed_id,
      modpath: './twitter/',
      count: feed_limit,
      loading_text: '<span class="loading-anim"><span></span></span>'
    });
  }
};

/* -------------------------------------------------------------------------
    MEDIA QUERY BREAKPOINT
------------------------------------------------------------------------- */
var uouMediaQueryBreakpoint = function() {

  if ($('#media-query-breakpoint').length < 1) {
    $('body').append('<var id="media-query-breakpoint"><span></span></var>');
  }
  var value = $('#media-query-breakpoint').css('content');
  if (typeof value !== 'undefined') {
    value = value.replace("\"", "").replace("\"", "").replace("\'", "").replace("\'", "");
    if (isNaN(parseInt(value, 10))) {
      $('#media-query-breakpoint span').each(function() {
        value = window.getComputedStyle(this, ':before').content;
      });
      value = value.replace("\"", "").replace("\"", "").replace("\'", "").replace("\'", "");
    }
    if (isNaN(parseInt(value, 10))) {
      value = 1199;
    }
  } else {
    value = 1199;
  }
  return value;
};

/* -------------------------------------------------------------------------
    UOU SELECT
  ------------------------------------------------------------------------- */
$.fn.uouCustomSelect = function () {
  var $select = $(this);

  $select.wrap('<div class="uou-custom-select"></div>');

  var $container = $select.parent('.uou-custom-select');

  $container.append('<ul class="select-clone"></ul>');

  var $list = $container.children('.select-clone'),
    placeholder = $select.data('placeholder') ? $select.data('placeholder') : $select.find('option:eq(0)').text();

  // $('<input class="value-holder" type="text" disabled="disabled" placeholder="' + placeholder + '"><i class="fa fa-chevron-down"></i>').insertBefore($list);
  $('<input class="value-holder" type="hidden" disabled="disabled" placeholder="' + placeholder + '"><span class="placeholder">' + placeholder + '</span><i class="fa fa-chevron-down"></i>').insertBefore($list);

  var $valueHolder = $container.children('.value-holder');
  var $valuePlaceholder = $container.children('.placeholder');

  // Create clone list
  $select.find('option').each(function () {
    var $this = $(this);

    $list.append('<li data-value="' + $this.val() + '">' + $this.text() + '</li>');
  });

  // Toggle list
  $container.on('click', function () {
    // console.log('click ' + $container);
    $container.toggleClass('active');
    $list.slideToggle(250);
  });

  // Option Select
  $list.children('li').on('click', function () {
    var $this = $(this);

    $valueHolder.val($this.text());
    $valuePlaceholder.html($this.text());
    $select.find('option[value="' + $this.data('value') + '"]').prop('selected', true);
  });

  // Hide
  $container.on('clickoutside touchendoutside', function () {
    if (!dragging) {
      $container.removeClass('active');
      $list.slideUp(250);
    }
  });

  // Links
  if ($select.hasClass('links')) {
    $select.on('change', function () {
      window.location.href = select.val();
    });
  }
};

$(document).ready(function() {

	// HOME SLIDER
	$("#home-slider").owlCarousel({
		autoPlay: false,
		singleItem: true
	});

	var homeSlide = $("#home-slider");

	$(".intro .next").click(function(){
      homeSlide.trigger('owl.next');
  });
  $(".intro .prev").click(function(){
    homeSlide.trigger('owl.prev');
  });

  // BACKGROUND FOR EACH SLIDE
  $( '#home-slider' ).each(function(){

    var self = $(this),
    images = self.find( '.item' );

    // SET BG IMAGES
    images.each(function(){
      var img =  $(this).find( 'img' );
      if ( img.length > 0 ) {
        $(this).css( 'background-image', 'url(' + img.attr( 'src' ) + ')' );
        img.hide();
      }
    });
  });

  // DATEPICKER
  $('.match-week').each(function(){
    $('.datepicker-inner').datepicker();
  });

  // SMOOTH SCROLLING
  $("nav ul li a[href^='#']").on('click', function(e) {

   // prevent default anchor click behavior
   e.preventDefault();

   // store hash
   var hash = this.hash;
   var headerHeight = $("#header.second-version").height() - 1; // Get fixed header height

   // animate
   $('html, body').animate({
       scrollTop: $(hash).offset().top - headerHeight
     }, 500);
    return false;
  });

  // BACK TO TOP BUTTON
  $('#back-to-top').each(function () {

    var $this = $(this);

    $this.on('click', function (event) {
      event.preventDefault();
      $('html, body').animate({ scrollTop: 0 }, 500);
    });

    $(window).scroll(function () {
      if ($(this).scrollTop() > 300) {
        $this.fadeIn(200);
      } else if ($(this).scrollTop() < 250) {
        $this.fadeOut(200);
      }
    });

  });
  
  // HEADER TOOLBAR LANGUAGE
  var $headerLanguageToggle = $('#header .header-toolbar .header-language');

  $headerLanguageToggle.children('a').on('click', function (event) {
    event.preventDefault();
    $(this).parent('.header-language').toggleClass('active');
  });

  $headerLanguageToggle.on('clickoutside touchendoutside', function () {
    if ($headerLanguageToggle.hasClass('active')) { $headerLanguageToggle.removeClass('active'); }
  });

  // HEADER TOOLBAR LOGIN/REGISTER
  var $headerLoginRegister = $('#header .header-toolbar .header-login, #header .header-toolbar .header-register');

  $headerLoginRegister.each(function () {
    var $this = $(this);

    $this.children('a').on('click', function (event) {
      event.preventDefault();
      $this.toggleClass('active');
    });

    $this.on('clickoutside touchendoutside', function () {
      if ($this.hasClass('active')) { $this.removeClass('active'); }
    });
  });

  $('select').each(function () {
    $(this).uouCustomSelect();
  });

  // PLAYERS PROFILES SLIDER
  $(".profile-slider").owlCarousel({
    autoPlay: false,
    singleItem: true,
    navigation: true,
    navigationText: false
  });

  // TWITTER WIDGET
  $( '.twitter-feed' ).each(function(){
    $(this).uouTwitterFeed();
  });

  // TABS
  $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    console.log(e.target) // activated tab
    $('#matches .teams-filter li, #trainings .teams-filter li').removeClass('active');
  });

  // CUPS SLIDER
  $(".cups-slider").owlCarousel({
    autoPlay: false,
    items: 5,
    navigation: true,
    navigationText: false,
    afterAction: function(el){
      this.$owlItems
      .find('.cup-single')
      .removeClass('active');
      this
      .$owlItems
      .eq(this.currentItem + 2)
      .find('.cup-single')
      .addClass('active');
    }
  });

  // GET ACTUAL MEDIA QUERY BREAKPOINT
  var media_query_breakpoint = uouMediaQueryBreakpoint();

  // TOGGLE SUBMENU
  var self = $('.header-navbar');

  // HOVER SUBMENU
  self.find('li.has-submenu').hover(function() {
    if (media_query_breakpoint > 992) {
      $(this).addClass('hover');
      $(this).find('.sub-menu').stop(true, true).fadeIn(200);
    }
  }, function() {
    if (media_query_breakpoint > 992) {
      $(this).removeClass('hover');
      $(this).find('.sub-menu').stop(true, true).delay(10).fadeOut(200);
    }
  });

  // RESPONSIVE HEADER
  self.find('.navbar-toggle').each(function() {
    $(this).click(function() {
      $(this).parent().find('.navigation').slideToggle('200');
      $(this).parent().find('.navigation').toggleClass('active');
      $(this).parent().parent().parent().parent().find('.header-navbar').toggleClass('active');
      $(this).parent().parent().parent().parent().parent().find('#header').toggleClass('active');
      $(this).parent().parent().parent().parent().parent().find('.header-logo').toggleClass('active');
      $(this).find('.fa').toggleClass('fa-list fa-outdent');
    });
  });

  self.find( 'li.has-submenu' ).each(function(){
    $(this).append( '<button class="submenu-toggle"><i class="fa fa-chevron-down"></i></button>' );
  });

  // TOGGLE SUBMENU
  self.find('.submenu-toggle').each(function() {
    $(this).click(function() {
      $(this).parent().find('.sub-menu').slideToggle(200);
      $(this).find('.fa').toggleClass('fa-chevron-up fa-chevron-down');
    });
  });

  // MEDIA QUERY BREAKPOINT
  $(window).resize(function(){
    if ( uouMediaQueryBreakpoint() !== media_query_breakpoint ) {
      media_query_breakpoint = uouMediaQueryBreakpoint();
      $( '.navigation, .has-submenu .sub-menu' ).removeAttr( 'style' );
      $( '#header' ).removeClass( 'active' );
    }
  });

  // MATCHES SLIDER
  $(".matches, .video-tabs").each(function(){
    var $ul = $(this);
 
    // SLIDE UP
    $(this).parent().find( ".prev" ).on("click", function() {
      var y = $ul.scrollTop();
      var $firstChild = $ul.children().first();
      var step = $firstChild.outerHeight() + parseInt($firstChild.css('marginBottom'), 10);
      if (y >= 0) {
        y -= step;
        $ul.stop().animate({
            scrollTop: y
        }, 100);
      }
    });
 
    // SLIDE DOWN
    $(this).parent().find( ".next" ).on("click", function() {
      var y = $ul.scrollTop();
      var h = $ul.height();
      var $firstChild = $ul.children().first();
      var step = $firstChild.outerHeight() + parseInt($firstChild.css('marginBottom'), 10);
      if (h >= y) {
        y += step;
        $ul.stop().animate({
            scrollTop: y
        }, 100);
      }
    });
  });

  // TEAMS-FILTER SLIDER
  $(".teams-filter").owlCarousel({
    autoPlay: false,
    items: 9,
    navigation: false
  });

  $('#matches .teams-filter li').on('click', function(){ 
    $(".active1").removeClass("active1");
    $(this).addClass("active1");
  });

  $('#trainings .teams-filter li').on('click', function(){ 
    $(".active2").removeClass("active2");
    $(this).addClass("active2");
  });

  // TESTIMONIALS SLIDER
  $(".testimonials-slider").owlCarousel({
    autoPlay: false,
    items: 2,
    itemsDesktop : [1199,2],
    itemsTablet: [988,1],
    itemsTabletSmall: [520,1],
    itemsMobile : [479,1],
    navigation: true,
    navigationText: false,
    scrollPerPage : true
  });

  $('.header-search .fa').on('click', function (){
    $('.header-search').toggleClass('active');
  });

  $(function(){
    $().timelinr({
      orientation: 'horizontal',
      // value: horizontal | vertical, default to horizontal
      containerDiv: '#timeline',
      // value: any HTML tag or #id, default to #timeline
      datesDiv: '#dates',
      // value: any HTML tag or #id, default to #dates
      datesSelectedClass: 'selected',
      // value: any class, default to selected
      datesSpeed: 'normal',
      // value: integer between 100 and 1000 (recommended) or 'slow', 'normal' or 'fast'; default to normal
      issuesDiv : '#issues',
      // value: any HTML tag or #id, default to #issues
      issuesSelectedClass: 'selected',
      // value: any class, default to selected
      issuesSpeed: 'fast',
      // value: integer between 100 and 1000 (recommended) or 'slow', 'normal' or 'fast'; default to fast
      issuesTransparency: 0.2,
      // value: integer between 0 and 1 (recommended), default to 0.2
      issuesTransparencySpeed: 500,
      // value: integer between 100 and 1000 (recommended), default to 500 (normal)
      prevButton: '#prev',
      // value: any HTML tag or #id, default to #prev
      nextButton: '#next',
      // value: any HTML tag or #id, default to #next
      arrowKeys: 'false',
      // value: true/false, default to false
      startAt: 1,
      // value: integer, default to 1 (first)
      autoPlay: 'false',
      // value: true | false, default to false
      autoPlayDirection: 'forward',
      // value: forward | backward, default to forward
      autoPlayPause: 2000
      // value: integer (1000 = 1 seg), default to 2000 (2segs)< });
    });
  });

});


// Touch
// ---------------------------------------------------------
var dragging = false;

$body.on('touchmove', function() {
	dragging = true;
});

$body.on('touchstart', function() {
	dragging = false;
});



}(jQuery));
