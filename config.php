<?php
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('OUTPUT_DIR', __DIR__ . '/processed');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(OUTPUT_DIR)) mkdir(OUTPUT_DIR, 0755, true);
?>