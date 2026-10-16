<?php
/** FancyPDF managed entry point. Restore through Addons > FancyPDF. */
require ROOTDIR . '/modules/addons/fancypdf/src/Render.php';
fancypdf_render('quote', get_defined_vars());
