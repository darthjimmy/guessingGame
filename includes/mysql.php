<?php
$mysql_servername = "localhost";
$mysql_username = "web";
$mysql_password = "J2skmKKE&Ly*gXQo5ofH";
$mysql_dbname = "NumberGuessing";

$cookie_name = "username";
$cookie_game = "gameid";
$mysql_conn = new mysqli($mysql_servername, $mysql_username, $mysql_password, $mysql_dbname);

$action = $_GET["action"];

if ($action == "chkCookie")
{
    if (!isset($_COOKIE[$cookie_name]))
    {
        echo "not logged in";
        return;
    }
    else
    {
        echo $_COOKIE[$cookie_name];
        return;
    }
}

if ($action == "login")
{
    $user = $_POST["username"];
    $pass = $_POST["password"];

    try
    {
        if (isset($_COOKIE[$cookie_name]))
        {
            echo "success|" . $_COOKIE[$cookie_name];
            return;
        }

        if (mysql_login($user, $pass))
        {
            echo "success|" . $user;
            setcookie($cookie_name, $user, time() + (86400 * 30), "/");
            return;
        }
        else
        {
            echo "failure";
            return;
        }
    }
    catch (Exception $e)
    {
        echo 'Error logging in :' . $e->getMessage(). "\n";
    }
}

if ($action == "logout")
{
    if (isset($_COOKIE[$cookie_name]))
    {
        setcookie($cookie_name, "", 1, "/");
    }

    if (isset($_COOKIE[$cookie_game]))
    {
        setcookie($cookie_game, "", 1, "/");
    }

    return;
}

if ($action == "create")
{
    $user = $_POST["username"];
    $pass = $_POST["password"];

    if (create_user($user, $pass))
    {
        echo "success|" . $user;
        setcookie($cookie_name, $user, time() + (86400 * 30), "/");
        return;
    }
    else
    {
        echo "failure";
        return;
    }
}

if ($action == "guess")
{
    $user = $_COOKIE[$cookie_name];
    $guess = $_POST["guess"];

    $result = check_guess($user, $guess);

    return;
}

if ($action == "getScores")
{
    get_high_scores();
    return;
}

function mysql_login($user, $pass)
{
    global $mysql_conn;
    $stmt = $mysql_conn->prepare("SELECT username, pass FROM users WHERE username=?");
    $stmt->bind_param("s", $user);

    $stmt->execute();
    $stmt->bind_result($founduser, $foundpass);

    $found = false;

    while (mysqli_stmt_fetch($stmt))
    {
        if($founduser == $user)
        {
            $found = ($foundpass == $pass);
        }
    }

    return $found;
}

function create_user($username, $password)
{
    global $mysql_conn;
    $stmt = $mysql_conn->prepare("SELECT username FROM users WHERE username=?");
    $stmt->bind_param("s", $username);

    $stmt->execute();
    $stmt->bind_result($founduser);

    // check if the username is unique

    while (mysqli_stmt_fetch($stmt))
    {
        if($founduser == $username)
        {
            throw new Exception('User name already exists');
        }
    }

    $stmt = $mysql_conn->prepare("INSERT INTO users (username, pass) VALUES (?,?)");
    $stmt->bind_param("ss", $username, $password);

    return $stmt->execute();
}

function increment_score($gameid)
{
    global $mysql_conn;

    $stmt = $mysql_conn->prepare("SELECT s.scoreid, s.score FROM gamehistory g INNER JOIN scores s ON s.scoreid = g.scoreid WHERE g.gameid=?");

    $stmt->bind_param("d", $gameid);
    $stmt->execute();

    $stmt->bind_result($scoreid, $theScore);
    $stmt->fetch();
    $stmt->close();

    $theScore++;

    if(!($stmt = $mysql_conn->prepare("UPDATE scores SET score = ? WHERE scoreid = ?")))
    {
        echo "Error updating score: " . $mysql_conn->errno . " " . $mysql_conn->error. "\n";
    }
    $stmt->bind_param("dd", $theScore, $scoreid);
    $stmt->execute();
    $stmt->close();
}

