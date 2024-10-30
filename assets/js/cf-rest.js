window.onload = function() {
	if (document.getElementById('secret_key_btn') && document.getElementById('secret_key_btn') != null) {
		var secretKeyBtn = document.getElementById('secret_key_btn');
		secretKeyBtn.onclick = copyKeyToClipboard;
	}

	if (document.getElementById('api_path_btn') && document.getElementById('api_path_btn') != null) {
		var apiPathBtn = document.getElementById('api_path_btn');
		apiPathBtn.onclick = copyPathToClipboard;
	}
}

function copyKeyToClipboard() {
	if(document.getElementById('secret_key') && document.getElementById('secret_key') != null) {
		var secretKey = document.getElementById("secret_key");
		secretKey.select();
		document.execCommand("copy");
		alert(`Copied the API Key to clipboard: ${secretKey.value}`);
	}
}

function copyPathToClipboard() {
	var apiPath = document.getElementById("api_path");
	apiPath.select();
	document.execCommand("copy");
	alert(`Copied the API Path to clipboard: ${apiPath.value}`);
}