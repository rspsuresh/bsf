function isNumberKey(evt){
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (evt.key == "%") charCode=0;
    if (evt.keyCode ==46 && evt.which==0) charCode=8;
    if (charCode == 0)
        return false;
    if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode !=37 && charCode!=39)
        return false;
    return true;
}
function emailValidation(emailaddress)	{
	 var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;  
	 if(!emailReg.test(emailaddress)) 
		$("#emailspan").html('<font color="#cc0000">Please enter valid Email address</font>');  
	 else
		$("#emailspan").html('<font color="#cc0000"></font>');  
}
function panValidation(pannumber)	{
	 var panReg =/^([a-zA-Z]){5}([0-9]){4}([a-zA-Z]){1}?$/;  
	 if(!panReg.test(pannumber)) 
		$("#panspan").html('<font color="#cc0000">Please enter valid Pan Number</font>');  
	 else
		$("#panspan").html('<font color="#cc0000"></font>');  
}
function alphaNumeric(e) {
    var k;
    document.all ? k = e.keyCode : k = e.which;
    return ((k > 64 && k < 91) || (k > 96 && k < 123) || k == 8 || k == 32 || (k >= 48 && k <= 57 || k == 0));
}
function panTypeValidation(pan){
	var panReg =/^([a-zA-Z]){5}([0-9]){4}([a-zA-Z]){1}?$/;  
	if(!panReg.test(pan)){
		$("#panspan").html('<font color="#cc0000">Please enter valid Pan Number</font>');
		$("#pantype").val('');
		return false;
	} else {
        var  sval= pan[3];
        if(sval == "c") {
        var panType = "Company";
        }	else if(sval == "p") {
                panType = "Person";
        }	else if(sval == "h") {
                panType = "Hindu Undivided Family";
        }	else if(sval == "f") {
                panType = "Firm";
        }	else if(sval == "a") {
                panType = "Association of Persons";
        }	else if(sval == "t") {
                panType = "Trust";
        }	else if(sval == "b") {
                panType = "Body of Individuals";
        }	else if(sval == "l") {
                panType = "Local Authority";
        }	else if(sval == "j") {
                panType = "Artificial Juridical Person";
        }	else if(sval == "g") {
                panType = "Government";
        }  else {
                panType = "Other";
        }
        $("#panspan").html('<font color="#cc0000"></font>');
        $("#pantype").val(panType);
    }
}
function isDecimal(evt, element) {
	var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (evt.keyCode ==46 && evt.which==0) charCode=8;
    if (evt.key =="%") charCode=0;
    if (evt.key =="#") charCode=0;
    if (
		(charCode != 46 || $(element).val().indexOf('.') != -1) &&      // �.� CHECK DOT, AND ONLY ONE.
        (charCode < 48 || charCode > 57) && charCode!=39 && charCode!=37 && charCode!=8 && charCode!=35 && charCode!=9)
		return false;
	return true;
}

function isPercentage(x) {
    var $x = $(x);
    if($x.val() > 100.00) {
        showError($x, "Percentage Cannot be more than 100%");
    } else {
        removeError($x);
    }
}
    function FormatNum(x,digit,type) {
        if (typeof digit == 'undefined')
            digit = 2;
        var obj = $(x);
        var value = parseFloatVal(obj.val());
        if (typeof type != 'undefined' && type) {
            value = sanitizeNumber(value, digit, true);
        } else {
            value = value.toFixed(digit);
        }
        obj.val(value);
    }
