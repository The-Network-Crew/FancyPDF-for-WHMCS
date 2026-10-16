<?php
/** FancyPDF managed entry point. Restore through Addons > FancyPDF. */
require ROOTDIR . '/modules/addons/fancypdf/src/Render.php';
fancypdf_render('invoice', get_defined_vars());
