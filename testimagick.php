<?php
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @author Stas Fomin <stas-fomin@yandex.ru>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
$i=new Imagick('diploma.png');

$class_methods = get_class_methods($i);
sort( $class_methods );
print count($class_methods);
foreach ($class_methods as $method_name) {
    echo "$method_name\n";
}
$i->trimImage(0);
?>
