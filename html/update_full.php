<?php
include "../db/conn.php";

if($_SERVER['REQUEST_METHOD']=='POST'){
    $farmer_id = $_POST['farmer_id'];

    // Fetch old data for logging
    $old = $conn->query("SELECT * FROM registration_form WHERE id=$farmer_id")->fetch_assoc();

    // Prepare new data
    $f_name = $_POST['f_name'];
    $s_name = $_POST['s_name'];
    $m_name = $_POST['m_name'];
    $mobile = $_POST['mobile'];
    $brgy = $_POST['brgy'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $total_farmarea = $_POST['total_farmarea'];
    $status = $_POST['status'];

    $for_farmer = $_POST['for_farmer'];
    $for_farmerworker = $_POST['for_farmerworker'];
    $for_fisherfolk = $_POST['for_fisherfolk'];
    $for_agri = $_POST['for_agri'];

    // Update query
    $stmt = $conn->prepare("UPDATE registration_form SET f_name=?, s_name=?, m_name=?, mobile=?, brgy=?, dob=?, gender=?, age=?, total_farmarea=?, status=?, for_farmer=?, for_farmerworker=?, for_fisherfolk=?, for_agri=? WHERE id=?");
    $stmt->bind_param("sssssssiisssssi",$f_name,$s_name,$m_name,$mobile,$brgy,$dob,$gender,$age,$total_farmarea,$status,$for_farmer,$for_farmerworker,$for_fisherfolk,$for_agri,$farmer_id);
    $stmt->execute();

    // Log activity
    $conn->query("INSERT INTO farmer_activity_logs (farmer_id, action, old_data, new_data, updated_by) 
        VALUES ($farmer_id, 'Full Update','".json_encode($old)."','".json_encode($_POST)."','admin')");

    header("Location: datalist.php");
}