function isNullCheck(obj,datatype) {
    if (datatype =='number') {
        if (typeof(obj) != "undefined" && obj!=null) obj = obj.toString().replace(/\,/g, '');
        if (typeof(obj) == "undefined" || obj==null || isNaN(obj) || $.isNumeric(obj)==false) value =0;
        else value = obj;
        //obj = obj.toString().replace(/\,/g, '');
    } else if (datatype=='string') {
        if (typeof(obj) == "undefined" || obj==null) value ='';
        else value =obj;
    } else if (datatype=='boolean') {
        if (typeof(obj) == "undefined" || obj==null) value =false;
        else value =obj;
    } else if (datatype=='date') {
        if (typeof(obj) == "undefined" || obj==null) {
           var curdate = new Date();
           value = curdate.getDate() + '/' + (curdate.getMonth() + 1) + '/'+curdate.getFullYear();
        } else value =obj;
    }
    return value;
}
function clearInputFile(file){
    var f = file[0];
    if(f.value){
        try{
            f.value = '';
        }catch(err){ }
        if(f.value){
            var form = document.createElement('form'),
                parentNode = f.parentNode, ref = f.nextSibling;
            form.appendChild(f);
            form.reset();
            parentNode.insertBefore(f,ref);
        }
    }
}
function validateNumberOnly(value) {
    return /^[0-9]+$/.test(value)
}
function showError($input, msg) {
    $input.addClass('error').attr('data-original-title', msg).attr('data-toggle', 'tooltip');
    $input.tooltip('show');
}
function removeError($input) {
    if (typeof $input.attr('data-toggle') !== 'undefined') {
        $input.tooltip('destroy');
    }
    $input.removeClass('error').removeAttr('data-original-title').removeAttr('data-toggle');
}
function validateEmail(email) {
	var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
	if(!emailReg.test(email))
		return false;
	return true;
}
function isDate(evt) {
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if(charCode == 45) //allow dash
        return true;

    if (charCode > 31 && (charCode < 48 || charCode > 57)) // allow only numbers
        return false;
    return true;
}
function validateDate(x) {
    var $el = $(x);
    if(!(/^[0-9]{2}\-(1[0-2]|0[1-9])\-[0-9]{4}$/.test($el.val())))
        showError($el, 'Invalid date');
    else
        removeError($el);
}
function validateEmailInput(x) {
    var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/,
        $x = $(x);
    if(!emailReg.test($x.val()))
        showError($x, 'Invalid Email');
    else
        removeError($x);
}

