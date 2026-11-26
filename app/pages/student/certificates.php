<?php
$st = my_student();
dash_header('Certificates');
echo view_certificates($st);
dash_footer();
