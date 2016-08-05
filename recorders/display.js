            // For version detection, set to min. required Flash Player version, or 0 (or 0.0.0), for no version detection.
            var swfVersionStr = "10.2.0";
            // To use express install, set to playerProductInstall.swf, otherwise the empty string.
            var xiSwfUrlStr = "playerProductInstall.swf";
            var flashvars = {};
            var params = {};
            params.quality = "high";
            params.bgcolor = "#ffffff";
            params.allowscriptaccess = "sameDomain";
            params.allowfullscreen = "true";
            var attributes = {};
            attributes.id = "LanguageLessonRecorder";
            attributes.name = "LanguageLessonRecorder";
            attributes.align = "middle";
            swfobject.embedSWF(
                "LanguageLessonRecorder.swf", "flashContent",
                "800", "140",
                swfVersionStr, xiSwfUrlStr,
                flashvars, params, attributes);
            // JavaScript enabled so display the flashContent div in case it is not replaced with a swf object.
            recorders.swfobject.createCSS("#flashContent", "display:block;text-align:left;");

    //Our global lessonid
    var lessonid;

    //From http://jquery-howto.blogspot.com.br/2009/09/get-url-parameters-values-with-jquery.html
    //Get URL values passed by php
    $.extend({
	  getUrlVars: function(){
	    var vars = [], hash;
	    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
	    for(var i = 0; i < hashes.length; i++)
	    {
	      hash = hashes[i].split('=');
	      vars.push(hash[0]);
	      vars[hash[0]] = hash[1];
	    }
	    return vars;
	  },
	  getUrlVar: function(name){
	    return $.getUrlVars()[name];
	  }
	});

	function micAccessStatusCallback(x){
		//Callback that indicates if the user has allowed or denied access to the microphone
		//We can add code here to tell if the user can record
	}

	function languageLessionAppletLoaded(x){
		//This is called when the Flash applet is ready

		//Our lessonid is passed in the URL
		lessonid = $.getUrlVar('lessonid');

		if (lessonid) {
			//Now get our lesson config from the server using php via JQuery/ajax

			$.getJSON('lessonconfig.php?lessonid='+lessonid+'&nocache=_'+Math.random(), function(data) {

			  //Pass lessonConfig to the applet
			  document.getElementById("LanguageLessonRecorder").loadLessonDescription(JSON.stringify(data));
			});
		}
	}

	function languageLessonUpdated(newLessonConfig, newUploadData){
		//Note: we pass newLessonConfig as a string, without parsing the json, as JS does not need to inspect it
		//The php page will parse it when it arrives there
		var updateInfo = {
			"lessonconfig": JSON.parse(newLessonConfig),
			"uploaddata": JSON.parse(newUploadData)
			};
		$.post('lessonupdate.php', updateInfo, function(data, textStatus) {
					  //data contains the JSON object
					  //textStatus contains the status: success, error, etc
					  console.log("Update function returned: " + data);
					}, "json");
	}

	function error(x){
		console.log("error: " + x);
	}

	function info(x){
		console.log("info: " + x);
	}