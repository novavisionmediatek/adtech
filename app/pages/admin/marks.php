<?php
$paper = paper_row((int)inp('paper'), sid());
if (!$paper) render_error(404, 'Paper not found', 'This exam paper does not exist.');
marks_page($paper, 'admin', true);
