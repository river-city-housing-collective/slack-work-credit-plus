<?php
// page is only accessible if authorized via slack
// $slack is available for additional API calls
require_once('auth.php');
?>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>

<script src="slack.js"></script>
<link rel="stylesheet" href="style.css">

<script>
    // get entire slack object - DEBUG ONLY
    let slack = <?= json_encode($slack); ?>

    let userInfo = <?= json_encode($slack->userInfo); ?>
</script>

<div id="loggedInDisplay">
    <div class="alert alert-info" role="alert">
        <img id="avatar">
        <ul id="userInfoDiv">
            <li id="username"></li>
            <li id="house">Resident @ Summit</li>
        </ul>
        <div id="logoutButtonDiv">
            <button type="button" class="btn btn-info">Logout</button>
        </div>
    </div>
</div>

<p>Welcome to the RCHC Member Portal!</p>

<a href="https://rchc.coop/">Logout</a>

<form method="get">
    <input type="hidden" name="logout" value=1>
    <input type="submit">
</form>