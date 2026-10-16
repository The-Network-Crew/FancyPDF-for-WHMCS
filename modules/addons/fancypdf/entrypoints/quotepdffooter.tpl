<?php
/** FancyPDF managed repeating footer. */
require_once ROOTDIR . '/modules/addons/fancypdf/src/Render.php';
fancypdf_footer('quote', get_defined_vars());
