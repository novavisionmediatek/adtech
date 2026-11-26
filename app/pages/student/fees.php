<?php
$st = my_student();
dash_header('Fees');
echo view_fees($st);
dash_footer();
