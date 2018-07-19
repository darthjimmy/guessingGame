
var newAccount = false;

function loggedIn(user)
{
	$("#msg").text("Hello " + user);
	$("#login").hide();
	$("#game").show();
	$("#error").text("");
	$("#logout").show();
}

function loginFailed()
{
	$("#error").text("Failed to log in!");
}

function showLogin()
{
	$("#msg").text("Log in to begin!");
	$("#login").show();
	$("#game").hide();
	$("#logout").hide();
	$("#register").text("Create a new account...");
}

function showRegister()
{
	$("#error").text("");
	$("#cpass").show();
	$("#msg").text("Cancel");
	newAccount = true;
}

function getCookie()
{
	var url = "includes/mysql.php?action=chkCookie";

	var xhttp = new XMLHttpRequest();

	xhttp.onreadystatechange = function(){
		if (this.readyState == 4 && this.status == 200) {
			if (this.responseText == "not logged in")
			{
				showLogin();
			}
			else
			{
				loggedIn(this.responseText);
			}
		}
	}

	xhttp.open("POST", url, false);
	xhttp.send();
}

function validateLogin(user, pass, cpass = "")
{
	try
	{
		if (user === "")
		{
			throw "Username can't be empty!";
		}

		if (pass === "")
		{
			throw "Password can't be empty!";
		}

		var action = "login";
		if(newAccount)
		{
			if(cpass === "" || cpass != pass)
				throw "Passwords don't match!";

			action="create";
		}

		var hash = sha256.create();
		hash.update(pass);

		var success = false;
		var url = "includes/mysql.php?action=" + action;

		var xhttp = new XMLHttpRequest();

		xhttp.onreadystatechange = function(){
			if (this.readyState == 4 && this.status == 200)
			{
				var data = xhttp.responseText.split('|');

				if(data[0] == "success")
				{
					loggedIn(data[1]);
				}
				else
				{
					loginFailed();
				}
			}
		};

		xhttp.open("POST", url, false);
		xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xhttp.send("username=" + user + "&password=" + hash.hex());
	}
	catch(ex)
	{
		$("#error").text(ex);
	}
}

function logout()
{
	var url = "includes/mysql.php?action=logout";
	var xhttp = new XMLHttpRequest();

	xhttp.onreadystatechange = function(){
		if (this.readyState == 4 && this.status == 200)
		{
			location.reload();
		}
	};

	xhttp.open("POST", url, false);
	xhttp.send();
}

function send_guess(guess)
{
	var xhttp = new XMLHttpRequest();

	xhttp.onreadystatechange = function(){
		if (this.readyState == 4 && this.status == 200)
		{
			var data = xhttp.responseText.split("|");

			if(data[0] == "success")
			{
				$("#game_msg").text("You got it in " + data[1] + " guesses!");
				get_scores();
			}
			else
			{
				$("#game_msg").text(data);
			}
		}
	};

	var url = "includes/mysql.php?action=guess";
	xhttp.open("POST", url, false);
	xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xhttp.send("guess=" + guess);
}

function get_scores()
{
	var xhttp = new XMLHttpRequest();

	xhttp.onreadystatechange = function(){
		if (this.readyState == 4 && this.status == 200)
		{
			var data = xhttp.responseText;

			$("#msg").text("High Scores:");
			$("#scores").html(data);
		}
	}

	var url = "includes/mysql.php?action=getScores";
	xhttp.open("POST", url, false);
	xhttp.send();
}