<?php
require_once('../../../wp-load.php');

$teacher_id = 2;
$available_days = get_user_meta($teacher_id, 'dnd_available_days', true);

echo "Available days for teacher $teacher_id: ";
var_dump($available_days);

echo "\nAll user meta for teacher $teacher_id:\n";
$user_meta = get_user_meta($teacher_id);
foreach ($user_meta as $key => $value) {
    if (strpos($key, 'dnd') === 0) {
        echo "$key: ";
        var_dump($value[0]);
    }
}
?>