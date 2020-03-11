<?php
// page is only accessible if authorized via slack
// $slack is available for additional API calls
require_once('../auth.php');
include('../includes.php');

//if not admin, die
if (!$slack->admin) {
    die('you do not have permission to access this page');
}

// wip work credit table
$sql = "
    select
        u.real_name as 'Name',
        h.name as 'House',
        r.room as 'Room'
    from sl_users as u
    left join sl_houses as h on h.id = u.house_id
    left join sl_rooms as r on r.id = u.room_id
    where u.deleted = 0
";
$result = $slack->conn->query($sql);
$tableData = array();

while ($row = $result->fetch_assoc()) {
    $tableData[] = $row;
}

// todo make button
// $slack->importSlackUsersToDb();

?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.css">
  
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.js"></script>

<script>
    var data = <?= json_encode($tableData) ?>;

    $(document).ready( function () {
        $('#table').DataTable({
            data: data,
            columns: [
                {data: 'Name'},
                {data: 'Room'},
                {data: 'House'}
            ]
        });
    } );
</script>

<table id='table'></table>