<?php
require 'c:/xampp/htdocs/veeru/config/db.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['teacher_id'] = 1;
$_POST['class_id'] = 1;
$_POST['update_type'] = 'worksheet';
$_POST['title'] = 'New Worksheet: Test';
$_POST['message'] = 'Please complete it.';
$_POST['payload'] = json_encode(['type' => 'worksheet_data', 'data' => 'test']);
include 'c:/xampp/htdocs/veeru/backend/api/teacher/upload_class_material.php';
