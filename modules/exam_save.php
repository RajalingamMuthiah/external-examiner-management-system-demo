<?php
// exam_save.php - Minimal handler for exam form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
    echo '<div style="color: green; font-weight: bold;">Exam data received successfully!</div>';
    // TODO: Insert exam data into database here
} else {
    echo '<div style="color: red; font-weight: bold;">Invalid request method.</div>';
}
?>
