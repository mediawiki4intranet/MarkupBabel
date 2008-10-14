<?php
$i=new Imagick('diploma.png');

$class_methods = get_class_methods($i);
sort( $class_methods );
print count($class_methods);
foreach ($class_methods as $method_name) {
    echo "$method_name\n";
}
$i->trimImage(0);
?>
