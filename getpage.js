function getPage(pageid) {
		var newtext = pageid + ',';
		alert("got " + pageid);
		document.getElementById("pages").value += newtext;
	}
