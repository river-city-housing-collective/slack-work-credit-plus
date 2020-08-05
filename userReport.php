<style>
    table {
        width: 100%;
        border-collapse: collapse;

    }

    th, td {
        padding: 15px;
        text-align: left;
    }

    /*table, th, td {*/
    /*    border: 1px solid black;*/
    /*}*/

    table.info-table {
        width: 100%;
        margin: 0 auto;
    }

    .info-table tr td {
        border: none;
        text-align: center;
    }

    .info-table td {
        width: 50%;
        font-size: 16px;
    }
</style>
<div style="text-align: right">Current as of <?= date("Y-m-d h:i:sa") ?></div>
<div style="text-align: center">
    <img src="assets/logo.png">
</div>
<div style="text-align: center; padding-top: 50px">
    <h1>
        Work Credit Snapshot
        <br />
        for <span style="color:#085f63"><?= $data['real_name'] ?></span>
    </h1>
</div>
<hr style="width:50%;text-align:left;margin-left:0">
<div style="width: 100%;">
    <table class="info-table">
        <tr>
            <td><strong>House:</strong> <?= $additionalUserInfo['house_name'] ?></td>
            <td><strong>Room:</strong> <?= $additionalUserInfo['room_number'] ?></td>
        </tr>
        <tr>
            <td><strong>Email:</strong> <?= $additionalUserInfo['email'] ?></td>
            <td><strong>Phone:</strong> <?= $additionalUserInfo['phone'] ?></td>
        </tr>
    </table>
</div>
<hr style="width:50%;text-align:left;margin-left:0">
<div style="width: 100%;">
    <table style="width: 100%; font-size: 16px; font-weight: bold">
        <?php foreach ($user['hoursData'] as $hours): ?>
        <tr style="background-color: <?= $hours['color'] ?>; border-bottom: 1px">
            <td style="padding-left:100px; width:50%;"><?= $hours['label'] . " Hours: " ?></td>
            <td style="width:50%; text-align: center"><?= $hours['diff'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<hr style="width:50%;text-align:left;margin-left:0">
<?php if (isset($additionalUserInfo['lease_termination_date'])): ?>
    <div style="text-align: center">
        <h2 style="color:#ff5959; font-weight:bold;">
            Lease terminated on <?= $additionalUserInfo['lease_termination_date'] ?>
        </h2>
        <?php if (!$additionalUserInfo['is_deleted']): ?>
            <h3>If this person is exiting the community, you should also disable their Slack account ASAP.</h3>
        <?php endif ?>
    </div>
<?php endif ?>