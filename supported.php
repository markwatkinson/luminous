<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // standalone install
    require_once(__DIR__ . '/vendor/autoload.php');
} elseif (file_exists(__DIR__ . '/../../autoload.php')) {
    // dep install
    require_once(__DIR__ . '/../../autoload.php');
} else {
    die('Please install the Composer autoloader by running `composer install` from within ' . __DIR__ . PHP_EOL);
}
?><!DOCTYPE html>
<html>
  <head>
    <style>
body {
  font-family: sans-serif;
}
table {
  border-collapse: collapse;
}
td {
 border: 1px solid gray;
 padding: 0 1em;
}
th {
  border-bottom: 1px solid black;
  text-align:center;
  font-weight:bold;
}

    </style>
  </head>
<body>
<table style='margin-left:auto; margin-right: auto;'>
    <thead>
        <tr>
            <th style='border: 0px'></th>
            <th>Language</th>
            <th>Valid Codes</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $i = 0;
        foreach (Luminous::scanners() as $l => $codes): ?>
            <tr>
                <td><?php echo ++$i; ?></td>
                <td><?php echo $l; ?></td>
                <td><?php echo join(', ', $codes); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
