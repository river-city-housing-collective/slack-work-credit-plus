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
$slack->importSlackUsersToDb();

?>

<link type="text/css" rel="stylesheet" href="/jsgrid-1.5.3/jsgrid.min.css" />
<link type="text/css" rel="stylesheet" href="/jsgrid-1.5.3/jsgrid-theme.min.css" />
    
<script type="text/javascript" src="/jsgrid-1.5.3/jsgrid.min.js"></script>

<script>
    var clients = [
        { "Name": "Otto Clay", "Age": 25, "Country": 1, "Address": "Ap #897-1459 Quam Avenue", "Married": false },
        { "Name": "Connor Johnston", "Age": 45, "Country": 2, "Address": "Ap #370-4647 Dis Av.", "Married": true },
        { "Name": "Lacey Hess", "Age": 29, "Country": 3, "Address": "Ap #365-8835 Integer St.", "Married": false },
        { "Name": "Timothy Henson", "Age": 56, "Country": 1, "Address": "911-5143 Luctus Ave", "Married": true },
        { "Name": "Ramona Benton", "Age": 32, "Country": 3, "Address": "Ap #614-689 Vehicula Street", "Married": false }
    ];
 
    var countries = [
        { Name: "", Id: 0 },
        { Name: "United States", Id: 1 },
        { Name: "Canada", Id: 2 },
        { Name: "United Kingdom", Id: 3 }
    ];
 
    $("#jsGrid").jsGrid({
        width: "100%",
        height: "400px",
 
        inserting: true,
        editing: true,
        sorting: true,
        paging: true,
 
        data: clients,
 
        fields: [
            { name: "Name", type: "text", width: 150, validate: "required" },
            { name: "Age", type: "number", width: 50 },
            { name: "Address", type: "text", width: 200 },
            { name: "Country", type: "select", items: countries, valueField: "Id", textField: "Name" },
            { name: "Married", type: "checkbox", title: "Is Married", sorting: false },
            { type: "control" }
        ]
    });
</script>

<div id="jsGrid"></div>