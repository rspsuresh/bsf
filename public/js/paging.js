(function($) {
    $(function() {
        $.widget("zpd.paging", {
            options: {
                limit: 5,
                rowDisplayStyle: 'block',
                activePage: 0,
                rows: []
            },
            _create: function() {
                thisVal = this;
                var rows = $("tbody", this.element).children();
                this.options.rows = rows;
                this.options.rowDisplayStyle = rows.css('display');
                var nav = this._getNavBar();
                $('.paging-nav').children('a[data-page=0]').trigger('click');
            },
            _getNavBar: function(val) {
                var rows = this.options.rows;
                $('#bindPageNav').remove();
                if(typeof val!="undefined") {
                    rows = val;
                    this.options.rows=val;
                }

                var l=0;
                $.each($(rows),function() {
                    if($(this).hasClass('count-tr')) {
                        l++;
                    }
                });

                var nav = $('<div>', {class: 'paging-nav'});
                var sel = '<div class="goto" style=""><label class="float_l" for="go_to">Go to</label><select class="dropdown float_l pagNavSelect" style="background-color: #ebeef4;border: 1px solid #dddddd !important;cursor: pointer;height: 30px;margin-top: 10px;padding: 0 5px;width: 60px!important;" title="Choose Page to go....">';

                for (var i = 0; i < Math.ceil(l/ this.options.limit); i++) {
                    this._on($('<a>', {
                            href: '#',
                            text: (i + 1),
                            "data-page": (i)
                        }).appendTo(nav),
                        {click: "pageClickHandler"});

                    sel+="<option value='"+(i)+"'>"+(i + 1)+"</option>";
                }
                sel+='</select></div>';
                //create previous link

                this._on($('<a>', {
                        href: '#',
                        text: '<<',
                        "alt":'previous',
                        "title":'previous',
                        "data-direction": -1
                    }).prependTo(nav),
                    {click: "pageStepHandler"});
                //create next link
                this._on($('<a>', {
                        href: '#',
                        text: '>>',
                        "alt":'next',
                        "title":'next',
                        "data-direction": +1
                    }).appendTo(nav),
                    {click: "pageStepHandler"});
				this.element.after('<div id="bindPageNav" class="col-lg-6 col-lg-offset-4"></div>');
                //this.element.after(sel);
				$('#bindPageNav').append(sel);
				$('#bindPageNav').append(nav);

            },
            showPage: function(pageNum) {
                var num = pageNum * 1; //it has to be numeric
                this.options.activePage = num;
                var rows = this.options.rows;
                var limit = this.options.limit;
                for (var i = 0; i < rows.length; i++) {
                    if (i >= limit * num && i < limit * (num + 1)) {
                        $(rows[i]).css('display', this.options.rowDisplayStyle);
                    } else {
                        if(!$(rows[i]).hasClass('dont-hide')) {
                            $(rows[i]).css('display', 'none');
                        }

                    }
                }
                //$('.pagNavSelect').find('option').attr('selected',false);
                //$('.pagNavSelect').find('option[value='+num+']').attr('selected',true);
            },
            pageClickHandler: function(event) {
                event.preventDefault();
                $('a','.paging-nav').show();
                $(event.target).siblings().attr('class', "");
                $(event.target).attr('class', "selected-page");
                var pageNum = $(event.target).attr('data-page');
                this.showPage(pageNum);
                $('a','.paging-nav').each(function() {
                    if(!this.hasAttribute('data-direction')) {
                        $(this).hide();
                    }
                });

                var nextEle = $('.selected-page','.paging-nav').nextAll('a:lt(2)');
                var prevEle = $('.selected-page','.paging-nav').prev();
                prevEle.show();
                prevEle.prev().show();

                $('.selected-page','.paging-nav').show();
                if(nextEle.length>0) {
                    $.each(nextEle,function() {
                        $(this).show();
                    });
                }
                if(prevEle.length>0) {
                    $.each(prevEle,function() {
                        $(this).show();
                    });
                }
            },
            pageStepHandler: function(event) {
                event.preventDefault();
                //get the direction and ensure it's numeric
                var dir = $(event.target).attr('data-direction') * 1;
                var pageNum = this.options.activePage + dir;
                //if we're in limit, trigger the requested pages link
                if (pageNum >= 0 && pageNum < this.options.rows.length) {
                    $("a[data-page=" + pageNum + "]", $(event.target).parent()).click();
                }
            }
        });
    });
    $(document).on('keydown',function(e) {
        var key= window.event? event.keyCode: e.keyCode;
        if(key==37) {
            e.preventDefault();
            $('.paging-nav').children('a:first').trigger('click');
        }  else if(key==39) {
            e.preventDefault();
            $('.paging-nav').children('a:last').trigger('click');

        }
    });
    $(document).on('change','.pagNavSelect',function(e) {

        $('.paging-nav').children('a[data-page='+ $(this).val()+']').trigger('click');
    });

})(jQuery);




