$(function () {
    // set user info in top right
    if (userInfo) {
        $('.user-display-name').html(userInfo.display_name ? userInfo.display_name : userInfo.real_name);
        $('#userInfoDiv .house-display-name').html(userInfo.type + (userInfo.house ? ' @ ' + userInfo.house : ''));
        $('#avatar > img').attr('src', userInfo.avatar);

        $('#loggedInDisplay').show();

        let alertClass = '';

        switch(userInfo.house) {
            case 'Anomy':
                alertClass = 'alert-danger';
                break;
            case 'Bloom':
                alertClass = 'alert-warning';
                break;
            case 'Summit':
                alertClass = 'alert-success';
                break;
        }

        $('#loggedInDisplay > .alert').addClass(alertClass);
    }

    $('.portal-back').click(function() {
        window.location = "/members-only/";
    })

    $('#logout').click(function() {
        window.location = "/members-only/logout.php";
    })
});