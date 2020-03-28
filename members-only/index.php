<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/functions.php');

// page is only accessible if authorized via slack
// $slack is available for additional API calls
$slack = signInWithSlack($conn);

?>
<script>let slack = <?= json_encode($slack) ?></script>

<div class="jumbotron">
    <h1 class="display-4">Welcome, <span class="user-display-name"></span>!</h1>
    <p class="lead">This is the RCHC Member Portal.</p>
    <hr class="my-4">
    <p class="lead">
        <a class="btn btn-primary btn-lg" href="/members-only/work-credit" role="button">Work Credit Report</a>
    </p>
    <?php if ($slack->admin): ?>
        <p class="lead">
            <a class="btn btn-warning btn-lg" href="/members-only/admin.php" role="button">Admin Tools</a>
        </p>
    <?php endif; ?>
</div>