function validateURL(x){
    // URL validation from http://stackoverflow.com/questions/3809401/what-is-a-good-regular-expression-to-match-a-url
    var expression = /[-a-zA-Z0-9@:%_\+.~#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&//=]*)?/gi,
        $x = $(x),
        value = $x.val();
    var regex = new RegExp(expression);
    if (!value.match(regex)) showError($x, 'Invalid Email');
    else removeError($x);
}


function validateMobileInput(x) {
    var mobileReg = /^\+?[0-9\-]{10,20}$/,
        $x = $(x),
        val = $x.val();
    if(val == "")
        return;

    if(!mobileReg.test(val))
        showError($x, 'Invalid Mobile');
    else
        removeError($x);
}
// date format dd-mm-yyyy, dd/mm/yyyy
function checkDateRange(FromDate, ToDate) {
    var d1 = new Date(FromDate.substring(3,5) + '-' + FromDate.substring(0,2) + '-' + FromDate.substring(6,10));
    var d2 = new Date(ToDate.substring(3,5) + '-' + ToDate.substring(0,2) + '-' + ToDate.substring(6,10));

    if(d1 > d2)
        return false;

    return true;
}
function parseFloatVal(v) {
    if(v == null)
        v = '';

    if(typeof v == 'string')
        v=v.replace(/[^0-9-.]/g, '');

    v = parseFloat(v);
    if (isNaN(v))
        v = 0;
    return v;
}
function parseIntVal(v) {
    if(v == null)
        v = '';

    v = parseInt(v);
    if (isNaN(v))
        v = 0;
    return v;
}

function sanitizeNumberZeroEmpty(obj, digit, type, emptyStr) {
    if(typeof digit == 'undefined')
        digit = 2;

    obj = parseFloat(obj);
    if(typeof type != 'undefined' && type && typeof emptyStr == 'undefined' && (isNaN(obj) || obj == 0)) {
        obj = 0;
    } else if((typeof type == 'undefined' || typeof emptyStr != 'undefined') && (isNaN(obj) || obj == 0)) {
        obj = '';
    } else {
        obj = obj.toFixed(digit);

        // currency conversion
        if(typeof type != 'undefined' && type) {
            var x=obj.toString();
            var afterPoint = '';
            if(x.indexOf('.') > 0)
                afterPoint = x.substring(x.indexOf('.'),x.length);
            x = Math.floor(x);
            x=x.toString();
            var lastThree = x.substring(x.length-3);
            var otherNumbers = x.substring(0,x.length-3);
            if(otherNumbers != '')
                lastThree = ',' + lastThree;
            obj = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree + afterPoint;
        }
    }
    if (obj==0) obj="";
    return obj;
}

function sanitizeNumber(obj, digit, type, emptyStr) {
    if(typeof digit == 'undefined')
        digit = 2;

    obj = parseFloat(obj);
    if(typeof type != 'undefined' && type && typeof emptyStr == 'undefined' && (isNaN(obj) || obj == 0)) {
        obj = 0;
    } else if((typeof type == 'undefined' || typeof emptyStr != 'undefined') && (isNaN(obj) || obj == 0)) {
        obj = '';
    } else {
        obj = obj.toFixed(digit);

		// currency conversion
		if(typeof type != 'undefined' && type) {
			var x=obj.toString();
			var afterPoint = '';
			if(x.indexOf('.') > 0)
			   afterPoint = x.substring(x.indexOf('.'),x.length);
			x = Math.floor(x);
			x=x.toString();
			var lastThree = x.substring(x.length-3);
			var otherNumbers = x.substring(0,x.length-3);
			if(otherNumbers != '')
				lastThree = ',' + lastThree;
			obj = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree + afterPoint;
		}
	}

    return obj;
}

function numberFormat(obj,type,digit) {
    if(typeof digit == 'undefined')
        digit = 2;
    if (type.toUpperCase() =="C") { digit = 2;}
    else if (type.toUpperCase() =="Q") { digit = 3;}

    obj = parseFloat(obj);
    if(isNaN(obj) || obj == 0)
        obj = '';
    else
        obj = obj.toFixed(digit);

    return obj;
}

function ucwords(str) {
    return str.toLowerCase().replace(/\b[a-z]/g, function(letter) {
        return letter.toUpperCase();
    });
}

function getJsonArrayIndex(arr, field, value) {
   return arr.map(function(d) { return d[field]; }).indexOf(value);
}

function validatePanNumber(pan) {
	var panReg = /^([a-zA-Z]){5}([0-9]){4}([a-zA-Z]){1}?$/;
	if(!panReg.test(pan))
		return false;
	return true;
}

function validateTanNumber(tan) {
	var tanReg = /^([a-zA-Z]){4}([0-9]){5}([a-zA-Z]){1}?$/;
	if(!tanReg.test(tan))
		return false;
	return true;
}

function isFormula(evt){
    var charCode = (evt.which) ? evt.which : evt.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode !=43 && charCode!=45 && charCode!=47 && charCode!=42 && charCode!=46 && charCode!=40 && charCode!=41 && charCode!=82 && charCode!=37 && charCode!=39 && charCode!=35 && charCode!=36) return false;
    return true;
}
function computeEval(formula) {
    with (document.forms){
        with (Math) {
            A = eval((formula));
        }
    }
    return A;
}

function isSeparator(evt){
    var charCode = (evt.which) ? evt.which : evt.keyCode
    if (charCode !=8 && charCode !=45  && charCode !=95 && charCode !=94 && charCode !=46 && charCode != 47 && charCode !=92 && charCode !=33 && charCode!=64 && charCode!=35 && charCode!=36 && charCode!=37 && charCode!=99 && charCode!=39 && charCode!=38 && charCode!=42) return false;
    return true;
}


// date format yyyy,mm,dd
function compareDate(FromDate, ToDate) {
    var d1 = new Date(FromDate.substring(6,10) + ',' + FromDate.substring(3,5) + ',' + FromDate.substring(0,2));
    var d2 = new Date(ToDate.substring(6,10) + ',' + ToDate.substring(3,5) + ',' + ToDate.substring(0,2));

    if(d1 > d2)
        return false;

    return true;
}
