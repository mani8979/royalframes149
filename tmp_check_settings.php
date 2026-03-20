<?php
$c = new mysqli('localhost', 'root', '', 'royal_frames');
if($c->connect_error) die("Connection failed: " . $c->connect_error);
$r = $c->query('SELECT * FROM site_settings');
while($row = $r->fetch_assoc()) {
    echo $row['setting_key'] . ': ' . $row['setting_value'] . PHP_EOL;
}
?>
