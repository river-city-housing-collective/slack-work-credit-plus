<?php if ($slack->config['DEBUG_MODE'] == 1) : ?>
<div class="alert alert-danger" role="alert" style="text-align: center">
    <strong>DEBUG MODE ENABLED!</strong> Don't trust anything here to be accurate or functional :)
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
<script
  src="https://code.jquery.com/jquery-3.4.1.min.js"
  integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
  crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>

<script src="https://kit.fontawesome.com/b1d2b46db4.js" crossorigin="anonymous"></script>

<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<link rel="stylesheet" href="/style.css">

<?php if ($slack->userInfo): ?>
<script src="/slack.js"></script>

<script>
    let userInfo = <?= json_encode($slack->userInfo); ?>
</script>

<div id="loggedInDisplay" style="display: none">
    <div class="alert" role="alert">
        <div id="avatar">
            <img>
        </div>
        <ul id="userInfoDiv">
            <li class="small">Logged in as:</li>
            <li class="user-display-name"></li>
            <li class="house-display-name"></li>
        </ul>
        <div id="logoutButtonDiv">
            <button type="button" id="logout" class="btn btn-info btn-sm">Logout</button>
        </div>
    </div>
</div>
<?php endif; ?>