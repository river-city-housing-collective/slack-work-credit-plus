<script>
    let oauthUrl = "https://slack.com/oauth/authorize?scope=identity.basic,identity.email,identity.team,identity.avatar&client_id=787965675794.822220955957&state=<?= $redirect ?>";

    $(document).ready(function() {
        $('.login > button').click(function() {
            window.location.href = oauthUrl;
        })
    })
</script>

<div class="jumbotron">
    <h1 class="display-4">RCHC Member Portal</h1>
    <p class="lead">You are not currently signed in.</p>
    <hr class="my-4">
    <p class="lead">
        <div class="login">
            <button type="button" class="btn btn-primary btn-lg"><i class="fab fa-slack"></i> Sign in with Slack</button>
        </div>
    </p>
</div>