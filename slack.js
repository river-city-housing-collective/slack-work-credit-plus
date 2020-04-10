$(function () {
    // set user info in top right
    if (userInfo) {
        $('.user-display-name').html(userInfo.display_name ? userInfo.display_name : userInfo.real_name);
        $('#userInfoDiv .house-display-name').html(userInfo.type + (userInfo.house ? ' @ ' + userInfo.house : ''));
        $('#avatar > img').attr('src', userInfo.avatar);

        $('#loggedInDisplay').show();

        let alertClass = '';

        switch(userInfo.house) { //todo generalize
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
    });

    $('#logout').click(function() {
        window.location = "/members-only/logout.php";
    });
});

function updateFormState($form, disabled, submit = false) {
    let data = false;

    if (submit) {
        data = $form.serialize();

        $form.find(".submit-label").hide();
        $form.find(".submitted-label").show();
    }
    else {
        $form.find(".submit-label").show();
        $form.find(".submitted-label").hide();
    }

    $form.find('input, select, button').prop('disabled', disabled);

    return data;
}