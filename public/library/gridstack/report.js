$(function() {
    var $options = $('#styleOptions'),
        $sel_el = null,
        scntDiv = $('#p_scents'),
        $elementTemplate = $('#ElementTemplate'),
        $selectGridItems = $('#selectGridItems');

    // Initialize Drag Plugin
//<![CDATA[

// Using DragResize is simple!
// You first declare a new DragResize() object, passing its own name and an object
// whose keys constitute optional parameters/settings:

    var dragresize = new DragResize('dragresize',
		{ minWidth: 50, minHeight:30, minLeft:8, minTop:8, maxLeft: 692, maxTop: 600 });

// Optional settings/properties of the DragResize object are:
//  enabled: Toggle whether the object is active.
//  handles[]: An array of drag handles to use (see the .JS file).
//  minWidth, minHeight: Minimum size to which elements are resized (in pixels).
//  minLeft, maxLeft, minTop, maxTop: Bounding box (in pixels).

// Next, you must define two functions, isElement and isHandle. These are passed
// a given DOM element, and must "return true" if the element in question is a
// draggable element or draggable handle. Here, I'm checking for the CSS classname
// of the elements, but you have have any combination of conditions you like:

    dragresize.isElement = function(elm)
    {
        if (elm.className && elm.className.indexOf('drsElement') > -1) return true;
    };
    dragresize.isHandle = function(elm)
    {
        if (elm.className && elm.className.indexOf('drsMoveHandle') > -1) return true;
    };

// You can define optional functions that are called as elements are dragged/resized.
// Some are passed true if the source event was a resize, or false if it's a drag.
// The focus/blur events are called as handles are added/removed from an object,
// and the others are called as users drag, move and release the object's handles.
// You might use these to examine the properties of the DragResize object to sync
// other page elements, etc.

    dragresize.ondragfocus = function() { };
    dragresize.ondragstart = function(isResize) { };
    dragresize.ondragmove = function(isResize) { };
    dragresize.ondragend = function(isResize) { };
    dragresize.ondragblur = function() { };

// Finally, you must apply() your DragResize object to a DOM node; all children of this
// node will then be made draggable. Here, I'm applying to the entire document.
    dragresize.apply(document);

//]]>


    // initialize color picker
    $('.demo').each( function() {
        //
        // Dear reader, it's actually very easy to initialize MiniColors. For example:
        //
        //  $(selector).minicolors();
        //
        // The way I've done it below is just for the demo, so don't get confused
        // by it. Also, data- attributes aren't supported at this time...they're
        // only used for this demo.
        //
        $(this).minicolors({
            control: $(this).attr('data-control') || 'hue',
            defaultValue: $(this).attr('data-defaultValue') || '',
            format: $(this).attr('data-format') || 'hex',
            keywords: $(this).attr('data-keywords') || '',
            inline: $(this).attr('data-inline') === 'true',
            letterCase: $(this).attr('data-letterCase') || 'lowercase',
            opacity: $(this).attr('data-opacity'),
            position: $(this).attr('data-position') || 'bottom left',
            change: function(value, opacity) {
                if( !value ) return;
                if( opacity ) value += ', ' + opacity;
                if( typeof console === 'object' ) {
                    //console.log(value);
                }
            },
            theme: 'bootstrap'
        });
    });


    // Main Fns

    // on click show options
    $('#p_scents').on('click','.drsElement',function (e) {
        e.stopPropagation();
        $sel_el = $(this);

        var styleProps = $( this ).find('*').css([
            "width", "height", "color","font-size", "background-color", "font-weight", "font-style", "text-align", "text-decoration", "style-left", "style-center", "style-right", "style-justify"
        ]);

        $.each( styleProps, function( prop1, value ) {
            //var fsize = parseInt($sel_el.find('*').css('font-size'));
            if(prop1=="font-size"){
                var fsize = value;
                fsize = fsize.replace('px', '');

                $('#fontsize').val(fsize);
            } else if(prop1=="background-color"){
                var bcolors = value;
                $('#b_color').val(bcolors).next('.minicolors-swatch').find('> .minicolors-swatch-color').css("background-color", bcolors);

            } else if(prop1=="color"){
                var bcolors = value;

                $('#t_color').val(bcolors).next('.minicolors-swatch').find('> .minicolors-swatch-color').css("background-color", bcolors);

            } else if(prop1=="font-weight"){
                if(value=="bold"){
                    $('#bold').prop( "checked", true );
                }else{
                    $('#bold').prop( "checked", false );
                }
                $('#bold').trigger('change');
            } else if(prop1=="font-style"){
                if(value=="italic"){
                    $('#italic').prop( "checked", true );
                }else{
                    $('#italic').prop( "checked", false );
                }
            } else if(prop1=="text-decoration"){
                if(value=="underline"){
                    $('#underline').prop( "checked", true );
                }else{
                    $('#underline').prop( "checked", false );
                }
            } else if(prop1=="text-align"){
                if(value=="justify"){
                    $('input:radio[name=alignment]').filter('[value=style-justify]').prop('checked', true);
                } else if(value=="right"){
                    $('input:radio[name=alignment]').filter('[value=style-right]').prop('checked', true);
                } else if(value=="center"){
                    $('input:radio[name=alignment]').filter('[value=style-center]').prop('checked', true);
                } else{
                    $('input:radio[name=alignment]').filter('[value=style-left]').prop('checked', true);
                }
            }

        });

        // show delete icon
        $('.remScnt').hide();
        $(this).find('.remScnt').show();

        $options.fadeIn();
    });

    $('body').on('click',function () {
        $('.remScnt').hide();
    });

    $('#addScnt').click( function() {
        $('<div class="drsElement drsMoveHandle" data-name="test"   style="left: 30px; top: 360px; width: 100px; height: 100px; background: #FDC; text-align: center"> Content test <button type="button" class="remScnt"><i class="fa fa-trash-o"></i></button></div>').appendTo(scntDiv);
        return false;
    });

    scntDiv.on('click', '.remScnt', function() {
        var $el = $(this).parent('.drsElement'),
            name = $el.data('name');
        $elementTemplate.append($el[0].outerHTML);
        $selectGridItems.append('<option>'+name+'</option>');
        $el.remove();
    });

    $("#fontsize").change(function() {
        $sel_el.css("font-size", $(this).val() + "px").find('*').css("font-size", $(this).val() + "px");
    });

    $("#b_color").change(function() {
        $sel_el.css("background-color", $(this).val()).find('*').css("background-color", $(this).val());
    });

    $("#t_color").change(function() {
        $sel_el.css("color", $(this).val()).find('*').css("color", $(this).val());
    });

    $('.selector').click(function () {
        var $this = $(this),
            value = $this.attr('value');

        if($this.is(':checked')) {
            $sel_el.addClass(value);
        } else {
            $sel_el.removeClass(value);
        }
    });

    $('.radio').click(function () {
        var $this = $(this);
        //value = $this.attr('value');
        var value = $("input[name='alignment']:checked").val();
        $sel_el.removeClass('style-left style-right style-center style-justify');
        $sel_el.addClass(value);
    });

    $(".exportAction").click(function(){
        $('#htmlcontent').val($('#htmlwrapper').html());
        $('form').submit();
    });
});

function addWidget() {
    var $elementTemplate = $('#ElementTemplate');
    if($elementTemplate.find('.drsElement').length == 0)
        return false;

    var $option = $('#selectGridItems').find('option:selected'),
        name = $option.val();

    if(name.length == 0)
        return false;

    var $el = $elementTemplate.find('.drsElement[data-name="'+name+'"]');

    if($el.length == 0)
        return false;

    $($el[0].outerHTML).appendTo($('#p_scents'));
    $el.remove();
    $option.remove();
}