<?php

// set default value
if (!isset($vars['entity']->num_display)) {
	$vars['entity']->num_display = 4;
}

// show only featured files
$noyes_options = array(
	"no" => elgg_echo("option:no"),
	"yes" => elgg_echo("option:yes")
);

// listing options
$listing_options = array(
	1 => elgg_echo("file:list"),
	2 => elgg_echo("file:gallery")
);

$params = array(
	'name' => 'params[num_display]',
	'value' => $vars['entity']->num_display,
	'options' => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20),
);
$dropdown = elgg_view('input/select', $params);

?>
<div>
	<?php echo elgg_echo('file:num_files'); ?>:
	<?php echo $dropdown; ?>
</div>

<div>
	<?php
	echo elgg_echo("widget:file:edit:show_only_featured");
	echo ":";
	echo elgg_view("input/dropdown", array(
		"name" => "params[featured_only]",
		"options_values" => $noyes_options,
		"value" => $widget->featured_only
	));
	?>
</div>

<div>
	<?php
	echo elgg_echo("file:gallery_list");
	echo ":";
	echo elgg_view("input/dropdown", array(
		"name" => "params[gallery_list]",
		"options_values" => $listing_options,
		"value" => $widget->gallery_list
	));
	?>
</div>