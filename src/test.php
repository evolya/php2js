<!DOCTYPE html>
<html>
<head>
	<title>PHP2JS Test Suite</title>
	<style type="text/css">
	table,
	table td { border: 1px solid black; border-spacing: 0; border-collapse: collapse; }
	table th { background: #eee; }
	</style>
</head>
<body>


<h1>PHP2JS Test Suite</h1>
<?php

include 'php2js.php';

?>

<h2>Supported types</h2>

<h3>Closure</h3>
<?php

$closure = function ($foo, $bar) {
	$foo->daddy();
	// If something ...
	if ($bar::$truc->machin()) {
		throw new Exception("This is not acceptable");
	}
};

echo '<table><tr><th>PHP</th><th>JS</th></tr><tr><td>';
echo '<pre class="brush: php">' . htmlspecialchars(PHP2JS::getInnerCode(new ReflectionFunction($closure), true, true)) . '</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateClosure($closure)) . '</pre>';
echo '</td></tr></table>';

?>

<h3>Method</h3>
<?php

class Foo {
	
public static function bar(Toto &$toto, Exception $ex) {
	 return array(
	 	0 => $toto,
	 	1 => 0x00001 | 0x01
	 );
}
	
}

echo '<table><tr><th>PHP</th><th>JS</th></tr><tr><td>';
echo '<pre class="brush: php">' . htmlspecialchars(PHP2JS::getInnerCode(new ReflectionMethod('Foo', 'bar'), true, true)) . '</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateMethod('Foo', 'bar')) . '</pre>';
echo '</td></tr></table>';

?>

<h3>Function</h3>
<?php

function foo($bar = true, $zoo = "Zoo") {
	return "Hello " . ($bar ? "World!" : "People"); 
}

echo '<table><tr><th>PHP</th><th>JS</th></tr><tr><td>';
echo '<pre class="brush: php">' . htmlspecialchars(PHP2JS::getInnerCode(new ReflectionFunction('foo'), true, true)) . '</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateFunction('foo')) . '</pre>';
echo '</td></tr></table>';

?>

<h2>Special features</h2>

<h3>Array</h3>
<?php

$a = array('apple', 'cherry', 'banana');
$b = array(0 => 'apple', 2 => 'cherry', 7 => 'banana');

echo '<table><tr><th>PHP</th><th>JS</th></tr><tr><td>';
echo '<pre class="brush: php">$a = array("apple", "cherry", "banana");</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateString("\$a = array('apple', 'cherry', 'banana');")) . '</pre>';
echo '</td></tr><tr><td>';
echo '<pre class="brush: php">$b = array(0 => "apple", 2 => "cherry", 7 => "banana");</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateString("\$b = array(0 => 'apple', 2 => 'cherry', 7 => 'banana');")) . '</pre>';
echo '</td></tr></table>';

?>

<h3>Foreach</h3>
<?php

$closure = function (array &$array) {
	foreach ($array as $value) {
		print(value);
	}
	foreach ($array as $index => &$value) {
		echo($value);
	}
};

echo '<table><tr><th>PHP</th><th>JS</th></tr><tr><td>';
echo '<pre class="brush: php">' . htmlspecialchars(PHP2JS::getInnerCode(new ReflectionFunction($closure), true, true)) . '</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateClosure($closure)) . '</pre>';
echo '</td></tr></table>';

?>

<h3>Special functions (echo, print)</h3>
<?php

$closure = function () {
	
	// Echo
	echo "foo";
	echo ("bar");
	echo("foot bar");
	
	// Print
	print "foo";
	print ("bar");
	print("foo bar");
	
};

echo '<table><tr><th>PHP</th><th>JS</th></tr><tr><td>';
echo '<pre class="brush: php">' . htmlspecialchars(PHP2JS::getInnerCode(new ReflectionFunction($closure), true, true)) . '</pre>';
echo '</td><td>';
echo '<pre class="brush: js">' . htmlspecialchars(PHP2JS::translateClosure($closure)) . '</pre>';
echo '</td></tr></table>';

?>

</body>
</html>