function get_game($user)
{
    global $mysql_conn;
    global $cookie_game;

    $stmt = $mysql_conn->prepare("SELECT g.gameid, s.scoreid, u.userid FROM gamehistory g INNER JOIN scores s ON s.scoreid = g.scoreid INNER JOIN users u on u.userid = s.userid WHERE complete=false AND u.username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();

    $stmt->bind_result($gameid, $scoreid, $userid);

    $gameCount = 0;
    while ($stmt->fetch())
    {
        $gameCount++;
        setcookie($cookie_game, $gameid, time() + (86400 * 30), "/");
    }

    if ($gameCount == 0)
    {
        // need to create a new game to play
        $gameid= new_game($user);
        
        if($gameid > 0)
        {
            setcookie($cookie_game, $gameid, time() + (86400 * 30), "/");
        }
    }

    $stmt->close();
    return $gameid;
}

function new_game($user)
{
    global $mysql_conn;

    $gameid = 0;
    if($stmt = $mysql_conn->prepare("SELECT userid FROM users WHERE username=?"))
    {
        $stmt->bind_param("s", $user);
        $stmt->execute();
    }
    else
    {
        echo "failed to prepare statement: " . $mysql_conn->errno;
        return -100;
    }

    $stmt->bind_result($userid);

    $stmt->fetch();

    $stmt->close();

    $theNumber = rand(0 ,99);
    //mysqli_report(MYSQLI_REPORT_ALL);
    if(!($stmt = $mysql_conn->prepare("CALL newGame(?,?,0,@gameid)")))
    {
        echo "failed to call newGame procedure, error " . $mysql_conn->errno . ": " . $mysql_conn->error . "\n";
        return -101;
    }

    $stmt->bind_param("dd", $userid, $theNumber);
    $stmt->execute();

    if($res = $mysql_conn->query("SELECT @gameid as gameid"))
    {
        $row = $res->fetch_assoc();
        $gameid = $row["gameid"];
    }
    else
    {
        echo "Error creating game: " . $mysql_conn->errno . ": " . $mysql_conn->error . "\n";
    }

    $stmt->close();
    return $gameid;
}

function get_score($gameid)
{
    global $mysql_conn;

    $stmt = $mysql_conn->prepare("SELECT s.score FROM gamehistory g INNER JOIN scores s ON s.scoreid = g.scoreid WHERE g.gameid=?");
    $stmt->bind_param("d", $gameid);

    $stmt->execute();
    $stmt->bind_result($curScore);
    $stmt->fetch();

    $stmt->close();
    return $curScore;
}

function check_guess($user, $guess)
{
    global $mysql_conn;
    global $cookie_game;

    $gameid = get_game($user);

    increment_score($gameid);

    if($gameid < 0)
        return -100;

    $stmt = $mysql_conn->prepare("SELECT theNumber FROM gamehistory WHERE gameid=?");
    $stmt->bind_param("d",$gameid);

    $stmt->execute();
    $stmt->bind_result($theNumber);

    $stmt->fetch();

    $stmt->close();
    //echo "Guess: " . $guess . " Number: " . $theNumber . "\n";
    if($guess > $theNumber)
    {   
        echo "Too High!";
        return 1;
    }
    else if ($guess < $theNumber)
    {
        echo "Too Low!";
        return -1;
    }
    else if ($guess == $theNumber)
    {
        complete_game($gameid);
        echo "success|" . get_score($gameid);
        return 0;
    }

    return -2;
}

function complete_game($gameid)
{
    global $mysql_conn;
    global $cookie_game;

    $stmt = $mysql_conn->prepare("UPDATE gamehistory SET complete=1 WHERE gameid=?");
    $stmt->bind_param("d", $gameid);

    $stmt->execute();

    setcookie($cookie_game, '', 1, "/");
}

function get_high_scores()
{
    global $mysql_conn;

    $stmt = $mysql_conn->prepare("SELECT s.score, u.username FROM scores s INNER JOIN users u ON u.userid = s.userid ORDER BY s.score ASC LIMIT 10");
    $stmt->execute();

    $stmt->bind_result($theScore, $theUser);

    echo "<table>";
    echo "<tr>";
    echo "  <th>User</th>";
    echo "  <th>Score</th>";
    echo "</tr>";
    while($stmt->fetch())
    {
        echo "<tr>";
        echo "  <td>" . $theUser . "</td>";
        echo "  <td>" . $theScore . "</td>";
        echo "<tr>";
    }
    echo "</table>";
}
?>