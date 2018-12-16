/**
 * stepsForm.js v1.0.0
 * http://www.codrops.com
 *
 * Licensed under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 *
 * Copyright 2014, Codrops
 * http://www.codrops.com
 */
;( function( window ) {

	'use strict';

	var transEndEventNames = {
			'WebkitTransition': 'webkitTransitionEnd',
			'MozTransition': 'transitionend',
			'OTransition': 'oTransitionEnd',
			'msTransition': 'MSTransitionEnd',
			'transition': 'transitionend'
		},
		transEndEventName = transEndEventNames[ Modernizr.prefixed( 'transition' ) ],
		support = { transitions : Modernizr.csstransitions };

	function extend( a, b ) {
		for( var key in b ) {
			if( b.hasOwnProperty( key ) ) {
				a[key] = b[key];
			}
		}
		return a;
	}

	function stepsForm( el, options ) {
		this.el = el;
		this.options = extend( {}, this.options );
  		extend( this.options, options );
  		this._init();
	}

	stepsForm.prototype.options = {
		onSubmit : function() { return false; }
	};

	stepsForm.prototype._init = function() {
		// current question
		this.current = 0;

		// questions
		this.questions = [].slice.call( this.el.querySelectorAll( 'ul.questions > li' ) );
		// total questions
		this.questionsCount = this.questions.length;
		// show first question
		classie.addClass( this.questions[0], 'current' );

		// next question control
		this.ctrlNext = this.el.querySelector( 'button.next' );

		// progress bar
		this.progress = this.el.querySelector( 'div.progress' );

		// question number status
		this.questionStatus = this.el.querySelector( 'span.number' );
		// current question placeholder
		this.currentNum = this.questionStatus.querySelector( 'span.number-current' );
		this.currentNum.innerHTML = Number( this.current + 1 );
		// total questions placeholder
		this.totalQuestionNum = this.questionStatus.querySelector( 'span.number-total' );
		this.totalQuestionNum.innerHTML = this.questionsCount;

		// error message
		this.error = this.el.querySelector( 'span.error-message' );

		// init events
		this._initEvents();
	};

	stepsForm.prototype._initEvents = function() {
		var self = this,
            // first select2
            firstElSelect2 = this.questions[ this.current ].querySelector( '.select2' ),
			// first input
			firstElInput = this.questions[ this.current ].querySelector( 'input,select' ),
			// focus
			onFocusStartFn = function() {
				firstElInput.removeEventListener( 'focus', onFocusStartFn );
				classie.addClass( self.ctrlNext, 'show' );
            },
            // click
            onClickStartFn = function() {
                firstElSelect2.removeEventListener( 'click', onClickStartFn );
                classie.addClass( self.ctrlNext, 'show' );
            };

		// show the next question control first time the input gets focused
        if(firstElInput != null)
		    firstElInput.addEventListener( 'focus', onFocusStartFn );

        // show the next question control first time the input clicked
        if(firstElSelect2 != null)
            firstElSelect2.addEventListener( 'click', onClickStartFn );

		// show next question
		this.ctrlNext.addEventListener( 'click', function( ev ) {
			ev.preventDefault();
			self._nextQuestion();
		} );

		// pressing enter will jump to next question
		document.addEventListener( 'keydown', function( ev ) {
			var keyCode = ev.keyCode || ev.which;
			// enter
			if( keyCode === 13 ) {
                $('.select2-container--open').hide();
				ev.preventDefault();
				self._nextQuestion();
			}
		} );

		// disable tab
		this.el.addEventListener( 'keydown', function( ev ) {
			var keyCode = ev.keyCode || ev.which;
			// tab
			if( keyCode === 9 ) {
				ev.preventDefault();
			}
		} );
	};

	stepsForm.prototype._nextQuestion = function() {
		if( !this._validade() ) {
			return false;
		}

		// check if form is filled
		if( this.current === this.questionsCount - 1 ) {
			this.isFilled = true;
		}

		// clear any previous error messages
		this._clearError();

		// current question
		var currentQuestion = this.questions[ this.current ];

		// increment current question iterator
		++this.current;

		// update progress bar
		this._progress();

		if( !this.isFilled ) {
			// change the current question number/status
			this._updateQuestionNumber();

			// add class "show-next" to form element (start animations)
			classie.addClass( this.el, 'show-next' );

			// remove class "current" from current question and add it to the next one
			// current question
			var nextQuestion = this.questions[ this.current ];
			classie.removeClass( currentQuestion, 'current' );
			classie.addClass( nextQuestion, 'current' );
		}

		// after animation ends, remove class "show-next" from form element and change current question placeholder
		var self = this,
			onEndTransitionFn = function( ev ) {
				if( support.transitions ) {
					this.removeEventListener( transEndEventName, onEndTransitionFn );
				}
				if( self.isFilled ) {
					self._submit();
				}
				else {
					classie.removeClass( self.el, 'show-next' );
					self.currentNum.innerHTML = self.nextQuestionNum.innerHTML;
					self.questionStatus.removeChild( self.nextQuestionNum );
					// force the focus on the next input
					var inputs = nextQuestion.querySelector( 'input,select' );
                    if(inputs != null)
                        inputs.focus();

                    // force the click on the next input
                    var select2 = nextQuestion.querySelector( '.select2' );
                    if(select2 != null)
                        select2.click();
				}
			};

		if( support.transitions ) {
			this.progress.addEventListener( transEndEventName, onEndTransitionFn );
		}
		else {
			onEndTransitionFn();
		}
	};

	// updates the progress bar by setting its width
	stepsForm.prototype._progress = function() {
		this.progress.style.width = this.current * ( 100 / this.questionsCount ) + '%';
	};

	// changes the current question number
	stepsForm.prototype._updateQuestionNumber = function() {
		// first, create next question number placeholder
		this.nextQuestionNum = document.createElement( 'span' );
		this.nextQuestionNum.className = 'number-next';
		this.nextQuestionNum.innerHTML = Number( this.current + 1 );
		// insert it in the DOM
		this.questionStatus.appendChild( this.nextQuestionNum );
	};

	// submits the form
	stepsForm.prototype._submit = function() {
		this.options.onSubmit( this.el );
	};

	// TODO (next version..)
	// the validation function
	stepsForm.prototype._validade = function() {
		// current question´s input
        var dataType= this.questions[ this.current ].querySelector( 'input,select' );
        if (dataType.hasAttribute('data-validate')) {
            switch (dataType.getAttribute('data-validate')) {
                	case 'novalidate' :
                        // there's no validation in this field, moving on
                        break;
                    case 'email' :
                        if (!this._validateEmail(dataType.value)) {
                                    this._showError( 'INVALIDEMAIL' );
                                    return false;
                        }
                        break;
                    case 'website' :
                        if ($.trim(dataType.value).substring(0,4)=='www.'){ $('#'+dataType.id).val('http://www.'+$.trim(dataType.value).substring(4));}
                        var re = /(http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&:\/~+#-]*[\w@?^=%&\/~+#-])?/;
                        var is_url=re.test($('#'+dataType.id).val());
                        if(is_url==false) {
                            this._showError( 'INVALIDWEBSITE' );
                            return false;
                        }
                        break;
                    case 'usercheck' :
                        var uName = $.trim(dataType.value);
                        if(uName=="") {
                            this._showError( 'EMPTYSTR' );
                            return false;
                        } else {
                            var cFlag = 0;
                            $.ajax({
                                url:getBaseURL()+'workflow/index/user-entry',
                                type:'POST',
                                data:{"userName" : uName,"uMode" : "userCheck","uId" : parseInt($('#userid').val())},
                                async:false,
                                success:function(data, textStatus, jqXHR){
                                    if (jqXHR.status != 200) {
                                        cFlag=1;
                                    } else {
                                        if(data=="fail") {
                                            cFlag=1;
                                        }
                                    }
                                },
                                error:function(jqXHR, textStatus, errorThrown){
                                    cFlag=1;
                                }
                            });

                            if(cFlag==1) {
                                this._showError( 'USERFALSE' );
                                return false;
                            }


                         }
                    break;
            }
        } else {
            if( dataType.value === '' ) {
                        this._showError( 'EMPTYSTR' );
                        return false;
            }
        }

		return true;
	};

	// TODO (next version..)
	stepsForm.prototype._showError = function( err ) {
		var message = '';
		switch( err ) {
			case 'EMPTYSTR' :
				message = 'Please fill the field before continuing';
				break;
			case 'INVALIDEMAIL' :
				message = 'Please fill a valid email address';
				break;
            case 'INVALIDWEBSITE' :
                message = 'Please fill a valid Website Url';
                break;
            case 'USERFALSE' :
                message = 'User Name Already Exist..!';
                break;
			// ...
		}
		this.error.innerHTML = message;
		classie.addClass( this.error, 'show' );
	};

	// clears/hides the current error message
	stepsForm.prototype._clearError = function() {
		classie.removeClass( this.error, 'show' );
	};

    stepsForm.prototype._validateEmail = function( email ) {
        var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        return regex.test(email);
    };
	// add to global namespace
	window.stepsForm = stepsForm;

})( window );