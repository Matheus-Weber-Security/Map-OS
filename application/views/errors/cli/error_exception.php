<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
An uncaught Exception was encountered
Type:        <?php echo get_class($exception), "
"; ?>
Message:     <?php echo $message, "
"; ?>
Filename:    <?php echo $exception->getFile(), "
"; ?>
Line Number: <?php echo $exception->getLine(), "
"; ?>