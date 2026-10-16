<?php
/** FancyPDF managed repeating footer. */
require_once ROOTDIR . '/modules/addons/fancypdf/src/Render.php';
fancypdf_footer('invoice', get_defined_vars());
